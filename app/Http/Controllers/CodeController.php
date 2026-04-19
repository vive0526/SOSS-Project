<?php

namespace App\Http\Controllers;

use App\Models\Code;
use Illuminate\Http\Request;

class CodeController extends Controller
{
    public function index()
    {
        $codes = Code::orderBy('id')->get();
        return view('codes.index', compact('codes'));
    }

    public function create()
    {
        return view('codes.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|unique:codes,code',
            'description' => 'required|string',
            'category' => 'required|string',
            'color' => [
                'required',
                'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'
            ],
        ]);

        Code::create([
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'category' => $request->category,
            'color' => strtoupper($request->color),
            'status' => 'active',
        ]);

        return redirect()->route('codes.index')
            ->with('success', 'Code added successfully.');
    }

    public function edit(Code $code)
    {
        return view('codes.edit', compact('code'));
    }

    public function update(Request $request, Code $code)
    {
        $request->validate([
            'code' => 'required|string|unique:codes,code,' . $code->id,
            'description' => 'required|string',
            'category' => 'required|string',
            'color' => [
                'required',
                'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'
            ],
        ]);

        $code->update([
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'category' => $request->category,
            'color' => strtoupper($request->color),
        ]);

        return redirect()->route('codes.index')
            ->with('success', 'Code updated successfully.');
    }

    // Delete = Deactivate
    public function deactivate(Code $code)
    {
        $code->update(['status' => 'inactive']);

        return redirect()->route('codes.index')
            ->with('success', 'Code deactivated.');
    }

    public function activate(Code $code)
    {
        $code->update(['status' => 'active']);

        return redirect()->route('codes.index')
            ->with('success', 'Code activated.');
    }
}
