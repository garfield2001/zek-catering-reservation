<?php

namespace App\Actions\ReservationRequest;

use App\Enums\ReservationRequestStatus;
use App\Models\IndividualListing;
use App\Models\PencilBooking;
use App\Models\Product;
use App\Models\ReservationRequest;
use App\Models\ReservationRequestLine;
use App\Models\ReservationRequestStatusEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\BusinessPolicies\CurrentBusinessPolicy;
use App\Services\Catalog\ListingConfiguration;
use App\Services\Catalog\MinorMoney;
use App\Services\Catalog\PackagePricePreview;
use App\Services\ReservationRequest\CustomerAccessCode;
use App\Services\Storefront\Selection;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubmitReservationRequest
{
    public function __construct(private Selection $selection, private PackagePricePreview $packages, private ListingConfiguration $listings, private MinorMoney $money, private AuditLogger $audit, private CurrentBusinessPolicy $policies, private CustomerAccessCode $accessCodes) {}

    /**
     * Public submissions deliberately obtain their commercial selection from the
     * storefront session. Internal workflows supply an explicit selection so they
     * cannot read or overwrite a customer's browser planning session.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>|null  $selection
     * @return array{request: ReservationRequest, created: bool, access_code: ?string}
     */
    public function handle(Request $httpRequest, array $data, ?array $selection = null, ?User $actor = null, string $origin = 'public', ?PencilBooking $pencilBooking = null): array
    {
        $this->assertOrigin($origin, $actor, $pencilBooking);

        try {
            return DB::transaction(function () use ($httpRequest, $data, $selection, $actor, $origin, $pencilBooking): array {
                $existing = ReservationRequest::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
                if ($existing) {
                    return ['request' => $existing, 'created' => false, 'access_code' => null];
                }

                $stored = array_values($selection ?? $this->selection->all($httpRequest));
                if ($stored === []) {
                    throw ValidationException::withMessages(['selection' => 'Choose one Main Catering package before submitting a request.']);
                }

                $lines = [];
                $main = null;
                $total = 0;
                $selectionKeys = [];
                foreach ($stored as $item) {
                    if (($item['type'] ?? null) === 'package') {
                        $key = 'package:'.($item['product_id'] ?? '');
                        if (isset($selectionKeys[$key])) {
                            throw ValidationException::withMessages(['selection' => 'Each package can be selected only once. Review your selection.']);
                        }
                        $selectionKeys[$key] = true;
                        $product = Product::query()->lockForUpdate()->with(['family', 'currentVersion'])->find($item['product_id'] ?? null);
                        if (! $product || ! $product->currentVersion || $product->current_version_id !== ($item['version_id'] ?? null) || $product->currentVersion->status !== 'published') {
                            throw ValidationException::withMessages(['selection' => 'A selected package changed or is no longer available. Review your selection.']);
                        }
                        $preview = $this->packages->calculatePublished($product->currentVersion, $item['quantity'] ?? 0, $item['groups'] ?? []);
                        $type = $product->family->behavior_type === 'main_package' ? 'main_package' : 'addon_package';
                        if ($type === 'main_package') {
                            if ($main !== null) {
                                throw ValidationException::withMessages(['selection' => 'Choose exactly one Main Catering package.']);
                            }
                            $main = ['version_id' => $product->currentVersion->id, 'guest_count' => $preview['quantity']];
                        } elseif (! $product->family->is_addon) {
                            throw ValidationException::withMessages(['selection' => 'Only published add-on packages may accompany Main Catering.']);
                        }
                        $amount = $this->money->parse($preview['total_minor']);
                        $lines[] = ['type' => $type, 'package_version_id' => $product->currentVersion->id, 'listing_version_id' => null, 'quantity' => $preview['quantity'], 'total_minor' => $amount,
                            'snapshot' => ['schema' => 1, 'published_package' => $product->currentVersion->snapshot(), 'customer_selection' => ['groups' => $item['groups'] ?? []], 'estimate' => $preview]];
                        $total = $this->money->add($total, $amount);

                        continue;
                    }
                    if (($item['type'] ?? null) === 'listing') {
                        $key = 'listing:'.($item['listing_id'] ?? '').':'.($item['selling_option_id'] ?? 'base');
                        if (isset($selectionKeys[$key])) {
                            throw ValidationException::withMessages(['selection' => 'Each item option can be selected only once. Review your selection.']);
                        }
                        $selectionKeys[$key] = true;
                        $listing = IndividualListing::query()->lockForUpdate()->with(['family', 'currentVersion'])->find($item['listing_id'] ?? null);
                        if (! $listing || ! $listing->family->is_addon || ! $listing->currentVersion || $listing->current_version_id !== ($item['version_id'] ?? null) || $listing->currentVersion->status !== 'published' || ! $this->listings->availability($listing)['available']) {
                            throw ValidationException::withMessages(['selection' => 'A selected add-on item changed or is no longer available. Review your selection.']);
                        }
                        $preview = $this->listings->preview($listing, $listing->currentVersion, $item['quantity'] ?? 0, $item['selling_option_id'] ?? null);
                        $amount = $this->money->parse($preview['total_minor']);
                        $lines[] = ['type' => 'listing', 'package_version_id' => null, 'listing_version_id' => $listing->currentVersion->id, 'quantity' => $preview['quantity'], 'total_minor' => $amount,
                            'snapshot' => ['schema' => 1, 'published_listing' => $listing->currentVersion->snapshot(), 'customer_selection' => ['selling_option_id' => $item['selling_option_id'] ?? null], 'estimate' => $preview]];
                        $total = $this->money->add($total, $amount);

                        continue;
                    }
                    throw ValidationException::withMessages(['selection' => 'Your planning selection is invalid. Please review it before submitting a request.']);
                }
                if ($main === null) {
                    throw ValidationException::withMessages(['selection' => 'Choose exactly one currently available Main Catering package before submitting a request.']);
                }

                $policy = $this->policies->current();
                $start = CarbonImmutable::createFromFormat('Y-m-d H:i', $data['event_date'].' '.$data['event_start_time'], 'Asia/Manila');
                $shortNotice = $start->startOfDay()->lt(CarbonImmutable::now('Asia/Manila')->startOfDay()->addDays($policy->minimum_advance_booking_days));
                $emergency = (bool) ($data['emergency_booking_exception'] ?? false);
                if ($shortNotice && $origin === 'public') {
                    throw ValidationException::withMessages(['event_date' => "Online requests require at least {$policy->minimum_advance_booking_days} days’ notice. Contact ZEK Catering for an Administrator-reviewed emergency booking."]);
                }
                if ($shortNotice && $origin !== 'public' && ! $emergency) {
                    throw ValidationException::withMessages(['event_date' => "This internal request is inside the {$policy->minimum_advance_booking_days}-day minimum notice period. An Administrator must record an emergency booking exception with a reason."]);
                }
                if ($emergency && ! $shortNotice) {
                    throw ValidationException::withMessages(['emergency_booking_exception' => 'An emergency booking exception is only available when the event is inside the minimum notice period.']);
                }
                if ($emergency && ($actor === null || ! $actor->isAdmin())) {
                    throw ValidationException::withMessages(['emergency_booking_exception' => 'Only an Administrator may authorize a short-notice emergency booking exception.']);
                }
                if ($emergency && blank($data['emergency_booking_reason'] ?? null)) {
                    throw ValidationException::withMessages(['emergency_booking_reason' => 'Give a reason for this Administrator-authorized emergency booking exception.']);
                }
                $end = $start->addHours($policy->event_duration_hours);
                $accessCode = $this->accessCodes->generate();
                $request = ReservationRequest::create(['reference' => 'ZEK-'.Str::upper(Str::random(12)), 'idempotency_key' => $data['idempotency_key'], 'status' => ReservationRequestStatus::Submitted,
                    'origin' => $origin, 'pencil_booking_id' => $pencilBooking?->id, 'created_by' => $actor?->id,
                    'emergency_booking_exception' => $emergency,
                    'emergency_booking_reason' => $emergency ? trim($data['emergency_booking_reason']) : null,
                    'emergency_booking_authorized_by' => $emergency ? $actor->id : null,
                    'emergency_booking_policy_snapshot' => $emergency ? ['schema' => 1, 'minimum_advance_booking_days' => $policy->minimum_advance_booking_days, 'emergency_booking_requires_administrator' => $policy->emergency_booking_requires_administrator, 'evaluated_at' => now()->toIso8601String()] : null,
                    'customer_access_code_hash' => $this->accessCodes->hash($accessCode),
                    'customer_name' => trim($data['full_name']), 'customer_mobile' => trim($data['mobile_number']), 'customer_email' => filled($data['email'] ?? null) ? strtolower(trim($data['email'])) : null,
                    'event_date' => $start->toDateString(), 'event_start_time' => $start->format('H:i:s'), 'event_end_time' => $end->format('H:i:s'), 'event_ends_next_day' => ! $start->isSameDay($end), 'timezone' => 'Asia/Manila',
                    'venue_information' => trim($data['venue_information']), 'event_notes' => filled($data['event_notes'] ?? null) ? trim($data['event_notes']) : null,
                    'guest_count' => $main['guest_count'], 'main_package_version_id' => $main['version_id'], 'estimated_subtotal_minor' => $total, 'currency' => 'PHP',
                    'commercial_snapshot' => array_filter(['schema' => 1, 'submitted_at' => now()->toIso8601String(), 'origin' => $origin === 'public' ? null : $origin, 'main_package_version_id' => $main['version_id'], 'estimated_subtotal_minor' => (string) $total, 'currency' => 'PHP'], static fn (mixed $value): bool => $value !== null)]);
                foreach ($lines as $index => $line) {
                    ReservationRequestLine::create(['reservation_request_id' => $request->id, 'line_number' => $index + 1, 'line_type' => $line['type'], 'package_version_id' => $line['package_version_id'], 'listing_version_id' => $line['listing_version_id'], 'quantity' => $line['quantity'], 'estimated_total_minor' => $line['total_minor'], 'commercial_snapshot' => $line['snapshot']]);
                }
                ReservationRequestStatusEvent::create(['reservation_request_id' => $request->id, 'from_status' => null, 'to_status' => ReservationRequestStatus::Submitted, 'changed_by' => $actor?->id]);
                $this->audit->record('reservation_request.submitted', $actor, $request, null, array_filter(['reference' => $request->reference, 'origin' => $origin === 'public' ? null : $origin, 'status' => $request->status->value, 'estimated_subtotal_minor' => (string) $total, 'emergency_booking_exception' => $emergency ?: null], static fn (mixed $value): bool => $value !== null));
                if ($emergency) {
                    $this->audit->record('reservation_request.emergency_booking_authorized', $actor, $request, null, ['reason' => trim($data['emergency_booking_reason']), 'minimum_advance_booking_days' => $policy->minimum_advance_booking_days]);
                }

                return ['request' => $request, 'created' => true, 'access_code' => $accessCode];
            }, 3);
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                $existing = ReservationRequest::where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    return ['request' => $existing, 'created' => false, 'access_code' => null];
                }
            }

            throw $exception;
        }
    }

    private function assertOrigin(string $origin, ?User $actor, ?PencilBooking $pencilBooking): void
    {
        if ($origin === 'public' && $actor === null && $pencilBooking === null) {
            return;
        }

        if (! in_array($origin, ['manual', 'pencil_booking'], true)
            || $actor === null
            || ! $actor->active
            || ($origin === 'pencil_booking' && $pencilBooking === null)
            || ($origin === 'manual' && $pencilBooking !== null)) {
            throw new \LogicException('The reservation request origin context is invalid.');
        }
    }
}
