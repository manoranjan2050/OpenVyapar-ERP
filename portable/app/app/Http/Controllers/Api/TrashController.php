<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;

class TrashController extends Controller
{
    // All soft-deleted records for this company
    public function index(Request $request)
    {
        $cid   = $request->user()->company_id;
        $type  = $request->input('type', 'all');
        $items = [];

        $models = [
            'sales'     => [SalesInvoice::class, ['id', 'invoice_number', 'invoice_date', 'total_amount', 'deleted_at'], 'invoice_number'],
            'purchases' => [PurchaseInvoice::class, ['id', 'bill_number', 'bill_date', 'total_amount', 'deleted_at'], 'bill_number'],
            'products'  => [Product::class, ['id', 'name', 'sku', 'deleted_at'], 'name'],
            'customers' => [Customer::class, ['id', 'name', 'phone', 'deleted_at'], 'name'],
            'suppliers' => [Supplier::class, ['id', 'name', 'phone', 'deleted_at'], 'name'],
            'payments'  => [Payment::class, ['id', 'payment_number', 'amount', 'payment_date', 'deleted_at'], 'payment_number'],
            'users'     => [User::class, ['id', 'name', 'email', 'deleted_at'], 'name'],
        ];

        foreach ($models as $key => [$model, $cols, $label]) {
            if ($type !== 'all' && $type !== $key) continue;
            $query = $model::onlyTrashed();
            if ($key !== 'users') $query->where('company_id', $cid);
            else $query->where('company_id', $cid);
            $rows = $query->select($cols)->orderByDesc('deleted_at')->get()
                ->map(fn($r) => array_merge($r->toArray(), ['_type' => $key, '_label' => $r->$label ?? '—']));
            $items = array_merge($items, $rows->toArray());
        }

        usort($items, fn($a, $b) => strcmp((string)$b['deleted_at'], (string)$a['deleted_at']));

        return response()->json([
            'items' => $items,
            'total' => count($items),
            'by_type' => collect($items)->groupBy('_type')->map->count(),
        ]);
    }

    // Restore a single record
    public function restore(Request $request)
    {
        $cid  = $request->user()->company_id;
        $type = $request->input('type');
        $id   = $request->input('id');

        $model = $this->resolveModel($type);
        if (!$model) return response()->json(['message' => 'Unknown type.'], 422);

        $record = $model::onlyTrashed()->where('company_id', $cid)->findOrFail($id);
        $record->restore();

        return response()->json(['message' => ucfirst($type) . ' restored successfully.']);
    }

    // Permanently delete a single record
    public function forceDelete(Request $request)
    {
        $cid  = $request->user()->company_id;
        $type = $request->input('type');
        $id   = $request->input('id');

        $model = $this->resolveModel($type);
        if (!$model) return response()->json(['message' => 'Unknown type.'], 422);

        $record = $model::onlyTrashed()->where('company_id', $cid)->findOrFail($id);
        $record->forceDelete();

        return response()->json(['message' => 'Permanently deleted.']);
    }

    // Empty entire trash (force delete all)
    public function empty(Request $request)
    {
        $cid = $request->user()->company_id;
        $count = 0;

        foreach ([SalesInvoice::class, PurchaseInvoice::class, Product::class, Customer::class, Supplier::class, Payment::class, User::class] as $model) {
            $rows = $model::onlyTrashed()->where('company_id', $cid)->get();
            foreach ($rows as $row) { $row->forceDelete(); $count++; }
        }

        return response()->json(['message' => "Trash emptied. {$count} items permanently deleted."]);
    }

    // Restore all
    public function restoreAll(Request $request)
    {
        $cid = $request->user()->company_id;
        $count = 0;

        foreach ([SalesInvoice::class, PurchaseInvoice::class, Product::class, Customer::class, Supplier::class, Payment::class, User::class] as $model) {
            $n = $model::onlyTrashed()->where('company_id', $cid)->restore();
            $count += $n;
        }

        return response()->json(['message' => "{$count} items restored."]);
    }

    private function resolveModel(string $type): ?string
    {
        return match ($type) {
            'sales'     => SalesInvoice::class,
            'purchases' => PurchaseInvoice::class,
            'products'  => Product::class,
            'customers' => Customer::class,
            'suppliers' => Supplier::class,
            'payments'  => Payment::class,
            'users'     => User::class,
            default     => null,
        };
    }
}
