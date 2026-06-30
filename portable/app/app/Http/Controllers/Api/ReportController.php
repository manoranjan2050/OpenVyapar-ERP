<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesInvoice;
use App\Models\PurchaseInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    // GSTR-1 Summary: B2B / B2C / HSN-wise
    public function gstr1(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $companyId = $request->user()->company_id;

        $invoices = SalesInvoice::where('company_id', $companyId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('invoice_date', [$request->from, $request->to])
            ->with(['customer', 'items'])
            ->get();

        // B2B — buyer has GSTIN
        $b2b = $invoices->filter(fn($i) => $i->customer?->gstin)
            ->map(fn($i) => [
                'invoice_number'  => $i->invoice_number,
                'invoice_date'    => $i->invoice_date,
                'customer'        => $i->customer->name,
                'gstin'           => $i->customer->gstin,
                'taxable_amount'  => (float) $i->taxable_amount,
                'cgst'            => (float) $i->cgst_amount,
                'sgst'            => (float) $i->sgst_amount,
                'igst'            => (float) $i->igst_amount,
                'total'           => (float) $i->total_amount,
                'supply_type'     => $i->supply_type,
            ])->values();

        // B2C — no GSTIN (amount ≤ 2.5L — simplified)
        $b2c = $invoices->filter(fn($i) => !$i->customer?->gstin && $i->total_amount <= 250000)
            ->groupBy(fn($i) => $i->supply_type)
            ->map(fn($group, $type) => [
                'supply_type'    => $type,
                'taxable_amount' => round($group->sum('taxable_amount'), 2),
                'cgst'           => round($group->sum('cgst_amount'), 2),
                'sgst'           => round($group->sum('sgst_amount'), 2),
                'igst'           => round($group->sum('igst_amount'), 2),
                'total'          => round($group->sum('total_amount'), 2),
                'invoice_count'  => $group->count(),
            ])->values();

        // HSN Summary
        $hsnData = [];
        foreach ($invoices as $inv) {
            foreach ($inv->items as $item) {
                $hsn = $item->hsn_code ?? 'N/A';
                if (!isset($hsnData[$hsn])) {
                    $hsnData[$hsn] = [
                        'hsn_code'       => $hsn,
                        'description'    => $item->product_name,
                        'uqc'            => $item->unit ?? 'NOS',
                        'quantity'       => 0,
                        'taxable_amount' => 0,
                        'cgst'           => 0,
                        'sgst'           => 0,
                        'igst'           => 0,
                        'total_tax'      => 0,
                    ];
                }
                $hsnData[$hsn]['quantity']       += (float) $item->quantity;
                $hsnData[$hsn]['taxable_amount'] += (float) $item->taxable_amount;
                $hsnData[$hsn]['cgst']           += (float) $item->cgst_amount;
                $hsnData[$hsn]['sgst']           += (float) $item->sgst_amount;
                $hsnData[$hsn]['igst']           += (float) $item->igst_amount;
                $hsnData[$hsn]['total_tax']      += (float) $item->cgst_amount + (float) $item->sgst_amount + (float) $item->igst_amount;
            }
        }

        // Summary totals
        $summary = [
            'total_invoices'  => $invoices->count(),
            'taxable_amount'  => round($invoices->sum('taxable_amount'), 2),
            'cgst_amount'     => round($invoices->sum('cgst_amount'), 2),
            'sgst_amount'     => round($invoices->sum('sgst_amount'), 2),
            'igst_amount'     => round($invoices->sum('igst_amount'), 2),
            'total_tax'       => round($invoices->sum(fn($i) => $i->cgst_amount + $i->sgst_amount + $i->igst_amount), 2),
            'total_amount'    => round($invoices->sum('total_amount'), 2),
            'period_from'     => $request->from,
            'period_to'       => $request->to,
        ];

        return response()->json([
            'summary' => $summary,
            'b2b'     => $b2b,
            'b2c'     => $b2c,
            'hsn'     => array_values($hsnData),
        ]);
    }

    // Purchase tax report (ITC summary)
    public function itc(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $companyId = $request->user()->company_id;

        $invoices = PurchaseInvoice::where('company_id', $companyId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('invoice_date', [$request->from, $request->to])
            ->with('supplier')
            ->get();

        $rows = $invoices->map(fn($i) => [
            'invoice_number'      => $i->invoice_number,
            'supplier_invoice'    => $i->supplier_invoice_number,
            'invoice_date'        => $i->invoice_date,
            'supplier'            => $i->supplier?->name,
            'gstin'               => $i->supplier?->gstin,
            'taxable_amount'      => (float) $i->taxable_amount,
            'cgst'                => (float) $i->cgst_amount,
            'sgst'                => (float) $i->sgst_amount,
            'igst'                => (float) $i->igst_amount,
            'total'               => (float) $i->total_amount,
        ]);

        $summary = [
            'total_invoices'  => $invoices->count(),
            'taxable_amount'  => round($invoices->sum('taxable_amount'), 2),
            'cgst_amount'     => round($invoices->sum('cgst_amount'), 2),
            'sgst_amount'     => round($invoices->sum('sgst_amount'), 2),
            'igst_amount'     => round($invoices->sum('igst_amount'), 2),
            'total_itc'       => round($invoices->sum(fn($i) => $i->cgst_amount + $i->sgst_amount + $i->igst_amount), 2),
        ];

        return response()->json(['summary' => $summary, 'rows' => $rows]);
    }

    // Tax liability (output - input = payable)
    public function taxLiability(Request $request)
    {
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);
        $cid = $request->user()->company_id;

        $sales = SalesInvoice::where('company_id', $cid)->where('status', '!=', 'cancelled')
            ->whereBetween('invoice_date', [$request->from, $request->to]);
        $purchase = PurchaseInvoice::where('company_id', $cid)->where('status', '!=', 'cancelled')
            ->whereBetween('invoice_date', [$request->from, $request->to]);

        $output = [
            'cgst' => (float) $sales->sum('cgst_amount'),
            'sgst' => (float) $sales->sum('sgst_amount'),
            'igst' => (float) $sales->sum('igst_amount'),
        ];
        $input = [
            'cgst' => (float) $purchase->sum('cgst_amount'),
            'sgst' => (float) $purchase->sum('sgst_amount'),
            'igst' => (float) $purchase->sum('igst_amount'),
        ];

        return response()->json([
            'output_tax'    => $output,
            'input_tax'     => $input,
            'net_payable'   => [
                'cgst' => max(0, round($output['cgst'] - $input['cgst'], 2)),
                'sgst' => max(0, round($output['sgst'] - $input['sgst'], 2)),
                'igst' => max(0, round($output['igst'] - $input['igst'], 2)),
                'total' => max(0, round(($output['cgst'] + $output['sgst'] + $output['igst']) - ($input['cgst'] + $input['sgst'] + $input['igst']), 2)),
            ],
        ]);
    }
}
