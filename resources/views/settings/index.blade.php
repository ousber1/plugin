@extends('layouts.app')
@section('title', 'Settings')

@php
    // Helper to safely get setting value
    $getSetting = function($group, $key, $default = '') use ($settings) {
        $groupSettings = $settings[$group] ?? collect();
        $setting = $groupSettings->firstWhere('key', $key);
        return $setting ? $setting->value : $default;
    };
@endphp

@section('content')
<div class="max-w-3xl space-y-6">
    <h2 class="text-xl font-bold">Settings</h2>

    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        @if($errors->any())
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
            <ul class="list-disc list-inside text-sm text-red-600">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif

        {{-- Store Info --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h3 class="font-semibold text-sm border-b border-slate-200 dark:border-slate-700 pb-3"><i class="fas fa-store mr-2 text-primary-500"></i>Store Information</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Store Name</label>
                    <input type="text" name="settings[store_name]" value="{{ $getSetting('general', 'store_name', 'OmniChannel Store') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Currency Symbol</label>
                    <input type="text" name="settings[currency]" value="{{ $getSetting('general', 'currency', '$') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="$, €, £, MAD">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Phone</label>
                    <input type="text" name="settings[store_phone]" value="{{ $getSetting('general', 'store_phone', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="+212 xxx xxx xxx">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input type="email" name="settings[store_email]" value="{{ $getSetting('general', 'store_email', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium mb-1">Address</label>
                    <input type="text" name="settings[store_address]" value="{{ $getSetting('general', 'store_address', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Tax Rate (%)</label>
                    <input type="number" name="settings[tax_rate]" value="{{ $getSetting('general', 'tax_rate', '0') }}" step="0.01" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Low Stock Threshold</label>
                    <input type="number" name="settings[low_stock_threshold]" value="{{ $getSetting('general', 'low_stock_threshold', '5') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                </div>
            </div>
        </div>

        {{-- Receipt Customization --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h3 class="font-semibold text-sm border-b border-slate-200 dark:border-slate-700 pb-3"><i class="fas fa-receipt mr-2 text-emerald-500"></i>Receipt Customization</h3>
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Receipt Logo</label>
                    @php $currentLogo = $getSetting('receipt', 'receipt_logo', ''); @endphp
                    @if($currentLogo)
                    <div class="mb-2">
                        <img src="{{ asset('storage/' . $currentLogo) }}" alt="Logo" class="h-16 object-contain rounded border border-slate-200 dark:border-slate-600 p-1 bg-white">
                    </div>
                    @endif
                    <input type="file" name="receipt_logo" accept="image/*" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                    <p class="text-xs text-slate-400 mt-1">Recommended: 200x80px PNG with transparent background</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Receipt Header Text</label>
                    <textarea name="settings[receipt_header]" rows="2" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="Extra text below store name">{{ $getSetting('receipt', 'receipt_header', '') }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Receipt Footer Text</label>
                    <textarea name="settings[receipt_footer]" rows="2" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="Thank you message, return policy, etc.">{{ $getSetting('receipt', 'receipt_footer', 'Thank you for your purchase!') }}</textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Receipt Width</label>
                        <select name="settings[receipt_width]" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                            <option value="58mm" {{ $getSetting('receipt', 'receipt_width', '80mm') == '58mm' ? 'selected' : '' }}>58mm (Small)</option>
                            <option value="80mm" {{ $getSetting('receipt', 'receipt_width', '80mm') == '80mm' ? 'selected' : '' }}>80mm (Standard)</option>
                            <option value="a4" {{ $getSetting('receipt', 'receipt_width', '80mm') == 'a4' ? 'selected' : '' }}>A4 (Full Page)</option>
                        </select>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 mt-6">
                            <input type="checkbox" name="settings[receipt_show_logo]" value="1" {{ $getSetting('receipt', 'receipt_show_logo', '1') == '1' ? 'checked' : '' }} class="w-4 h-4 rounded border-slate-300 text-primary-600">
                            <span class="text-sm font-medium">Show Logo on Receipt</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        {{-- WhatsApp --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h3 class="font-semibold text-sm border-b border-slate-200 dark:border-slate-700 pb-3"><i class="fab fa-whatsapp mr-2 text-emerald-500"></i>WhatsApp Cloud API</h3>
            <div class="space-y-4">
                <div><label class="block text-sm font-medium mb-1">Access Token</label><input type="password" name="settings[whatsapp_token]" value="{{ $getSetting('whatsapp', 'whatsapp_token', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="EAAxxxxxxx..."></div>
                <div><label class="block text-sm font-medium mb-1">Phone Number ID</label><input type="text" name="settings[whatsapp_phone_id]" value="{{ $getSetting('whatsapp', 'whatsapp_phone_id', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
                <div><label class="block text-sm font-medium mb-1">Webhook Verify Token</label><input type="text" name="settings[whatsapp_verify_token]" value="{{ $getSetting('whatsapp', 'whatsapp_verify_token', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            </div>
        </div>

        {{-- API Keys --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h3 class="font-semibold text-sm border-b border-slate-200 dark:border-slate-700 pb-3"><i class="fas fa-key mr-2 text-amber-500"></i>API Keys</h3>
            <div class="space-y-4">
                <div><label class="block text-sm font-medium mb-1">OpenAI API Key</label><input type="password" name="settings[openai_api_key]" value="{{ $getSetting('api', 'openai_api_key', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="sk-..."></div>
                <div><label class="block text-sm font-medium mb-1">Meta Ads Access Token</label><input type="password" name="settings[meta_ads_token]" value="{{ $getSetting('api', 'meta_ads_token', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
                <div><label class="block text-sm font-medium mb-1">Meta Ads Account ID</label><input type="text" name="settings[meta_ads_account_id]" value="{{ $getSetting('api', 'meta_ads_account_id', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
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
