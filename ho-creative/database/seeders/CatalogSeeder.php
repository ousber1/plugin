<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Template;
use App\Services\Pricing\DefinitionValidator;
use App\Services\Pricing\PricingEngine;
use Illuminate\Database\Seeder;

/**
 * Original example catalog (flagged is_demo). ALL PRICES ARE ILLUSTRATIVE and must be
 * reviewed by the owner in Admin → Produits → Options & prix before going live.
 */
class CatalogSeeder extends Seeder
{
    protected function t(string $fr, string $ar): array
    {
        return ['fr' => $fr, 'ar' => $ar];
    }

    protected function c(string $code, string $fr, string $ar, array $mods = [], array $extra = []): array
    {
        return ['code' => $code, 'label' => $this->t($fr, $ar), 'modifiers' => array_map(fn ($m) => ['type' => $m[0], 'amount' => (string) $m[1]], $mods)] + $extra;
    }

    protected function o(string $code, string $fr, string $ar, array $choices, array $extra = []): array
    {
        return ['code' => $code, 'label' => $this->t($fr, $ar), 'display' => 'cards', 'required' => true, 'default' => $choices[0]['code'], 'choices' => $choices] + $extra;
    }

    protected function turnaround(int $express = 1, int $pct = 35): array
    {
        return $this->o('turnaround', 'Délai de production', 'مدة الإنتاج', [
            $this->c('standard', 'Standard', 'عادي'),
            $this->c('express', 'Express', 'مستعجل', [['percent', $pct]], ['production_days' => $express]),
        ]);
    }

    protected function sides(): array
    {
        return $this->o('sides', 'Impression', 'الطباعة', [$this->c('recto', 'Recto (1 face)', 'وجه واحد'), $this->c('rv', 'Recto-verso', 'وجهين')]);
    }

    protected function tiers(array $base, array $match = [], float $factor = 1.0): array
    {
        return array_map(fn ($q, $p) => ['qty' => $q, 'price' => number_format($p * $factor, 2, '.', '')] + ($match ? ['match' => $match] : []), array_keys($base), $base);
    }

    public function run(): void
    {
        if (Product::exists()) {
            return;
        }
        $img = fn ($k) => '/images/catalog/'.$k.'.svg';
        $cats = [];
        $tree = [
            ['cartes-de-visite', 'Cartes de visite', 'بطاقات الأعمال', null],
            ['flyers-depliants', 'Flyers & dépliants', 'مطويات ومنشورات', null],
            ['flyers', 'Flyers', 'منشورات', 'flyers-depliants'],
            ['depliants', 'Dépliants', 'مطويات', 'flyers-depliants'],
            ['brochures-catalogues', 'Brochures & catalogues', 'كتيبات وكتالوجات', null],
            ['brochures', 'Brochures', 'كتيبات', 'brochures-catalogues'],
            ['catalogues', 'Catalogues', 'كتالوجات', 'brochures-catalogues'],
            ['papeterie', 'Papeterie', 'القرطاسية', null],
            ['packaging-boites', 'Packaging & étiquettes', 'التغليف والملصقات', null],
            ['boites', 'Boîtes', 'علب', 'packaging-boites'],
            ['packaging', 'Packaging', 'تغليف', 'packaging-boites'],
            ['etiquettes', 'Étiquettes autocollantes', 'ملصقات', 'packaging-boites'],
            ['grand-format', 'Grand format & PLV', 'الطباعة الكبيرة', null],
            ['affiches', 'Affiches', 'ملصقات حائطية', 'grand-format'],
            ['roll-up', 'Roll-up & PLV', 'رول أب', 'grand-format'],
            ['baches', 'Bâches', 'لافتات قماشية', 'grand-format'],
            ['vinyle', 'Vinyle adhésif', 'فينيل لاصق', 'grand-format'],
            ['supports-rigides', 'Supports rigides', 'دعامات صلبة', 'grand-format'],
            ['photo-cadeaux', 'Photo & cadeaux', 'صور وهدايا', null],
            ['produits-photo', 'Produits photo', 'منتجات الصور', 'photo-cadeaux'],
            ['cadeaux-entreprise', 'Cadeaux d\'entreprise', 'هدايا الشركات', 'photo-cadeaux'],
            ['menus-restaurant', 'Menus restaurant', 'قوائم المطاعم', null],
        ];
        foreach ($tree as $i => [$slug, $fr, $ar, $parent]) {
            $cats[$slug] = Category::create(['slug' => $slug, 'name' => $this->t($fr, $ar), 'parent_id' => $parent ? $cats[$parent]->id : null,
                'image' => $img($slug), 'sort' => $i * 10, 'is_published' => true, 'is_demo' => true,
                'description' => $this->t("Impression professionnelle : {$fr}. Prix indicatifs de démonstration.", "طباعة احترافية: {$ar}.")]);
        }

        $std = ['upload', 'later', 'template', 'editor', 'design_service'];
        $uploadOnly = ['upload', 'later', 'design_service'];
        $specs = fn (array $rows) => array_map(fn ($r) => ['label' => $this->t($r[0], $r[2] ?? ''), 'value' => $this->t($r[1], $r[3] ?? '')], $rows);
        $finishes = fn (array $rows) => array_map(fn ($r) => $this->t($r[0], $r[1]), $rows);
        $faqCommon = [['q' => $this->t('Quel fichier dois-je envoyer ?', 'ما نوع الملف المطلوب؟'), 'a' => $this->t('Un PDF avec 3 mm de fond perdu, images 300 dpi. PNG et JPEG sont acceptés.', 'ملف PDF بهامش قص 3 مم وصور بدقة 300 نقطة.')],
            ['q' => $this->t('Quand démarre la production ?', 'متى يبدأ الإنتاج؟'), 'a' => $this->t('Après votre approbation du BAT (bon à tirer) et la confirmation de la commande.', 'بعد مصادقتكم على النموذج وتأكيد الطلب.')]];

        $products = [];

        // 1. Business cards – tiers, matrix by sides, many finishes, spot UV only on laminated cards
        $products[] = ['cartes-de-visite', 'cartes-de-visite-standard', 'BC-STD', 'Cartes de visite', 'بطاقات الأعمال', 'Format 85 × 55 mm, papiers premium, finitions pelliculage, vernis sélectif et coins arrondis.', 'مقاس 85 × 55 مم بورق فاخر ولمسات متنوعة.', $std, true, [
            'mode' => 'tiers', 'quantity' => ['presets' => [100, 250, 500, 1000, 2000], 'custom' => true, 'min' => 100, 'max' => 20000, 'step' => 50],
            'dimensions' => ['enabled' => true, 'unit' => 'mm', 'custom' => false, 'presets' => [['code' => 'std', 'label' => $this->t('85 × 55 mm (standard)', '85 × 55 مم'), 'w' => 85, 'h' => 55], ['code' => 'square', 'label' => $this->t('55 × 55 mm (carré)', '55 × 55 مم'), 'w' => 55, 'h' => 55]]],
            'tiers' => array_merge($this->tiers([100 => 90, 250 => 140, 500 => 210, 1000 => 330, 2000 => 560]), $this->tiers([100 => 120, 250 => 185, 500 => 270, 1000 => 430, 2000 => 720], ['sides' => ['rv']])),
            'setup_fee' => '0', 'min_charge' => '90', 'production' => ['days' => 3], 'artwork' => ['back_side_if' => ['sides' => ['rv']]],
            'options' => [
                $this->o('paper', 'Papier', 'الورق', [$this->c('couche350', 'Couché mat 350 g', 'ورق مطفي 350 غ'), $this->c('couche400', 'Couché 400 g extra-rigide', 'ورق 400 غ', [['per_unit', '0.04']]), $this->c('kraft', 'Kraft recyclé 300 g', 'ورق كرافت 300 غ', [['per_unit', '0.06']])]),
                $this->sides(),
                $this->o('lamination', 'Pelliculage', 'التغليف', [$this->c('none', 'Sans', 'بدون'), $this->c('mat', 'Mat', 'مطفي', [['per_unit', '0.05'], ['fixed', '20']], ['available_if' => ['paper' => ['couche350', 'couche400']]]), $this->c('gloss', 'Brillant', 'لامع', [['per_unit', '0.05'], ['fixed', '20']], ['available_if' => ['paper' => ['couche350', 'couche400']]]), $this->c('soft', 'Soft-touch velours', 'ملمس مخملي', [['per_unit', '0.09'], ['fixed', '30']], ['available_if' => ['paper' => ['couche350', 'couche400']]])]),
                $this->o('spot_uv', 'Vernis sélectif 3D', 'ورنيش انتقائي', [$this->c('none', 'Sans', 'بدون'), $this->c('front', 'Recto', 'الوجه', [['setup', '150'], ['per_unit', '0.15']], ['available_if' => ['lamination' => ['mat', 'soft']], 'extra_days' => 2])], ['visible_if' => ['lamination' => ['mat', 'soft']], 'help' => $this->t('Disponible sur pelliculage mat ou soft-touch.', 'متوفر مع التغليف المطفي')]),
                $this->o('corners', 'Coins', 'الزوايا', [$this->c('square', 'Droits', 'مستقيمة'), $this->c('rounded', 'Arrondis', 'مستديرة', [['per_unit', '0.08']], ['extra_days' => 1])]),
                $this->turnaround(1, 35),
            ]], $specs([['Format fini', '85 × 55 mm ou 55 × 55 mm', 'المقاس', '85 × 55 مم'], ['Impression', 'Quadrichromie numérique ou offset', 'الطباعة', 'رباعية الألوان'], ['Fond perdu', '3 mm', 'هامش القص', '3 مم']]),
            $finishes([['Pelliculage mat / brillant', 'تغليف مطفي / لامع'], ['Soft-touch', 'ملمس مخملي'], ['Vernis sélectif 3D', 'ورنيش انتقائي'], ['Coins arrondis', 'زوايا مستديرة']]), '150'];

        // 2. Flyers – size-specific tiers
        $fl = [250 => 1, 500 => 1.55, 1000 => 2.4, 2500 => 4.6, 5000 => 7.8];
        $flTiers = [];
        foreach (['a6' => 110, 'a5' => 160, 'a4' => 260, 'dl' => 150] as $size => $base) {
            foreach ($fl as $q => $f) {
                $flTiers[] = ['qty' => $q, 'price' => number_format($base * $f, 2, '.', ''), 'match' => ['size' => [$size]]];
            }
        }
        $products[] = ['flyers', 'flyers-publicitaires', 'FLY', 'Flyers publicitaires', 'منشورات إشهارية', 'A6, A5, A4 ou DL, couché 135 à 250 g, recto ou recto-verso.', 'مقاسات A6 إلى A4 بورق 135 إلى 250 غ.', $std, true, [
            'mode' => 'tiers', 'quantity' => ['presets' => [250, 500, 1000, 2500, 5000], 'custom' => true, 'min' => 250, 'max' => 100000, 'step' => 250],
            'dimensions' => ['enabled' => true, 'unit' => 'mm', 'custom' => false, 'presets' => [['code' => 'a6', 'label' => $this->t('A6 – 105 × 148 mm', 'A6'), 'w' => 105, 'h' => 148], ['code' => 'a5', 'label' => $this->t('A5 – 148 × 210 mm', 'A5'), 'w' => 148, 'h' => 210], ['code' => 'a4', 'label' => $this->t('A4 – 210 × 297 mm', 'A4'), 'w' => 210, 'h' => 297], ['code' => 'dl', 'label' => $this->t('DL – 99 × 210 mm', 'DL'), 'w' => 99, 'h' => 210]]],
            'tiers' => $flTiers, 'min_charge' => '110', 'production' => ['days' => 3], 'artwork' => ['back_side_if' => ['sides' => ['rv']]],
            'options' => [
                $this->o('paper', 'Papier', 'الورق', [$this->c('c135', 'Couché brillant 135 g', 'ورق لامع 135 غ'), $this->c('c170', 'Couché mat 170 g', 'ورق مطفي 170 غ', [['per_thousand', '25']]), $this->c('c250', 'Couché 250 g', 'ورق 250 غ', [['per_thousand', '60']])]),
                $this->o('sides', 'Impression', 'الطباعة', [$this->c('recto', 'Recto', 'وجه واحد'), $this->c('rv', 'Recto-verso', 'وجهين', [['per_thousand', '70']])]),
                $this->turnaround(1, 30),
            ]], $specs([['Formats', 'A6, A5, A4, DL'], ['Papiers', 'Couché 135 / 170 / 250 g']]), $finishes([['Massicotage au format', 'قص حسب المقاس']]), '200'];

        // 3. Folded leaflets
        $products[] = ['depliants', 'depliants-plies', 'DEP', 'Dépliants pliés', 'مطويات مطوية', 'A4 ouvert, 2 ou 3 volets, pli roulé ou accordéon.', 'A4 مفتوح بطيتين أو ثلاث.', $uploadOnly, true, [
            'mode' => 'tiers', 'quantity' => ['presets' => [250, 500, 1000, 2500], 'custom' => false],
            'dimensions' => ['enabled' => true, 'unit' => 'mm', 'presets' => [['code' => 'a4', 'label' => $this->t('A4 ouvert (210 × 297 mm)', 'A4 مفتوح'), 'w' => 297, 'h' => 210]]],
            'tiers' => $this->tiers([250 => 420, 500 => 620, 1000 => 950, 2500 => 1900]), 'min_charge' => '420', 'production' => ['days' => 4], 'artwork' => ['back_side_if' => ['paper' => ['c135', 'c170']]],
            'options' => [
                $this->o('fold', 'Pliage', 'الطي', [$this->c('two', '2 volets (1 pli)', 'طيتان'), $this->c('roll', '3 volets roulé', 'ثلاث طيات', [['setup', '60'], ['per_thousand', '40']]), $this->c('zig', '3 volets accordéon', 'أكورديون', [['setup', '60'], ['per_thousand', '40']])]),
                $this->o('paper', 'Papier', 'الورق', [$this->c('c135', 'Couché 135 g', 'ورق 135 غ'), $this->c('c170', 'Couché 170 g', 'ورق 170 غ', [['per_thousand', '45']])]),
                $this->turnaround(2, 30),
            ]], $specs([['Format ouvert', '297 × 210 mm'], ['Impression', 'Recto-verso quadri']]), $finishes([['Rainage + pliage', 'تحضير الطي']]), '300'];

        // 4. Brochures – pages as per-unit modifiers, binding availability depends on pages
        $products[] = ['brochures', 'brochures-agrafees', 'BRO', 'Brochures', 'كتيبات', 'Brochures A5 ou A4 de 8 à 48 pages, agrafées ou dos carré collé.', 'كتيبات من 8 إلى 48 صفحة.', $uploadOnly, true, [
            'mode' => 'tiers', 'quantity' => ['presets' => [50, 100, 250, 500], 'custom' => true, 'min' => 25, 'max' => 10000, 'step' => 25],
            'dimensions' => ['enabled' => true, 'presets' => [['code' => 'a5', 'label' => $this->t('A5', 'A5'), 'w' => 148, 'h' => 210], ['code' => 'a4', 'label' => $this->t('A4', 'A4'), 'w' => 210, 'h' => 297]]],
            'tiers' => array_merge($this->tiers([50 => 300, 100 => 480, 250 => 950, 500 => 1650], ['size' => ['a5']]), $this->tiers([50 => 480, 100 => 780, 250 => 1550, 500 => 2700], ['size' => ['a4']])),
            'setup_fee' => '80', 'min_charge' => '300', 'production' => ['days' => 5],
            'options' => [
                $this->o('pages', 'Nombre de pages', 'عدد الصفحات', [$this->c('p8', '8 pages', '8 صفحات'), $this->c('p12', '12 pages', '12 صفحة', [['per_unit', '0.9']]), $this->c('p16', '16 pages', '16 صفحة', [['per_unit', '1.8']]), $this->c('p24', '24 pages', '24 صفحة', [['per_unit', '3.4']]), $this->c('p32', '32 pages', '32 صفحة', [['per_unit', '5']]), $this->c('p48', '48 pages', '48 صفحة', [['per_unit', '8']])], ['display' => 'select']),
                $this->o('binding', 'Reliure', 'التجليد', [$this->c('staple', 'Piqûre à cheval (agrafes)', 'دبابيس', [], ['available_if' => ['pages' => ['p8', 'p12', 'p16', 'p24', 'p32']]]), $this->c('pur', 'Dos carré collé', 'تجليد ملصق', [['setup', '120'], ['per_unit', '1.2']], ['available_if' => ['pages' => ['p24', 'p32', 'p48']], 'extra_days' => 2])], ['default' => 'staple']),
                $this->o('cover', 'Couverture', 'الغلاف', [$this->c('self', 'Même papier (135 g)', 'نفس الورق'), $this->c('c300', 'Couverture 300 g pelliculée', 'غلاف 300 غ', [['per_unit', '0.7']])]),
                $this->turnaround(3, 30),
            ]], $specs([['Pages', '8 à 48'], ['Intérieur', 'Couché 135 g']]), $finishes([['Agrafage', 'دبابيس'], ['Dos carré collé', 'تجليد'], ['Pelliculage couverture', 'تغليف الغلاف']]), '400'];

        // 5. Catalogues – quote only
        $products[] = ['catalogues', 'catalogues-sur-devis', 'CAT', 'Catalogues', 'كتالوجات', 'Catalogues produits multi-pages, offset grand tirage. Sur devis.', 'كتالوجات بكميات كبيرة – حسب عرض السعر.', $uploadOnly, false, null,
            $specs([['Pagination', '16 à 200 pages'], ['Impression', 'Offset quadri'], ['Reliure', 'Dos carré collé, cousu, spirale']]), $finishes([['Vernis sélectif couverture', 'ورنيش انتقائي'], ['Dorure à chaud', 'تذهيب']]), '0', 'quote'];

        // 6. Letterheads
        $products[] = ['papeterie', 'papier-en-tete', 'LET', 'Papier en-tête', 'أوراق رسمية', 'Papier à lettre A4 personnalisé, 80 à 120 g.', 'ورق رسائل A4 مخصص.', $std, true, [
            'mode' => 'tiers', 'quantity' => ['presets' => [250, 500, 1000, 2500], 'custom' => false],
            'dimensions' => ['enabled' => true, 'presets' => [['code' => 'a4', 'label' => $this->t('A4', 'A4'), 'w' => 210, 'h' => 297]]],
            'tiers' => $this->tiers([250 => 260, 500 => 380, 1000 => 600, 2500 => 1250]), 'min_charge' => '260', 'production' => ['days' => 3],
            'options' => [$this->o('paper', 'Papier', 'الورق', [$this->c('o90', 'Offset 90 g', 'أوفست 90 غ'), $this->c('o120', 'Offset 120 g premium', 'أوفست 120 غ', [['per_thousand', '90']])]), $this->turnaround(1, 30)],
        ], $specs([['Format', 'A4'], ['Compatible', 'Imprimantes laser et jet d\'encre']]), [], '200'];

        // 7. Boxes – quote only
        $products[] = ['boites', 'boites-pliantes', 'BOX', 'Boîtes pliantes personnalisées', 'علب مطوية مخصصة', 'Boîtes en carton compact, découpe à la forme, dorure, fenêtre. Sur devis.', 'علب من الكرتون حسب الطلب.', $uploadOnly, false, null,
            $specs([['Matières', 'Carton compact 300–400 g, kraft, micro-cannelure'], ['Délai', 'À partir de 10 jours ouvrés']]), $finishes([['Découpe forme', 'قص حسب الشكل'], ['Dorure', 'تذهيب'], ['Fenêtre PVC', 'نافذة شفافة']]), '0', 'quote'];

        // 8. Kraft bags – tiers per size
        $products[] = ['packaging', 'sacs-kraft-personnalises', 'BAG', 'Sacs kraft personnalisés', 'أكياس كرافت مخصصة', 'Sacs kraft à poignées imprimés 1 ou 2 couleurs.', 'أكياس كرافت مطبوعة بلون أو لونين.', $uploadOnly, true, [
            'mode' => 'tiers', 'quantity' => ['presets' => [100, 250, 500, 1000], 'custom' => false],
            'dimensions' => ['enabled' => true, 'presets' => [['code' => 's', 'label' => $this->t('Petit 18 × 22 cm', 'صغير'), 'w' => 180, 'h' => 220], ['code' => 'm', 'label' => $this->t('Moyen 26 × 32 cm', 'متوسط'), 'w' => 260, 'h' => 320], ['code' => 'l', 'label' => $this->t('Grand 32 × 42 cm', 'كبير'), 'w' => 320, 'h' => 420]]],
            'tiers' => array_merge($this->tiers([100 => 650, 250 => 1250, 500 => 2100, 1000 => 3700], ['size' => ['s']]), $this->tiers([100 => 850, 250 => 1650, 500 => 2850, 1000 => 5000], ['size' => ['m']]), $this->tiers([100 => 1100, 250 => 2150, 500 => 3700, 1000 => 6500], ['size' => ['l']])),
            'setup_fee' => '250', 'production' => ['days' => 8],
            'options' => [$this->o('colors', 'Couleurs d\'impression', 'ألوان الطباعة', [$this->c('c1', '1 couleur', 'لون واحد'), $this->c('c2', '2 couleurs', 'لونان', [['setup', '200'], ['per_unit', '0.5']])])],
        ], $specs([['Matière', 'Kraft brun 100 g, poignées torsadées'], ['Impression', 'Sérigraphie / flexographie']]), [], '250'];

        // 9. Stickers – area mode, rates by material, custom shape surcharge
        $products[] = ['etiquettes', 'etiquettes-autocollantes', 'STK', 'Étiquettes autocollantes', 'ملصقات لاصقة', 'Stickers sur mesure en vinyle ou papier, découpe à la forme.', 'ملصقات حسب المقاس.', $std, true, [
            'mode' => 'area', 'quantity' => ['presets' => [50, 100, 250, 500, 1000], 'custom' => true, 'min' => 10, 'max' => 50000, 'step' => 10],
            'dimensions' => ['enabled' => true, 'unit' => 'mm', 'custom' => true, 'min_w' => 20, 'max_w' => 300, 'min_h' => 20, 'max_h' => 300, 'rounding_mm' => 1,
                'presets' => [['code' => 'r50', 'label' => $this->t('Rond Ø 50 mm', 'دائري 50 مم'), 'w' => 50, 'h' => 50], ['code' => 'r80', 'label' => $this->t('Rond Ø 80 mm', 'دائري 80 مم'), 'w' => 80, 'h' => 80], ['code' => 'rect', 'label' => $this->t('Rectangle 100 × 50 mm', 'مستطيل'), 'w' => 100, 'h' => 50]]],
            'area' => ['area_step_m2' => '0.0001', 'min_area_m2' => '0.0016', 'rates' => [['min_m2' => '0', 'rate' => '320', 'match' => ['material' => ['paper']]], ['min_m2' => '2', 'rate' => '260', 'match' => ['material' => ['paper']]], ['min_m2' => '0', 'rate' => '420', 'match' => ['material' => ['vinyl', 'clear']]], ['min_m2' => '2', 'rate' => '340', 'match' => ['material' => ['vinyl', 'clear']]]]],
            'setup_fee' => '40', 'min_charge' => '120', 'production' => ['days' => 3],
            'options' => [
                $this->o('material', 'Matière', 'المادة', [$this->c('paper', 'Papier adhésif blanc', 'ورق لاصق'), $this->c('vinyl', 'Vinyle blanc résistant à l\'eau', 'فينيل أبيض'), $this->c('clear', 'Vinyle transparent', 'فينيل شفاف', [['percent', '10']])]),
                $this->o('cut', 'Découpe', 'القص', [$this->c('kiss', 'Forme standard (rond / rectangle)', 'شكل عادي'), $this->c('shape', 'Découpe à la forme du visuel', 'قص حسب الشكل', [['percent', '15'], ['setup', '60']])]),
                $this->o('finish', 'Protection', 'الحماية', [$this->c('none', 'Sans', 'بدون'), $this->c('lam', 'Pelliculage brillant', 'تغليف لامع', [['per_m2', '60']])], ['visible_if' => ['material' => ['vinyl', 'clear']]]),
            ]], $specs([['Dimensions', 'De 20 × 20 à 300 × 300 mm'], ['Livraison', 'En planches ou à l\'unité']]), $finishes([['Découpe à la forme', 'قص بالشكل'], ['Pelliculage', 'تغليف']]), '120'];

        // 10. Posters – area mode with presets + custom
        $products[] = ['affiches', 'affiches-grand-format', 'POS', 'Affiches', 'ملصقات حائطية', 'Affiches A3 à A0 ou sur mesure, papier satin, blueback ou photo.', 'ملصقات من A3 إلى A0 أو حسب المقاس.', $std, true, [
            'mode' => 'area', 'quantity' => ['presets' => [1, 2, 5, 10, 25, 50], 'custom' => true, 'min' => 1, 'max' => 1000],
            'dimensions' => ['enabled' => true, 'unit' => 'cm', 'custom' => true, 'min_w' => 200, 'max_w' => 1500, 'min_h' => 200, 'max_h' => 5000, 'rounding_mm' => 5,
                'presets' => [['code' => 'a3', 'label' => $this->t('A3 – 29,7 × 42 cm', 'A3'), 'w' => 297, 'h' => 420], ['code' => 'a2', 'label' => $this->t('A2 – 42 × 59,4 cm', 'A2'), 'w' => 420, 'h' => 594], ['code' => 'a1', 'label' => $this->t('A1 – 59,4 × 84,1 cm', 'A1'), 'w' => 594, 'h' => 841], ['code' => 'a0', 'label' => $this->t('A0 – 84,1 × 118,9 cm', 'A0'), 'w' => 841, 'h' => 1189]]],
            'area' => ['area_step_m2' => '0.01', 'min_area_m2' => '0.12', 'rates' => [['min_m2' => '0', 'rate' => '140'], ['min_m2' => '5', 'rate' => '115'], ['min_m2' => '20', 'rate' => '95']]],
            'min_charge' => '50', 'production' => ['days' => 2],
            'options' => [
                $this->o('paper', 'Papier', 'الورق', [$this->c('satin', 'Satin 200 g', 'ساتان 200 غ'), $this->c('blueback', 'Blueback 115 g (affichage)', 'بلوباك 115 غ', [['per_m2', '-20']]), $this->c('photo', 'Photo brillant 240 g', 'ورق صور 240 غ', [['per_m2', '45']])]),
                $this->turnaround(1, 40),
            ]], $specs([['Impression', 'Jet d\'encre grand format'], ['Formats', 'A3 à A0 ou sur mesure jusqu\'à 1,5 m de large']]), [], '150'];

        // 11. Roll-ups – tiers per size
        $products[] = ['roll-up', 'roll-up-enrouleur', 'RUP', 'Roll-up', 'رول أب', 'Kit roll-up enrouleur avec visuel et sac de transport.', 'رول أب مع حقيبة نقل.', $std, true, [
            'mode' => 'tiers', 'quantity' => ['presets' => [1, 2, 5, 10], 'custom' => true, 'min' => 1, 'max' => 200],
            'dimensions' => ['enabled' => true, 'unit' => 'cm', 'presets' => [['code' => '85', 'label' => $this->t('85 × 200 cm', '85 × 200 سم'), 'w' => 850, 'h' => 2000], ['code' => '100', 'label' => $this->t('100 × 200 cm', '100 × 200 سم'), 'w' => 1000, 'h' => 2000], ['code' => '120', 'label' => $this->t('120 × 200 cm', '120 × 200 سم'), 'w' => 1200, 'h' => 2000]]],
            'tiers' => array_merge($this->tiers([1 => 590, 2 => 1120, 5 => 2650, 10 => 5000], ['size' => ['85']]), $this->tiers([1 => 690, 2 => 1310, 5 => 3100, 10 => 5900], ['size' => ['100']]), $this->tiers([1 => 850, 2 => 1620, 5 => 3850, 10 => 7300], ['size' => ['120']])),
            'production' => ['days' => 2],
            'options' => [$this->o('frame', 'Structure', 'الهيكل', [$this->c('std', 'Standard aluminium', 'ألومنيوم عادي'), $this->c('premium', 'Premium (embout réglable)', 'ممتاز', [['per_unit', '220']])]), $this->turnaround(1, 30)],
        ], $specs([['Bâche', 'PVC anti-curling 440 g'], ['Livré avec', 'Sac de transport']]), [], '200'];

        // 12. Banners – area-based with minimum charge (acceptance flow 2)
        $products[] = ['baches', 'bache-pvc-sur-mesure', 'BAN', 'Bâche PVC sur mesure', 'لافتة PVC حسب المقاس', 'Bâche imprimée sur mesure, ourlets et œillets, usage intérieur / extérieur.', 'لافتة مطبوعة حسب المقاس مع حلقات.', $std, true, [
            'mode' => 'area', 'quantity' => ['presets' => [1, 2, 3, 5, 10], 'custom' => true, 'min' => 1, 'max' => 500],
            'dimensions' => ['enabled' => true, 'unit' => 'cm', 'custom' => true, 'min_w' => 300, 'max_w' => 5000, 'min_h' => 300, 'max_h' => 50000, 'rounding_mm' => 10,
                'presets' => [['code' => '200x100', 'label' => $this->t('200 × 100 cm', '200 × 100 سم'), 'w' => 2000, 'h' => 1000], ['code' => '300x100', 'label' => $this->t('300 × 100 cm', '300 × 100 سم'), 'w' => 3000, 'h' => 1000], ['code' => '400x200', 'label' => $this->t('400 × 200 cm', '400 × 200 سم'), 'w' => 4000, 'h' => 2000]]],
            'area' => ['area_step_m2' => '0.01', 'min_area_m2' => '0.5', 'rates' => [['min_m2' => '0', 'rate' => '95'], ['min_m2' => '10', 'rate' => '80'], ['min_m2' => '50', 'rate' => '68'], ['min_m2' => '0', 'rate' => '120', 'match' => ['material' => ['mesh']]], ['min_m2' => '10', 'rate' => '105', 'match' => ['material' => ['mesh']]]]],
            'min_charge' => '150', 'production' => ['days' => 2],
            'options' => [
                $this->o('material', 'Matière', 'المادة', [$this->c('frontlit', 'Bâche frontlit 510 g', 'فرونتليت 510 غ'), $this->c('mesh', 'Bâche microperforée (mesh)', 'شبكية مثقبة')]),
                $this->o('finishing', 'Finition', 'التشطيب', [$this->c('eyelets', 'Ourlets + œillets tous les 50 cm', 'حواف وحلقات', [['per_m2', '12']]), $this->c('raw', 'Coupe franche (sans œillet)', 'قص بدون حلقات'), $this->c('sleeves', 'Fourreaux haut et bas', 'جيوب علوية وسفلية', [['per_m2', '18'], ['fixed', '30']])]),
                $this->turnaround(1, 40),
            ]], $specs([['Largeur max.', '5 m (au-delà : soudure)'], ['Encres', 'Éco-solvant, résistance extérieure']]), $finishes([['Œillets', 'حلقات'], ['Fourreaux', 'جيوب'], ['Soudure', 'لحام']]), '150'];

        // 13. Adhesive vinyl
        $products[] = ['vinyle', 'vinyle-adhesif-imprime', 'VIN', 'Vinyle adhésif imprimé', 'فينيل لاصق مطبوع', 'Vitrophanie, décoration murale, signalétique : vinyle imprimé ou lettrage découpé.', 'فينيل للواجهات والديكور.', $std, true, [
            'mode' => 'area', 'quantity' => ['presets' => [1, 2, 5, 10], 'custom' => true, 'min' => 1, 'max' => 500],
            'dimensions' => ['enabled' => true, 'unit' => 'cm', 'custom' => true, 'min_w' => 100, 'max_w' => 1370, 'min_h' => 100, 'max_h' => 30000, 'rounding_mm' => 10, 'presets' => []],
            'area' => ['area_step_m2' => '0.01', 'min_area_m2' => '0.25', 'rates' => [['min_m2' => '0', 'rate' => '180'], ['min_m2' => '10', 'rate' => '150']]],
            'min_charge' => '120', 'production' => ['days' => 2],
            'options' => [
                $this->o('type', 'Type', 'النوع', [$this->c('printed', 'Vinyle imprimé quadri', 'فينيل مطبوع'), $this->c('cut', 'Lettrage découpé (couleur unie)', 'حروف مقصوصة', [['per_m2', '40'], ['setup', '50']])]),
                $this->o('lam', 'Lamination', 'التغليف', [$this->c('none', 'Sans', 'بدون'), $this->c('uv', 'Lamination anti-UV', 'حماية من الأشعة', [['per_m2', '55']])], ['visible_if' => ['type' => ['printed']]]),
                $this->o('install', 'Pose', 'التركيب', [$this->c('no', 'Sans pose', 'بدون تركيب'), $this->c('yes', 'Pose sur site (Casablanca)', 'تركيب في الموقع', [['per_m2', '60'], ['fixed', '200']])]),
            ]], $specs([['Largeur laize', '137 cm max.']]), [], '150'];

        // 14. Rigid boards
        $products[] = ['supports-rigides', 'panneaux-rigides', 'RIG', 'Panneaux rigides (Forex, Dibond, Plexi)', 'ألواح صلبة', 'Panneaux imprimés en impression directe UV, découpe sur mesure.', 'ألواح مطبوعة مباشرة بالأشعة فوق البنفسجية.', $std, true, [
            'mode' => 'area', 'quantity' => ['presets' => [1, 2, 5, 10], 'custom' => true, 'min' => 1, 'max' => 500],
            'dimensions' => ['enabled' => true, 'unit' => 'cm', 'custom' => true, 'min_w' => 100, 'max_w' => 3000, 'min_h' => 100, 'max_h' => 1500, 'rounding_mm' => 5,
                'presets' => [['code' => 'a3', 'label' => $this->t('A3 – 30 × 42 cm', 'A3'), 'w' => 297, 'h' => 420], ['code' => '60x40', 'label' => $this->t('60 × 40 cm', '60 × 40 سم'), 'w' => 600, 'h' => 400], ['code' => '100x70', 'label' => $this->t('100 × 70 cm', '100 × 70 سم'), 'w' => 1000, 'h' => 700]]],
            'area' => ['area_step_m2' => '0.01', 'min_area_m2' => '0.1', 'rates' => [['min_m2' => '0', 'rate' => '260', 'match' => ['material' => ['forex3']]], ['min_m2' => '0', 'rate' => '310', 'match' => ['material' => ['forex5']]], ['min_m2' => '0', 'rate' => '480', 'match' => ['material' => ['dibond']]], ['min_m2' => '0', 'rate' => '620', 'match' => ['material' => ['plexi']]]]],
            'min_charge' => '90', 'production' => ['days' => 3],
            'options' => [
                $this->o('material', 'Support', 'الدعامة', [$this->c('forex3', 'PVC Forex 3 mm', 'فوركس 3 مم'), $this->c('forex5', 'PVC Forex 5 mm', 'فوركس 5 مم'), $this->c('dibond', 'Dibond aluminium 3 mm', 'ديبوند 3 مم'), $this->c('plexi', 'Plexiglas 5 mm', 'بليكسي 5 مم', [], ['extra_days' => 2])], ['display' => 'select']),
                $this->o('holes', 'Perçage', 'الثقب', [$this->c('none', 'Sans', 'بدون'), $this->c('four', '4 trous d\'angle', '4 ثقوب', [['per_unit', '15']])]),
                $this->o('spacers', 'Entretoises', 'فواصل', [$this->c('none', 'Sans', 'بدون'), $this->c('chrome', '4 entretoises chromées', '4 فواصل', [['per_unit', '80']])], ['visible_if' => ['holes' => ['four']]]),
            ]], $specs([['Impression', 'UV directe haute définition']]), [], '200'];

        // 15. Photo prints
        $products[] = ['produits-photo', 'tirages-photo', 'PHO', 'Tirages photo', 'طبع الصور', 'Tirages sur papier photo brillant ou mat, du 10 × 15 au 30 × 45 cm.', 'طبع الصور بأحجام مختلفة.', ['upload', 'later'], true, [
            'mode' => 'tiers', 'quantity' => ['presets' => [10, 25, 50, 100], 'custom' => true, 'min' => 1, 'max' => 5000],
            'dimensions' => ['enabled' => true, 'presets' => [['code' => '10x15', 'label' => $this->t('10 × 15 cm', '10 × 15'), 'w' => 100, 'h' => 150], ['code' => '13x18', 'label' => $this->t('13 × 18 cm', '13 × 18'), 'w' => 130, 'h' => 180], ['code' => '20x30', 'label' => $this->t('20 × 30 cm', '20 × 30'), 'w' => 200, 'h' => 300], ['code' => '30x45', 'label' => $this->t('30 × 45 cm', '30 × 45'), 'w' => 300, 'h' => 450]]],
            'tiers' => array_merge($this->tiers([10 => 25, 25 => 55, 50 => 100, 100 => 180], ['size' => ['10x15']]), $this->tiers([10 => 40, 25 => 90, 50 => 165, 100 => 300], ['size' => ['13x18']]), $this->tiers([10 => 120, 25 => 270, 50 => 500, 100 => 900], ['size' => ['20x30']]), $this->tiers([10 => 260, 25 => 600, 50 => 1100, 100 => 2000], ['size' => ['30x45']])),
            'min_charge' => '25', 'production' => ['days' => 1],
            'options' => [$this->o('finish', 'Finition', 'اللمسة', [$this->c('gloss', 'Brillant', 'لامع'), $this->c('matte', 'Mat', 'مطفي')])],
        ], $specs([['Papier', 'Photo 240 g']]), [], '0'];

        // 16. Mugs
        $products[] = ['cadeaux-entreprise', 'mugs-personnalises', 'MUG', 'Mugs personnalisés', 'أكواب مخصصة', 'Mugs céramique 330 ml imprimés en sublimation, idéal cadeaux d\'entreprise.', 'أكواب سيراميك مطبوعة.', $std, true, [
            'mode' => 'tiers', 'quantity' => ['presets' => [1, 6, 12, 24, 50, 100], 'custom' => true, 'min' => 1, 'max' => 2000],
            'dimensions' => ['enabled' => true, 'presets' => [['code' => 'wrap', 'label' => $this->t('Zone d\'impression 200 × 85 mm', 'منطقة الطباعة'), 'w' => 200, 'h' => 85]]],
            'tiers' => $this->tiers([1 => 70, 6 => 360, 12 => 660, 24 => 1200, 50 => 2300, 100 => 4200]), 'production' => ['days' => 3],
            'options' => [$this->o('color', 'Couleur intérieure', 'اللون الداخلي', [$this->c('white', 'Blanc', 'أبيض'), $this->c('color', 'Intérieur coloré', 'ملون', [['per_unit', '8']])]), $this->o('box', 'Emballage', 'التغليف', [$this->c('none', 'Sans', 'بدون'), $this->c('gift', 'Boîte cadeau individuelle', 'علبة هدية', [['per_unit', '6']])])],
        ], $specs([['Contenance', '330 ml'], ['Lave-vaisselle', 'Oui (programme doux)']]), [], '100'];

        // 17. Restaurant menus
        $products[] = ['menus-restaurant', 'menus-restaurant-plastifies', 'MEN', 'Menus restaurant', 'قوائم المطاعم', 'Menus A4 ou A3 recto-verso, plastifiés pour un usage quotidien.', 'قوائم مطاعم مغلفة.', $std, true, [
            'mode' => 'tiers', 'quantity' => ['presets' => [10, 25, 50, 100], 'custom' => true, 'min' => 5, 'max' => 2000],
            'dimensions' => ['enabled' => true, 'presets' => [['code' => 'a4', 'label' => $this->t('A4', 'A4'), 'w' => 210, 'h' => 297], ['code' => 'a3', 'label' => $this->t('A3 plié', 'A3 مطوي'), 'w' => 297, 'h' => 420]]],
            'tiers' => array_merge($this->tiers([10 => 150, 25 => 300, 50 => 520, 100 => 900], ['size' => ['a4']]), $this->tiers([10 => 260, 25 => 540, 50 => 950, 100 => 1700], ['size' => ['a3']])),
            'min_charge' => '150', 'production' => ['days' => 3], 'artwork' => ['back_side_if' => ['size' => ['a4', 'a3']]],
            'options' => [$this->o('lamination', 'Plastification', 'التغليف', [$this->c('thick', 'Plastification épaisse 125 µ', 'تغليف سميك'), $this->c('soft', 'Pelliculage mat 350 g', 'تغليف مطفي', [['per_unit', '-1']])]), $this->turnaround(1, 30)],
        ], $specs([['Impression', 'Recto-verso quadri']]), [], '250'];

        $validator = new DefinitionValidator;
        $engine = app(PricingEngine::class);
        foreach ($products as $i => $p) {
            [$cat, $slug, $sku, $fr, $ar, $sumFr, $sumAr, $modes, $priced, $def, $spec, $fin, $fee] = $p;
            $mode = $p[13] ?? ($priced ? 'instant' : 'quote');
            if ($def && ($errs = $validator->errors($def))) {
                throw new \RuntimeException("Seed pricing invalid for {$slug}: ".implode('; ', $errs));
            }
            $product = Product::create([
                'category_id' => $cats[$cat]->id, 'slug' => $slug, 'sku' => $sku, 'name' => $this->t($fr, $ar), 'summary' => $this->t($sumFr, $sumAr),
                'description' => $this->t('<p>'.$sumFr.'</p><p>Tous nos travaux sont contrôlés par notre prépresse avant impression. <em>Contenu et prix d\'exemple à adapter.</em></p>', '<p>'.$sumAr.'</p>'),
                'sale_mode' => $mode, 'images' => [$img($cat)], 'specs' => $spec, 'finishes' => $fin, 'faq' => $faqCommon,
                'guides' => [['title' => $this->t('Guide de préparation des fichiers (PDF)', 'دليل تحضير الملفات'), 'path' => '/docs/guide-fichiers-ho-creative.pdf']],
                'artwork_modes' => $modes, 'design_fee' => $fee, 'requires_proof' => true, 'is_popular' => in_array($slug, ['cartes-de-visite-standard', 'flyers-publicitaires', 'bache-pvc-sur-mesure', 'roll-up-enrouleur', 'etiquettes-autocollantes', 'affiches-grand-format', 'mugs-personnalises', 'sacs-kraft-personnalises']),
                'is_demo' => true, 'sort' => $i * 10,
                'meta_title' => $this->t($fr.' – impression au Maroc', $ar), 'meta_description' => $this->t($sumFr, $sumAr),
            ]);
            if ($def) {
                $rs = $product->ruleSets()->create(['version' => 1, 'status' => 'published', 'definition' => $def, 'published_at' => now(), 'notes' => 'Grille d\'exemple (démo)']);
                $product->update(['from_price' => $engine->fromPrice($product->fresh(), $rs)]);
            }
        }

        $this->templates();
    }

    /** Original business-card / flyer templates in design units (4 units per mm, bleed box origin). */
    protected function templates(): void
    {
        $card = Product::where('slug', 'cartes-de-visite-standard')->first();
        $flyer = Product::where('slug', 'flyers-publicitaires')->first();
        $U = 4;
        $W = (85 + 6) * $U;
        $H = (55 + 6) * $U;
        $txt = fn ($text, $x, $y, $size, $fill, $weight = 'normal', $family = 'Arial') => ['type' => 'i-text', 'version' => '5.3.0', 'left' => $x * $U, 'top' => $y * $U, 'text' => $text, 'fontSize' => $size * $U, 'fill' => $fill, 'fontFamily' => $family, 'fontWeight' => $weight, 'styles' => []];
        $rect = fn ($x, $y, $w, $h, $fill, $extra = []) => ['type' => 'rect', 'version' => '5.3.0', 'left' => $x * $U, 'top' => $y * $U, 'width' => $w * $U, 'height' => $h * $U, 'fill' => $fill, 'strokeWidth' => 0] + $extra;
        $circle = fn ($x, $y, $r, $fill, $op = 1) => ['type' => 'circle', 'version' => '5.3.0', 'left' => $x * $U, 'top' => $y * $U, 'radius' => $r * $U, 'fill' => $fill, 'opacity' => $op, 'strokeWidth' => 0];
        $sets = [
            ['encre-nuit', 'Encre de nuit', 'حبر الليل', [
                'front' => ['version' => '5.3.0', 'background' => '#1B1F3B', 'objects' => [$rect(0, 0, 91, 61, '#1B1F3B'), $circle(8, 9, 4, '#00A0E3', .9), $circle(13, 9, 4, '#E4007C', .85), $circle(10.5, 13, 4, '#FFD400', .85), $txt('Prénom NOM', 9, 30, 4.2, '#FFFFFF', 'bold'), $txt('Fonction · Société', 9, 37, 2.6, '#C9CCE0'), $txt('+212 6 00 00 00 00 · contact@exemple.ma', 9, 48, 2.2, '#FFFFFF')]],
                'back' => ['version' => '5.3.0', 'background' => '#E4007C', 'objects' => [$rect(0, 0, 91, 61, '#E4007C'), $txt('VOTRE LOGO', 26, 26, 5, '#FFFFFF', 'bold')]]]],
            ['bande-magenta', 'Bande magenta', 'شريط وردي', [
                'front' => ['version' => '5.3.0', 'background' => '#FFFFFF', 'objects' => [$rect(0, 0, 91, 61, '#FFFFFF'), $rect(0, 0, 12, 61, '#E4007C'), $txt('Prénom NOM', 19, 16, 4, '#1B1F3B', 'bold'), $txt('Fonction', 19, 23, 2.6, '#6B6F86'), $txt('Tél. +212 5 00 00 00 00', 19, 40, 2.3, '#1B1F3B'), $txt('www.exemple.ma', 19, 45, 2.3, '#E4007C')]]]],
            ['atlas-kraft', 'Atlas minimal', 'أطلس', [
                'front' => ['version' => '5.3.0', 'background' => '#F3EBDD', 'objects' => [$rect(0, 0, 91, 61, '#F3EBDD'), $rect(9, 40, 30, 0.6, '#1B1F3B'), $txt('ATELIER', 9, 18, 6, '#1B1F3B', 'bold', 'Georgia'), $txt('Artisanat · Casablanca', 9, 28, 2.6, '#6B6F86', 'normal', 'Georgia'), $txt('+212 6 00 00 00 00', 9, 44, 2.4, '#1B1F3B')]]]],
        ];
        foreach ($sets as $i => [$slug, $fr, $ar, $design]) {
            Template::create(['product_id' => $card?->id, 'slug' => $slug, 'name' => $this->t($fr, $ar), 'width_mm' => 85, 'height_mm' => 55, 'bleed_mm' => 3, 'safe_mm' => 4,
                'sides' => isset($design['back']) ? 2 : 1, 'design' => $design, 'thumbnail' => '/images/templates/'.$slug.'.svg', 'is_published' => true, 'is_demo' => true, 'sort' => $i]);
        }
        $fw = (148 + 6);
        Template::create(['product_id' => $flyer?->id, 'slug' => 'promo-soldes', 'name' => $this->t('Promo soldes', 'تخفيضات'), 'width_mm' => 148, 'height_mm' => 210, 'bleed_mm' => 3, 'safe_mm' => 5, 'sides' => 1,
            'design' => ['front' => ['version' => '5.3.0', 'background' => '#FFFFFF', 'objects' => [$rect(0, 0, $fw, 216, '#00A0E3'), $circle(40, 40, 50, '#FFD400', .95), $txt('-30 %', 25, 80, 22, '#1B1F3B', 'bold'), $txt('GRANDES SOLDES', 14, 140, 10, '#FFFFFF', 'bold'), $txt('Du 1er au 15 · Boutique Exemple', 14, 160, 5, '#FFFFFF')]]],
            'thumbnail' => '/images/templates/promo-soldes.svg', 'is_published' => true, 'is_demo' => true, 'sort' => 10]);
    }
}
