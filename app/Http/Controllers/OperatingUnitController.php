<?php

namespace App\Http\Controllers;

use App\Models\OperatingUnit;
use App\Models\Region;
use Illuminate\Http\Request;

class OperatingUnitController extends Controller
{
    public function index()
    {
        $units = OperatingUnit::with('region')->orderBy('id')->paginate(20)->withQueryString();
        return view('operating_units.index', compact('units'));
    }

    public function create()
    {
        $regions = Region::where('status', 'active')->get();
        return view('operating_units.create', compact('regions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'code' => 'required|string|unique:operating_units,code',
            'type' => 'required|in:mill,estate,other,department',
            'manager' => 'required|string',
            'region_id' => 'required|exists:regions,id',
        ]);

        OperatingUnit::create([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'type' => $request->type,
            'address' => $request->address,
            'manager' => $request->manager,
            'region_id' => $request->region_id,
        ]);

        return redirect()->route('operating-units.index')
            ->with('success', 'Operating Unit created successfully.');
    }

    public function edit(OperatingUnit $operatingUnit)
    {
        $regions = Region::where('status', 'active')->get();
        return view('operating_units.edit', compact('operatingUnit', 'regions'));
    }

    public function update(Request $request, OperatingUnit $operatingUnit)
    {
        $request->validate([
            'name' => 'required|string',
            'code' => 'required|string|unique:operating_units,code,' . $operatingUnit->id,
            'type' => 'required|in:mill,estate,other,department',
            'manager' => 'required|string',
            'region_id' => 'required|exists:regions,id',
        ]);

        $operatingUnit->update([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'type' => $request->type,
            'address' => $request->address,
            'manager' => $request->manager,
            'region_id' => $request->region_id,
        ]);

        return redirect()->route('operating-units.index')
            ->with('success', 'Operating Unit updated successfully.');
    }

    public function deactivate(OperatingUnit $operatingUnit)
    {
        $operatingUnit->update(['status' => 'inactive']);

        return redirect()->route('operating-units.index')
            ->with('success', 'Operating Unit deactivated.');
    }

    public function activate(OperatingUnit $operatingUnit)
    {
        $operatingUnit->update(['status' => 'active']);

        return redirect()->route('operating-units.index')
            ->with('success', 'Operating Unit activated.');
    }
}
