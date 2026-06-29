<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\FinancialYear;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index()
    {
        return Company::where('is_active', true)->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string',
            'gstin' => 'nullable|string|size:15',
            'pan' => 'nullable|string|size:10',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'pincode' => 'nullable|string',
        ]);

        $company = Company::create($data);

        // Auto-create current financial year (April–March)
        $now = now();
        $startYear = $now->month >= 4 ? $now->year : $now->year - 1;
        FinancialYear::create([
            'company_id' => $company->id,
            'name' => $startYear . '-' . ($startYear + 1),
            'start_date' => "{$startYear}-04-01",
            'end_date' => ($startYear + 1) . '-03-31',
            'is_current' => true,
        ]);

        return response()->json($company->load('financialYears'), 201);
    }

    public function show(Company $company)
    {
        return $company->load('branches', 'financialYears');
    }

    public function update(Request $request, Company $company)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'legal_name' => 'nullable|string',
            'gstin' => 'nullable|string|size:15',
            'pan' => 'nullable|string|size:10',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'pincode' => 'nullable|string',
        ]);

        $company->update($data);
        return $company;
    }
}
