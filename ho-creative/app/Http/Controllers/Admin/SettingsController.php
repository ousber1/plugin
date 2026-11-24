<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use App\Support\Settings;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public const SCHEMA = [
        'Identité' => ['brand.name' => ['text', 'Nom commercial'], 'brand.tagline' => ['tr', 'Slogan'], 'brand.logo' => ['image', 'Logo (PNG/SVG)'],
            'brand.primary_color' => ['color', 'Couleur principale'], 'brand.accent_color' => ['color', 'Couleur d\'accent'],
            'seo.title' => ['tr', 'Titre SEO par défaut'], 'seo.description' => ['tr', 'Description SEO par défaut']],
        'Société (documents)' => ['company.legal_name' => ['text', 'Raison sociale'], 'company.address' => ['text', 'Adresse'], 'company.city' => ['text', 'Ville'],
            'company.phone' => ['text', 'Téléphone'], 'company.email' => ['text', 'E-mail'], 'company.ice' => ['text', 'ICE'], 'company.rc' => ['text', 'RC'],
            'company.if' => ['text', 'IF'], 'company.patente' => ['text', 'Patente / TP'], 'company.cnss' => ['text', 'CNSS'], 'company.bank_name' => ['text', 'Banque'],
            'company.rib' => ['text', 'RIB'], 'company.document_footer' => ['tr', 'Mentions bas de document']],
        'Contact' => ['contact.whatsapp' => ['text', 'WhatsApp (format international sans +, ex. 2126XXXXXXXX)'], 'contact.phone' => ['text', 'Téléphone affiché'],
            'contact.email' => ['text', 'E-mail de contact (reçoit les demandes de devis)'], 'contact.help_url' => ['text', 'Lien d\'aide'], 'hours.text' => ['tr', 'Horaires affichés']],
        'Taxes & paiements' => ['tax.enabled' => ['bool', 'Appliquer la TVA'], 'tax.rate' => ['number', 'Taux de TVA (%) – à vérifier avec votre comptable'], 'tax.shipping_taxable' => ['bool', 'Livraison soumise à TVA'],
            'tax.label' => ['tr', 'Libellé de la taxe'], 'checkout.guest_enabled' => ['bool', 'Commande sans compte (invité)'], 'checkout.deposit_percent' => ['number', 'Acompte suggéré (%)'],
            'payments.bank_transfer' => ['bool', 'Virement bancaire'], 'payments.in_store' => ['bool', 'Paiement à l\'atelier'], 'payments.cod' => ['bool', 'Paiement à la livraison'],
            'payments.online' => ['bool', 'Paiement en ligne (CMI) – nécessite les identifiants CMI_* dans .env'], 'payments.online_provider' => ['select', 'Prestataire en ligne', ['cmi' => 'CMI', 'sandbox' => 'Test (hors production)']]],
        'Numérotation' => ['numbering.quote' => ['text', 'Devis'], 'numbering.order' => ['text', 'Commandes'], 'numbering.invoice' => ['text', 'Factures'], 'numbering.credit_note' => ['text', 'Avoirs'],
            'numbering.proforma' => ['text', 'Pro forma'], 'numbering.delivery_note' => ['text', 'Bons de livraison'], 'numbering.receipt' => ['text', 'Reçus'],
            'numbering.purchase_order' => ['text', 'Bons de commande'], 'numbering.job' => ['text', 'Travaux'], 'quotes.validity_days' => ['number', 'Validité des devis (jours)']],
        'Magasin (caisse)' => ['retail.registers' => ['list', 'Caisses (noms séparés par des virgules)'], 'retail.default_tax_rate' => ['number', 'TVA par défaut des articles (%)'],
            'retail.allow_negative_stock' => ['bool', 'Autoriser la vente sans stock (stock négatif)'], 'retail.receipt_footer' => ['tr', 'Message en bas du ticket'], 'numbering.ticket' => ['text', 'Numérotation des tickets']],
        'Délais & fichiers' => ['hours.working_days' => ['days', 'Jours ouvrés'], 'hours.cutoff' => ['text', 'Heure limite de commande (HH:MM)'],
            'uploads.max_mb' => ['number', 'Taille max. des fichiers (Mo) – doit rester ≤ upload_max_filesize de PHP'], 'uploads.min_dpi' => ['number', 'Résolution minimale recommandée (dpi)'],
            'retention.artwork_days' => ['number', 'Purge des fichiers clients des commandes terminées après N jours (0 = jamais)'], 'retention.cart_days' => ['number', 'Purge des paniers abandonnés après N jours']],
    ];

    public function edit(Settings $settings)
    {
        $this->authorize('settings.manage');

        return view('admin.settings', ['schema' => self::SCHEMA, 's' => $settings]);
    }

    public function update(Request $request, Settings $settings)
    {
        $this->authorize('settings.manage');
        $changes = [];
        foreach (self::SCHEMA as $fields) {
            foreach ($fields as $key => $f) {
                $field = str_replace('.', '__', $key);
                $value = match ($f[0]) {
                    'bool' => $request->boolean($field),
                    'tr' => ['fr' => (string) $request->input($field.'.fr', ''), 'ar' => (string) $request->input($field.'.ar', '')],
                    'days' => array_map('intval', (array) $request->input($field, [])),
                    'list' => array_values(array_filter(array_map('trim', explode(',', (string) $request->input($field))), 'strlen')) ?: $settings->get($key),
                    'number' => is_numeric($request->input($field)) ? (string) $request->input($field) : $settings->get($key),
                    'image' => $request->hasFile($field) ? '/storage/'.$request->validate([$field => 'image|max:2048'])[$field]->store('branding', 'public') : ($request->boolean($field.'_remove') ? null : $settings->get($key)),
                    'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $request->input($field)) ? $request->input($field) : $settings->get($key),
                    default => $request->input($field),
                };
                if ($key === 'hours.cutoff' && ! preg_match('/^\d{2}:\d{2}$/', (string) $value)) {
                    $value = $settings->get($key);
                }
                if ($value !== $settings->get($key)) {
                    $changes[$key] = [$settings->get($key), $value];
                    $settings->set($key, $value);
                }
            }
        }
        if ($changes) {
            AuditLogger::log('settings.updated', null, array_map(fn ($c) => $c[0], $changes), array_map(fn ($c) => $c[1], $changes));
        }

        return back()->with('ok', count($changes).' paramètre(s) mis à jour.');
    }

    public function audit(Request $request)
    {
        $this->authorize('audit.view');
        $logs = AuditLog::with('user')->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', $a.'%'))
            ->when($request->query('user'), fn ($q, $u) => $q->where('user_id', $u))->latest('created_at')->paginate(60)->withQueryString();

        return view('admin.audit', compact('logs'));
    }
}
