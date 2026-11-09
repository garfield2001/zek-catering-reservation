<?php

namespace App\Console\Commands;

use App\Actions\Quotation\ExpireQuotationVersion;
use App\Enums\QuotationVersionStatus;
use App\Models\QuotationVersion;
use Illuminate\Console\Command;

class ExpireQuotations extends Command
{
    protected $signature = 'quotations:expire';

    protected $description = 'Mark issued quotations past their validity deadline as expired.';

    public function handle(ExpireQuotationVersion $expire): int
    {
        $expired = 0;
        QuotationVersion::query()->where('status', QuotationVersionStatus::Issued)->where('valid_until', '<=', now())->orderBy('id')->each(function (QuotationVersion $version) use ($expire, &$expired): void {
            $expired += $expire->handle($version) ? 1 : 0;
        });
        $this->components->info("Expired {$expired} quotation version(s).");

        return self::SUCCESS;
    }
}
