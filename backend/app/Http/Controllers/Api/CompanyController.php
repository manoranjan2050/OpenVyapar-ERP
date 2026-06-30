<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\FinancialYear;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    private function addLogoUrl(Company $c): Company
    {
        $c->logo_url = $c->logo_path
            ? url('uploads/logos/' . basename($c->logo_path))
            : null;
        return $c;
    }

    public function index()
    {
        return Company::where('is_active', true)->get()->map(fn ($c) => $this->addLogoUrl($c));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'legal_name' => 'nullable|string',
            'gstin'      => 'nullable|string|size:15',
            'pan'        => 'nullable|string|size:10',
            'phone'      => 'nullable|string',
            'email'      => 'nullable|email',
            'address'    => 'nullable|string',
            'city'       => 'nullable|string',
            'state'      => 'nullable|string',
            'pincode'    => 'nullable|string',
        ]);

        $company = Company::create($data);

        $now = now();
        $startYear = $now->month >= 4 ? $now->year : $now->year - 1;
        FinancialYear::create([
            'company_id' => $company->id,
            'name'       => $startYear . '-' . ($startYear + 1),
            'start_date' => "{$startYear}-04-01",
            'end_date'   => ($startYear + 1) . '-03-31',
            'is_current' => true,
        ]);

        return response()->json($this->addLogoUrl($company->load('financialYears')), 201);
    }

    public function show(Company $company)
    {
        return $this->addLogoUrl($company->load('branches', 'financialYears'));
    }

    public function update(Request $request, Company $company)
    {
        $data = $request->validate([
            'name'       => 'sometimes|string|max:255',
            'legal_name' => 'nullable|string',
            'gstin'      => 'nullable|string|size:15',
            'pan'        => 'nullable|string|size:10',
            'phone'      => 'nullable|string',
            'email'      => 'nullable|email',
            'address'    => 'nullable|string',
            'city'       => 'nullable|string',
            'state'      => 'nullable|string',
            'pincode'    => 'nullable|string',
        ]);

        $company->update($data);
        return $this->addLogoUrl($company);
    }

    public function uploadLogo(Request $request, Company $company)
    {
        $request->validate(['logo' => 'required|image|max:2048']);

        $dir = public_path('uploads/logos');
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        if ($company->logo_path) {
            $old = public_path('uploads/logos/' . basename($company->logo_path));
            if (file_exists($old)) unlink($old);
        }

        $file     = $request->file('logo');
        $filename = $company->id . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move($dir, $filename);

        $company->update(['logo_path' => $filename]);

        return response()->json(['logo_url' => url('uploads/logos/' . $filename)]);
    }
}
