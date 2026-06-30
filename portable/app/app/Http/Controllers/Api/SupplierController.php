<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        return Supplier::where('company_id', $request->user()->company_id)
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
                  ->orWhere('gstin', 'like', "%{$request->search}%");
            }))
            ->orderBy('name')
            ->paginate($request->per_page ?? 25);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'gstin' => 'nullable|string|size:15',
            'pan' => 'nullable|string|size:10',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'pincode' => 'nullable|string',
            'credit_days' => 'integer|min:0',
            'opening_balance' => 'numeric|min:0',
            'opening_balance_type' => 'in:debit,credit',
        ]);

        $data['company_id'] = $request->user()->company_id;
        return response()->json(Supplier::create($data), 201);
    }

    public function show(Request $request, Supplier $supplier)
    {
        abort_if($supplier->company_id !== $request->user()->company_id, 403);
        return $supplier->load('purchaseInvoices');
    }

    public function update(Request $request, Supplier $supplier)
    {
        abort_if($supplier->company_id !== $request->user()->company_id, 403);
        $supplier->update($request->validate([
            'name' => 'sometimes|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'gstin' => 'nullable|string|size:15',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'pincode' => 'nullable|string',
            'credit_days' => 'integer|min:0',
            'is_active' => 'boolean',
        ]));
        return $supplier;
    }

    public function destroy(Request $request, Supplier $supplier)
    {
        abort_if($supplier->company_id !== $request->user()->company_id, 403);
        $supplier->delete();
        return response()->json(['message' => 'Deleted.']);
    }
}
