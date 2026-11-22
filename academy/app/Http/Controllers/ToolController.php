<?php

namespace App\Http\Controllers;

use App\Models\Tool;
use App\Models\ToolSuggestion;
use Illuminate\Http\Request;

class ToolController extends Controller
{
    public function index()
    {
        return view('tools.index', ['tools' => Tool::where('is_enabled', true)->orderBy('sort_order')->get()]);
    }

    public function show(Tool $tool)
    {
        abort_unless($tool->is_enabled && in_array($tool->key, Tool::KNOWN, true), 404);

        return view('tools.'.$tool->key, compact('tool'));
    }

    public function suggest(Request $request)
    {
        if (filled($request->input('website'))) {
            return back()->with('success', __('شكرًا! وصلتنا فكرتك.'));
        }
        $data = $request->validate([
            'idea' => ['required', 'string', 'min:10', 'max:1500'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
        ]);
        ToolSuggestion::create($data);

        return back()->with('success', __('شكرًا! وصلتنا فكرتك وسنراجعها قريبًا.'));
    }
}
