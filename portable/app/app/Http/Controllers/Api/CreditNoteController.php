<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesInvoice;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreditNoteController extends Controller
{
    public function index(Request $request)
    {
        $cid = $request->user()->company_id;
        $rows = SalesInvoice::where('company_id', $cid)
            ->where('type', 'credit_note')
            ->with('customer:id,name')
            ->latest('invoice_date')
            ->paginate(30);
        return response()->json($rows);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'original_invoice_id' => 'required|exists:sales_invoices,id',
            'reason'              => 'required|string|max:500',
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'required|exists:products,id',
            'items.*.quantity'    => 'required|numeric|min:0.001',
            'items.*.rate'        => 'required|numeric|min:0',
            'items.*.gst_rate'    => 'required|numeric|min:0',
        ]);

        $cid     = $request->user()->company_id;
        $original = SalesInvoice::where('company_id', $cid)->with('items')->findOrFail($data['original_invoice_id']);

        if ($original->type === 'credit_note') {
            return response()->json(['message' => 'Cannot create a credit note against another credit note.'], 422);
        }

        $cn = DB::transaction(function () use ($data, $original, $cid, $request) {
            $subtotal = 0;
            $taxable  = 0;
            $cgst     = 0;
            $sgst     = 0;
            $igst     = 0;
            $items    = [];

            $supplyType = $original->supply_type ?? 'intra';

            foreach ($data['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                $qty     = (float) $item['quantity'];
                $rate    = (float) $item['rate'];
                $gstRate = (float) $item['gst_rate'];

                $lineTotal     = $qty * $rate;
                $lineTaxable   = $lineTotal;
                $lineTax       = $lineTaxable * $gstRate / 100;
                $lineTotal    += $lineTax;

                $subtotal += $qty * $rate;
                $taxable  += $lineTaxable;

                if ($supplyType === 'inter') {
                    $igst += $lineTax;
                } else {
                    $cgst += $lineTax / 2;
                    $sgst += $lineTax / 2;
                }

                $items[] = [
                    'product_id'     => $product->id,
                    'product_name'   => $product->name,
                    'hsn_code'       => $product->hsn_code,
                    'quantity'       => $qty,
                    'unit'           => $product->unit,
                    'rate'           => $rate,
                    'discount_pct'   => 0,
                    'taxable_amount' => $lineTaxable,
                    'gst_rate'       => $gstRate,
                    'cgst_amount'    => $supplyType === 'inter' ? 0 : $lineTax / 2,
                    'sgst_amount'    => $supplyType === 'inter' ? 0 : $lineTax / 2,
                    'igst_amount'    => $supplyType === 'inter' ? $lineTax : 0,
                    'total_amount'   => $lineTotal,
                ];
            }

            $total = round($taxable + $cgst + $sgst + $igst, 2);

            // Credit note number
            $count  = SalesInvoice::where('company_id', $cid)->where('type', 'credit_note')->count();
            $cnNum  = 'CN/' . date('y-m') . '/' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

            $cn = SalesInvoice::create([
                'company_id'          => $cid,
                'type'                => 'credit_note',
                'invoice_number'      => $cnNum,
                'invoice_date'        => now()->toDateString(),
                'customer_id'         => $original->customer_id,
                'supply_type'         => $supplyType,
                'notes'               => 'Credit Note against ' . $original->invoice_number . '. Reason: ' . $data['reason'],
                'subtotal'            => $subtotal,
                'discount_amount'     => 0,
                'taxable_amount'      => $taxable,
                'cgst_amount'         => $cgst,
                'sgst_amount'         => $sgst,
                'igst_amount'         => $igst,
                'round_off'           => 0,
                'total_amount'        => $total,
                'paid_amount'         => $total,
                'balance_amount'      => 0,
                'status'              => 'paid',
                'payment_mode'        => 'credit_note',
                'created_by'          => $request->user()->id,
            ]);

            foreach ($items as $item) {
                $cn->items()->create($item);
                // Reverse stock
                $product = Product::find($item['product_id']);
                if ($product?->track_inventory) {
                    $product->increment('opening_stock', $item['quantity']);
                }
            }

            return $cn;
        });

        return response()->json($cn->load(['customer', 'items']), 201);
    }
}
