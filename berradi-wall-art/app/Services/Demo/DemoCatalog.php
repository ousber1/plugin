<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Core\Database;
use App\Helpers\Money;
use App\Services\SettingsService;
use App\Services\TranslationService;

/**
 * Optional demonstration catalogue (products, variants, collections, FAQ) with generated artwork.
 *
 *  - never installed automatically: installer checkbox or `php bin/console demo:install`
 *  - every demo product SKU starts with "DEMO-"; created ids are stored in the setting "demo_catalog"
 *  - `php bin/console demo:remove` deletes everything it created (rows, translations, image files)
 *  - no fake orders, customers, reviews or statistics are created
 *
 * Variant prices are derived from the seeded substrate price/m² and frame price/metre,
 * so they follow the real pricing configuration of the shop.
 */
final class DemoCatalog
{
    private const SETTING = 'demo_catalog';
    private string $font;

    public function __construct(private Database $db, private string $publicPath, string $basePath)
    {
        $this->font = $basePath . '/vendor/dompdf/dompdf/lib/fonts/DejaVuSerif-Italic.ttf';
    }

    public function isInstalled(): bool
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM products WHERE sku LIKE 'DEMO-%'") > 0;
    }

    /** @return array{products:int,variants:int,collections:int,faqs:int,images:int} */
    public function install(): array
    {
        if ($this->isInstalled()) {
            throw new \RuntimeException('The demo catalogue is already installed.');
        }
        $materials = [];
        foreach ($this->db->fetchAll('SELECT id, code, price_per_m2, minimum_price, cost_per_m2 FROM product_materials WHERE is_active = 1') as $m) {
            $materials[$m['code']] = $m;
        }
        $frames = [];
        foreach ($this->db->fetchAll('SELECT id, code, price_per_meter, fixed_price, cost_per_meter FROM frames WHERE is_active = 1') as $f) {
            $frames[$f['code']] = $f;
        }
        if (!isset($materials['canvas'], $materials['poster'], $materials['photo_paper'], $frames['black'])) {
            throw new \RuntimeException('Default print materials and frames must be seeded first.');
        }
        $categories = [];
        foreach ($this->db->fetchAll('SELECT id, slug FROM categories') as $c) {
            $categories[$c['slug']] = (int) $c['id'];
        }

        $created = ['products' => [], 'collections' => [], 'faqs' => [], 'categories_with_image' => [], 'files' => []];
        $counts = ['products' => 0, 'variants' => 0, 'collections' => 0, 'faqs' => 0, 'images' => 0];
        $dir = 'products/demo';

        $this->db->transaction(function (Database $db) use (&$created, &$counts, $materials, $frames, $categories, $dir): void {
            // ---------------------------------------------------- collections
            $collectionIds = [];
            foreach ($this->collections() as $i => $c) {
                $id = $db->insert('collections', [
                    'slug' => $c['slug'], 'name' => $c['fr'], 'description' => $c['desc_fr'],
                    'is_featured' => 1, 'is_limited' => $c['limited'] ? 1 : 0, 'sort_order' => $i + 1, 'is_active' => 1,
                ]);
                TranslationService::save('collection', $id, 'ar', ['name' => $c['ar'], 'description' => $c['desc_ar']]);
                $collectionIds[$c['key']] = $id;
                $created['collections'][] = $id;
                $counts['collections']++;
            }

            // ------------------------------------------------------- products
            $coverFor = [];
            foreach ($this->products() as $n => $p) {
                $seed = 1000 + $n;
                [$w, $h] = $p['landscape'] ?? false ? [1250, 1000] : [1000, 1250];
                $art = $this->render($p['art'], $w, $h, $seed);
                $file = sprintf('%s/%s.jpg', $dir, $p['slug']);
                $detail = sprintf('%s/%s-detail.jpg', $dir, $p['slug']);
                $art->save($this->publicPath . '/uploads/' . $file);
                $art->saveDetail($this->publicPath . '/uploads/' . $detail);
                $created['files'][] = $file;
                $created['files'][] = $detail;
                $counts['images'] += 2;

                $variants = $this->variantsFor($p['type'], $materials, $frames, $p['landscape'] ?? false);
                $min = min(array_map(fn ($v) => Money::toCents($v['price']), $variants));
                $promo = !empty($p['promo']);
                $sku = 'DEMO-' . strtoupper(substr(preg_replace('/[^a-z]/', '', $p['slug']), 0, 8)) . '-' . ($n + 1);
                $productId = $db->insert('products', [
                    'category_id' => $categories[$p['category']] ?? null,
                    'collection_id' => isset($p['collection']) ? $collectionIds[$p['collection']] : null,
                    'product_type' => $p['type'],
                    'title' => $p['fr'],
                    'slug' => $p['slug'],
                    'sku' => $sku,
                    'short_description' => $p['short_fr'],
                    'description' => $p['desc_fr'],
                    'main_image' => $file,
                    'base_price' => Money::fromCents($min),
                    'selling_price' => Money::fromCents($min),
                    'promo_price' => $promo ? Money::fromCents($this->round9(intdiv($min * 85, 100))) : null,
                    'promo_starts_at' => $promo ? date('Y-m-d H:i:s', time() - 86400) : null,
                    'promo_ends_at' => $promo ? date('Y-m-d 23:59:59', time() + 86400 * 30) : null,
                    'cost_price' => Money::fromCents(intdiv($min * 35, 100)),
                    'track_stock' => $p['type'] === 'limited' ? 1 : 0,
                    'stock_quantity' => $p['type'] === 'limited' ? 25 : 0,
                    'is_customizable' => !empty($p['customizable']) ? 1 : 0,
                    'min_panels' => $p['type'] === 'multi_panel' ? 3 : 1,
                    'max_panels' => $p['type'] === 'multi_panel' ? 3 : 1,
                    'is_featured' => !empty($p['featured']) ? 1 : 0,
                    'is_best_seller' => !empty($p['best']) ? 1 : 0,
                    'is_new' => !empty($p['new']) ? 1 : 0,
                    'is_active' => 1,
                    'sort_order' => $n,
                    'seo_title' => $p['fr'] . ' — ' . $this->typeLabel($p['type']),
                    'seo_description' => $p['short_fr'],
                ]);
                TranslationService::save('product', $productId, 'ar', [
                    'title' => $p['ar'], 'short_description' => $p['short_ar'], 'description' => $p['desc_ar'],
                ]);
                $db->insert('product_images', ['product_id' => $productId, 'path' => $file, 'alt' => $p['fr'], 'sort_order' => 0]);
                $db->insert('product_images', ['product_id' => $productId, 'path' => $detail, 'alt' => $p['fr'] . ' — détail', 'sort_order' => 1]);

                $vMaterials = $vFrames = [];
                foreach ($variants as $vi => $v) {
                    $db->insert('product_variants', [
                        'product_id' => $productId,
                        'sku' => $sku . '-' . $v['code'],
                        'name' => $v['name'],
                        'width_cm' => $v['w'],
                        'height_cm' => $v['h'],
                        'product_material_id' => (int) $materials[$v['material']]['id'],
                        'frame_id' => $v['frame'] !== null ? (int) $frames[$v['frame']]['id'] : null,
                        'panels' => $v['panels'],
                        'price' => $v['price'],
                        'promo_price' => $promo ? Money::fromCents($this->round9(intdiv(Money::toCents($v['price']) * 85, 100))) : null,
                        'cost_price' => $v['cost'],
                        'stock_quantity' => $p['type'] === 'limited' ? 25 : 0,
                        'is_default' => $vi === 0 ? 1 : 0,
                        'sort_order' => $vi,
                    ]);
                    $vMaterials[(int) $materials[$v['material']]['id']] = true;
                    if ($v['frame'] !== null) {
                        $vFrames[(int) $frames[$v['frame']]['id']] = true;
                    }
                    $counts['variants']++;
                }
                foreach (array_keys($vMaterials) as $mid) {
                    $db->query('INSERT IGNORE INTO product_material_map (product_id, product_material_id) VALUES (?, ?)', [$productId, $mid]);
                }
                foreach (array_keys($vFrames) as $fid) {
                    $db->query('INSERT IGNORE INTO product_frame_map (product_id, frame_id) VALUES (?, ?)', [$productId, $fid]);
                }
                $created['products'][] = $productId;
                $counts['products']++;
                if (isset($p['collection']) && !isset($coverFor[$p['collection']])) {
                    $coverFor[$p['collection']] = $p;
                    $coverFor[$p['collection']]['seed'] = $seed;
                }
                if (isset($categories[$p['category']]) && !isset($created['categories_with_image'][$p['category']])) {
                    $hasImage = (string) $db->value('SELECT image FROM categories WHERE id = ?', [$categories[$p['category']]]);
                    if ($hasImage === '') {
                        $db->update('categories', ['image' => $file], ['id' => $categories[$p['category']]]);
                        $created['categories_with_image'][$p['category']] = $categories[$p['category']];
                    }
                }
            }

            // collection covers (wide crops of the first artwork)
            foreach ($coverFor as $key => $p) {
                $cover = sprintf('%s/collection-%s.jpg', $dir, $key);
                $this->render($p['art'], 1600, 1000, $p['seed'])->save($this->publicPath . '/uploads/' . $cover);
                $db->update('collections', ['image' => $cover], ['id' => $collectionIds[$key]]);
                $created['files'][] = $cover;
                $counts['images']++;
            }

            // ------------------------------------------------------------ FAQ
            foreach ($this->faqs() as $i => [$qfr, $afr, $qar, $aar]) {
                $id = $db->insert('faqs', ['question' => $qfr, 'answer' => $afr, 'sort_order' => $i + 1, 'is_active' => 1]);
                TranslationService::save('faq', $id, 'ar', ['question' => $qar, 'answer' => $aar]);
                $created['faqs'][] = $id;
                $counts['faqs']++;
            }

            SettingsService::set(self::SETTING, $created, 'general', 'json');
        });
        return $counts;
    }

    /** Remove everything the demo created. @return array{products:int,collections:int,faqs:int,files:int} */
    public function remove(): array
    {
        $created = SettingsService::get(self::SETTING, []);
        $created = is_array($created) ? $created : [];
        $out = ['products' => 0, 'collections' => 0, 'faqs' => 0, 'files' => 0];
        $this->db->transaction(function (Database $db) use ($created, &$out): void {
            $ids = array_map('intval', array_column($db->fetchAll("SELECT id FROM products WHERE sku LIKE 'DEMO-%'"), 'id'));
            foreach ($ids as $id) {
                $db->query("DELETE FROM translations WHERE entity_type = 'product' AND entity_id = ?", [$id]);
                // order lines keep their snapshot (product_id is SET NULL by the FK)
                $db->delete('products', ['id' => $id]);
                $out['products']++;
            }
            foreach ((array) ($created['collections'] ?? []) as $id) {
                $db->query("DELETE FROM translations WHERE entity_type = 'collection' AND entity_id = ?", [(int) $id]);
                $out['collections'] += $db->delete('collections', ['id' => (int) $id]);
            }
            foreach ((array) ($created['faqs'] ?? []) as $id) {
                $db->query("DELETE FROM translations WHERE entity_type = 'faq' AND entity_id = ?", [(int) $id]);
                $out['faqs'] += $db->delete('faqs', ['id' => (int) $id]);
            }
            foreach ((array) ($created['categories_with_image'] ?? []) as $catId) {
                $db->query("UPDATE categories SET image = NULL WHERE id = ? AND image LIKE 'products/demo/%'", [(int) $catId]);
            }
            $db->query('DELETE FROM settings WHERE setting_key = ?', [self::SETTING]);
        });
        SettingsService::flush();
        foreach (glob($this->publicPath . '/uploads/products/demo/*.jpg') ?: [] as $f) {
            if (@unlink($f)) {
                $out['files']++;
            }
        }
        @rmdir($this->publicPath . '/uploads/products/demo');
        return $out;
    }

    // ----------------------------------------------------------------- data

    private function round9(int $cents): int
    {
        // psychological pricing: round up to the next ...9 dirhams
        $dh = (int) ceil($cents / 100);
        $dh = (int) (ceil(($dh + 1) / 10) * 10) - 1;
        return $dh * 100;
    }

    /**
     * @param array<string,array<string,mixed>> $materials
     * @param array<string,array<string,mixed>> $frames
     * @return list<array{code:string,name:string,w:string,h:string,material:string,frame:?string,panels:int,price:string,cost:string}>
     */
    private function variantsFor(string $type, array $materials, array $frames, bool $landscape): array
    {
        $sizes = match ($type) {
            'canvas' => [[30, 40], [40, 60], [60, 90], [70, 100]],
            'framed' => [[30, 40], [50, 70], [70, 100]],
            'poster', 'photo_print' => [[30, 40], [50, 70], [70, 100]],
            'multi_panel' => [[90, 40], [150, 70], [180, 90]],
            'limited' => [[60, 90], [100, 150]],
            default => [[40, 60]],
        };
        [$material, $frameCodes] = match ($type) {
            'framed' => ['photo_paper', ['black', 'white', 'gold', 'oak']],
            'poster' => ['poster', [null, 'black', 'white']],
            'photo_print' => ['photo_paper', [null, 'black']],
            default => ['canvas', [null]],
        };
        $panels = $type === 'multi_panel' ? 3 : 1;
        $out = [];
        foreach ($sizes as [$w, $h]) {
            if ($landscape && $type !== 'multi_panel') {
                [$w, $h] = [$h, $w];
            }
            foreach ($frameCodes as $fc) {
                $m = $materials[$material];
                $areaCm2 = $w * $h;
                $printCents = max(Money::toCents((string) $m['minimum_price']), intdiv(Money::toCents((string) $m['price_per_m2']) * $areaCm2, 10000));
                $costCents = intdiv(Money::toCents((string) $m['cost_per_m2']) * $areaCm2, 10000);
                if ($type === 'multi_panel') {
                    $printCents = intdiv($printCents * 115, 100); // 3 stretched panels
                }
                $frameCents = 0;
                if ($fc !== null && isset($frames[$fc])) {
                    $perimeterCm = 2 * ($w + $h);
                    $frameCents = Money::toCents((string) $frames[$fc]['fixed_price']) + intdiv(Money::toCents((string) $frames[$fc]['price_per_meter']) * $perimeterCm, 100);
                    $costCents += intdiv(Money::toCents((string) $frames[$fc]['cost_per_meter']) * $perimeterCm, 100);
                }
                $price = $this->round9($printCents + $frameCents);
                $sizeLabel = $w . '×' . $h;
                $frameLabel = $fc === null ? '' : ' · ' . ['black' => 'cadre noir', 'white' => 'cadre blanc', 'gold' => 'cadre doré', 'oak' => 'cadre chêne'][$fc];
                $out[] = [
                    'code' => $w . 'X' . $h . ($fc ? '-' . strtoupper(substr($fc, 0, 3)) : ''),
                    'name' => $sizeLabel . ' cm' . $frameLabel,
                    'w' => $w . '.0',
                    'h' => $h . '.0',
                    'material' => $material,
                    'frame' => $fc,
                    'panels' => $panels,
                    'price' => Money::fromCents($price),
                    'cost' => Money::fromCents($costCents),
                ];
            }
        }
        return $out;
    }

    private function render(array $art, int $w, int $h, int $seed): ArtGenerator
    {
        $g = new ArtGenerator($w, $h, $seed);
        $style = $art[0];
        $p = $art[1];
        return match ($style) {
            'arches' => $g->arches($p),
            'zellige' => $g->zellige($p),
            'landscape' => $g->landscape($p),
            'dunes' => $g->landscape($p, true),
            'bw' => $g->landscape($p, true)->monochrome(),
            'gold' => $g->abstractGold($p),
            'orbits' => $g->abstractGold($p, true),
            'minimal' => $g->minimal($p, 0),
            'minimal2' => $g->minimal($p, 1),
            'quote' => $g->quote($p, (string) $art[2], $this->font),
            'kids' => $g->kids($p),
            'photo' => $g->photoPlaceholder((string) $art[2], $this->font),
            default => throw new \InvalidArgumentException('Unknown art style ' . $style),
        };
    }

    private function typeLabel(string $type): string
    {
        return ['canvas' => 'Toile canvas', 'framed' => 'Tableau encadré', 'poster' => 'Poster', 'photo_print' => 'Tirage photo',
            'multi_panel' => 'Triptyque', 'limited' => 'Édition limitée'][$type] ?? 'Tableau';
    }

    /** @return list<array<string,mixed>> */
    private function collections(): array
    {
        return [
            ['key' => 'heritage', 'slug' => 'heritage-marocain', 'fr' => 'Héritage Marocain', 'ar' => 'التراث المغربي', 'limited' => false,
                'desc_fr' => 'Arches, zelliges et portes bleues : une ode à l’architecture et aux artisans du Royaume.',
                'desc_ar' => 'أقواس وزليج وأبواب زرقاء: تحية للعمارة المغربية وصناعها.'],
            ['key' => 'atlas', 'slug' => 'lumiere-atlas', 'fr' => 'Lumière d’Atlas', 'ar' => 'نور الأطلس', 'limited' => false,
                'desc_fr' => 'Des cimes de l’Atlas aux dunes de Merzouga, la lumière du Maroc en grand format.',
                'desc_ar' => 'من قمم الأطلس إلى كثبان مرزوكة، نور المغرب بمقاسات كبيرة.'],
            ['key' => 'noir-or', 'slug' => 'noir-et-or', 'fr' => 'Noir & Or', 'ar' => 'الأسود والذهبي', 'limited' => true,
                'desc_fr' => 'Compositions abstraites aux reflets dorés, signature de la maison. Tirages numérotés.',
                'desc_ar' => 'تراكيب تجريدية بلمسات ذهبية، توقيع الدار. نسخ مرقمة.'],
            ['key' => 'essentiels', 'slug' => 'essentiels-minimalistes', 'fr' => 'Essentiels Minimalistes', 'ar' => 'أساسيات بسيطة', 'limited' => false,
                'desc_fr' => 'Formes pures et palettes douces pour des intérieurs apaisés.',
                'desc_ar' => 'أشكال نقية وألوان هادئة لديكور مريح.'],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function products(): array
    {
        $d = fn (string $fr) => $fr . "\n\nImpression haute définition aux encres pigmentaires, montée et contrôlée à la main dans notre atelier. Livrée prête à accrocher.";
        $da = fn (string $ar) => $ar . "\n\nطباعة عالية الدقة بأحبار صبغية، تُركّب وتُراقب يدوياً في ورشتنا. تصلك جاهزة للتعليق.";
        return [
            ['slug' => 'arches-de-marrakech', 'fr' => 'Arches de Marrakech', 'ar' => 'أقواس مراكش', 'type' => 'canvas', 'category' => 'modern-abstract-art', 'collection' => 'heritage', 'featured' => true, 'best' => true,
                'art' => ['arches', ['#E9C9A8', '#D9A47E', '#B5563A', '#F3DCC2', '#F7E7D0']],
                'short_fr' => 'Les arches ocre des riads, sous un soleil d’après-midi.', 'short_ar' => 'أقواس الرياض المغرة تحت شمس العصر.',
                'desc_fr' => $d('Une composition chaleureuse inspirée des passages voûtés de la médina de Marrakech.'), 'desc_ar' => $da('تركيبة دافئة مستوحاة من ممرات مدينة مراكش العتيقة.')],
            ['slug' => 'zellige-de-fes', 'fr' => 'Zellige de Fès', 'ar' => 'زليج فاس', 'type' => 'canvas', 'category' => 'islamic-wall-art', 'collection' => 'heritage', 'best' => true,
                'art' => ['zellige', ['#F1ECE2', '#1F5C5A', '#C6A66B', '#2C3E66']],
                'short_fr' => 'Étoiles à huit branches et émeraude profonde, d’après les maîtres zelligeurs.', 'short_ar' => 'نجوم ثمانية وأخضر زمردي، على طريقة معلمي الزليج.',
                'desc_fr' => $d('Un motif géométrique hérité des médersas de Fès, réinterprété en toile contemporaine.'), 'desc_ar' => $da('زخرفة هندسية موروثة عن مدارس فاس، بقراءة معاصرة.')],
            ['slug' => 'porte-bleue-chefchaouen', 'fr' => 'Porte bleue de Chefchaouen', 'ar' => 'الباب الأزرق بشفشاون', 'type' => 'framed', 'category' => 'modern-abstract-art', 'collection' => 'heritage', 'new' => true,
                'art' => ['arches', ['#CFE0F0', '#9BBBE0', '#2F5DA8', '#DCE8F5', '#FFFFFF']],
                'short_fr' => 'Le bleu de la perle du Rif, encadré avec élégance.', 'short_ar' => 'أزرق جوهرة الريف في إطار أنيق.',
                'desc_fr' => $d('Une porte, des arches et ce bleu unique qui fait la réputation de Chefchaouen.'), 'desc_ar' => $da('باب وأقواس وذلك الأزرق الفريد الذي اشتهرت به شفشاون.')],
            ['slug' => 'etoile-andalouse-triptyque', 'fr' => 'Étoile andalouse — triptyque', 'ar' => 'النجمة الأندلسية — ثلاثية', 'type' => 'multi_panel', 'category' => 'multi-panel-wall-art', 'collection' => 'heritage', 'landscape' => true,
                'art' => ['zellige', ['#171717', '#C6A66B', '#F1ECE2', '#7C623A']],
                'short_fr' => 'Trois panneaux noir et or pour un mur spectaculaire.', 'short_ar' => 'ثلاث قطع بالأسود والذهبي لجدار مبهر.',
                'desc_fr' => $d('Un triptyque géométrique pensé pour les salons et les grands volumes.'), 'desc_ar' => $da('ثلاثية هندسية مصممة للصالونات والمساحات الكبيرة.')],
            ['slug' => 'aube-sur-atlas', 'fr' => 'Aube sur l’Atlas', 'ar' => 'فجر على الأطلس', 'type' => 'canvas', 'category' => 'nature-landscapes', 'collection' => 'atlas', 'best' => true, 'promo' => true, 'landscape' => true,
                'art' => ['landscape', ['#F6D7B8', '#F2B999', '#FFF1DC', '#9D7A8C', '#6D5873', '#43385A', '#272236']],
                'short_fr' => 'Les crêtes du Haut Atlas dans la douceur rosée du matin.', 'short_ar' => 'قمم الأطلس الكبير في نعومة الصباح الوردية.',
                'desc_fr' => $d('Des plans successifs de montagnes qui se fondent dans une lumière d’aube.'), 'desc_ar' => $da('سلاسل جبلية متتالية تذوب في ضوء الفجر.')],
            ['slug' => 'dunes-de-merzouga', 'fr' => 'Dunes de Merzouga', 'ar' => 'كثبان مرزوكة', 'type' => 'canvas', 'category' => 'nature-landscapes', 'collection' => 'atlas', 'featured' => true, 'landscape' => true,
                'art' => ['dunes', ['#F3C98B', '#EBA25E', '#FFF0C9', '#D98B4A', '#C06F35', '#9C5426']],
                'short_fr' => 'L’Erg Chebbi au coucher du soleil, tout en courbes.', 'short_ar' => 'عرق الشبي عند الغروب، منحنيات ساحرة.',
                'desc_fr' => $d('La chaleur du désert marocain et le dessin infini des dunes.'), 'desc_ar' => $da('دفء الصحراء المغربية ورسم الكثبان اللامتناهي.')],
            ['slug' => 'vallee-du-dades', 'fr' => 'Vallée du Dadès', 'ar' => 'وادي دادس', 'type' => 'framed', 'category' => 'nature-landscapes', 'collection' => 'atlas', 'new' => true,
                'art' => ['landscape', ['#D7E3E1', '#B7CDC9', '#FBF6EA', '#8FA79E', '#6B857B', '#4C6258', '#2E3D37']],
                'short_fr' => 'Les gorges du Dadès dans des verts minéraux apaisants.', 'short_ar' => 'مضايق دادس بألوان خضراء معدنية هادئة.',
                'desc_fr' => $d('Une vue panoramique et silencieuse, idéale pour un bureau ou une chambre.'), 'desc_ar' => $da('منظر بانورامي هادئ، مثالي للمكتب أو غرفة النوم.')],
            ['slug' => 'dunes-noir-et-blanc', 'fr' => 'Dunes en noir & blanc', 'ar' => 'كثبان بالأبيض والأسود', 'type' => 'photo_print', 'category' => 'black-white-photography', 'collection' => 'atlas', 'landscape' => true,
                'art' => ['bw', ['#DADADA', '#9A9A9A', '#FFFFFF', '#7A7A7A', '#4A4A4A', '#1E1E1E']],
                'short_fr' => 'Le désert réduit à la lumière et à l’ombre.', 'short_ar' => 'الصحراء مختزلة في الضوء والظل.',
                'desc_fr' => $d('Un tirage photographique contrasté au grain délicat.'), 'desc_ar' => $da('طباعة فوتوغرافية متباينة بحبيبات دقيقة.')],
            ['slug' => 'eclipse-doree', 'fr' => 'Éclipse dorée', 'ar' => 'الكسوف الذهبي', 'type' => 'limited', 'category' => 'modern-abstract-art', 'collection' => 'noir-or', 'featured' => true,
                'art' => ['gold', ['#141414', '#C6A66B', '#EDE3CF', '#2A2520']],
                'short_fr' => 'Édition limitée numérotée, 25 exemplaires.', 'short_ar' => 'إصدار محدود ومرقم، 25 نسخة.',
                'desc_fr' => $d('Un croissant d’or sur fond noir profond, signé et numéroté.'), 'desc_ar' => $da('هلال ذهبي على خلفية سوداء عميقة، موقّع ومرقّم.')],
            ['slug' => 'orbites', 'fr' => 'Orbites', 'ar' => 'مدارات', 'type' => 'canvas', 'category' => 'modern-abstract-art', 'collection' => 'noir-or', 'best' => true,
                'art' => ['orbits', ['#171717', '#C6A66B', '#F8F7F4', '#2B241B']],
                'short_fr' => 'Cercles concentriques et éclats d’or.', 'short_ar' => 'دوائر متحدة المركز ولمعات ذهبية.',
                'desc_fr' => $d('Une pièce graphique qui structure immédiatement un mur.'), 'desc_ar' => $da('قطعة غرافيكية تمنح الجدار هيكلة فورية.')],
            ['slug' => 'fil-d-or-triptyque', 'fr' => 'Fil d’or — triptyque', 'ar' => 'خيط الذهب — ثلاثية', 'type' => 'multi_panel', 'category' => 'multi-panel-wall-art', 'collection' => 'noir-or', 'promo' => true, 'landscape' => true,
                'art' => ['gold', ['#1C1C1C', '#D4B47A', '#F1ECE2', '#33291C']],
                'short_fr' => 'Trois toiles, une ligne dorée continue.', 'short_ar' => 'ثلاث لوحات وخط ذهبي متصل.',
                'desc_fr' => $d('Un triptyque élégant pour salon, entrée ou réception.'), 'desc_ar' => $da('ثلاثية أنيقة للصالون أو المدخل أو الاستقبال.')],
            ['slug' => 'equilibre', 'fr' => 'Équilibre', 'ar' => 'توازن', 'type' => 'poster', 'category' => 'minimalist-posters', 'collection' => 'essentiels', 'new' => true,
                'art' => ['minimal', ['#F3EEE6', '#C97B5A', '#E2C9A3', '#2F4A45']],
                'short_fr' => 'Demi-lune terracotta et soleil vert sauge.', 'short_ar' => 'نصف قمر بلون الطين وشمس بلون المريمية.',
                'desc_fr' => $d('Un poster mid-century aux tons naturels.'), 'desc_ar' => $da('ملصق بأسلوب منتصف القرن بألوان طبيعية.')],
            ['slug' => 'terracotta', 'fr' => 'Terracotta', 'ar' => 'تيراكوتا', 'type' => 'poster', 'category' => 'minimalist-posters', 'collection' => 'essentiels',
                'art' => ['minimal2', ['#EFE7DC', '#B8643F', '#E4B98E', '#1E3A37']],
                'short_fr' => 'Blocs de couleur chauds, composition apaisée.', 'short_ar' => 'كتل لونية دافئة وتركيبة هادئة.',
                'desc_fr' => $d('Parfait en duo avec « Équilibre ».'), 'desc_ar' => $da('مثالي مع ملصق « توازن ».')],
            ['slug' => 'crois-en-tes-reves', 'fr' => 'Crois en tes rêves', 'ar' => 'آمن بأحلامك', 'type' => 'poster', 'category' => 'motivational-quotes', 'collection' => 'essentiels', 'new' => true,
                'art' => ['quote', ['#F8F7F4', '#171717', '#C6A66B'], "Crois en\ntes rêves"],
                'short_fr' => 'Une citation pour bien commencer chaque journée.', 'short_ar' => 'اقتباس لبداية جميلة لكل يوم.',
                'desc_fr' => $d('Typographie serif élégante sur papier mat.'), 'desc_ar' => $da('خط أنيق على ورق مطفي.')],
            ['slug' => 'le-voyage-commence-ici', 'fr' => 'Le voyage commence ici', 'ar' => 'الرحلة تبدأ هنا', 'type' => 'poster', 'category' => 'motivational-quotes',
                'art' => ['quote', ['#171717', '#F1ECE2', '#C6A66B'], "Le voyage\ncommence\nici"],
                'short_fr' => 'Pour une entrée, un bureau ou une chambre d’ado.', 'short_ar' => 'للمدخل أو المكتب أو غرفة المراهق.',
                'desc_fr' => $d('Lettrage clair sur fond noir profond.'), 'desc_ar' => $da('حروف فاتحة على خلفية سوداء عميقة.')],
            ['slug' => 'ballons-arc-en-ciel', 'fr' => 'Ballons & arc-en-ciel', 'ar' => 'بالونات وقوس قزح', 'type' => 'poster', 'category' => 'baby-kids', 'new' => true,
                'art' => ['kids', ['#FBF6EE', '#E7A79B', '#F2C98F', '#A9C6B8', '#7E9CC0']],
                'short_fr' => 'Douceur pastel pour chambre d’enfant.', 'short_ar' => 'ألوان باستيل ناعمة لغرفة الطفل.',
                'desc_fr' => $d('Un poster tendre aux couleurs poudrées.'), 'desc_ar' => $da('ملصق رقيق بألوان هادئة.')],
            ['slug' => 'toile-photo-personnalisee', 'fr' => 'Toile photo personnalisée', 'ar' => 'لوحة كانفاس بصورتك', 'type' => 'canvas', 'category' => 'personalized-gifts', 'customizable' => true, 'featured' => true, 'best' => true,
                'art' => ['photo', [], 'Votre photo ici'],
                'short_fr' => 'Votre photo imprimée sur toile tendue sur châssis bois.', 'short_ar' => 'صورتك مطبوعة على كانفاس مشدود على إطار خشبي.',
                'desc_fr' => $d('Envoyez-nous votre plus beau souvenir : nous vérifions la qualité et vous envoyons un aperçu avant impression.'), 'desc_ar' => $da('أرسل لنا أجمل ذكرياتك: نتحقق من الجودة ونرسل لك معاينة قبل الطباعة.')],
            ['slug' => 'portrait-de-famille-encadre', 'fr' => 'Portrait de famille encadré', 'ar' => 'صورة عائلية مؤطرة', 'type' => 'framed', 'category' => 'family-portraits', 'customizable' => true,
                'art' => ['photo', [], 'Votre famille'],
                'short_fr' => 'Votre photo de famille sur papier photo premium, cadre au choix.', 'short_ar' => 'صورتكم العائلية على ورق فوتوغرافي فاخر مع إطار حسب اختيارك.',
                'desc_fr' => $d('Le cadeau idéal pour une fête, un anniversaire ou une crémaillère.'), 'desc_ar' => $da('الهدية المثالية للأعياد وأعياد الميلاد.')],
            ['slug' => 'souvenir-de-mariage', 'fr' => 'Souvenir de mariage', 'ar' => 'ذكرى الزفاف', 'type' => 'canvas', 'category' => 'couple-wedding', 'customizable' => true, 'new' => true,
                'art' => ['photo', [], 'Votre plus beau jour'],
                'short_fr' => 'Le plus beau jour de votre vie, en grand format.', 'short_ar' => 'أجمل يوم في حياتكم، بمقاس كبير.',
                'desc_fr' => $d('Toile premium pour immortaliser votre mariage.'), 'desc_ar' => $da('كانفاس فاخر لتخليد يوم زفافكم.')],
        ];
    }

    /** @return list<array{0:string,1:string,2:string,3:string}> */
    private function faqs(): array
    {
        return [
            ['Quelle qualité de photo faut-il pour un tableau personnalisé ?',
                'Une photo prise avec un smartphone récent suffit pour la plupart des formats. Nous vérifions systématiquement la résolution et vous prévenons si l’image risque d’être floue au format choisi.',
                'ما الجودة المطلوبة للصورة؟', 'صورة بهاتف حديث تكفي لأغلب المقاسات. نتحقق دائماً من الدقة وننبهك إذا كانت الصورة قد تبدو غير واضحة بالمقاس المختار.'],
            ['Quels sont les délais de fabrication et de livraison ?',
                'Chaque œuvre est produite à la commande. Comptez quelques jours ouvrés de production, puis la livraison selon votre ville. Le délai estimé est affiché sur chaque produit.',
                'ما هي آجال الإنتاج والتوصيل؟', 'يُنتج كل عمل حسب الطلب. احتسب بضعة أيام عمل للإنتاج ثم التوصيل حسب مدينتك. المدة التقديرية معروضة في كل منتج.'],
            ['Livrez-vous partout au Maroc ?',
                'Oui, nous livrons dans toutes les villes du Maroc. Les frais dépendent de votre ville et la livraison est offerte au-delà d’un certain montant.',
                'هل توصلون إلى جميع أنحاء المغرب؟', 'نعم، نوصل إلى جميع المدن المغربية. تختلف الرسوم حسب المدينة والتوصيل مجاني فوق مبلغ معين.'],
            ['Quels moyens de paiement acceptez-vous ?',
                'Paiement à la livraison, virement bancaire ou paiement manuel confirmé par notre équipe.',
                'ما هي طرق الأداء المقبولة؟', 'الدفع عند الاستلام، التحويل البنكي أو الأداء اليدوي المؤكد من طرف فريقنا.'],
            ['Puis-je voir un aperçu avant l’impression ?',
                'Oui. Pour les créations personnalisées, nous vous envoyons un aperçu à valider avant de lancer la production.',
                'هل يمكنني رؤية معاينة قبل الطباعة؟', 'نعم. بالنسبة للإبداعات المخصصة، نرسل لك معاينة للموافقة عليها قبل الإنتاج.'],
            ['Comment accrocher mon tableau ?',
                'Les toiles sont livrées prêtes à accrocher. Un simple clou ou une vis suffit pour les formats moyens ; nous recommandons deux points de fixation pour les grands formats.',
                'كيف أعلّق اللوحة؟', 'تصل اللوحات جاهزة للتعليق. يكفي مسمار أو برغي للمقاسات المتوسطة، وننصح بنقطتي تثبيت للمقاسات الكبيرة.'],
        ];
    }
}
