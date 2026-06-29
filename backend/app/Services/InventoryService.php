<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockTransaction;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function recordMovement(
        Product $product,
        string $type,
        int $quantity,
        ?float $rate = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
        ?int $warehouseId = null,
        ?int $createdBy = null,
    ): StockTransaction {
        return DB::transaction(function () use ($product, $type, $quantity, $rate, $referenceType, $referenceId, $note, $warehouseId, $createdBy) {
            $current = StockTransaction::where('product_id', $product->id)
                ->latest('transacted_at')
                ->lockForUpdate()
                ->value('balance_after') ?? $product->opening_stock;

            $delta = in_array($type, ['in']) ? abs($quantity) : -abs($quantity);
            $newBalance = $current + $delta;

            return StockTransaction::create([
                'company_id' => $product->company_id,
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'type' => $type,
                'quantity' => abs($quantity),
                'balance_after' => $newBalance,
                'rate' => $rate,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'note' => $note,
                'created_by' => $createdBy,
                'transacted_at' => now(),
            ]);
        });
    }

    public function currentStock(Product $product): int
    {
        return StockTransaction::where('product_id', $product->id)
            ->latest('transacted_at')
            ->value('balance_after') ?? $product->opening_stock;
    }

    public function lowStockProducts(int $companyId): \Illuminate\Database\Eloquent\Collection
    {
        return Product::where('company_id', $companyId)
            ->where('track_inventory', true)
            ->where('is_active', true)
            ->with(['stockTransactions' => fn ($q) => $q->latest('transacted_at')->limit(1)])
            ->get()
            ->filter(fn ($p) => $this->currentStock($p) <= $p->low_stock_alert);
    }
}
