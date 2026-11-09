<?php

namespace App\Actions\Reservations;

use App\Actions\Quotation\CreateQuotationRevision;
use App\Enums\ReservationChangeRequestStatus;
use App\Models\ReservationChangeRequest;
use App\Models\ReservationChangeRequestEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\MinorMoney;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewReservationChangeRequest
{
    public function __construct(private CreateQuotationRevision $revisions, private MinorMoney $money, private AuditLogger $audit) {}

    public function handle(User $actor, ReservationChangeRequest $attempt, bool $approved, string $note, ?string $exceptionReason): ReservationChangeRequest
    {
        if (! $actor->active || ! $actor->isAdmin()) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $attempt, $approved, $note, $exceptionReason): ReservationChangeRequest {
            $change = ReservationChangeRequest::query()->with(['reservation.quotation.currentVersion', 'reservation.confirmedQuotationVersion.acceptance'])->lockForUpdate()->findOrFail($attempt->id);
            if ($change->status !== ReservationChangeRequestStatus::PendingReview) {
                throw ValidationException::withMessages(['change_request' => 'Only a pending reservation change request can be reviewed.']);
            }
            if (! $approved) {
                return $this->decline($actor, $change, $note);
            }

            $this->assertExceptionalTiming($change, $exceptionReason);
            $this->assertMenuDeadline($change, $exceptionReason);
            $before = $change->status->value;
            $change->forceFill([
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => trim($note),
                'exception_reason' => filled($exceptionReason) ? trim($exceptionReason) : null,
            ]);
            if ($change->requires_quotation_revision) {
                $revision = $this->revisions->handle($actor, $change->reservation->quotation);
                $this->applyCommercialReplacements($revision, $change);
                if (! isset($change->proposed_snapshot['commercial_replacements']['main_package'])) {
                    $this->repriceGuestChange($revision, $change);
                }
                $change->forceFill([
                    'status' => ReservationChangeRequestStatus::AwaitingCustomerAgreement,
                    'quotation_version_id' => $revision->id,
                ])->save();
            } else {
                $change->forceFill(['status' => ReservationChangeRequestStatus::AwaitingReconfirmation])->save();
            }
            ReservationChangeRequestEvent::create([
                'reservation_change_request_id' => $change->id,
                'from_status' => $before,
                'to_status' => $change->status->value,
                'note' => trim($note),
                'actor_id' => $actor->id,
            ]);
            $this->audit->record('reservation.change_approved', $actor, $change, ['status' => $before], [
                'status' => $change->status->value, 'quotation_version_id' => $change->quotation_version_id,
                'exception_reason' => $change->exception_reason,
            ]);

            return $change->fresh(['quotationVersion']);
        });
    }

    private function decline(User $actor, ReservationChangeRequest $change, string $note): ReservationChangeRequest
    {
        if (! filled($note)) {
            throw ValidationException::withMessages(['review_note' => 'Explain why this reservation change request was declined.']);
        }
        $change->forceFill([
            'status' => ReservationChangeRequestStatus::Declined,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_note' => trim($note),
        ])->save();
        ReservationChangeRequestEvent::create([
            'reservation_change_request_id' => $change->id,
            'from_status' => ReservationChangeRequestStatus::PendingReview->value,
            'to_status' => ReservationChangeRequestStatus::Declined->value,
            'note' => trim($note), 'actor_id' => $actor->id,
        ]);
        $this->audit->record('reservation.change_declined', $actor, $change, ['status' => 'pending_review'], ['status' => 'declined', 'review_note' => trim($note)]);

        return $change;
    }

    private function assertExceptionalTiming(ReservationChangeRequest $change, ?string $exceptionReason): void
    {
        if (! in_array('event_date', $change->change_types, true)) {
            return;
        }
        $date = CarbonImmutable::parse($change->proposed_snapshot['event_date'], 'Asia/Manila')->startOfDay();
        $notice = (int) ($change->policy_snapshot['minimum_rescheduling_notice_days'] ?? 14);
        if ($date->lt(CarbonImmutable::now('Asia/Manila')->startOfDay()->addDays($notice)) && ! filled($exceptionReason)) {
            throw ValidationException::withMessages(['exception_reason' => "This proposed date is inside the {$notice}-day rescheduling notice period. An Administrator exception reason is required."]);
        }
    }

    private function assertMenuDeadline(ReservationChangeRequest $change, ?string $exceptionReason): void
    {
        if (array_intersect($change->change_types, ['menu', 'addons']) === []) {
            return;
        }
        $deadline = $change->reservation->confirmedQuotationVersion?->acceptance?->menu_finalization_due_at;
        if ($deadline?->isPast() && ! filled($exceptionReason)) {
            throw ValidationException::withMessages(['exception_reason' => 'The menu-finalization deadline has passed. An Administrator exception reason is required.']);
        }
    }

    private function repriceGuestChange($revision, ReservationChangeRequest $change): void
    {
        if (! in_array('guest_count', $change->change_types, true)) {
            return;
        }
        $target = (int) $change->proposed_snapshot['guest_count'];
        $lines = $revision->line_snapshots;
        $changed = false;
        $subtotal = 0;
        foreach ($lines as &$line) {
            if (($line['line_type'] ?? null) === 'main_package' && (int) ($line['quantity'] ?? 0) > 0) {
                $unit = intdiv($this->money->parse($line['total_minor']), (int) $line['quantity']);
                $line['quantity'] = $target;
                $line['total_minor'] = (string) $this->money->multiply($unit, $target);
                $changed = true;
            }
            $subtotal = $this->money->add($subtotal, $this->money->parse($line['total_minor']));
        }
        unset($line);
        if ($changed) {
            $revision->forceFill(['line_snapshots' => $lines, 'subtotal_minor' => $subtotal, 'total_minor' => $subtotal])->save();
        }
    }

    private function applyCommercialReplacements($revision, ReservationChangeRequest $change): void
    {
        $replacements = $change->proposed_snapshot['commercial_replacements'] ?? null;
        if (! is_array($replacements)) {
            return;
        }
        $lines = collect($revision->line_snapshots);
        if (isset($replacements['main_package']) && is_array($replacements['main_package'])) {
            $index = $lines->search(fn (array $line) => ($line['line_type'] ?? null) === 'main_package');
            if ($index === false) {
                throw ValidationException::withMessages(['menu_selection' => 'This quotation no longer has a Main Catering package to replace.']);
            }
            $line = $replacements['main_package'];
            $line['line_number'] = $lines[$index]['line_number'];
            $line['reservation_request_line_id'] = $lines[$index]['reservation_request_line_id'] ?? null;
            $lines[$index] = $line;
        }
        if (array_key_exists('addons', $replacements) && is_array($replacements['addons'])) {
            $main = $lines->filter(fn (array $line) => ($line['line_type'] ?? null) === 'main_package')->values();
            $addons = collect($replacements['addons'])->map(function (array $line, int $index): array {
                $line['line_number'] = $index + 2;

                return $line;
            });
            $lines = $main->concat($addons)->values();
        }
        $subtotal = $lines->sum(fn (array $line) => $this->money->parse($line['total_minor']));
        $revision->forceFill([
            'line_snapshots' => $lines->all(),
            'subtotal_minor' => $subtotal,
            'total_minor' => $subtotal,
        ])->save();
    }
}
