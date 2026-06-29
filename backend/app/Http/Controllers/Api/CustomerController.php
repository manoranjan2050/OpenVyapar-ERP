<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        return Customer::where('company_id', $companyId)
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
                  ->orWhere('gstin', 'like', "%{$request->search}%");
            }))
            ->when($request->active_only, fn ($q) => $q->where('is_active', true))
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
            'billing_address' => 'nullable|string',
            'billing_city' => 'nullable|string',
            'billing_state' => 'nullable|string',
            'billing_pincode' => 'nullable|string',
            'shipping_address' => 'nullable|string',
            'credit_limit' => 'numeric|min:0',
            'credit_days' => 'integer|min:0',
            'opening_balance' => 'numeric|min:0',
            'opening_balance_type' => 'in:debit,credit',
        ]);

        $data['company_id'] = $request->user()->company_id;
        $customer = Customer::create($data);

        return response()->json($customer, 201);
    }

    public function show(Request $request, Customer $customer)
    {
        abort_if($customer->company_id !== $request->user()->company_id, 403);
        return $customer->load('salesInvoices');
    }

    public function update(Request $request, Customer $customer)
    {
        abort_if($customer->company_id !== $request->user()->company_id, 403);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'gstin' => 'nullable|string|size:15',
            'billing_address' => 'nullable|string',
            'billing_city' => 'nullable|string',
            'billing_state' => 'nullable|string',
            'billing_pincode' => 'nullable|string',
            'credit_limit' => 'numeric|min:0',
            'credit_days' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $customer->update($data);
        return $customer;
    }

    public function destroy(Request $request, Customer $customer)
    {
        abort_if($customer->company_id !== $request->user()->company_id, 403);
        $customer->delete();
        return response()->json(['message' => 'Deleted.']);
    }
}
