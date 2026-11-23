<?php

namespace App\Http\Controllers\Admin;

use App\Models\Tool;
use App\Models\ToolSuggestion;
use App\Support\Locales;
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
        $rules = ['tools' => ['array'], 'tools.*.sort_order' => ['nullable', 'integer', 'min:0'], 'tools.*.badge' => ['nullable', 'string', 'max:30']];
        foreach (Locales::codes() as $code) {
            $rules["tools.*.name.$code"] = [$code === 'ar' ? 'required' : 'nullable', 'string', 'max:80'];
            $rules["tools.*.description.$code"] = ['nullable', 'string', 'max:300'];
        }
        $request->validate($rules, ['tools.*.name.ar.required' => 'اسم الأداة بالعربية مطلوب.']);
        foreach (Tool::all() as $tool) {
            $row = $request->input('tools.'.$tool->id);
            if (! is_array($row)) {
                continue;
            }
            $tool->update([
                'is_enabled' => ! empty($row['is_enabled']),
                'sort_order' => (int) ($row['sort_order'] ?? $tool->sort_order),
                'badge' => $row['badge'] ?? null,
                'name' => isset($row['name']) ? array_map(fn ($v) => trim((string) $v), $row['name']) : $tool->name,
                'description' => isset($row['description']) ? array_map(fn ($v) => trim((string) $v), $row['description']) : $tool->description,
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
