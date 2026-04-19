<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::orderBy('id')->get();
        return view('companies.index', compact('companies'));
    }

    public function create()
    {
        $parentCompanies = Company::where('status', 'active')->orderBy('name')->get();
        return view('companies.create', compact('parentCompanies'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'code' => 'required|string|unique:companies,code',
            'roc_number' => 'nullable|string',
            'address' => 'nullable|string',
            'parent_company_id' => 'nullable|exists:companies,id',
        ]);

        Company::create([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'roc_number' => $request->roc_number,
            'address' => $request->address,
            'parent_company_id' => $request->parent_company_id,
            'status' => 'active',
        ]);

        return redirect()->route('companies.index')
            ->with('success', 'Company created successfully.');
    }

    public function edit(Company $company)
    {
        // Only active parent companies, exclude itself
        $parentCompanies = Company::where('status', 'active')
            ->where('id', '!=', $company->id)
            ->orderBy('name')
            ->get();

        return view('companies.edit', compact('company', 'parentCompanies'));
    }

    public function update(Request $request, Company $company)
    {
        $request->validate([
            'name' => 'required|string',
            'code' => 'required|string|unique:companies,code,' . $company->id,
            'roc_number' => 'nullable|string',
            'address' => 'nullable|string',
            'parent_company_id' => 'nullable|exists:companies,id',
        ]);

        // prevent self-parent (double safety)
        if ($request->parent_company_id && (int)$request->parent_company_id === (int)$company->id) {
            return back()->withErrors([
                'parent_company_id' => 'Parent company cannot be the same company.',
            ])->withInput();
        }

        $company->update([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'roc_number' => $request->roc_number,
            'address' => $request->address,
            'parent_company_id' => $request->parent_company_id,
        ]);

        return redirect()->route('companies.index')
            ->with('success', 'Company updated successfully.');
    }

    // Delete = Deactivate
    public function deactivate(Company $company)
    {
        $company->update(['status' => 'inactive']);

        return redirect()->route('companies.index')
            ->with('success', 'Company deactivated.');
    }

    public function activate(Company $company)
    {
        $company->update(['status' => 'active']);

        return redirect()->route('companies.index')
            ->with('success', 'Company activated.');
    }
}
