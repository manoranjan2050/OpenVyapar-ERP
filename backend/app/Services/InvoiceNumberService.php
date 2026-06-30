<?php

namespace App\Services;

use App\Models\Company;
use App\Models\FinancialYear;
use App\Models\SalesInvoice;
use App\Models\PurchaseInvoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    public function nextSalesNumber(Company $company, FinancialYear $fy): string
    {
        $prefix = $company->settings()->where('key', 'invoice_prefix')->value('value') ?? 'INV';
        $fyCode = substr($fy->start_date->format('Y'), 2) . '-' . substr($fy->end_date->format('Y'), 2);
        $last = SalesInvoice::withTrashed()->where('company_id', $company->id)
            ->where('financial_year_id', $fy->id)
            ->lockForUpdate()
            ->count();
        return "{$prefix}/{$fyCode}/" . str_pad($last + 1, 4, '0', STR_PAD_LEFT);
    }

    public function nextPurchaseNumber(Company $company, FinancialYear $fy): string
    {
        $fyCode = substr($fy->start_date->format('Y'), 2) . '-' . substr($fy->end_date->format('Y'), 2);
        $last = PurchaseInvoice::withTrashed()->where('company_id', $company->id)
            ->where('financial_year_id', $fy->id)
            ->lockForUpdate()
            ->count();
        return "PUR/{$fyCode}/" . str_pad($last + 1, 4, '0', STR_PAD_LEFT);
    }

    public function nextPaymentNumber(Company $company): string
    {
        $last = Payment::where('company_id', $company->id)->lockForUpdate()->count();
        return 'PAY/' . str_pad($last + 1, 5, '0', STR_PAD_LEFT);
    }
}
