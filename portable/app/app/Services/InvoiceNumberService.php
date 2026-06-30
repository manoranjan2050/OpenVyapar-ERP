<?php

namespace App\Services;

use App\Models\Company;
use App\Models\FinancialYear;
use App\Models\SalesInvoice;
use App\Models\PurchaseInvoice;
use App\Models\Payment;

class InvoiceNumberService
{
    public function nextSalesNumber(Company $company, FinancialYear $fy): string
    {
        $prefix = $company->settings()->where('key', 'invoice_prefix')->value('value') ?? 'INV';
        $fyCode = substr($fy->start_date->format('Y'), 2) . '-' . substr($fy->end_date->format('Y'), 2);

        // Use max() on numeric suffix (includes soft-deleted) so we never reuse a taken number
        $last = SalesInvoice::withTrashed()
            ->where('company_id', $company->id)
            ->where('financial_year_id', $fy->id)
            ->where('invoice_number', 'like', "{$prefix}/{$fyCode}/%")
            ->lockForUpdate()
            ->max(\DB::raw("CAST(SUBSTR(invoice_number, " . (strlen("{$prefix}/{$fyCode}/") + 1) . ") AS UNSIGNED)"));

        return "{$prefix}/{$fyCode}/" . str_pad(((int)$last) + 1, 4, '0', STR_PAD_LEFT);
    }

    public function nextPurchaseNumber(Company $company, FinancialYear $fy): string
    {
        $fyCode = substr($fy->start_date->format('Y'), 2) . '-' . substr($fy->end_date->format('Y'), 2);

        $last = PurchaseInvoice::withTrashed()
            ->where('company_id', $company->id)
            ->where('financial_year_id', $fy->id)
            ->where('invoice_number', 'like', "PUR/{$fyCode}/%")
            ->lockForUpdate()
            ->max(\DB::raw("CAST(SUBSTR(invoice_number, " . (strlen("PUR/{$fyCode}/") + 1) . ") AS UNSIGNED)"));

        return "PUR/{$fyCode}/" . str_pad(((int)$last) + 1, 4, '0', STR_PAD_LEFT);
    }

    public function nextPaymentNumber(Company $company): string
    {
        $last = Payment::withTrashed()
            ->where('company_id', $company->id)
            ->where('payment_number', 'like', 'PAY/%')
            ->lockForUpdate()
            ->max(\DB::raw("CAST(SUBSTR(payment_number, 5) AS UNSIGNED)"));

        return 'PAY/' . str_pad(((int)$last) + 1, 5, '0', STR_PAD_LEFT);
    }
}
