<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\AlertController;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\SalesInvoice;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LedgerController extends Controller
{
    // ─────────────────────────────────────────────
    //  CUSTOMER LEDGER
    // ─────────────────────────────────────────────
    public function customer(Request $request, Customer $customer)
    {
        $cid  = $request->user()->company_id;
        $from = $request->input('from');
        $to   = $request->input('to');

        $invQ = SalesInvoice::where('company_id', $cid)
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['cancelled', 'draft']);

        $payQ = Payment::where('company_id', $cid)
            ->where('party_type', Customer::class)
            ->where('party_id', $customer->id);

        if ($from) { $invQ->whereDate('invoice_date', '>=', $from); $payQ->whereDate('payment_date', '>=', $from); }
        if ($to)   { $invQ->whereDate('invoice_date', '<=', $to);   $payQ->whereDate('payment_date', '<=', $to); }

        $invoices = $invQ->orderBy('invoice_date')->get();
        $payments = $payQ->orderBy('payment_date')->get();

        $entries = collect();

        // Opening balance as first entry
        if ($customer->opening_balance && (float)$customer->opening_balance > 0) {
            $entries->push([
                'date'        => $customer->created_at->format('Y-m-d'),
                'type'        => 'opening',
                'ref'         => 'OB',
                'description' => 'Opening Balance',
                'debit'       => $customer->opening_balance_type === 'debit'  ? (float)$customer->opening_balance : 0,
                'credit'      => $customer->opening_balance_type === 'credit' ? (float)$customer->opening_balance : 0,
                'mode'        => null,
                'id'          => null,
            ]);
        }

        foreach ($invoices as $inv) {
            $entries->push([
                'date'        => (string)$inv->invoice_date,
                'type'        => $inv->type === 'credit_note' ? 'credit_note' : 'invoice',
                'ref'         => $inv->invoice_number,
                'description' => $inv->type === 'credit_note' ? 'Credit Note' : 'Sales Invoice',
                'debit'       => $inv->type === 'credit_note' ? 0 : (float)$inv->total_amount,
                'credit'      => $inv->type === 'credit_note' ? (float)$inv->total_amount : 0,
                'mode'        => null,
                'id'          => $inv->id,
                'status'      => $inv->status,
                'due_date'    => (string)$inv->due_date,
                'balance_due' => (float)$inv->balance_amount,
            ]);
        }

        foreach ($payments as $pay) {
            $isAdvance = $pay->type === 'advance';
            $entries->push([
                'date'        => (string)$pay->payment_date,
                'type'        => $isAdvance ? 'advance' : 'payment',
                'ref'         => $pay->payment_number,
                'description' => ($isAdvance ? 'Advance Payment' : 'Payment Received') . ' (' . ucfirst($pay->mode ?? 'cash') . ')',
                'debit'       => 0,
                'credit'      => (float)$pay->amount,
                'mode'        => $pay->mode,
                'id'          => $pay->id,
                'notes'       => $pay->notes,
                'reference'   => $pay->reference,
            ]);
        }

        $sorted  = $entries->sortBy('date')->values();
        $balance = 0;
        $rows    = $sorted->map(function ($e) use (&$balance) {
            $balance += $e['debit'] - $e['credit'];
            return array_merge($e, ['balance' => round($balance, 2)]);
        });

        $totalDebit   = round($sorted->sum('debit'), 2);
        $totalCredit  = round($sorted->sum('credit'), 2);
        $netBalance   = round($totalDebit - $totalCredit, 2);

        // Overdue invoices
        $overdue = SalesInvoice::where('company_id', $cid)
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['confirmed', 'partially_paid'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->get(['invoice_number', 'due_date', 'balance_amount']);

        // Advance balance (unused advance payments)
        $advanceTotal = Payment::where('company_id', $cid)
            ->where('party_type', Customer::class)
            ->where('party_id', $customer->id)
            ->where('type', 'advance')
            ->sum('amount');

        return response()->json([
            'party'          => array_merge($customer->only(['id', 'name', 'gstin', 'phone', 'email', 'credit_limit', 'credit_days', 'opening_balance', 'opening_balance_type']), ['party_type' => 'customer']),
            'entries'        => $rows,
            'total_debit'    => $totalDebit,
            'total_credit'   => $totalCredit,
            'balance'        => $netBalance,
            'overdue'        => $overdue,
            'overdue_amount' => $overdue->sum('balance_amount'),
            'advance_balance'=> round((float)$advanceTotal, 2),
            'credit_limit'   => (float)$customer->credit_limit,
            'credit_used'    => $netBalance,
            'credit_available'=> max(0, (float)$customer->credit_limit - $netBalance),
        ]);
    }

    // ─────────────────────────────────────────────
    //  SUPPLIER LEDGER
    // ─────────────────────────────────────────────
    public function supplier(Request $request, Supplier $supplier)
    {
        $cid  = $request->user()->company_id;
        $from = $request->input('from');
        $to   = $request->input('to');

        $invQ = PurchaseInvoice::where('company_id', $cid)
            ->where('supplier_id', $supplier->id)
            ->where('status', '!=', 'cancelled');

        $payQ = Payment::where('company_id', $cid)
            ->where('party_type', Supplier::class)
            ->where('party_id', $supplier->id);

        if ($from) { $invQ->whereDate('invoice_date', '>=', $from); $payQ->whereDate('payment_date', '>=', $from); }
        if ($to)   { $invQ->whereDate('invoice_date', '<=', $to);   $payQ->whereDate('payment_date', '<=', $to); }

        $invoices = $invQ->orderBy('invoice_date')->get();
        $payments = $payQ->orderBy('payment_date')->get();

        $entries = collect();

        if ($supplier->opening_balance && (float)$supplier->opening_balance > 0) {
            $entries->push([
                'date' => $supplier->created_at->format('Y-m-d'), 'type' => 'opening', 'ref' => 'OB',
                'description' => 'Opening Balance',
                'debit'  => $supplier->opening_balance_type === 'debit'  ? (float)$supplier->opening_balance : 0,
                'credit' => $supplier->opening_balance_type === 'credit' ? (float)$supplier->opening_balance : 0,
                'mode' => null, 'id' => null,
            ]);
        }

        foreach ($invoices as $inv) {
            $entries->push([
                'date'        => (string)$inv->invoice_date,
                'type'        => 'invoice',
                'ref'         => $inv->invoice_number ?? $inv->bill_number,
                'description' => 'Purchase Bill',
                'debit'       => 0,
                'credit'      => (float)$inv->total_amount,
                'mode'        => null,
                'id'          => $inv->id,
                'status'      => $inv->status,
                'balance_due' => (float)$inv->balance_amount,
            ]);
        }

        foreach ($payments as $pay) {
            $entries->push([
                'date'        => (string)$pay->payment_date,
                'type'        => $pay->type === 'advance' ? 'advance' : 'payment',
                'ref'         => $pay->payment_number,
                'description' => ($pay->type === 'advance' ? 'Advance Paid' : 'Payment Made') . ' (' . ucfirst($pay->mode ?? 'cash') . ')',
                'debit'       => (float)$pay->amount,
                'credit'      => 0,
                'mode'        => $pay->mode,
                'id'          => $pay->id,
                'notes'       => $pay->notes,
                'reference'   => $pay->reference,
            ]);
        }

        $sorted  = $entries->sortBy('date')->values();
        $balance = 0;
        $rows    = $sorted->map(function ($e) use (&$balance) {
            $balance += $e['credit'] - $e['debit'];
            return array_merge($e, ['balance' => round($balance, 2)]);
        });

        $totalDebit  = round($sorted->sum('debit'), 2);
        $totalCredit = round($sorted->sum('credit'), 2);

        // Overdue
        $overdue = PurchaseInvoice::where('company_id', $cid)
            ->where('supplier_id', $supplier->id)
            ->whereIn('status', ['confirmed', 'partially_paid'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->get(['invoice_number', 'due_date', 'balance_amount']);

        return response()->json([
            'party'          => array_merge($supplier->only(['id', 'name', 'gstin', 'phone', 'email']), ['party_type' => 'supplier']),
            'entries'        => $rows,
            'total_debit'    => $totalDebit,
            'total_credit'   => $totalCredit,
            'balance'        => round($totalCredit - $totalDebit, 2),
            'overdue'        => $overdue,
            'overdue_amount' => $overdue->sum('balance_amount'),
        ]);
    }

    // ─────────────────────────────────────────────
    //  QUICK PAYMENT (Customer or Supplier)
    // ─────────────────────────────────────────────
    public function recordPayment(Request $request)
    {
        $cid  = $request->user()->company_id;
        $data = $request->validate([
            'party_type'    => 'required|in:customer,supplier',
            'party_id'      => 'required|integer',
            'amount'        => 'required|numeric|min:0.01',
            'mode'          => 'required|in:cash,bank,upi,cheque,online,neft,rtgs,imps',
            'type'          => 'required|in:received,paid,advance',
            'payment_date'  => 'required|date',
            'reference'     => 'nullable|string|max:100',
            'notes'         => 'nullable|string|max:500',
            'invoice_id'    => 'nullable|integer',  // link to specific invoice
        ]);

        $partyClass = $data['party_type'] === 'customer' ? Customer::class : Supplier::class;

        // Auto-number
        $last = Payment::where('company_id', $cid)
            ->where('payment_number', 'like', 'PAY/%')
            ->orderByDesc('id')->first();
        $seq  = $last ? ((int) substr($last->payment_number, strrpos($last->payment_number, '/') + 1)) + 1 : 1;
        $num  = 'PAY/' . now()->format('y-m') . '/' . str_pad($seq, 4, '0', STR_PAD_LEFT);

        $payment = Payment::create([
            'uuid'           => (string)Str::uuid(),
            'company_id'     => $cid,
            'payment_number' => $num,
            'type'           => $data['type'],
            'party_type'     => $partyClass,
            'party_id'       => $data['party_id'],
            'amount'         => $data['amount'],
            'mode'           => $data['mode'],
            'reference'      => $data['reference'] ?? null,
            'payment_date'   => $data['payment_date'],
            'notes'          => $data['notes'] ?? null,
            'created_by'     => $request->user()->id,
        ]);

        // If linked to an invoice, update paid/balance
        if (!empty($data['invoice_id'])) {
            if ($data['party_type'] === 'customer') {
                $inv = SalesInvoice::where('company_id', $cid)->find($data['invoice_id']);
                if ($inv) {
                    $inv->paid_amount    = min($inv->total_amount, (float)$inv->paid_amount + (float)$data['amount']);
                    $inv->balance_amount = max(0, (float)$inv->total_amount - (float)$inv->paid_amount);
                    $inv->status         = $inv->balance_amount <= 0 ? 'paid' : 'partially_paid';
                    $inv->save();
                }
            } else {
                $inv = PurchaseInvoice::where('company_id', $cid)->find($data['invoice_id']);
                if ($inv) {
                    $inv->paid_amount    = min($inv->total_amount, (float)$inv->paid_amount + (float)$data['amount']);
                    $inv->balance_amount = max(0, (float)$inv->total_amount - (float)$inv->paid_amount);
                    $inv->status         = $inv->balance_amount <= 0 ? 'paid' : 'partially_paid';
                    $inv->save();
                }
            }
        }

        // Alert dispatch
        AlertController::dispatch($cid, 'new_sale',
            "💳 Payment {$data['type']} ₹{$data['amount']} via " . strtoupper($data['mode']) . " | {$num}"
        );

        return response()->json(['message' => 'Payment recorded.', 'payment' => $payment->fresh()], 201);
    }

    // ─────────────────────────────────────────────
    //  CREDIT DUE ALERT
    // ─────────────────────────────────────────────
    public function sendCreditDueAlert(Request $request)
    {
        $cid      = $request->user()->company_id;
        $settings = DB::table('notification_settings')->where('company_id', $cid)->first();

        // All customers with overdue
        $customers = Customer::where('company_id', $cid)->where('is_active', true)->get();
        $alerts    = [];

        foreach ($customers as $c) {
            $overdue = SalesInvoice::where('company_id', $cid)
                ->where('customer_id', $c->id)
                ->whereIn('status', ['confirmed', 'partially_paid'])
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString())
                ->get(['invoice_number', 'due_date', 'balance_amount']);

            if ($overdue->isEmpty()) continue;

            $totalDue = $overdue->sum('balance_amount');
            $daysArr  = $overdue->map(fn($i) => now()->diffInDays($i->due_date, false) * -1);
            $maxDays  = $daysArr->max();
            $alerts[] = ['customer' => $c->name, 'phone' => $c->phone, 'total_due' => $totalDue, 'invoices' => $overdue->count(), 'max_days' => $maxDays];
        }

        if (empty($alerts)) return response()->json(['message' => 'No overdue customers. All clear!', 'count' => 0]);

        $lines = collect($alerts)->map(fn($a) =>
            "📌 *{$a['customer']}*: ₹" . number_format($a['total_due'], 2) . " ({$a['invoices']} invoice(s), {$a['max_days']} days overdue)"
        )->join("\n");

        $msg = "🔔 *Credit Due Alert — OpenVyapar ERP*\n\n{$lines}\n\nTotal: " . count($alerts) . " customers with overdue payments.";

        if ($settings) {
            if ($settings->telegram_enabled && $settings->telegram_bot_token) {
                \Illuminate\Support\Facades\Http::post(
                    "https://api.telegram.org/bot{$settings->telegram_bot_token}/sendMessage",
                    ['chat_id' => $settings->telegram_chat_id, 'text' => $msg, 'parse_mode' => 'Markdown']
                );
            }
            if ($settings->email_enabled && $settings->alert_email) {
                try {
                    app('mailer')->raw(strip_tags(str_replace('*', '', $msg)), fn($m) =>
                        $m->to($settings->alert_email)->subject('Credit Due Alert — ' . count($alerts) . ' customers')
                    );
                } catch (\Exception $e) {}
            }
        }

        return response()->json(['message' => 'Alert sent for ' . count($alerts) . ' overdue customers.', 'alerts' => $alerts, 'count' => count($alerts)]);
    }

    // ─────────────────────────────────────────────
    //  ALL CUSTOMERS SUMMARY (outstanding list)
    // ─────────────────────────────────────────────
    public function customersSummary(Request $request)
    {
        $cid = $request->user()->company_id;

        $customers = Customer::where('company_id', $cid)->where('is_active', true)->get();

        $result = $customers->map(function ($c) use ($cid) {
            $totalInvoiced = SalesInvoice::where('company_id', $cid)->where('customer_id', $c->id)
                ->whereNotIn('status', ['cancelled', 'draft'])->sum('total_amount');
            $totalPaid = Payment::where('company_id', $cid)
                ->where('party_type', Customer::class)->where('party_id', $c->id)->sum('amount');
            $overdueCnt = SalesInvoice::where('company_id', $cid)->where('customer_id', $c->id)
                ->whereIn('status', ['confirmed', 'partially_paid'])
                ->whereNotNull('due_date')->whereDate('due_date', '<', now()->toDateString())->count();
            $balance = round((float)$totalInvoiced - (float)$totalPaid + (float)$c->opening_balance, 2);

            return [
                'id'            => $c->id,
                'name'          => $c->name,
                'phone'         => $c->phone,
                'email'         => $c->email,
                'credit_limit'  => (float)$c->credit_limit,
                'total_invoiced'=> (float)$totalInvoiced,
                'total_paid'    => (float)$totalPaid,
                'balance'       => $balance,
                'overdue_count' => $overdueCnt,
                'credit_status' => $c->credit_limit > 0
                    ? ($balance >= (float)$c->credit_limit ? 'exceeded' : ($balance >= (float)$c->credit_limit * 0.8 ? 'warning' : 'ok'))
                    : 'none',
            ];
        })->sortByDesc('balance')->values();

        return response()->json([
            'customers'     => $result,
            'total_outstanding' => $result->sum('balance'),
            'overdue_customers' => $result->where('overdue_count', '>', 0)->count(),
        ]);
    }

    // ─────────────────────────────────────────────
    //  ALL SUPPLIERS SUMMARY
    // ─────────────────────────────────────────────
    public function suppliersSummary(Request $request)
    {
        $cid = $request->user()->company_id;

        $suppliers = Supplier::where('company_id', $cid)->where('is_active', true)->get();

        $result = $suppliers->map(function ($s) use ($cid) {
            $totalBills = PurchaseInvoice::where('company_id', $cid)->where('supplier_id', $s->id)
                ->where('status', '!=', 'cancelled')->sum('total_amount');
            $totalPaid  = Payment::where('company_id', $cid)
                ->where('party_type', Supplier::class)->where('party_id', $s->id)->sum('amount');
            $overdueCnt = PurchaseInvoice::where('company_id', $cid)->where('supplier_id', $s->id)
                ->whereIn('status', ['confirmed', 'partially_paid'])
                ->whereNotNull('due_date')->whereDate('due_date', '<', now()->toDateString())->count();
            $balance = round((float)$totalBills - (float)$totalPaid, 2);

            return [
                'id'          => $s->id,
                'name'        => $s->name,
                'phone'       => $s->phone,
                'email'       => $s->email,
                'total_bills' => (float)$totalBills,
                'total_paid'  => (float)$totalPaid,
                'balance'     => $balance,
                'overdue_count' => $overdueCnt,
            ];
        })->sortByDesc('balance')->values();

        return response()->json([
            'suppliers'         => $result,
            'total_payable'     => $result->sum('balance'),
            'overdue_suppliers' => $result->where('overdue_count', '>', 0)->count(),
        ]);
    }

    // ─────────────────────────────────────────────
    //  STATEMENT EXPORT (Excel-friendly JSON)
    // ─────────────────────────────────────────────
    public function statement(Request $request, string $partyType, int $partyId)
    {
        if ($partyType === 'customer') {
            $party = Customer::findOrFail($partyId);
            $resp  = $this->customer($request, $party);
        } else {
            $party = Supplier::findOrFail($partyId);
            $resp  = $this->supplier($request, $party);
        }
        return $resp;
    }
}
