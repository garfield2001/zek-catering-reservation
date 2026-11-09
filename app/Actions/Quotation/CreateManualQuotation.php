<?php

namespace App\Actions\Quotation;

use App\Enums\QuotationStatus;
use App\Enums\QuotationVersionStatus;
use App\Models\Quotation;
use App\Models\QuotationVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\MinorMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateManualQuotation
{
    public function __construct(private MinorMoney $money, private AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data): Quotation
    {
        return DB::transaction(function () use ($actor, $data): Quotation {
            $subtotal = 0;
            $lines = [];
            foreach ($data['lines'] as $index => $line) {
                $unit = $this->money->fromPesos($line['unit_price'], "lines.{$index}.unit_price");
                $quantity = (int) $line['quantity'];
                $total = $this->money->multiply($unit, $quantity);
                $subtotal = $this->money->add($subtotal, $total);
                $lines[] = ['line_number' => $index + 1, 'line_type' => 'manual', 'description' => trim($line['description']), 'quantity' => $quantity, 'unit_price_minor' => (string) $unit, 'total_minor' => (string) $total];
            }
            $quotation = Quotation::create([
                'reference' => $this->reference(), 'origin' => 'manual', 'status' => QuotationStatus::Draft,
                'customer_name' => trim($data['customer_name']), 'customer_mobile' => trim($data['customer_mobile']),
                'customer_email' => filled($data['customer_email'] ?? null) ? strtolower(trim($data['customer_email'])) : null,
                'event_date' => $data['event_date'] ?? null, 'event_start_time' => $data['event_start_time'] ?? null,
                'venue_information' => filled($data['venue_information'] ?? null) ? trim($data['venue_information']) : null,
                'event_notes' => filled($data['event_notes'] ?? null) ? trim($data['event_notes']) : null,
                'source_snapshot' => ['schema' => 1, 'source' => 'manual'], 'created_by' => $actor->id,
            ]);
            $version = QuotationVersion::create([
                'quotation_id' => $quotation->id, 'version_number' => 1, 'status' => QuotationVersionStatus::Draft,
                'line_snapshots' => $lines, 'subtotal_minor' => $subtotal, 'adjustment_minor' => 0, 'total_minor' => $subtotal,
                'currency' => 'PHP', 'created_by' => $actor->id,
            ]);
            $quotation->update(['current_version_id' => $version->id]);
            $this->audit->record('quotation.manual_created', $actor, $quotation, null, ['reference' => $quotation->reference, 'quotation_version_id' => $version->id, 'line_count' => count($lines), 'total_minor' => (string) $subtotal]);

            return $quotation->fresh(['currentVersion']);
        });
    }

    private function reference(): string
    {
        do {
            $reference = 'QT-'.Str::upper(Str::random(12));
        } while (Quotation::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
