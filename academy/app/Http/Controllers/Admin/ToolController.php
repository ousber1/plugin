<?php

namespace App\Http\Controllers\Admin;

use App\Models\Tool;
use App\Models\ToolSuggestion;
use Illuminate\Http\Request;

class ToolController extends AdminController
{
    public function index(Request $request)
    {
        return view('admin.tools.index', [
            'tools' => Tool::orderBy('sort_order')->get(),
            'suggestions' => ToolSuggestion::when($request->input('status'), fn ($q, $s) => $q->where('status', $s))->latest()->paginate(20)->withQueryString(),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate(['tools' => ['array'], 'tools.*.sort_order' => ['nullable', 'integer', 'min:0'], 'tools.*.badge' => ['nullable', 'string', 'max:30']]);
        foreach (Tool::all() as $tool) {
            $row = $request->input('tools.'.$tool->id, []);
            $tool->update([
                'is_enabled' => ! empty($row['is_enabled']),
                'sort_order' => (int) ($row['sort_order'] ?? $tool->sort_order),
                'badge' => $row['badge'] ?? null,
            ]);
        }

        return back()->with('success', 'تم حفظ إعدادات الأدوات.');
    }

    public function updateSuggestion(Request $request, ToolSuggestion $suggestion)
    {
        $data = $request->validate(['status' => ['required', 'in:pending,approved,rejected,done'], 'admin_note' => ['nullable', 'string', 'max:1000']]);
        $suggestion->update($data);

        return back()->with('success', 'تم تحديث الاقتراح.');
    }

    public function destroySuggestion(ToolSuggestion $suggestion)
    {
        $suggestion->delete();

        return back()->with('success', 'تم حذف الاقتراح.');
    }
}
