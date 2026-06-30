<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialYear;
use App\Models\SalesInvoice;
use App\Services\GstService;
use App\Services\InventoryService;
use App\Services\InvoiceNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesInvoiceController extends Controller
{
    public function __construct(
        private GstService $gst,
        private InventoryService $inventory,
        private InvoiceNumberService $numberService,
    ) {}

    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        return SalesInvoice::where('company_id', $companyId)
            ->with(['customer', 'creator'])
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->from, fn ($q) => $q->whereDate('invoice_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('invoice_date', '<=', $request->to))
            ->latest('invoice_date')
            ->paginate($request->per_page ?? 25);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'invoice_type' => 'in:b2b,b2c,b2cl,export',
            'billing_address' => 'nullable|string',
            'shipping_address' => 'nullable|string',
            'notes' => 'nullable|string',
            'payment_mode' => 'nullable|string',
            'upi_ref' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.rate' => 'required|numeric|min:0',
            'items.*.gst_rate' => 'required|numeric',
            'items.*.discount_pct' => 'numeric|min:0|max:100',
        ]);

        return DB::transaction(function () use ($data, $request) {
            $user = $request->user();
            $company = $user->company;
            $fy = FinancialYear::where('company_id', $company->id)->where('is_current', true)->firstOrFail();

            $customer = \App\Models\Customer::findOrFail($data['customer_id']);
            $isIntra = !$company->gstin || !$customer->gstin
                ? true
                : $this->gst->isIntraState($company->gstin, $customer->gstin);

            $totals = $this->gst->calculateInvoiceTotals($data['items'], $isIntra);

            $invoice = SalesInvoice::create([
                'company_id' => $company->id,
                'branch_id' => $user->branch_id,
                'financial_year_id' => $fy->id,
                'customer_id' => $data['customer_id'],
                'created_by' => $user->id,
                'invoice_number' => $this->numberService->nextSalesNumber($company, $fy),
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'invoice_type' => $data['invoice_type'] ?? 'b2c',
                'supply_type' => $isIntra ? 'intra' : 'inter',
                'billing_address' => $data['billing_address'] ?? $customer->billing_address,
                'shipping_address' => $data['shipping_address'] ?? $customer->shipping_address,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'taxable_amount' => $totals['taxable_amount'],
                'cgst_amount' => $totals['cgst_amount'],
                'sgst_amount' => $totals['sgst_amount'],
                'igst_amount' => $totals['igst_amount'],
                'round_off' => $totals['round_off'],
                'total_amount' => $totals['total_amount'],
                'balance_amount' => $totals['total_amount'],
                'status' => 'confirmed',
                'payment_mode' => $data['payment_mode'] ?? null,
                'upi_ref' => $data['upi_ref'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($totals['items'] as $item) {
                $product = \App\Models\Product::find($item['product_id']);
                $invoice->items()->create(array_merge($item, [
                    'product_name' => $product->name,
                    'hsn_code' => $item['hsn_code'] ?? $product->hsn_code,
                    'unit' => $product->unit?->short_name,
                ]));

                if ($product->track_inventory) {
                    $this->inventory->recordMovement(
                        product: $product,
                        type: 'out',
                        quantity: (int) $item['quantity'],
                        rate: $item['rate'],
                        referenceType: 'sales_invoices',
                        referenceId: $invoice->id,
                        createdBy: $user->id,
                    );
                }
            }

            return response()->json($invoice->load('items.product', 'customer'), 201);
        });
    }

    public function show(Request $request, SalesInvoice $salesInvoice)
    {
        abort_if($salesInvoice->company_id !== $request->user()->company_id, 403);
        return $salesInvoice->load('items.product', 'customer', 'creator');
    }

    public function cancel(Request $request, SalesInvoice $salesInvoice)
    {
        abort_if($salesInvoice->company_id !== $request->user()->company_id, 403);
        abort_if($salesInvoice->status === 'cancelled', 422, 'Already cancelled.');

        DB::transaction(function () use ($salesInvoice, $request) {
            $salesInvoice->update(['status' => 'cancelled']);

            // Reverse stock
            foreach ($salesInvoice->items as $item) {
                $product = $item->product;
                if ($product->track_inventory) {
                    $this->inventory->recordMovement(
                        product: $product,
                        type: 'in',
                        quantity: (int) $item->quantity,
                        note: "Cancellation of {$salesInvoice->invoice_number}",
                        createdBy: $request->user()->id,
                    );
                }
            }
        });

        return response()->json(['message' => 'Invoice cancelled.']);
    }

    public function destroy(Request $request, SalesInvoice $salesInvoice)
    {
        abort_if($salesInvoice->company_id !== $request->user()->company_id, 403);
        $salesInvoice->delete();
        return response()->json(['message' => 'Invoice deleted.']);
    }
}
