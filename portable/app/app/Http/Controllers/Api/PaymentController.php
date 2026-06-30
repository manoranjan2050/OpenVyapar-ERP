<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SalesInvoice;
use App\Models\PurchaseInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    // Record payment against a Sales Invoice
    public function storeSales(Request $request, SalesInvoice $salesInvoice)
    {
        abort_if($salesInvoice->company_id !== $request->user()->company_id, 403);
        abort_if($salesInvoice->status === 'cancelled', 422, 'Cannot pay a cancelled invoice.');
        abort_if((float) $salesInvoice->balance_amount <= 0, 422, 'Invoice is already fully paid.');

        $data = $request->validate([
            'amount'       => 'required|numeric|min:0.01|max:' . $salesInvoice->balance_amount,
            'mode'         => 'required|in:cash,upi,bank,cheque,card',
            'reference'    => 'nullable|string|max:100',
            'payment_date' => 'required|date',
            'notes'        => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($data, $salesInvoice, $request) {
            $payment = Payment::create([
                'uuid'         => (string) \Illuminate\Support\Str::uuid(),
                'company_id'   => $salesInvoice->company_id,
                'payment_number' => 'REC/' . date('y-m') . '/' . str_pad(Payment::where('company_id', $salesInvoice->company_id)->count() + 1, 4, '0', STR_PAD_LEFT),
                'type'         => 'received',
                'party_type'   => 'App\\Models\\Customer',
                'party_id'     => $salesInvoice->customer_id,
                'amount'       => $data['amount'],
                'mode'         => $data['mode'],
                'reference'    => $data['reference'] ?? null,
                'payment_date' => $data['payment_date'],
                'notes'        => $data['notes'] ?? null,
                'created_by'   => $request->user()->id,
            ]);

            $newPaid    = (float) $salesInvoice->paid_amount + (float) $data['amount'];
            $newBalance = (float) $salesInvoice->total_amount - $newPaid;

            $status = $newBalance <= 0 ? 'paid' : 'partially_paid';

            $salesInvoice->update([
                'paid_amount'    => $newPaid,
                'balance_amount' => max(0, $newBalance),
                'status'         => $status,
            ]);

            return response()->json([
                'payment' => $payment,
                'invoice' => $salesInvoice->fresh(),
                'message' => 'Payment recorded successfully.',
            ], 201);
        });
    }

    // Record payment against a Purchase Invoice
    public function storePurchase(Request $request, PurchaseInvoice $purchaseInvoice)
    {
        abort_if($purchaseInvoice->company_id !== $request->user()->company_id, 403);
        abort_if($purchaseInvoice->status === 'cancelled', 422, 'Cannot pay a cancelled invoice.');
        abort_if((float) $purchaseInvoice->balance_amount <= 0, 422, 'Invoice is already fully paid.');

        $data = $request->validate([
            'amount'       => 'required|numeric|min:0.01|max:' . $purchaseInvoice->balance_amount,
            'mode'         => 'required|in:cash,upi,bank,cheque,card',
            'reference'    => 'nullable|string|max:100',
            'payment_date' => 'required|date',
            'notes'        => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($data, $purchaseInvoice, $request) {
            $payment = Payment::create([
                'uuid'         => (string) \Illuminate\Support\Str::uuid(),
                'company_id'   => $purchaseInvoice->company_id,
                'payment_number' => 'PAY/' . date('y-m') . '/' . str_pad(Payment::where('company_id', $purchaseInvoice->company_id)->count() + 1, 4, '0', STR_PAD_LEFT),
                'type'         => 'made',
                'party_type'   => 'App\\Models\\Supplier',
                'party_id'     => $purchaseInvoice->supplier_id,
                'amount'       => $data['amount'],
                'mode'         => $data['mode'],
                'reference'    => $data['reference'] ?? null,
                'payment_date' => $data['payment_date'],
                'notes'        => $data['notes'] ?? null,
                'created_by'   => $request->user()->id,
            ]);

            $newPaid    = (float) $purchaseInvoice->paid_amount + (float) $data['amount'];
            $newBalance = (float) $purchaseInvoice->total_amount - $newPaid;
            $status     = $newBalance <= 0 ? 'paid' : 'partially_paid';

            $purchaseInvoice->update([
                'paid_amount'    => $newPaid,
                'balance_amount' => max(0, $newBalance),
                'status'         => $status,
            ]);

            return response()->json([
                'payment' => $payment,
                'invoice' => $purchaseInvoice->fresh(),
                'message' => 'Payment recorded successfully.',
            ], 201);
        });
    }
}
