@extends('layouts.app')
@section('title', \App\Helpers\Lang::t('settings.title'))

@php
    $L = \App\Helpers\Lang::class;
    $getSetting = function($group, $key, $default = '') use ($settings) {
        $groupSettings = $settings[$group] ?? collect();
        $setting = $groupSettings->firstWhere('key', $key);
        return $setting ? $setting->value : $default;
    };
@endphp

@section('content')
<div class="max-w-3xl space-y-6">
    <h2 class="text-xl font-bold">{{ $L::t('settings.title') }}</h2>

    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        @if($errors->any())
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
            <ul class="list-disc list-inside text-sm text-red-600">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif

        {{-- Store Info --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h3 class="font-semibold text-sm border-b border-slate-200 dark:border-slate-700 pb-3"><i class="fas fa-store mr-2 text-primary-500"></i>{{ $L::t('settings.store_info') }}</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $L::t('settings.store_name') }}</label>
                    <input type="text" name="settings[store_name]" value="{{ $getSetting('general', 'store_name', 'OmniChannel Store') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $L::t('settings.currency') }}</label>
                    <input type="text" name="settings[currency]" value="{{ $getSetting('general', 'currency', 'DH') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="MAD, $, €, £">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $L::t('settings.phone') }}</label>
                    <input type="text" name="settings[store_phone]" value="{{ $getSetting('general', 'store_phone', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="+212 xxx xxx xxx">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $L::t('settings.email') }}</label>
                    <input type="email" name="settings[store_email]" value="{{ $getSetting('general', 'store_email', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium mb-1">{{ $L::t('settings.address') }}</label>
                    <input type="text" name="settings[store_address]" value="{{ $getSetting('general', 'store_address', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $L::t('settings.tax_rate') }} (%)</label>
                    <input type="number" name="settings[tax_rate]" value="{{ $getSetting('general', 'tax_rate', '20') }}" step="0.01" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $L::t('settings.low_stock') }}</label>
                    <input type="number" name="settings[low_stock_threshold]" value="{{ $getSetting('general', 'low_stock_threshold', '5') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                </div>
            </div>
        </div>

        {{-- Receipt Customization --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h3 class="font-semibold text-sm border-b border-slate-200 dark:border-slate-700 pb-3"><i class="fas fa-receipt mr-2 text-emerald-500"></i>{{ $L::t('settings.receipt') }}</h3>
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $L::t('settings.receipt_logo') }}</label>
                    @php $currentLogo = $getSetting('receipt', 'receipt_logo', ''); @endphp
                    @if($currentLogo)
                    <div class="mb-2">
                        <img src="{{ asset('storage/' . $currentLogo) }}" alt="Logo" class="h-16 object-contain rounded border border-slate-200 dark:border-slate-600 p-1 bg-white">
                    </div>
                    @endif
                    <input type="file" name="receipt_logo" accept="image/*" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                    <p class="text-xs text-slate-400 mt-1">{{ $L::locale() === 'fr' ? 'Recommandé : 200x80px PNG fond transparent' : 'Recommended: 200x80px PNG with transparent background' }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $L::t('settings.receipt_header') }}</label>
                    <textarea name="settings[receipt_header]" rows="2" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">{{ $getSetting('receipt', 'receipt_header', '') }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $L::t('settings.receipt_footer') }}</label>
                    <textarea name="settings[receipt_footer]" rows="2" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">{{ $getSetting('receipt', 'receipt_footer', '') }}</textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">{{ $L::t('settings.receipt_width') }}</label>
                        <select name="settings[receipt_width]" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                            <option value="58mm" {{ $getSetting('receipt', 'receipt_width', '80mm') == '58mm' ? 'selected' : '' }}>58mm</option>
                            <option value="80mm" {{ $getSetting('receipt', 'receipt_width', '80mm') == '80mm' ? 'selected' : '' }}>80mm (Standard)</option>
                            <option value="a4" {{ $getSetting('receipt', 'receipt_width', '80mm') == 'a4' ? 'selected' : '' }}>A4</option>
                        </select>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 mt-6">
                            <input type="checkbox" name="settings[receipt_show_logo]" value="1" {{ $getSetting('receipt', 'receipt_show_logo', '1') == '1' ? 'checked' : '' }} class="w-4 h-4 rounded border-slate-300 text-primary-600">
                            <span class="text-sm font-medium">{{ $L::t('settings.show_logo') }}</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        {{-- Moroccan Fiscal / Invoice Settings --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h3 class="font-semibold text-sm border-b border-slate-200 dark:border-slate-700 pb-3"><i class="fas fa-file-invoice mr-2 text-primary-500"></i>{{ $L::t('settings.invoice_settings') }} (Maroc)</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $L::t('settings.ice') }}</label>
                    <input type="text" name="settings[ice]" value="{{ $getSetting('invoice', 'ice', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="Identifiant Commun de l'Entreprise">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $L::t('settings.if_number') }}</label>
                    <input type="text" name="settings[if_number]" value="{{ $getSetting('invoice', 'if_number', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $L::t('settings.rc') }}</label>
                    <input type="text" name="settings[rc]" value="{{ $getSetting('invoice', 'rc', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $L::t('settings.cnss') }}</label>
                    <input type="text" name="settings[cnss]" value="{{ $getSetting('invoice', 'cnss', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $L::t('settings.patente') }}</label>
                    <input type="text" name="settings[patente]" value="{{ $getSetting('invoice', 'patente', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $L::t('settings.bank_name') }}</label>
                    <input type="text" name="settings[bank_name]" value="{{ $getSetting('invoice', 'bank_name', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="ex: Attijariwafa Bank">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium mb-1">{{ $L::t('settings.bank_rib') }}</label>
                    <input type="text" name="settings[bank_rib]" value="{{ $getSetting('invoice', 'bank_rib', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="ex: 007 780 0001234567890123 45">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium mb-1">{{ $L::t('settings.invoice_conditions') }}</label>
                    <textarea name="settings[invoice_conditions]" rows="2" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="{{ $L::locale() === 'fr' ? 'ex: Paiement à 30 jours' : 'e.g. Payment within 30 days' }}">{{ $getSetting('invoice', 'invoice_conditions', '') }}</textarea>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium mb-1">{{ $L::t('settings.invoice_footer') }}</label>
                    <textarea name="settings[invoice_footer]" rows="2" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="{{ $L::locale() === 'fr' ? 'ex: Merci pour votre confiance' : 'e.g. Thank you for your business' }}">{{ $getSetting('invoice', 'invoice_footer', '') }}</textarea>
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
            <h3 class="font-semibold text-sm border-b border-slate-200 dark:border-slate-700 pb-3"><i class="fas fa-key mr-2 text-amber-500"></i>{{ $L::locale() === 'fr' ? 'Clés API' : 'API Keys' }}</h3>
            <div class="space-y-4">
                <div><label class="block text-sm font-medium mb-1">OpenAI API Key</label><input type="password" name="settings[openai_api_key]" value="{{ $getSetting('api', 'openai_api_key', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="sk-..."></div>
                <div><label class="block text-sm font-medium mb-1">Meta Ads Access Token</label><input type="password" name="settings[meta_ads_token]" value="{{ $getSetting('api', 'meta_ads_token', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
                <div><label class="block text-sm font-medium mb-1">Meta Ads Account ID</label><input type="text" name="settings[meta_ads_account_id]" value="{{ $getSetting('api', 'meta_ads_account_id', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="px-6 py-2.5 bg-primary-600 text-white rounded-lg text-sm font-bold hover:bg-primary-700"><i class="fas fa-save mr-1"></i> {{ $L::t('common.save') }}</button>
        </div>
    </form>

    @if(auth()->user()->isAdmin())
    <div class="border-t border-slate-200 dark:border-slate-700 pt-6">
        <a href="{{ route('admin.users') }}" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium hover:bg-slate-300"><i class="fas fa-users-cog mr-1"></i> {{ $L::t('nav.users') }}</a>
    </div>
    @endif
</div>
@endsection
