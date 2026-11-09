<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationChangeRequestStatus;
use App\Enums\ReservationStatus;
use App\Models\BusinessPolicy;
use App\Models\PackageVersion;
use App\Models\Reservation;
use App\Models\ReservationChangeRequest;
use App\Models\ReservationChangeRequestEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\PackageQuantityRules;
use App\Services\Reservations\ReservationChangeCommercialProposal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateReservationChangeRequest
{
    /** @var list<string> */
    private const CHANGE_TYPES = ['event_date', 'event_start_time', 'venue_information', 'guest_count', 'menu', 'addons'];

    public function __construct(
        private AuditLogger $audit,
        private PackageQuantityRules $quantities,
        private ReservationChangeCommercialProposal $commercialProposal,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(?User $actor, Reservation $attempt, array $data, bool $fromCustomer): ReservationChangeRequest
    {
        return DB::transaction(function () use ($actor, $attempt, $data, $fromCustomer): ReservationChangeRequest {
            $reservation = Reservation::query()->with(['quotation.currentVersion', 'confirmedQuotationVersion'])->lockForUpdate()->findOrFail($attempt->id);
            if ($reservation->status !== ReservationStatus::Confirmed) {
                throw ValidationException::withMessages(['reservation' => 'Only a confirmed reservation can receive a change request.']);
            }
            $existing = ReservationChangeRequest::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($existing) {
                if ($existing->reservation_id !== $reservation->id) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This request key was already used for another reservation change.']);
                }

                return $existing;
            }
            if (ReservationChangeRequest::query()
                ->where('reservation_id', $reservation->id)
                ->whereIn('status', [
                    ReservationChangeRequestStatus::PendingReview,
                    ReservationChangeRequestStatus::AwaitingCustomerAgreement,
                    ReservationChangeRequestStatus::AwaitingReconfirmation,
                ])
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages([
                    'changes' => 'This reservation already has a change request in progress. Review its outcome before submitting another request.',
                ]);
            }
            $policy = BusinessPolicy::query()->where('key', 'current')->lockForUpdate()->sole();
            $proposed = $this->proposed($reservation, $data, $policy->event_duration_hours);
            $types = $this->changeTypes($reservation, $proposed, $data);
            if ($types === []) {
                throw ValidationException::withMessages(['changes' => 'Choose at least one value that differs from the confirmed reservation.']);
            }
            $this->assertGuestQuantity($reservation, $proposed, $types);
            $replacements = $this->commercialProposal->validate($reservation, $data, $proposed);
            if ($replacements !== []) {
                $proposed['commercial_replacements'] = $replacements;
            }
            $policySnapshot = [
                'minimum_rescheduling_notice_days' => $policy->minimum_rescheduling_notice_days,
                'minimum_advance_booking_days' => $policy->minimum_advance_booking_days,
                'event_duration_hours' => $policy->event_duration_hours,
                'menu_finalization_days_after_acceptance' => $policy->menu_finalization_days_after_acceptance,
                'captured_at' => now()->toIso8601String(),
            ];
            $commercial = array_intersect($types, ['guest_count', 'menu', 'addons']) !== [];
            $original = $this->snapshot($reservation);
            $change = ReservationChangeRequest::create([
                'reservation_id' => $reservation->id,
                'quotation_id' => $reservation->quotation_id,
                'idempotency_key' => $data['idempotency_key'],
                'status' => ReservationChangeRequestStatus::PendingReview,
                'change_types' => $types,
                'original_snapshot' => $original,
                'proposed_snapshot' => $proposed,
                'policy_snapshot' => $policySnapshot,
                'request_note' => filled($data['request_note'] ?? null) ? trim((string) $data['request_note']) : null,
                'requested_by_customer' => $fromCustomer,
                'requested_by' => $fromCustomer ? null : $actor?->id,
                'requested_at' => now(),
                'requires_quotation_revision' => $commercial,
            ]);
            ReservationChangeRequestEvent::create([
                'reservation_change_request_id' => $change->id,
                'from_status' => null,
                'to_status' => ReservationChangeRequestStatus::PendingReview->value,
                'note' => $change->request_note,
                'actor_id' => $fromCustomer ? null : $actor?->id,
            ]);
            $this->audit->record('reservation.change_requested', $actor, $change, null, [
                'reservation_id' => $reservation->id,
                'quotation_id' => $reservation->quotation_id,
                'change_types' => $types,
                'requested_by_customer' => $fromCustomer,
                'requires_quotation_revision' => $commercial,
            ]);

            return $change;
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function proposed(Reservation $reservation, array $data, int $eventDurationHours): array
    {
        $date = array_key_exists('event_date', $data) && filled($data['event_date']) ? (string) $data['event_date'] : $reservation->event_date->toDateString();
        $time = array_key_exists('event_start_time', $data) && filled($data['event_start_time']) ? (string) $data['event_start_time'] : $reservation->event_start_time;
        $start = $time === null ? null : CarbonImmutable::createFromFormat('Y-m-d H:i', $date.' '.substr($time, 0, 5), 'Asia/Manila');

        return [
            'event_date' => $start?->toDateString() ?? $date,
            'event_start_time' => $start?->format('H:i:s'),
            'event_end_time' => $start?->addHours($eventDurationHours)->format('H:i:s'),
            'venue_information' => array_key_exists('venue_information', $data) && filled($data['venue_information']) ? trim((string) $data['venue_information']) : $reservation->venue_information,
            'guest_count' => array_key_exists('guest_count', $data) && $data['guest_count'] !== null ? (int) $data['guest_count'] : $reservation->guest_count,
            'menu_selection' => $data['menu_selection'] ?? null,
            'addon_selection' => $data['addon_selection'] ?? null,
        ];
    }

    /** @param array<string, mixed> $proposed @param array<string, mixed> $data @return list<string> */
    private function changeTypes(Reservation $reservation, array $proposed, array $data): array
    {
        $types = [];
        if ($reservation->event_date->toDateString() !== $proposed['event_date']) {
            $types[] = 'event_date';
        }
        if ($reservation->event_start_time !== $proposed['event_start_time']) {
            $types[] = 'event_start_time';
        }
        if ($reservation->venue_information !== $proposed['venue_information']) {
            $types[] = 'venue_information';
        }
        if ($reservation->guest_count !== $proposed['guest_count']) {
            $types[] = 'guest_count';
        }
        foreach (['menu', 'addons'] as $type) {
            if (array_key_exists($type.'_selection', $data) && $data[$type.'_selection'] !== null) {
                $types[] = $type;
            }
        }

        return array_values(array_intersect(self::CHANGE_TYPES, $types));
    }

    /** @return array<string, mixed> */
    private function snapshot(Reservation $reservation): array
    {
        return [
            'event_date' => $reservation->event_date->toDateString(),
            'event_start_time' => $reservation->event_start_time,
            'venue_information' => $reservation->venue_information,
            'guest_count' => $reservation->guest_count,
            'confirmed_quotation_version_id' => $reservation->confirmed_quotation_version_id,
            'confirmation_snapshot' => $reservation->confirmation_snapshot,
            'capacity_snapshot' => $reservation->capacity_snapshot,
        ];
    }

    /** @param array<string, mixed> $proposed @param list<string> $types */
    private function assertGuestQuantity(Reservation $reservation, array $proposed, array $types): void
    {
        if (! in_array('guest_count', $types, true)) {
            return;
        }

        $lines = $reservation->confirmedQuotationVersion->line_snapshots;
        $main = collect($lines)->firstWhere('line_type', 'main_package');
        $versionId = is_array($main)
            ? data_get($main, 'commercial_snapshot.published_package.version.id')
            : null;
        if (! is_int($versionId) && ! ctype_digit((string) $versionId)) {
            // A manually prepared quotation has no package quantity rule to
            // apply. It still requires an explicit commercial revision.
            return;
        }

        $version = PackageVersion::query()->with(['family', 'product'])->find($versionId);
        if ($version === null) {
            throw ValidationException::withMessages([
                'guest_count' => 'The package facts behind this confirmed quotation are unavailable. An Administrator must resolve the package record before changing the guest count.',
            ]);
        }
        $this->quantities->validate($version, (int) $proposed['guest_count']);
    }
}
