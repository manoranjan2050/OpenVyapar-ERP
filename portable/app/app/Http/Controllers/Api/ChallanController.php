<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Challan;
use App\Models\SalesInvoice;
use Illuminate\Http\Request;

class ChallanController extends Controller
{
    public function index(Request $request)
    {
        $cid = $request->user()->company_id;
        $challans = Challan::where('company_id', $cid)
            ->with('customer:id,name,phone')
            ->orderByDesc('challan_date')
            ->get();
        return response()->json($challans);
    }

    public function store(Request $request)
    {
        $cid = $request->user()->company_id;
        $data = $request->validate([
            'challan_date'  => 'required|date',
            'customer_id'   => 'nullable|integer|exists:customers,id',
            'items'         => 'required|array|min:1',
            'items.*.name'  => 'required|string',
            'items.*.qty'   => 'required|numeric|min:0.01',
            'items.*.unit'  => 'nullable|string',
            'items.*.rate'  => 'nullable|numeric|min:0',
            'transporter'   => 'nullable|string|max:255',
            'vehicle_no'    => 'nullable|string|max:30',
            'notes'         => 'nullable|string|max:1000',
        ]);

        $challan = Challan::create([
            ...$data,
            'company_id'     => $cid,
            'challan_number' => Challan::nextNumber($cid),
            'status'         => 'draft',
        ]);

        return response()->json($challan->load('customer:id,name'), 201);
    }

    public function show(Request $request, Challan $challan)
    {
        if ($challan->company_id !== $request->user()->company_id) abort(403);
        return response()->json($challan->load('customer', 'salesInvoice:id,invoice_number'));
    }

    public function dispatch(Request $request, Challan $challan)
    {
        if ($challan->company_id !== $request->user()->company_id) abort(403);
        if ($challan->status !== 'draft') return response()->json(['message' => 'Only draft challans can be dispatched.'], 422);

        $challan->update(['status' => 'dispatched']);
        return response()->json($challan);
    }

    public function convert(Request $request, Challan $challan)
    {
        if ($challan->company_id !== $request->user()->company_id) abort(403);
        if (!in_array($challan->status, ['draft', 'dispatched'])) {
            return response()->json(['message' => 'Challan already converted or cancelled.'], 422);
        }

        $challan->update(['status' => 'converted']);
        return response()->json(['message' => 'Challan marked as converted.', 'challan' => $challan]);
    }

    public function cancel(Request $request, Challan $challan)
    {
        if ($challan->company_id !== $request->user()->company_id) abort(403);
        if ($challan->status === 'cancelled') return response()->json(['message' => 'Already cancelled.'], 422);
        $challan->update(['status' => 'cancelled']);
        return response()->json($challan);
    }
}
