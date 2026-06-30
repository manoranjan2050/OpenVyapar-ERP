<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $cid = $request->user()->company_id;
        $rows = StockTransaction::where('company_id', $cid)
            ->where('type', 'adjustment')
            ->with('product:id,name,sku')
            ->latest()
            ->paginate(30);
        return response()->json($rows);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|numeric|not_in:0',
            'reason'     => 'required|string|max:255',
            'note'       => 'nullable|string|max:500',
            'date'       => 'required|date',
        ]);

        $cid     = $request->user()->company_id;
        $product = Product::where('company_id', $cid)->findOrFail($data['product_id']);

        if (!$product->track_inventory) {
            return response()->json(['message' => 'Inventory tracking is disabled for this product.'], 422);
        }

        DB::transaction(function () use ($data, $product, $cid, $request) {
            $qty = (float) $data['quantity'];
            $newStock = max(0, ($product->opening_stock ?? 0) + $qty);

            $product->update(['opening_stock' => $newStock]);

            StockTransaction::create([
                'company_id'     => $cid,
                'product_id'     => $product->id,
                'type'           => 'adjustment',
                'quantity'       => $qty,
                'balance_after'  => $newStock,
                'rate'           => $product->purchase_price,
                'note'           => '[' . $data['reason'] . '] ' . ($data['note'] ?? ''),
                'created_by'     => $request->user()->id,
                'transacted_at'  => $data['date'],
            ]);
        });

        return response()->json(['message' => 'Stock adjusted successfully.', 'product' => $product->fresh()]);
    }
}
