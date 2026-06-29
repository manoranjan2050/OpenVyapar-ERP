<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        return Product::where('company_id', $companyId)
            ->with(['category', 'unit'])
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('sku', 'like', "%{$request->search}%")
                  ->orWhere('barcode', 'like', "%{$request->search}%");
            }))
            ->when($request->category_id, fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->active_only, fn ($q) => $q->where('is_active', true))
            ->paginate($request->per_page ?? 25);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'unit_id' => 'nullable|exists:units,id',
            'sku' => 'nullable|string',
            'barcode' => 'nullable|string',
            'hsn_code' => 'nullable|string|max:8',
            'gst_rate' => 'required|in:0,0.25,3,5,12,18,28',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'mrp' => 'nullable|numeric|min:0',
            'opening_stock' => 'integer|min:0',
            'low_stock_alert' => 'integer|min:0',
            'track_inventory' => 'boolean',
        ]);

        $data['company_id'] = $request->user()->company_id;
        $product = Product::create($data);

        return response()->json($product->load('category', 'unit'), 201);
    }

    public function show(Request $request, Product $product)
    {
        $this->authorizeCompany($request, $product->company_id);
        return $product->load('category', 'unit')->append([]);
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeCompany($request, $product->company_id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'unit_id' => 'nullable|exists:units,id',
            'sku' => 'nullable|string',
            'barcode' => 'nullable|string',
            'hsn_code' => 'nullable|string|max:8',
            'gst_rate' => 'sometimes|in:0,0.25,3,5,12,18,28',
            'purchase_price' => 'sometimes|numeric|min:0',
            'selling_price' => 'sometimes|numeric|min:0',
            'mrp' => 'nullable|numeric|min:0',
            'low_stock_alert' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $product->update($data);
        return $product->load('category', 'unit');
    }

    public function destroy(Request $request, Product $product)
    {
        $this->authorizeCompany($request, $product->company_id);
        $product->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    public function stock(Request $request, Product $product)
    {
        $this->authorizeCompany($request, $product->company_id);
        return response()->json([
            'product_id' => $product->id,
            'current_stock' => $this->inventory->currentStock($product),
            'low_stock_alert' => $product->low_stock_alert,
        ]);
    }

    public function lowStock(Request $request)
    {
        return $this->inventory->lowStockProducts($request->user()->company_id)->values();
    }

    private function authorizeCompany(Request $request, int $companyId): void
    {
        abort_if($request->user()->company_id !== $companyId, 403, 'Forbidden.');
    }
}
