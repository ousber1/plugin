@extends('layouts.app')
@section('title', 'WhatsApp Automation')

@section('content')
<div class="space-y-6" x-data="{ showForm: false }">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('whatsapp.index') }}" class="text-slate-400 hover:text-slate-600"><i class="fas fa-arrow-left"></i></a>
            <h2 class="text-xl font-bold">Auto-Reply Rules</h2>
        </div>
        <button @click="showForm = !showForm" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
            <i class="fas fa-plus mr-1"></i> Add Rule
        </button>
    </div>

    {{-- Add Rule Form --}}
    <div x-show="showForm" x-cloak x-transition class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <form method="POST" action="{{ route('whatsapp.automation.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            @csrf
            <div><label class="block text-sm font-medium mb-1">Name</label><input type="text" name="name" required class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            <div><label class="block text-sm font-medium mb-1">Trigger Keyword</label><input type="text" name="trigger_keyword" required class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="e.g. hello, price"></div>
            <div><label class="block text-sm font-medium mb-1">Response</label><textarea name="response_text" rows="2" required class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></textarea></div>
            <div class="flex items-end"><button type="submit" class="w-full py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">Save Rule</button></div>
        </form>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500"><tr><th class="px-6 py-3 text-left">Name</th><th class="px-6 py-3 text-left">Keyword</th><th class="px-6 py-3 text-left">Response</th><th class="px-6 py-3 text-center">Active</th><th class="px-6 py-3 text-center">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($rules as $rule)
                <tr>
                    <td class="px-6 py-3 font-medium">{{ $rule->name }}</td>
                    <td class="px-6 py-3"><code class="bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded text-xs">{{ $rule->trigger_keyword }}</code></td>
                    <td class="px-6 py-3 text-sm text-slate-500 truncate max-w-xs">{{ $rule->response_text }}</td>
                    <td class="px-6 py-3 text-center">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $rule->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                            {{ $rule->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-center">
                        <form method="POST" action="{{ route('whatsapp.automation.delete', $rule->id) }}" class="inline" onsubmit="return confirm('Delete this rule?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-600"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400">No automation rules configured</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
