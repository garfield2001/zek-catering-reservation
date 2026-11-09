<?php

namespace App\Http\Requests\Quotation;

use Illuminate\Foundation\Http\FormRequest;

class RecordOfflineQuotationAcceptanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recordOfflineAcceptance', $this->route('quotationVersion')) ?? false;
    }

    public function rules(): array
    {
        return [
            'accepted_by_name' => ['required', 'string', 'max:255'],
            'evidence' => ['nullable', 'file', 'max:10240', 'mimetypes:application/pdf,image/jpeg,image/png'],
        ];
    }
}
