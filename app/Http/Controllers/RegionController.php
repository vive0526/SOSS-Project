<?php

namespace App\Http\Controllers;

use App\Models\Region;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    // List regions
    public function index()
    {
        $regions = Region::orderBy('id')->paginate(20)->withQueryString();
        return view('regions.index', compact('regions'));
    }

    // Show create form
    public function create()
    {
        return view('regions.create');
    }

    // Store new region
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'code' => 'required|string|unique:regions,code',
        ]);

        Region::create([
            'name' => $request->name,
            'code' => strtoupper($request->code),
        ]);

        return redirect()->route('regions.index')
            ->with('success', 'Region created successfully.');
    }

    // Show edit form
    public function edit(Region $region)
    {
        return view('regions.edit', compact('region'));
    }

    // Update region (name & code only)
    public function update(Request $request, Region $region)
    {
        $request->validate([
            'name' => 'required|string',
            'code' => 'required|string|unique:regions,code,' . $region->id,
        ]);

        $region->update([
            'name' => $request->name,
            'code' => strtoupper($request->code),
        ]);

        return redirect()->route('regions.index')
            ->with('success', 'Region updated successfully.');
    }

    // Deactivate region
    public function deactivate(Region $region)
    {
        $region->update(['status' => 'inactive']);

        return redirect()->route('regions.index')
            ->with('success', 'Region deactivated.');
    }

    // Activate region
    public function activate(Region $region)
    {
        $region->update(['status' => 'active']);

        return redirect()->route('regions.index')
            ->with('success', 'Region activated.');
    }
}
