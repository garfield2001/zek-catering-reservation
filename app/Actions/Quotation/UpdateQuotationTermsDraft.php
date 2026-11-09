<?php

namespace App\Actions\Quotation;

use App\Models\QuotationTermsVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateQuotationTermsDraft extends CreateQuotationTermsDraft
{
    /** @param array<string, mixed> $data */
    public function update(User $actor, QuotationTermsVersion $version, array $data): QuotationTermsVersion
    {
        return DB::transaction(function () use ($actor, $version, $data): QuotationTermsVersion {
            $version = QuotationTermsVersion::query()->lockForUpdate()->findOrFail($version->id);
            $before = $this->auditData($version);
            $version->fill($this->attributes($data));
            $version->save();
            $this->audit->record('quotation_terms.draft_updated', $actor, $version, $before, $this->auditData($version));

            return $version->refresh();
        });
    }
}
