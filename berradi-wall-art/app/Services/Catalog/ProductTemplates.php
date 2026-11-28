<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Core\Database;
use App\Helpers\Money;
use App\Services\ActivityLogger;
use App\Services\Admin\CatalogAdmin;
use App\Services\TranslationService;

/**
 * Ready-made product configurations for a print shop: personalised gifts (sublimation, textiles, stationery) and
 * CO2-laser products (engraving / cutting, sized for a 40 × 40 cm bed). One click creates the product with its
 * texts (FR/AR), options, personalisation fields, quantity price breaks and bill of materials.
 *
 * Products are created HIDDEN with indicative prices: the owner adds real photos, checks prices / costs, then
 * activates them. Nothing is created automatically.
 */
final class ProductTemplates
{
    /** Field helper: [type, key, label FR, label AR, required, max length, choices, extra price, print w, print h, placeholder FR] */
    private static function f(string $type, string $key, string $fr, string $ar, bool $req = false, ?int $max = null, array $choices = [], string $price = '0', ?float $pw = null, ?float $ph = null, string $ph_fr = ''): array
    {
        return compact('type', 'key', 'fr', 'ar', 'req', 'max', 'choices', 'price', 'pw', 'ph', 'ph_fr');
    }

    /** @return array<string,array<string,mixed>> */
    public static function all(): array
    {
        $font = fn (bool $req = false) => self::f('font', 'font', 'Police', 'نوع الخط', $req);
        $name = fn (int $max = 30, bool $req = true, string $price = '0') => self::f('text', 'name', 'Prénom / texte à graver', 'الاسم / النص المراد نقشه', $req, $max, [], $price, null, null, 'Ex. Sara & Youssef');
        $giftTiers = [[10, 5], [25, 10], [50, 15], [100, 20]];
        $laserTiers = [[10, 10], [25, 15], [50, 20], [100, 25]];
        return [
            // ------------------------------------------------------------------ personalised gifts (sublimation / print)
            'mug_ceramic' => [
                'family' => 'gift', 'type' => 'gift_print', 'category' => 'mugs-bottles', 'sku' => 'MUG-PHOTO', 'price' => '79.00',
                'title' => ['Mug photo personnalisé', 'كوب بصورة مخصصة'],
                'short' => ['Mug céramique blanc 325 ml imprimé avec votre photo et votre texte, compatible lave-vaisselle.', 'كوب سيراميك أبيض 325 مل مطبوع بصورتك ونصك، يتحمل غسالة الأواني.'],
                'desc' => ["Impression par sublimation sur mug céramique blanc de qualité AAA (325 ml).\nCouleurs éclatantes, résistantes au lave-vaisselle et au micro-ondes.\nIdéal pour un anniversaire, la fête des mères, un cadeau d'entreprise.", "طباعة بتقنية التسامي على كوب سيراميك أبيض عالي الجودة (325 مل).\nألوان زاهية تتحمل غسالة الأواني والميكروويف.\nمثالي لعيد الميلاد أو عيد الأم أو كهدية للشركات."],
                'options' => [['Modèle', 'النموذج'], null], 'variants' => [['Blanc classique', null, '79.00'], ['Intérieur & anse colorés', null, '89.00']],
                'fields' => [self::f('photo', 'photo', 'Votre photo', 'صورتك', true, null, [], '0', 20, 8.5), self::f('text', 'text', 'Texte (facultatif)', 'نص (اختياري)', false, 40, [], '0', null, null, 'Ex. Meilleure maman du monde'), $font()],
                'tiers' => $giftTiers, 'components' => [['BLANK-MUG-325', 1], ['SUBLI-PAPER-A4', 1]], 'proof' => true, 'max' => 500,
            ],
            'mug_magic' => [
                'family' => 'gift', 'type' => 'gift_print', 'category' => 'mugs-bottles', 'sku' => 'MUG-MAGIC', 'price' => '99.00',
                'title' => ['Mug magique personnalisé', 'كوب سحري مخصص'],
                'short' => ['Noir à froid, votre photo apparaît avec une boisson chaude.', 'أسود وهو بارد، وتظهر صورتك مع المشروب الساخن.'],
                'desc' => ["Mug thermo-réactif 325 ml : la surface noire révèle votre photo dès qu'on y verse une boisson chaude.\nLavage à la main recommandé.", "كوب حراري 325 مل: يكشف السطح الأسود صورتك بمجرد صب مشروب ساخن.\nيُنصح بالغسل اليدوي."],
                'options' => [null, null], 'variants' => [],
                'fields' => [self::f('photo', 'photo', 'Votre photo', 'صورتك', true, null, [], '0', 20, 8.5), self::f('text', 'text', 'Texte (facultatif)', 'نص (اختياري)', false, 40)],
                'tiers' => $giftTiers, 'components' => [['BLANK-MUG-MAGIC', 1], ['SUBLI-PAPER-A4', 1]], 'proof' => true, 'max' => 300,
            ],
            'bottle_steel' => [
                'family' => 'gift', 'type' => 'gift_print', 'category' => 'mugs-bottles', 'sku' => 'GOURDE', 'price' => '129.00',
                'title' => ['Gourde inox personnalisée', 'قنينة ستانلس مخصصة'],
                'short' => ['Gourde isotherme 500 ml avec prénom, logo ou photo.', 'قنينة عازلة 500 مل بالاسم أو الشعار أو الصورة.'],
                'desc' => ["Gourde en acier inoxydable 500 ml, double paroi : boissons fraîches 24 h, chaudes 12 h.\nPersonnalisation par sublimation.", "قنينة من الفولاذ المقاوم للصدأ 500 مل بجدار مزدوج: تحافظ على البرودة 24 ساعة والحرارة 12 ساعة.\nتخصيص بتقنية التسامي."],
                'options' => [null, null], 'variants' => [],
                'fields' => [$name(25, true), $font(), self::f('photo', 'photo', 'Logo ou photo (facultatif)', 'شعار أو صورة (اختياري)', false, null, [], '0', 18, 7)],
                'tiers' => $giftTiers, 'components' => [['BLANK-BOTTLE-500', 1], ['SUBLI-PAPER-A4', 1]], 'proof' => true, 'max' => 500,
            ],
            'tshirt_photo' => [
                'family' => 'gift', 'type' => 'textile', 'category' => 'custom-textile', 'sku' => 'TSHIRT', 'price' => '119.00',
                'title' => ['T-shirt personnalisé', 'تيشيرت مخصص'],
                'short' => ['T-shirt blanc imprimé avec votre photo, logo ou texte.', 'تيشيرت أبيض مطبوع بصورتك أو شعارك أو نصك.'],
                'desc' => ["T-shirt blanc, impression haute définition qui ne craque pas au lavage.\nParfait pour les événements, associations, équipes et cadeaux.", "تيشيرت أبيض بطباعة عالية الدقة لا تتشقق مع الغسل.\nمثالي للمناسبات والجمعيات والفرق والهدايا."],
                'options' => [['Taille', 'المقاس'], null], 'variants' => [['S', null, '119.00'], ['M', null, '119.00'], ['L', null, '119.00'], ['XL', null, '119.00'], ['XXL', null, '129.00']],
                'fields' => [self::f('photo', 'photo', 'Image du devant', 'صورة الواجهة', true, null, [], '0', 25, 30), self::f('text', 'text', 'Texte (facultatif)', 'نص (اختياري)', false, 40), self::f('photo', 'back', 'Image du dos (facultatif)', 'صورة الظهر (اختياري)', false, null, [], '30', 25, 30)],
                'tiers' => $giftTiers, 'components' => [['BLANK-TSHIRT-WHITE', 1], ['SUBLI-PAPER-A3', 1]], 'proof' => true, 'max' => 1000,
            ],
            'cushion_photo' => [
                'family' => 'gift', 'type' => 'gift_print', 'category' => 'photo-gifts', 'sku' => 'COUSSIN', 'price' => '139.00',
                'title' => ['Coussin photo personnalisé', 'وسادة بصورة مخصصة'],
                'short' => ['Coussin 40 × 40 cm avec votre photo, garnissage inclus.', 'وسادة 40 × 40 سم بصورتك مع الحشوة.'],
                'desc' => ["Housse douce au toucher, fermeture éclair, garnissage inclus.\nUn cadeau chaleureux pour la maison.", "غطاء ناعم بسحاب مع الحشوة.\nهدية دافئة للمنزل."],
                'options' => [null, null], 'variants' => [],
                'fields' => [self::f('photo', 'photo', 'Votre photo', 'صورتك', true, null, [], '0', 38, 38), self::f('text', 'text', 'Texte (facultatif)', 'نص (اختياري)', false, 40)],
                'tiers' => $giftTiers, 'components' => [['BLANK-CUSHION-40', 1], ['SUBLI-PAPER-A3', 2]], 'proof' => true, 'max' => 200,
            ],
            'puzzle_photo' => [
                'family' => 'gift', 'type' => 'gift_print', 'category' => 'photo-gifts', 'sku' => 'PUZZLE', 'price' => '109.00',
                'title' => ['Puzzle photo personnalisé', 'لغز بصورة مخصصة'],
                'short' => ['Puzzle A4 de 120 pièces avec votre photo, en boîte.', 'لغز A4 من 120 قطعة بصورتك في علبة.'],
                'desc' => ["Puzzle A4 (21 × 29,7 cm), 120 pièces, livré dans une boîte.\nUn cadeau ludique pour petits et grands.", "لغز بمقاس A4 (21 × 29.7 سم)، 120 قطعة، يسلم في علبة.\nهدية ممتعة للكبار والصغار."],
                'options' => [null, null], 'variants' => [],
                'fields' => [self::f('photo', 'photo', 'Votre photo', 'صورتك', true, null, [], '0', 28, 19)],
                'tiers' => $giftTiers, 'components' => [['BLANK-PUZZLE-A4', 1], ['SUBLI-PAPER-A4', 1]], 'proof' => true, 'max' => 200,
            ],
            'notebook_a5' => [
                'family' => 'gift', 'type' => 'stationery', 'category' => 'notebooks-stationery', 'sku' => 'CARNET-A5', 'price' => '89.00',
                'title' => ['Carnet A5 personnalisé', 'دفتر A5 مخصص'],
                'short' => ['Carnet A5 avec couverture à votre nom, photo ou logo.', 'دفتر A5 بغلاف يحمل اسمك أو صورتك أو شعارك.'],
                'desc' => ["Carnet A5, 96 feuilles 90 g, couverture rigide personnalisée.\nPour l'école, le bureau, un séminaire ou un cadeau.", "دفتر A5، 96 ورقة 90 غ، بغلاف مقوى مخصص.\nللمدرسة أو المكتب أو الندوات أو كهدية."],
                'options' => [['Pages', 'الصفحات'], null], 'variants' => [['Lignées', null, '89.00'], ['Pointillés', null, '89.00'], ['Blanches', null, '89.00']],
                'fields' => [$name(30, true), $font(), self::f('photo', 'cover', 'Photo ou logo de couverture (facultatif)', 'صورة أو شعار للغلاف (اختياري)', false, null, [], '0', 14, 20)],
                'tiers' => $giftTiers, 'components' => [['BLANK-NOTEBOOK-A5', 1], ['SUBLI-PAPER-A4', 1]], 'proof' => true, 'max' => 1000,
            ],

            // ------------------------------------------------------------------ laser CO2 (engraving & cutting) — bed 40 × 40 cm
            'keychain_wood' => [
                'family' => 'laser', 'type' => 'laser_engraving', 'category' => 'keychains-accessories', 'sku' => 'PORTE-CLES-BOIS', 'price' => '35.00',
                'title' => ['Porte-clés en bois gravé', 'ميدالية خشبية منقوشة'],
                'short' => ['Porte-clés en bois gravé au laser avec un prénom, une date ou un logo.', 'ميدالية خشبية منقوشة بالليزر باسم أو تاريخ أو شعار.'],
                'desc' => ["Bois de peuplier 3 mm gravé au laser, anneau métallique.\nCadeau d'invités pour mariages, baptêmes, entreprises (prix dégressifs).", "خشب الحور 3 مم منقوش بالليزر مع حلقة معدنية.\nهدية للضيوف في الأعراس والعقيقة والشركات (أسعار تنازلية)."],
                'options' => [['Forme', 'الشكل'], null], 'variants' => [['Rond', null, '35.00', 5, 5], ['Rectangle', null, '35.00', 6, 3.5], ['Cœur', null, '35.00', 5, 5]],
                'fields' => [$name(15, true), $font(), self::f('text', 'back', 'Texte au dos (facultatif)', 'نص على الظهر (اختياري)', false, 20, [], '5')],
                'tiers' => $laserTiers, 'components' => [['WOOD-PLY3-4040', 0.02], ['BLANK-KEYRING', 1]], 'proof' => false, 'min' => 1, 'max' => 2000,
            ],
            'cutting_board' => [
                'family' => 'laser', 'type' => 'laser_engraving', 'category' => 'engraved-wood', 'sku' => 'PLANCHE-GRAVEE', 'price' => '179.00',
                'title' => ['Planche à découper gravée', 'لوح تقطيع منقوش'],
                'short' => ['Planche en hêtre massif gravée avec votre nom, une recette ou un message.', 'لوح من خشب الزان منقوش باسمك أو وصفة أو رسالة.'],
                'desc' => ["Hêtre massif, gravure laser profonde et alimentaire (sans encre).\nCadeau de mariage, crémaillère, fête des mères.", "خشب الزان الصلب بنقش ليزر عميق وآمن للطعام (بدون حبر).\nهدية للزفاف أو المنزل الجديد أو عيد الأم."],
                'options' => [['Taille', 'المقاس'], null], 'variants' => [['30 × 20 cm', null, '179.00', 30, 20], ['38 × 25 cm', null, '229.00', 38, 25]],
                'fields' => [$name(30, true), self::f('textarea', 'message', 'Message ou date (facultatif)', 'رسالة أو تاريخ (اختياري)', false, 120), $font()],
                'tiers' => [[10, 5], [25, 10]], 'components' => [['WOOD-BOARD-BEECH', 1]], 'proof' => true, 'max' => 200,
            ],
            'wood_box' => [
                'family' => 'laser', 'type' => 'laser_engraving', 'category' => 'engraved-wood', 'sku' => 'BOITE-BOIS', 'price' => '199.00',
                'title' => ['Boîte en bois gravée', 'علبة خشبية منقوشة'],
                'short' => ['Boîte à souvenirs, à montre ou à bijoux gravée à vos noms.', 'علبة للذكريات أو الساعة أو المجوهرات منقوشة بأسمائكم.'],
                'desc' => ["Boîte en bois avec couvercle gravé au laser.\nPour une demande en mariage, une naissance ou un témoin.", "علبة خشبية بغطاء منقوش بالليزر.\nلطلب الزواج أو المولود أو كهدية للشاهد."],
                'options' => [['Taille', 'المقاس'], null], 'variants' => [['20 × 15 cm', null, '199.00', 20, 15], ['30 × 20 cm', null, '259.00', 30, 20]],
                'fields' => [$name(40, true), self::f('date', 'date', 'Date (facultatif)', 'التاريخ (اختياري)'), $font()],
                'tiers' => [[10, 5], [25, 10]], 'components' => [], 'proof' => true, 'max' => 200,
            ],
            'acrylic_led_lamp' => [
                'family' => 'laser', 'type' => 'laser_engraving', 'category' => 'acrylic-led-lamps', 'sku' => 'LAMPE-LED', 'price' => '249.00',
                'title' => ['Lampe LED acrylique personnalisée', 'مصباح LED أكريليك مخصص'],
                'short' => ['Votre photo ou votre texte gravé dans l’acrylique, illuminé par un socle LED.', 'صورتك أو نصك منقوش على الأكريليك ومضاء بقاعدة LED.'],
                'desc' => ["Plaque acrylique transparente 3 mm gravée au laser, socle LED (USB).\nUne veilleuse unique pour une chambre, un couple, un enfant.", "لوح أكريليك شفاف 3 مم منقوش بالليزر مع قاعدة LED (USB).\nضوء ليلي فريد لغرفة أو زوجين أو طفل."],
                'options' => [['Socle', 'القاعدة'], null], 'variants' => [['Lumière blanche chaude', null, '249.00', 20, 25], ['7 couleurs + télécommande', null, '289.00', 20, 25]],
                'fields' => [self::f('photo', 'photo', 'Photo (bien contrastée)', 'صورة (واضحة التباين)', false, null, [], '0', 18, 22), $name(30, false), $font()],
                'tiers' => [[10, 5], [25, 10]], 'components' => [['ACRYL-CLEAR3-4040', 0.35], ['LED-BASE', 1]], 'proof' => true, 'max' => 100,
            ],
            'wood_photo_frame' => [
                'family' => 'laser', 'type' => 'laser_engraving', 'category' => 'engraved-wood', 'sku' => 'CADRE-BOIS-GRAVE', 'price' => '169.00',
                'title' => ['Cadre photo en bois gravé', 'إطار صور خشبي منقوش'],
                'short' => ['Cadre en bois avec prénoms et date gravés, pour photo 10 × 15.', 'إطار خشبي بأسماء وتاريخ منقوشين لصورة 10 × 15.'],
                'desc' => ["Cadre en contreplaqué gravé au laser, pied chevalet.\nVous gardez votre propre photo ou nous l'imprimons.", "إطار من الخشب الرقائقي منقوش بالليزر مع حامل.\nتضع صورتك أو نطبعها لك."],
                'options' => [['Photo', 'الصورة'], null], 'variants' => [['Sans impression', null, '169.00', 22, 17], ['Avec impression de la photo', null, '189.00', 22, 17]],
                'fields' => [$name(40, true), self::f('date', 'date', 'Date (facultatif)', 'التاريخ (اختياري)'), $font(), self::f('photo', 'photo', 'Photo à imprimer (si choisi)', 'الصورة للطباعة (إن اخترت ذلك)', false, null, [], '0', 15, 10)],
                'tiers' => [[10, 5], [25, 10]], 'components' => [['WOOD-PLY3-4040', 0.3]], 'proof' => true, 'max' => 200,
            ],
            'name_sign_wood' => [
                'family' => 'laser', 'type' => 'laser_cut', 'category' => 'name-signs', 'sku' => 'PRENOM-BOIS', 'price' => '149.00',
                'title' => ['Prénom en bois découpé', 'اسم مقصوص من الخشب'],
                'short' => ['Prénom découpé au laser pour chambre d’enfant, porte ou décoration.', 'اسم مقصوص بالليزر لغرفة الطفل أو الباب أو الديكور.'],
                'desc' => ["Découpe laser en MDF ou contreplaqué 3 mm, lettres attachées.\nLongueur maximale 38 cm (au-delà, réalisé en plusieurs parties).", "قص بالليزر من MDF أو الخشب الرقائقي 3 مم بحروف متصلة.\nأقصى طول 38 سم (أكثر من ذلك يُنجز على عدة أجزاء)."],
                'options' => [['Matière', 'المادة'], ['Hauteur', 'الارتفاع']],
                'variants' => [['MDF brut', '10 cm', '149.00', 38, 10], ['MDF brut', '15 cm', '189.00', 38, 15], ['Contreplaqué', '10 cm', '169.00', 38, 10], ['Contreplaqué', '15 cm', '209.00', 38, 15]],
                'fields' => [self::f('text', 'name', 'Prénom', 'الاسم', true, 12, [], '0', null, null, 'Ex. Yasmine'), $font(true)],
                'tiers' => [[5, 5], [10, 10]], 'components' => [['WOOD-MDF3-4040', 0.3]], 'proof' => true, 'max' => 100,
            ],
            'wall_decor_wood' => [
                'family' => 'laser', 'type' => 'laser_cut', 'category' => 'laser-wall-decor', 'sku' => 'DECO-MURALE-BOIS', 'price' => '249.00',
                'title' => ['Décoration murale en bois découpé', 'ديكور جداري خشبي مقصوص'],
                'short' => ['Mandala, carte du Maroc ou calligraphie découpés au laser.', 'ماندالا أو خريطة المغرب أو خط عربي مقصوص بالليزر.'],
                'desc' => ["Pièce découpée au laser dans du MDF 3 mm, prête à accrocher (38 × 38 cm).\nPersonnalisable avec un nom ou une phrase.", "قطعة مقصوصة بالليزر من MDF 3 مم جاهزة للتعليق (38 × 38 سم).\nقابلة للتخصيص باسم أو عبارة."],
                'options' => [['Motif', 'الزخرفة'], null], 'variants' => [['Mandala', null, '249.00', 38, 38], ['Carte du Maroc', null, '269.00', 38, 38], ['Calligraphie « Bismillah »', null, '269.00', 38, 38]],
                'fields' => [self::f('text', 'text', 'Nom ou phrase à intégrer (facultatif)', 'اسم أو عبارة للإدراج (اختياري)', false, 25, [], '20')],
                'tiers' => [[5, 5], [10, 10]], 'components' => [['WOOD-MDF3-4040', 1]], 'proof' => false, 'max' => 50,
            ],
            'coasters_wood' => [
                'family' => 'laser', 'type' => 'laser_engraving', 'category' => 'engraved-wood', 'sku' => 'SOUS-VERRES', 'price' => '129.00',
                'title' => ['Sous-verres gravés (lot de 4)', 'قواعد أكواب منقوشة (4 قطع)'],
                'short' => ['4 sous-verres gravés avec vos initiales, un logo ou un motif.', '4 قواعد أكواب منقوشة بحروفكم الأولى أو شعار أو زخرفة.'],
                'desc' => ["Lot de 4 sous-verres ronds 10 cm gravés au laser.\nPour la maison, un café, un restaurant (logo).", "4 قواعد دائرية 10 سم منقوشة بالليزر.\nللمنزل أو المقهى أو المطعم (بالشعار)."],
                'options' => [['Matière', 'المادة'], null], 'variants' => [['Bois', null, '129.00', 10, 10], ['Liège', null, '119.00', 10, 10]],
                'fields' => [self::f('text', 'text', 'Initiales ou texte', 'الحروف الأولى أو النص', true, 20), $font(), self::f('photo', 'logo', 'Logo (facultatif)', 'الشعار (اختياري)', false)],
                'tiers' => [[5, 5], [10, 10], [25, 15]], 'components' => [['WOOD-PLY3-4040', 0.3]], 'proof' => true, 'max' => 500,
            ],
            'leather_keychain' => [
                'family' => 'laser', 'type' => 'laser_engraving', 'category' => 'engraved-leather', 'sku' => 'PORTE-CLES-CUIR', 'price' => '59.00',
                'title' => ['Porte-clés en cuir gravé', 'ميدالية جلدية منقوشة'],
                'short' => ['Cuir naturel gravé à vos initiales ou à votre logo.', 'جلد طبيعي منقوش بحروفك الأولى أو شعارك.'],
                'desc' => ["Cuir naturel découpé et gravé au laser, anneau métallique.\nCadeau d'entreprise élégant (prix dégressifs).", "جلد طبيعي مقصوص ومنقوش بالليزر مع حلقة معدنية.\nهدية أنيقة للشركات (أسعار تنازلية)."],
                'options' => [null, null], 'variants' => [],
                'fields' => [self::f('text', 'text', 'Initiales ou prénom', 'الحروف الأولى أو الاسم', true, 12), $font()],
                'tiers' => $laserTiers, 'components' => [['LEATHER-NATURAL', 0.003], ['BLANK-KEYRING', 1]], 'proof' => false, 'max' => 2000,
            ],
            'bookmark_wood' => [
                'family' => 'laser', 'type' => 'laser_engraving', 'category' => 'engraved-wood', 'sku' => 'MARQUE-PAGE', 'price' => '29.00',
                'title' => ['Marque-page en bois gravé', 'فاصل كتب خشبي منقوش'],
                'short' => ['Marque-page fin en bois gravé avec un prénom ou une citation.', 'فاصل كتب خشبي رفيع منقوش باسم أو اقتباس.'],
                'desc' => ["Bois 2 mm gravé au laser, 15 × 4 cm.\nCadeau de fin d'année pour élèves et enseignants.", "خشب 2 مم منقوش بالليزر، 15 × 4 سم.\nهدية آخر السنة للتلاميذ والأساتذة."],
                'options' => [null, null], 'variants' => [],
                'fields' => [self::f('text', 'text', 'Prénom ou citation', 'الاسم أو الاقتباس', true, 40), $font()],
                'tiers' => $laserTiers, 'components' => [['WOOD-PLY3-4040', 0.04]], 'proof' => false, 'max' => 2000,
            ],
            'acrylic_sign' => [
                'family' => 'laser', 'type' => 'laser_cut', 'category' => 'name-signs', 'sku' => 'PLAQUE-ACRYLIQUE', 'price' => '299.00',
                'title' => ['Plaque professionnelle en acrylique', 'لوحة مهنية من الأكريليك'],
                'short' => ['Plaque de cabinet, bureau ou boutique gravée, avec entretoises.', 'لوحة للعيادة أو المكتب أو المتجر منقوشة مع حوامل التثبيت.'],
                'desc' => ["Acrylique transparent 3 mm découpé et gravé au laser, livré avec entretoises inox.\nNom, profession, horaires, logo.", "أكريليك شفاف 3 مم مقصوص ومنقوش بالليزر مع حوامل من الستانلس.\nالاسم والمهنة وأوقات العمل والشعار."],
                'options' => [['Format', 'المقاس'], null], 'variants' => [['30 × 20 cm', null, '299.00', 30, 20], ['40 × 30 cm', null, '399.00', 40, 30]],
                'fields' => [self::f('textarea', 'text', 'Texte de la plaque', 'نص اللوحة', true, 200), self::f('photo', 'logo', 'Logo (facultatif)', 'الشعار (اختياري)', false), $font()],
                'tiers' => [[3, 5], [10, 10]], 'components' => [['ACRYL-CLEAR3-4040', 0.75]], 'proof' => true, 'max' => 50,
            ],
        ];
    }

    /**
     * Create the product of a template (hidden, to be completed). Returns the new product id.
     */
    public static function create(string $key): int
    {
        $t = self::all()[$key] ?? null;
        if ($t === null) {
            throw new \InvalidArgumentException('Unknown template');
        }
        $db = Database::instance();
        return $db->transaction(function (Database $db) use ($t, $key): int {
            $categoryId = $db->value('SELECT id FROM categories WHERE slug = ?', [$t['category']]);
            $o1 = $t['options'][0] ?? null;
            $o2 = $t['options'][1] ?? null;
            $id = CatalogAdmin::saveProduct(null, [
                'title' => $t['title'][0], 'slug' => null, 'sku' => $t['sku'],
                'category_id' => $categoryId !== null ? (int) $categoryId : null, 'collection_id' => null,
                'product_type' => $t['type'], 'short_description' => $t['short'][0], 'description' => $t['desc'][0],
                'base_price' => $t['price'], 'selling_price' => $t['price'], 'promo_price' => null, 'promo_starts_at' => null, 'promo_ends_at' => null,
                'cost_price' => '0.00', 'track_stock' => 0, 'stock_quantity' => 0, 'is_customizable' => 0, 'allow_custom_size' => 0,
                'min_panels' => 1, 'max_panels' => 1, 'is_featured' => 0, 'is_best_seller' => 0, 'is_new' => 1,
                'is_active' => 0, // hidden until the owner adds photos and checks the prices
                'sort_order' => 0, 'seo_title' => null, 'seo_description' => mb_substr($t['short'][0], 0, 160),
                'option1_name' => $o1[0] ?? null, 'option2_name' => $o2[0] ?? null, 'requires_proof' => !empty($t['proof']) ? 1 : 0,
                'min_qty' => (int) ($t['min'] ?? 1), 'max_qty' => (int) ($t['max'] ?? 50), 'production_days' => null,
            ], [
                'title' => $t['title'][1], 'short_description' => $t['short'][1], 'description' => $t['desc'][1],
                'seo_title' => null, 'seo_description' => mb_substr($t['short'][1], 0, 160), 'option1_name' => $o1[1] ?? null, 'option2_name' => $o2[1] ?? null,
            ], [], [], []);
            $product = $db->fetch('SELECT id, sku FROM products WHERE id = ?', [$id]);
            $sort = 0;
            foreach ($t['variants'] as $v) {
                [$v1, $v2, $price] = $v;
                $w = (float) ($v[3] ?? 0);
                $h = (float) ($v[4] ?? 0);
                $name = implode(' · ', array_filter([$v1, $v2]));
                $db->insert('product_variants', [
                    'product_id' => $id, 'sku' => CatalogAdmin::uniqueSku('product_variants', $product['sku'] . '-' . strtoupper(substr(\App\Helpers\Str::slug($name), 0, 24))),
                    'name' => $name, 'option1' => $v1, 'option2' => $v2, 'width_cm' => number_format($w, 1, '.', ''), 'height_cm' => number_format($h, 1, '.', ''),
                    'price' => Money::normalize($price), 'is_default' => $sort === 0 ? 1 : 0, 'sort_order' => ++$sort,
                ]);
            }
            if ($t['variants'] !== []) {
                CatalogAdmin::syncFromVariants($id);
            }
            foreach ($t['fields'] as $i => $f) {
                $fid = $db->insert('product_personalization_fields', [
                    'product_id' => $id, 'field_key' => $f['key'], 'field_type' => $f['type'], 'label' => $f['fr'],
                    'placeholder' => $f['ph_fr'] !== '' ? $f['ph_fr'] : null, 'is_required' => $f['req'] ? 1 : 0, 'max_length' => $f['max'],
                    'choices' => $f['choices'] !== [] ? json_encode($f['choices'], JSON_UNESCAPED_UNICODE) : null, 'price_add' => Money::normalize($f['price']),
                    'print_width_cm' => $f['pw'], 'print_height_cm' => $f['ph'], 'sort_order' => $i,
                ]);
                TranslationService::save('personalization_field', $fid, 'ar', ['label' => $f['ar']]);
            }
            foreach ($t['tiers'] as [$q, $pct]) {
                $db->insert('product_price_tiers', ['product_id' => $id, 'min_qty' => $q, 'discount_percent' => number_format((float) $pct, 2, '.', '')]);
            }
            foreach ($t['components'] as [$sku, $qty]) {
                $mid = $db->value('SELECT id FROM materials WHERE sku = ?', [$sku]);
                if ($mid !== null) {
                    $db->insert('product_components', ['product_id' => $id, 'material_id' => (int) $mid, 'quantity' => number_format((float) $qty, 3, '.', '')]);
                }
            }
            ActivityLogger::log('product.created_from_template', 'product', $id, $key);
            Personalization::forget();
            return $id;
        });
    }
}
