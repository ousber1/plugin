<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Services\StockService;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function index()
    {
        $this->authorize('stock.manage');
        $materials = Material::with('supplier')->orderBy('category')->orderBy('name')->get();

        return view('admin.stock.index', compact('materials'));
    }

    public function show(Material $material)
    {
        $this->authorize('stock.manage');
        $movements = $material->movements()->with('job', 'user')->latest('id')->paginate(50);

        return view('admin.stock.show', compact('material', 'movements'));
    }

    public function adjust(Request $request, Material $material, StockService $stock)
    {
        $this->authorize('stock.manage');
        $v = $request->validate(['mode' => 'required|in:count,receipt', 'quantity' => 'required|numeric|min:0', 'unit_cost' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:300', 'key' => 'required|uuid']);
        if ($v['mode'] === 'count') {
            $stock->adjust($material, (string) $v['quantity'], $v['reason'], 'adjust:'.$v['key']);
        } else {
            $stock->move($material, 'receipt', (string) $v['quantity'], 'receipt:'.$v['key'], ['unit_cost' => $v['unit_cost'] ?? $material->unit_cost, 'notes' => $v['reason']]);
        }

        return back()->with('ok', 'Stock mis à jour.');
    }
}
