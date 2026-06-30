<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockTransaction;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockAdjustmentController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request)
    {
        $cid  = $request->user()->company_id;
        $type = $request->input('type', 'adjustment'); // adjustment | damage | loss
        $rows = StockTransaction::where('company_id', $cid)
            ->where('type', $type)
            ->with('product:id,name,sku')
            ->latest('transacted_at')
            ->paginate(50);
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
            'type'       => 'nullable|in:adjustment,damage,loss',
        ]);

        $cid     = $request->user()->company_id;
        $product = Product::where('company_id', $cid)->findOrFail($data['product_id']);

        if (!$product->track_inventory) {
            return response()->json(['message' => 'Inventory tracking is disabled for this product.'], 422);
        }

        $txType = $data['type'] ?? 'adjustment';
        $qty    = (float) $data['quantity'];

        // Damage/loss always subtracts
        if (in_array($txType, ['damage', 'loss']) && $qty > 0) {
            $qty = -$qty;
        }

        DB::transaction(function () use ($data, $product, $cid, $request, $qty, $txType) {
            // Always use actual current stock, not opening_stock
            $current  = $this->inventory->currentStock($product);
            $newStock = max(0, $current + $qty);

            StockTransaction::create([
                'company_id'    => $cid,
                'product_id'    => $product->id,
                'type'          => $txType,
                'quantity'      => $qty,
                'balance_after' => $newStock,
                'rate'          => $product->purchase_price,
                'note'          => '[' . $data['reason'] . '] ' . ($data['note'] ?? ''),
                'created_by'    => $request->user()->id,
                'transacted_at' => $data['date'],
            ]);
        });

        return response()->json(['message' => 'Stock updated.', 'product' => $product->fresh()]);
    }

    public function history(Request $request, Product $product)
    {
        abort_if($product->company_id !== $request->user()->company_id, 403);
        $rows = StockTransaction::where('product_id', $product->id)
            ->orderByDesc('transacted_at')
            ->limit(50)
            ->get();
        return response()->json([
            'product'      => $product->only(['id','name','sku','opening_stock']),
            'current_stock'=> $this->inventory->currentStock($product),
            'history'      => $rows,
        ]);
    }
}
