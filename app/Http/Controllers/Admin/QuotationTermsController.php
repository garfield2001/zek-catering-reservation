<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class QuotationTermsController extends Controller
{
    /**
     * Historical terms snapshots remain attached to issued quotations. New
     * quotations use the structured Business Policies workflow instead.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('business-policies.show');
    }

    public function store(): never
    {
        abort(410, 'Quotation Terms have been replaced by Business Policies.');
    }

    public function update(): never
    {
        abort(410, 'Quotation Terms have been replaced by Business Policies.');
    }

    public function approve(): never
    {
        abort(410, 'Quotation Terms have been replaced by Business Policies.');
    }
}
