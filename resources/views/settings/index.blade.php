@extends('layouts.app')
@section('title', 'Settings')

@section('content')
<div class="max-w-3xl space-y-6">
    <h2 class="text-xl font-bold">Settings</h2>

    <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
        @csrf

        {{-- General --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h3 class="font-semibold text-sm border-b border-slate-200 dark:border-slate-700 pb-3"><i class="fas fa-store mr-2 text-primary-500"></i>General</h3>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium mb-1">Store Name</label><input type="text" name="settings[store_name]" value="{{ ($settings['general'] ?? collect())->firstWhere('key', 'store_name')->value ?? 'OmniChannel Store' }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
                <div><label class="block text-sm font-medium mb-1">Currency</label><input type="text" name="settings[currency]" value="{{ ($settings['general'] ?? collect())->firstWhere('key', 'currency')->value ?? 'USD' }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
                <div><label class="block text-sm font-medium mb-1">Tax Rate (%)</label><input type="number" name="settings[tax_rate]" value="{{ ($settings['general'] ?? collect())->firstWhere('key', 'tax_rate')->value ?? '0' }}" step="0.01" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
                <div><label class="block text-sm font-medium mb-1">Low Stock Threshold</label><input type="number" name="settings[low_stock_threshold]" value="{{ ($settings['general'] ?? collect())->firstWhere('key', 'low_stock_threshold')->value ?? '5' }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            </div>
        </div>

        {{-- WhatsApp --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h3 class="font-semibold text-sm border-b border-slate-200 dark:border-slate-700 pb-3"><i class="fab fa-whatsapp mr-2 text-emerald-500"></i>WhatsApp Cloud API</h3>
            <div class="space-y-4">
                <div><label class="block text-sm font-medium mb-1">Access Token</label><input type="password" name="settings[whatsapp_token]" value="{{ ($settings['whatsapp'] ?? collect())->firstWhere('key', 'whatsapp_token')->value ?? '' }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="EAAxxxxxxx..."></div>
                <div><label class="block text-sm font-medium mb-1">Phone Number ID</label><input type="text" name="settings[whatsapp_phone_id]" value="{{ ($settings['whatsapp'] ?? collect())->firstWhere('key', 'whatsapp_phone_id')->value ?? '' }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
                <div><label class="block text-sm font-medium mb-1">Webhook Verify Token</label><input type="text" name="settings[whatsapp_verify_token]" value="{{ ($settings['whatsapp'] ?? collect())->firstWhere('key', 'whatsapp_verify_token')->value ?? '' }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            </div>
        </div>

        {{-- API Keys --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h3 class="font-semibold text-sm border-b border-slate-200 dark:border-slate-700 pb-3"><i class="fas fa-key mr-2 text-amber-500"></i>API Keys</h3>
            <div class="space-y-4">
                <div><label class="block text-sm font-medium mb-1">OpenAI API Key</label><input type="password" name="settings[openai_api_key]" value="{{ ($settings['api'] ?? collect())->firstWhere('key', 'openai_api_key')->value ?? '' }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="sk-..."></div>
                <div><label class="block text-sm font-medium mb-1">Meta Ads Access Token</label><input type="password" name="settings[meta_ads_token]" value="{{ ($settings['api'] ?? collect())->firstWhere('key', 'meta_ads_token')->value ?? '' }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
                <div><label class="block text-sm font-medium mb-1">Meta Ads Account ID</label><input type="text" name="settings[meta_ads_account_id]" value="{{ ($settings['api'] ?? collect())->firstWhere('key', 'meta_ads_account_id')->value ?? '' }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="px-6 py-2.5 bg-primary-600 text-white rounded-lg text-sm font-bold hover:bg-primary-700"><i class="fas fa-save mr-1"></i> Save Settings</button>
        </div>
    </form>

    @if(auth()->user()->isAdmin())
    <div class="border-t border-slate-200 dark:border-slate-700 pt-6">
        <a href="{{ route('admin.users') }}" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium hover:bg-slate-300"><i class="fas fa-users-cog mr-1"></i> Manage Users</a>
    </div>
    @endif
</div>
@endsection
