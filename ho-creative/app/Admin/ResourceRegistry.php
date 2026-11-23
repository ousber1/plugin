<?php

namespace App\Admin;

use App\Models;

/**
 * Declarative definitions for the simple back-office resources rendered by
 * Admin\ResourceController. Field types: text, textarea, number, decimal, checkbox,
 * select, date, color, tr (fr/ar text), tr_area (fr/ar textarea), json, list (comma separated), image.
 */
class ResourceRegistry
{
    public static function all(): array
    {
        $yesno = fn ($v) => $v ? '✔' : '—';

        return [
            'categories' => [
                'model' => Models\Category::class, 'label' => 'Catégories', 'perm' => 'catalog.manage', 'order' => ['sort', 'asc'],
                'columns' => ['name' => 'Nom', 'slug' => 'Slug', 'parent' => 'Parent', 'sort' => 'Ordre', 'is_published' => 'Publiée'],
                'fields' => [
                    'name' => ['tr', 'Nom', 'required'], 'slug' => ['text', 'Slug (URL)', 'required|alpha_dash|max:120|unique:categories,slug,{id}'],
                    'parent_id' => ['select', 'Catégorie parente', 'nullable|exists:categories,id', fn () => Models\Category::whereNull('parent_id')->get()->mapWithKeys(fn ($c) => [$c->id => $c->t('name')])->all()],
                    'description' => ['tr_area', 'Description', 'nullable'], 'image' => ['image', 'Image', 'nullable'],
                    'sort' => ['number', 'Ordre', 'integer|min:0'], 'is_published' => ['checkbox', 'Publiée'],
                ],
                'display' => ['parent' => fn ($m) => $m->parent?->t('name'), 'is_published' => fn ($m) => $yesno($m->is_published)],
            ],
            'pages' => [
                'model' => Models\Page::class, 'label' => 'Pages', 'perm' => 'content.manage',
                'columns' => ['title' => 'Titre', 'slug' => 'Slug', 'is_published' => 'Publiée'],
                'fields' => [
                    'title' => ['tr', 'Titre', 'required'], 'slug' => ['text', 'Slug', 'required|alpha_dash|unique:pages,slug,{id}'],
                    'body' => ['tr_area', 'Contenu (HTML simple autorisé)', 'nullable'], 'meta_description' => ['tr', 'Meta description (SEO)', 'nullable'],
                    'is_published' => ['checkbox', 'Publiée'],
                ],
                'display' => ['is_published' => fn ($m) => $yesno($m->is_published)],
            ],
            'menu' => [
                'model' => Models\MenuItem::class, 'label' => 'Navigation', 'perm' => 'content.manage', 'order' => ['sort', 'asc'],
                'columns' => ['label' => 'Libellé', 'url' => 'URL', 'location' => 'Emplacement', 'sort' => 'Ordre', 'is_active' => 'Actif'],
                'fields' => [
                    'label' => ['tr', 'Libellé', 'required'], 'url' => ['text', 'URL (ex. /categorie/flyers)', 'required|max:255'],
                    'location' => ['select', 'Emplacement', 'required|in:header,footer', ['header' => 'En-tête', 'footer' => 'Pied de page']],
                    'sort' => ['number', 'Ordre', 'integer|min:0'], 'is_active' => ['checkbox', 'Actif'],
                ],
                'display' => ['is_active' => fn ($m) => $yesno($m->is_active)],
            ],
            'home' => [
                'model' => Models\HomeSection::class, 'label' => 'Page d\'accueil (sections)', 'perm' => 'content.manage', 'order' => ['sort', 'asc'],
                'columns' => ['type' => 'Type', 'title' => 'Titre', 'sort' => 'Ordre', 'is_active' => 'Active'],
                'fields' => [
                    'type' => ['select', 'Type de section', 'required', ['hero' => 'Bannière principale', 'promotions' => 'Promotions', 'categories' => 'Catégories', 'popular' => 'Produits populaires', 'how_it_works' => 'Comment ça marche ?', 'quote_cta' => 'Appel devis', 'reviews' => 'Avis clients', 'support' => 'Aide & contact', 'rich_text' => 'Texte libre']],
                    'title' => ['tr', 'Titre', 'nullable'],
                    'content' => ['json', 'Contenu (JSON) – voir docs/CONTENT.md', 'nullable'],
                    'sort' => ['number', 'Ordre (glisser = modifier ce nombre)', 'integer|min:0'], 'is_active' => ['checkbox', 'Active'],
                ],
                'display' => ['is_active' => fn ($m) => $yesno($m->is_active)],
            ],
            'coupons' => [
                'model' => Models\Coupon::class, 'label' => 'Codes promo', 'perm' => 'catalog.manage', 'audit' => true,
                'columns' => ['code' => 'Code', 'type' => 'Type', 'value' => 'Valeur', 'used_count' => 'Utilisations', 'ends_at' => 'Fin', 'is_active' => 'Actif'],
                'fields' => [
                    'code' => ['text', 'Code', 'required|alpha_dash|max:40|unique:coupons,code,{id}'],
                    'type' => ['select', 'Type', 'required|in:percent,fixed', ['percent' => 'Pourcentage', 'fixed' => 'Montant fixe (DH)']],
                    'value' => ['decimal', 'Valeur', 'required|numeric|min:0'], 'min_subtotal' => ['decimal', 'Sous-total minimum HT', 'nullable|numeric|min:0'],
                    'product_ids' => ['list', 'Limiter aux produits (IDs séparés par des virgules)', 'nullable'],
                    'starts_at' => ['date', 'Début', 'nullable|date'], 'ends_at' => ['date', 'Fin', 'nullable|date'],
                    'max_uses' => ['number', 'Utilisations max.', 'nullable|integer|min:1'], 'is_active' => ['checkbox', 'Actif'],
                ],
                'display' => ['is_active' => fn ($m) => $yesno($m->is_active), 'ends_at' => fn ($m) => d($m->ends_at)],
            ],
            'zones' => [
                'model' => Models\ShippingZone::class, 'label' => 'Zones de livraison', 'perm' => 'settings.manage', 'order' => ['sort', 'asc'], 'audit' => true,
                'columns' => ['name' => 'Zone', 'price' => 'Prix HT', 'free_over' => 'Offerte dès', 'delivery_days' => 'Délai (j. ouvrés)', 'is_default' => 'Par défaut', 'is_active' => 'Active'],
                'fields' => [
                    'name' => ['tr', 'Nom', 'required'], 'cities' => ['list', 'Villes (séparées par des virgules)', 'nullable'],
                    'price' => ['decimal', 'Prix HT', 'required|numeric|min:0'], 'free_over' => ['decimal', 'Livraison offerte à partir de (HT)', 'nullable|numeric|min:0'],
                    'delivery_days' => ['number', 'Délai de livraison (jours ouvrés)', 'required|integer|min:0|max:60'], 'carrier' => ['text', 'Transporteur par défaut', 'nullable|max:120'],
                    'is_default' => ['checkbox', 'Zone par défaut (villes non listées)'], 'is_active' => ['checkbox', 'Active'], 'sort' => ['number', 'Ordre', 'integer|min:0'],
                ],
                'display' => ['is_default' => fn ($m) => $yesno($m->is_default), 'is_active' => fn ($m) => $yesno($m->is_active), 'price' => fn ($m) => money($m->price), 'free_over' => fn ($m) => $m->free_over ? money($m->free_over) : '—'],
            ],
            'holidays' => [
                'model' => Models\Holiday::class, 'label' => 'Jours fériés', 'perm' => 'settings.manage', 'order' => ['date', 'asc'],
                'columns' => ['date' => 'Date', 'name' => 'Nom'],
                'fields' => ['date' => ['date', 'Date', 'required|date|unique:holidays,date,{id}'], 'name' => ['text', 'Nom', 'required|max:120']],
                'display' => ['date' => fn ($m) => d($m->date)],
            ],
            'machines' => [
                'model' => Models\Machine::class, 'label' => 'Machines', 'perm' => 'production.manage',
                'columns' => ['name' => 'Nom', 'department' => 'Atelier', 'hourly_cost' => 'Coût horaire', 'is_active' => 'Active'],
                'fields' => [
                    'name' => ['text', 'Nom', 'required|max:120'],
                    'department' => ['select', 'Atelier', 'nullable', ['digital' => 'Numérique', 'offset' => 'Offset', 'large_format' => 'Grand format', 'finishing' => 'Façonnage', 'packaging' => 'Packaging', 'studio' => 'Studio / PAO']],
                    'hourly_cost' => ['decimal', 'Coût horaire (DH)', 'required|numeric|min:0'], 'is_active' => ['checkbox', 'Active'],
                ],
                'display' => ['is_active' => fn ($m) => $yesno($m->is_active)],
            ],
            'materials' => [
                'model' => Models\Material::class, 'label' => 'Matières & fournitures', 'perm' => 'stock.manage', 'show' => 'admin.materials.show',
                'columns' => ['sku' => 'Réf.', 'name' => 'Nom', 'unit' => 'Unité', 'stock_qty' => 'Stock', 'reserved_qty' => 'Réservé', 'available' => 'Disponible', 'low_stock_threshold' => 'Seuil'],
                'fields' => [
                    'sku' => ['text', 'Référence', 'required|max:60|unique:materials,sku,{id}'], 'name' => ['text', 'Nom', 'required|max:190'],
                    'category' => ['select', 'Catégorie', 'nullable', ['paper' => 'Papier', 'vinyl' => 'Vinyle', 'banner' => 'Bâche', 'ink' => 'Encre', 'board' => 'Support rigide', 'film' => 'Film de pelliculage', 'packaging' => 'Emballage', 'other' => 'Autre']],
                    'unit' => ['select', 'Unité', 'required', ['sheet' => 'Feuille', 'roll' => 'Rouleau', 'm' => 'Mètre linéaire', 'm2' => 'm²', 'piece' => 'Pièce', 'l' => 'Litre', 'kg' => 'kg']],
                    'low_stock_threshold' => ['decimal', 'Seuil d\'alerte', 'required|numeric|min:0'], 'unit_cost' => ['decimal', 'Coût unitaire (DH)', 'required|numeric|min:0'],
                    'supplier_id' => ['select', 'Fournisseur', 'nullable|exists:suppliers,id', fn () => Models\Supplier::orderBy('name')->pluck('name', 'id')->all()],
                    'is_active' => ['checkbox', 'Active'],
                ],
                'display' => ['available' => fn ($m) => $m->available().($m->isLow() ? ' ⚠' : '')],
                'note' => 'Le stock se modifie uniquement par mouvements (réception, consommation, ajustement) depuis la fiche matière.',
            ],
            'suppliers' => [
                'model' => Models\Supplier::class, 'label' => 'Fournisseurs', 'perm' => 'suppliers.manage',
                'columns' => ['name' => 'Nom', 'contact_name' => 'Contact', 'phone' => 'Téléphone', 'city' => 'Ville'],
                'fields' => [
                    'name' => ['text', 'Nom', 'required|max:190'], 'contact_name' => ['text', 'Contact', 'nullable|max:120'], 'email' => ['text', 'E-mail', 'nullable|email'],
                    'phone' => ['text', 'Téléphone', 'nullable|max:40'], 'city' => ['text', 'Ville', 'nullable|max:80'], 'ice' => ['text', 'ICE', 'nullable|max:30'], 'notes' => ['textarea', 'Notes', 'nullable'],
                ],
            ],
            'expenses' => [
                'model' => Models\Expense::class, 'label' => 'Dépenses', 'perm' => 'expenses.manage', 'order' => ['date', 'desc'], 'audit' => true,
                'columns' => ['date' => 'Date', 'category' => 'Catégorie', 'description' => 'Description', 'amount' => 'Montant', 'supplier' => 'Fournisseur'],
                'fields' => [
                    'date' => ['date', 'Date', 'required|date'],
                    'category' => ['select', 'Catégorie', 'required', ['rent' => 'Loyer', 'salaries' => 'Salaires', 'utilities' => 'Électricité / eau', 'maintenance' => 'Maintenance machines', 'supplies' => 'Fournitures', 'transport' => 'Transport', 'marketing' => 'Marketing', 'taxes' => 'Impôts & taxes', 'other' => 'Autre']],
                    'description' => ['text', 'Description', 'required|max:190'], 'amount' => ['decimal', 'Montant TTC (DH)', 'required|numeric|min:0'],
                    'supplier_id' => ['select', 'Fournisseur', 'nullable|exists:suppliers,id', fn () => Models\Supplier::orderBy('name')->pluck('name', 'id')->all()],
                ],
                'display' => ['date' => fn ($m) => d($m->date), 'amount' => fn ($m) => money($m->amount), 'supplier' => fn ($m) => $m->supplier?->name],
            ],
            'notifications' => [
                'model' => Models\NotificationTemplate::class, 'label' => 'Modèles de notifications', 'perm' => 'settings.manage',
                'columns' => ['key' => 'Clé', 'subject' => 'Sujet', 'is_active' => 'Actif'],
                'fields' => [
                    'key' => ['select', 'Événement', 'required|unique:notification_templates,key,{id}', array_combine(array_keys(\App\Notifications\SimpleNotification::DEFAULTS), array_keys(\App\Notifications\SimpleNotification::DEFAULTS))],
                    'subject' => ['tr', 'Sujet', 'required'], 'body' => ['tr_area', 'Message – variables : {order} {quote} {total} {url} {version} {expires} {name} {status}', 'required'],
                    'is_active' => ['checkbox', 'Actif'],
                ],
                'display' => ['is_active' => fn ($m) => $yesno($m->is_active)],
            ],
        ];
    }

    public static function get(string $key): array
    {
        $all = self::all();
        abort_unless(isset($all[$key]), 404);

        return $all[$key] + ['key' => $key];
    }
}
