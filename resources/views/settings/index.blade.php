@extends('layouts.app')
@section('title', \App\Helpers\Lang::t('settings.title'))

@php
    $L = \App\Helpers\Lang::class;
    $isFr = $L::locale() === 'fr';
    $getSetting = function($group, $key, $default = '') use ($settings) {
        $groupSettings = $settings[$group] ?? collect();
        $setting = $groupSettings->firstWhere('key', $key);
        return $setting ? $setting->value : $default;
    };
@endphp

@section('content')
<div class="max-w-4xl space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold"><i class="fas fa-cog mr-2 text-primary-500"></i>{{ $L::t('settings.title') }}</h2>
        @if(auth()->user()->isAdmin())
        <a href="{{ route('admin.users') }}" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700"><i class="fas fa-users-cog mr-1"></i> {{ $isFr ? 'Gestion des utilisateurs' : 'Manage Users' }}</a>
        @endif
    </div>

    @if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg p-3 text-sm text-emerald-700 dark:text-emerald-400">
        <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
        <ul class="list-disc list-inside text-sm text-red-600">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        {{-- ==================== GENERAL / STORE INFO ==================== --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h3 class="font-semibold text-sm border-b border-slate-200 dark:border-slate-700 pb-3">
                <i class="fas fa-store mr-2 text-primary-500"></i>{{ $isFr ? 'Informations de la boutique' : 'Store Information' }}
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $isFr ? 'Nom de la boutique' : 'Store Name' }} *</label>
                    <input type="text" name="settings[store_name]" value="{{ $getSetting('general', 'store_name', 'OmniChannel Store') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $isFr ? 'Devise' : 'Currency' }}</label>
                    <input type="text" name="settings[currency]" value="{{ $getSetting('general', 'currency', 'DH') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="DH, MAD, $, EUR">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $isFr ? 'Telephone' : 'Phone' }}</label>
                    <input type="text" name="settings[store_phone]" value="{{ $getSetting('general', 'store_phone', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="+212 xxx xxx xxx">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input type="email" name="settings[store_email]" value="{{ $getSetting('general', 'store_email', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $isFr ? 'Site web' : 'Website' }}</label>
                    <input type="text" name="settings[store_website]" value="{{ $getSetting('general', 'store_website', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="https://www.example.com">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $isFr ? 'Ville' : 'City' }}</label>
                    <input type="text" name="settings[store_city]" value="{{ $getSetting('general', 'store_city', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">{{ $isFr ? 'Adresse' : 'Address' }}</label>
                    <input type="text" name="settings[store_address]" value="{{ $getSetting('general', 'store_address', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $isFr ? 'Taux de TVA' : 'Tax Rate' }} (%)</label>
                    <input type="number" name="settings[tax_rate]" value="{{ $getSetting('general', 'tax_rate', '20') }}" step="0.01" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $isFr ? 'Seuil de stock faible' : 'Low Stock Threshold' }}</label>
                    <input type="number" name="settings[low_stock_threshold]" value="{{ $getSetting('general', 'low_stock_threshold', '5') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $isFr ? 'Langue par defaut' : 'Default Language' }}</label>
                    <select name="settings[default_language]" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                        <option value="fr" {{ $getSetting('general', 'default_language', 'fr') == 'fr' ? 'selected' : '' }}>Francais</option>
                        <option value="en" {{ $getSetting('general', 'default_language', 'fr') == 'en' ? 'selected' : '' }}>English</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $isFr ? 'Fuseau horaire' : 'Timezone' }}</label>
                    <select name="settings[timezone]" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                        <option value="Africa/Casablanca" {{ $getSetting('general', 'timezone', 'Africa/Casablanca') == 'Africa/Casablanca' ? 'selected' : '' }}>Africa/Casablanca (GMT+1)</option>
                        <option value="Europe/Paris" {{ $getSetting('general', 'timezone', '') == 'Europe/Paris' ? 'selected' : '' }}>Europe/Paris (GMT+1/+2)</option>
                        <option value="UTC" {{ $getSetting('general', 'timezone', '') == 'UTC' ? 'selected' : '' }}>UTC</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- ==================== RECEIPT ==================== --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h3 class="font-semibold text-sm border-b border-slate-200 dark:border-slate-700 pb-3">
                <i class="fas fa-receipt mr-2 text-emerald-500"></i>{{ $isFr ? 'Personnalisation du ticket' : 'Receipt Customization' }}
            </h3>
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $isFr ? 'Logo du ticket' : 'Receipt Logo' }}</label>
                    @php $currentLogo = $getSetting('receipt', 'receipt_logo', ''); @endphp
                    @if($currentLogo)
                    <div class="mb-2">
                        <img src="{{ asset('storage/' . $currentLogo) }}" alt="Logo" class="h-16 object-contain rounded border border-slate-200 dark:border-slate-600 p-1 bg-white">
                    </div>
                    @endif
                    <input type="file" name="receipt_logo" accept="image/*" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                    <p class="text-xs text-slate-400 mt-1">{{ $isFr ? 'Recommande : 200x80px PNG fond transparent' : 'Recommended: 200x80px PNG with transparent background' }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $isFr ? 'En-tete du ticket' : 'Receipt Header' }}</label>
                    <textarea name="settings[receipt_header]" rows="2" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">{{ $getSetting('receipt', 'receipt_header', '') }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $isFr ? 'Pied du ticket' : 'Receipt Footer' }}</label>
                    <textarea name="settings[receipt_footer]" rows="2" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">{{ $getSetting('receipt', 'receipt_footer', '') }}</textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">{{ $isFr ? 'Largeur du ticket' : 'Receipt Width' }}</label>
                        <select name="settings[receipt_width]" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                            <option value="58mm" {{ $getSetting('receipt', 'receipt_width', '80mm') == '58mm' ? 'selected' : '' }}>58mm</option>
                            <option value="80mm" {{ $getSetting('receipt', 'receipt_width', '80mm') == '80mm' ? 'selected' : '' }}>80mm (Standard)</option>
                            <option value="a4" {{ $getSetting('receipt', 'receipt_width', '80mm') == 'a4' ? 'selected' : '' }}>A4</option>
                        </select>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 mt-6">
                            <input type="checkbox" name="settings[receipt_show_logo]" value="1" {{ $getSetting('receipt', 'receipt_show_logo', '1') == '1' ? 'checked' : '' }} class="w-4 h-4 rounded border-slate-300 text-primary-600">
                            <span class="text-sm font-medium">{{ $isFr ? 'Afficher le logo' : 'Show Logo' }}</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        {{-- ==================== INVOICE TEMPLATES & SETTINGS ==================== --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h3 class="font-semibold text-sm border-b border-slate-200 dark:border-slate-700 pb-3">
                <i class="fas fa-file-invoice mr-2 text-primary-500"></i>{{ $isFr ? 'Modeles de facture & Parametres' : 'Invoice Templates & Settings' }}
            </h3>

            {{-- Template Selection --}}
            <div>
                <label class="block text-sm font-medium mb-2">{{ $isFr ? 'Modele de facture' : 'Invoice Template' }}</label>
                @php $selectedTemplate = $getSetting('invoice', 'invoice_template', 'modern'); @endphp
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    @foreach(['modern' => ['Modern', $isFr ? 'Design moderne avec couleur primaire et bordures arrondies' : 'Modern design with primary color and rounded borders', 'fa-palette'],
                              'classic' => ['Classic', $isFr ? 'Design classique formel avec bordures nettes' : 'Classic formal design with sharp borders', 'fa-landmark'],
                              'minimal' => ['Minimal', $isFr ? 'Design epure et minimaliste noir et blanc' : 'Clean minimalist black & white design', 'fa-minus-circle']] as $tplKey => $tplInfo)
                    <label class="relative cursor-pointer">
                        <input type="radio" name="settings[invoice_template]" value="{{ $tplKey }}" {{ $selectedTemplate == $tplKey ? 'checked' : '' }} class="peer sr-only">
                        <div class="border-2 border-slate-200 dark:border-slate-700 rounded-xl p-4 text-center transition-all peer-checked:border-primary-500 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/20 hover:border-slate-300">
                            <i class="fas {{ $tplInfo[2] }} text-2xl mb-2 text-slate-400 peer-checked:text-primary-500"></i>
                            <p class="text-sm font-bold">{{ $tplInfo[0] }}</p>
                            <p class="text-xs text-slate-400 mt-1">{{ $tplInfo[1] }}</p>
                        </div>
                        <div class="absolute top-2 right-2 w-5 h-5 rounded-full bg-primary-500 text-white items-center justify-center text-xs hidden peer-checked:flex"><i class="fas fa-check"></i></div>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Invoice Color --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $isFr ? 'Couleur principale' : 'Primary Color' }}</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="settings[invoice_color]" value="{{ $getSetting('invoice', 'invoice_color', '#4f46e5') }}" class="w-10 h-10 rounded border border-slate-300 cursor-pointer">
                        <input type="text" value="{{ $getSetting('invoice', 'invoice_color', '#4f46e5') }}" class="flex-1 border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent font-mono" readonly id="colorHex">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $isFr ? 'Delai de paiement (jours)' : 'Payment Due (days)' }}</label>
                    <input type="number" name="settings[invoice_due_days]" value="{{ $getSetting('invoice', 'invoice_due_days', '30') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                </div>
                <div>
                    <label class="flex items-center gap-2 mt-6">
                        <input type="checkbox" name="settings[invoice_show_logo]" value="1" {{ $getSetting('invoice', 'invoice_show_logo', '1') == '1' ? 'checked' : '' }} class="w-4 h-4 rounded border-slate-300 text-primary-600">
                        <span class="text-sm font-medium">{{ $isFr ? 'Logo sur facture' : 'Show Logo on Invoice' }}</span>
                    </label>
                </div>
            </div>

            {{-- Moroccan Fiscal IDs --}}
            <div class="pt-2">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3"><i class="fas fa-id-card mr-1"></i>{{ $isFr ? 'Identifiants fiscaux (Maroc)' : 'Fiscal Identifiers (Morocco)' }}</p>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">ICE</label>
                        <input type="text" name="settings[ice]" value="{{ $getSetting('invoice', 'ice', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="Identifiant Commun de l'Entreprise">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">IF ({{ $isFr ? 'Identifiant Fiscal' : 'Tax ID' }})</label>
                        <input type="text" name="settings[if_number]" value="{{ $getSetting('invoice', 'if_number', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">RC ({{ $isFr ? 'Registre de Commerce' : 'Trade Register' }})</label>
                        <input type="text" name="settings[rc]" value="{{ $getSetting('invoice', 'rc', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">CNSS</label>
                        <input type="text" name="settings[cnss]" value="{{ $getSetting('invoice', 'cnss', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Patente</label>
                        <input type="text" name="settings[patente]" value="{{ $getSetting('invoice', 'patente', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                    </div>
                </div>
            </div>

            {{-- Banking --}}
            <div class="pt-2">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3"><i class="fas fa-university mr-1"></i>{{ $isFr ? 'Coordonnees bancaires' : 'Bank Details' }}</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">{{ $isFr ? 'Nom de la banque' : 'Bank Name' }}</label>
                        <input type="text" name="settings[bank_name]" value="{{ $getSetting('invoice', 'bank_name', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="ex: Attijariwafa Bank">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">RIB</label>
                        <input type="text" name="settings[bank_rib]" value="{{ $getSetting('invoice', 'bank_rib', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="007 780 0001234567890123 45">
                    </div>
                </div>
            </div>

            {{-- Invoice Text --}}
            <div class="pt-2">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3"><i class="fas fa-align-left mr-1"></i>{{ $isFr ? 'Textes de la facture' : 'Invoice Texts' }}</p>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">{{ $isFr ? 'Conditions de paiement' : 'Payment Terms' }}</label>
                        <textarea name="settings[invoice_conditions]" rows="2" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="{{ $isFr ? 'ex: Paiement a 30 jours' : 'e.g. Payment within 30 days' }}">{{ $getSetting('invoice', 'invoice_conditions', '') }}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">{{ $isFr ? 'Mentions legales' : 'Legal Notice' }}</label>
                        <textarea name="settings[invoice_mention_legale]" rows="2" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="{{ $isFr ? 'ex: TVA non applicable, art. 293 B du CGI' : 'e.g. VAT not applicable' }}">{{ $getSetting('invoice', 'invoice_mention_legale', '') }}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">{{ $isFr ? 'Notes par defaut' : 'Default Notes' }}</label>
                        <textarea name="settings[invoice_notes]" rows="2" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="{{ $isFr ? 'ex: Notes supplementaires...' : 'e.g. Additional notes...' }}">{{ $getSetting('invoice', 'invoice_notes', '') }}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">{{ $isFr ? 'Pied de facture' : 'Invoice Footer' }}</label>
                        <textarea name="settings[invoice_footer]" rows="2" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="{{ $isFr ? 'ex: Merci pour votre confiance' : 'e.g. Thank you for your business' }}">{{ $getSetting('invoice', 'invoice_footer', '') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- ==================== POS SETTINGS ==================== --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h3 class="font-semibold text-sm border-b border-slate-200 dark:border-slate-700 pb-3">
                <i class="fas fa-cash-register mr-2 text-blue-500"></i>{{ $isFr ? 'Parametres du POS' : 'POS Settings' }}
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $isFr ? 'Client par defaut' : 'Default Customer' }}</label>
                    <input type="text" name="settings[pos_default_customer]" value="{{ $getSetting('pos', 'pos_default_customer', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="{{ $isFr ? 'Client de passage' : 'Walk-in Customer' }}">
                </div>
                <div class="flex items-center gap-6 pt-4">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="settings[pos_sound_enabled]" value="1" {{ $getSetting('pos', 'pos_sound_enabled', '1') == '1' ? 'checked' : '' }} class="w-4 h-4 rounded border-slate-300 text-primary-600">
                        <span class="text-sm font-medium">{{ $isFr ? 'Son active' : 'Sound Enabled' }}</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="settings[pos_auto_print]" value="1" {{ $getSetting('pos', 'pos_auto_print', '0') == '1' ? 'checked' : '' }} class="w-4 h-4 rounded border-slate-300 text-primary-600">
                        <span class="text-sm font-medium">{{ $isFr ? 'Impression auto' : 'Auto Print Receipt' }}</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- ==================== NOTIFICATIONS ==================== --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h3 class="font-semibold text-sm border-b border-slate-200 dark:border-slate-700 pb-3">
                <i class="fas fa-bell mr-2 text-amber-500"></i>{{ $isFr ? 'Notifications' : 'Notifications' }}
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="settings[notification_low_stock]" value="1" {{ $getSetting('notifications', 'notification_low_stock', '1') == '1' ? 'checked' : '' }} class="w-4 h-4 rounded border-slate-300 text-primary-600">
                        <span class="text-sm font-medium">{{ $isFr ? 'Alerte stock faible' : 'Low Stock Alert' }}</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="settings[notification_new_order]" value="1" {{ $getSetting('notifications', 'notification_new_order', '1') == '1' ? 'checked' : '' }} class="w-4 h-4 rounded border-slate-300 text-primary-600">
                        <span class="text-sm font-medium">{{ $isFr ? 'Nouvelle commande' : 'New Order' }}</span>
                    </label>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $isFr ? 'Email de notification' : 'Notification Email' }}</label>
                    <input type="email" name="settings[notification_email]" value="{{ $getSetting('notifications', 'notification_email', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="admin@example.com">
                </div>
            </div>
        </div>

        {{-- ==================== WHATSAPP ==================== --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h3 class="font-semibold text-sm border-b border-slate-200 dark:border-slate-700 pb-3">
                <i class="fab fa-whatsapp mr-2 text-emerald-500"></i>WhatsApp Cloud API
            </h3>
            <div class="space-y-4">
                <div><label class="block text-sm font-medium mb-1">Access Token</label><input type="password" name="settings[whatsapp_token]" value="{{ $getSetting('whatsapp', 'whatsapp_token', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="EAAxxxxxxx..."></div>
                <div><label class="block text-sm font-medium mb-1">Phone Number ID</label><input type="text" name="settings[whatsapp_phone_id]" value="{{ $getSetting('whatsapp', 'whatsapp_phone_id', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
                <div><label class="block text-sm font-medium mb-1">Webhook Verify Token</label><input type="text" name="settings[whatsapp_verify_token]" value="{{ $getSetting('whatsapp', 'whatsapp_verify_token', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            </div>
        </div>

        {{-- ==================== API KEYS ==================== --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h3 class="font-semibold text-sm border-b border-slate-200 dark:border-slate-700 pb-3">
                <i class="fas fa-key mr-2 text-amber-500"></i>{{ $isFr ? 'Cles API' : 'API Keys' }}
            </h3>
            <div class="space-y-4">
                <div><label class="block text-sm font-medium mb-1">OpenAI API Key</label><input type="password" name="settings[openai_api_key]" value="{{ $getSetting('api', 'openai_api_key', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="sk-..."></div>
                <div><label class="block text-sm font-medium mb-1">Meta Ads Access Token</label><input type="password" name="settings[meta_ads_token]" value="{{ $getSetting('api', 'meta_ads_token', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
                <div><label class="block text-sm font-medium mb-1">Meta Ads Account ID</label><input type="text" name="settings[meta_ads_account_id]" value="{{ $getSetting('api', 'meta_ads_account_id', '') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="px-6 py-2.5 bg-primary-600 text-white rounded-lg text-sm font-bold hover:bg-primary-700 transition">
                <i class="fas fa-save mr-1"></i> {{ $isFr ? 'Enregistrer les parametres' : 'Save Settings' }}
            </button>
        </div>
    </form>
</div>

<script>
document.querySelector('input[name="settings[invoice_color]"]').addEventListener('input', function() {
    document.getElementById('colorHex').value = this.value;
});
</script>
@endsection
