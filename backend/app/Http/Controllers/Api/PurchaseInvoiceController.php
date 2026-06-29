<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialYear;
use App\Models\PurchaseInvoice;
use App\Services\GstService;
use App\Services\InventoryService;
use App\Services\InvoiceNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceController extends Controller
{
    public function __construct(
        private GstService $gst,
        private InventoryService $inventory,
        private InvoiceNumberService $numberService,
    ) {}

    public function index(Request $request)
    {
        return PurchaseInvoice::where('company_id', $request->user()->company_id)
            ->with(['supplier', 'creator'])
            ->when($request->supplier_id, fn ($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->from, fn ($q) => $q->whereDate('invoice_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('invoice_date', '<=', $request->to))
            ->latest('invoice_date')
            ->paginate($request->per_page ?? 25);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'supplier_invoice_number' => 'nullable|string',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string',
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

            $supplier = \App\Models\Supplier::findOrFail($data['supplier_id']);
            $isIntra = !$company->gstin || !$supplier->gstin
                ? true
                : $this->gst->isIntraState($company->gstin, $supplier->gstin);

            $totals = $this->gst->calculateInvoiceTotals($data['items'], $isIntra);

            $invoice = PurchaseInvoice::create([
                'company_id' => $company->id,
                'branch_id' => $user->branch_id,
                'financial_year_id' => $fy->id,
                'supplier_id' => $data['supplier_id'],
                'created_by' => $user->id,
                'invoice_number' => $this->numberService->nextPurchaseNumber($company, $fy),
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'taxable_amount' => $totals['taxable_amount'],
                'cgst_amount' => $totals['cgst_amount'],
                'sgst_amount' => $totals['sgst_amount'],
                'igst_amount' => $totals['igst_amount'],
                'total_amount' => $totals['total_amount'],
                'balance_amount' => $totals['total_amount'],
                'status' => 'confirmed',
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
                        type: 'in',
                        quantity: (int) $item['quantity'],
                        rate: $item['rate'],
                        referenceType: 'purchase_invoices',
                        referenceId: $invoice->id,
                        createdBy: $user->id,
                    );
                }
            }

            return response()->json($invoice->load('items.product', 'supplier'), 201);
        });
    }

    public function show(Request $request, PurchaseInvoice $purchaseInvoice)
    {
        abort_if($purchaseInvoice->company_id !== $request->user()->company_id, 403);
        return $purchaseInvoice->load('items.product', 'supplier', 'creator');
    }
}
