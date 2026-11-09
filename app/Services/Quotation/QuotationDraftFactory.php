<?php

namespace App\Services\Quotation;

use App\Models\ReservationRequest;
use App\Models\ReservationRequestLine;
use App\Services\Catalog\MinorMoney;

class QuotationDraftFactory
{
    public function __construct(private MinorMoney $money) {}

    /** @return array{lines: array<int, array<string, mixed>>, subtotal_minor: int} */
    public function fromReservationRequest(ReservationRequest $request): array
    {
        $lines = [];
        $subtotal = 0;
        foreach ($request->lines as $line) {
            $amount = $this->money->parse($line->estimated_total_minor);
            $lines[] = $this->requestLineSnapshot($line, $amount);
            $subtotal = $this->money->add($subtotal, $amount);
        }

        return ['lines' => $lines, 'subtotal_minor' => $subtotal];
    }

    /** @return array<string, mixed> */
    private function requestLineSnapshot(ReservationRequestLine $line, int $amount): array
    {
        $snapshot = $line->commercial_snapshot;

        return [
            'line_number' => $line->line_number,
            'line_type' => $line->line_type,
            'description' => $this->lineDescription($line),
            'quantity' => $line->quantity,
            'total_minor' => (string) $amount,
            'reservation_request_line_id' => $line->id,
            'commercial_snapshot' => $snapshot,
        ];
    }

    private function lineDescription(ReservationRequestLine $line): string
    {
        $snapshot = $line->commercial_snapshot;

        return match ($line->line_type) {
            'main_package', 'addon_package' => $this->snapshotText($snapshot, ['published_package', 'product', 'name'], 'Catering package'),
            'listing' => $this->snapshotText($snapshot, ['published_listing', 'name'], $this->snapshotText($snapshot, ['published_listing', 'food', 'name'], 'Add-on item')),
            default => 'Quoted item',
        };
    }

    /** @param array<string, mixed> $snapshot @param array<int, string> $path */
    private function snapshotText(array $snapshot, array $path, string $fallback): string
    {
        $value = $snapshot;
        foreach ($path as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return $fallback;
            }
            $value = $value[$key];
        }

        return is_string($value) && trim($value) !== '' ? $value : $fallback;
    }
}
