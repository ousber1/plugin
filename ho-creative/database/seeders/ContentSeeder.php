<?php

namespace Database\Seeders;

use App\Models\HomeSection;
use App\Models\Holiday;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

/** Editable starting content: homepage sections, pages, navigation, zones, fixed public holidays. */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $t = fn ($fr, $ar) => ['fr' => $fr, 'ar' => $ar];

        if (! HomeSection::exists()) {
            $sections = [
                ['hero', $t('Vos imprimés pros, prix immédiats, livrés partout au Maroc', 'مطبوعاتكم الاحترافية بأسعار فورية وتوصيل لكل المغرب'), [
                    'text' => $t('Configurez cartes de visite, flyers, packaging, bâches et PLV : le prix se calcule en direct, vous validez votre BAT en ligne et nous imprimons.', 'اختاروا مواصفات بطاقاتكم، مطوياتكم، التغليف واللافتات: يُحسب السعر مباشرة، تصادقون على النموذج عبر الإنترنت ونتكفل بالطباعة.'),
                    'cta_label' => $t('Découvrir nos produits', 'اكتشفوا منتجاتنا'), 'cta_url' => '#categories']],
                ['promotions', $t('', ''), ['items' => [
                    ['title' => $t('Cartes de visite', 'بطاقات الأعمال'), 'text' => $t('Dès 100 ex., recto-verso, pelliculage au choix', 'ابتداءً من 100 نسخة، وجهين، تغليف حسب الاختيار'), 'url' => '/produit/cartes-de-visite-standard'],
                    ['title' => $t('Bâches sur mesure', 'لافتات حسب المقاس'), 'text' => $t('Prix au m² calculé en direct, œillets inclus', 'سعر المتر المربع يُحسب مباشرة'), 'url' => '/produit/bache-pvc-sur-mesure'],
                    ['title' => $t('Devis pro & B2B', 'عروض أسعار للشركات'), 'text' => $t('Catalogues, boîtes, gros volumes : réponse rapide', 'كتالوجات، علب، كميات كبيرة: رد سريع'), 'url' => '/devis'],
                ]]],
                ['categories', $t('Nos produits', 'منتجاتنا'), []],
                ['popular', $t('Les plus demandés', 'الأكثر طلباً'), []],
                ['how_it_works', $t('Comment ça marche ?', 'كيف يعمل ذلك؟'), ['steps' => [
                    ['title' => $t('Configurez', 'اختاروا'), 'text' => $t('Format, papier, finitions, quantité : le prix HT s\'affiche instantanément.', 'المقاس، الورق، اللمسات والكمية: يظهر السعر فوراً.')],
                    ['title' => $t('Ajoutez votre fichier', 'أضيفوا ملفكم'), 'text' => $t('Téléversez un PDF, personnalisez un modèle, créez en ligne ou confiez-nous la création.', 'حمّلوا ملف PDF، خصصوا نموذجاً، صمموا عبر الإنترنت أو اتركوا لنا التصميم.')],
                    ['title' => $t('Validez le BAT', 'صادقوا على النموذج'), 'text' => $t('Notre prépresse vérifie et vous soumet un bon à tirer à approuver.', 'يراجع فريقنا الملف ويرسل لكم نموذجاً للمصادقة.')],
                    ['title' => $t('Recevez', 'استلموا'), 'text' => $t('Retrait à l\'atelier ou livraison dans tout le Maroc.', 'استلام من الورشة أو توصيل لكل المدن.')],
                ]]],
                ['quote_cta', $t('Un projet sur mesure ?', 'مشروع حسب الطلب؟'), ['text' => $t('Enseignes, packaging, grands volumes : décrivez votre besoin, un conseiller vous répond.', 'لافتات، تغليف، كميات كبيرة: صفوا حاجتكم وسيجيبكم مستشار.')]],
                ['reviews', $t('Ils nous font confiance', 'يثقون بنا'), []],
                ['support', $t('', ''), []],
            ];
            foreach ($sections as $i => [$type, $title, $content]) {
                HomeSection::create(['type' => $type, 'title' => $title, 'content' => $content, 'sort' => ($i + 1) * 10, 'is_active' => true]);
            }
        }

        $pages = [
            'aide' => [$t('Aide & préparation des fichiers', 'المساعدة وتحضير الملفات'), $t(
                '<h2>Préparer votre fichier</h2><ul><li>Format PDF de préférence (PNG/JPEG acceptés).</li><li>Fond perdu de 3 mm de chaque côté, textes à 4 mm minimum du bord.</li><li>Images à 300 dpi à la taille finale.</li><li>Polices vectorisées ou incorporées.</li></ul><h2>BAT</h2><p>Chaque commande est contrôlée par notre prépresse. Lorsque vous recevez un bon à tirer, la production démarre uniquement après votre approbation de la version en cours.</p><h2>Délais</h2><p>Délais exprimés en jours ouvrés, comptés à partir de la validation du BAT. Commandes reçues après l\'heure limite traitées le jour ouvré suivant.</p>',
                '<h2>تحضير الملف</h2><ul><li>يفضل ملف PDF (نقبل PNG و JPEG).</li><li>هامش قص 3 مم من كل جهة والنصوص على بعد 4 مم من الحافة.</li><li>صور بدقة 300 نقطة.</li></ul><h2>نموذج الطباعة</h2><p>لا يبدأ الإنتاج إلا بعد مصادقتكم على النموذج الحالي.</p>')],
            'cgv' => [$t('Conditions générales de vente', 'الشروط العامة للبيع'), $t('<p><strong>À compléter par HO CREATIVE avec son conseil juridique</strong> (prix, paiement, délais, réclamations, propriété des fichiers, données personnelles – loi 09-08).</p>', '<p>يُستكمل من طرف HO CREATIVE.</p>')],
            'confidentialite' => [$t('Confidentialité', 'الخصوصية'), $t('<p>Vos fichiers et données sont stockés de façon privée et utilisés uniquement pour traiter vos commandes. <strong>Texte à compléter (loi 09-08, déclaration CNDP le cas échéant).</strong></p>', '<p>تُحفظ ملفاتكم وبياناتكم بشكل خاص. نص يُستكمل.</p>')],
            'contact' => [$t('Contact', 'اتصل بنا'), $t('<p>Écrivez-nous par WhatsApp, téléphone ou via le formulaire de devis.</p>', '<p>راسلونا عبر واتساب أو الهاتف أو استمارة عرض السعر.</p>')],
        ];
        foreach ($pages as $slug => [$title, $body]) {
            Page::firstOrCreate(['slug' => $slug], ['title' => $title, 'body' => $body, 'is_published' => true]);
        }

        if (! MenuItem::exists()) {
            foreach ([['aide', 'Aide', 'مساعدة'], ['contact', 'Contact', 'اتصل بنا'], ['cgv', 'CGV', 'شروط البيع'], ['confidentialite', 'Confidentialité', 'الخصوصية']] as $i => [$slug, $fr, $ar]) {
                MenuItem::create(['location' => 'footer', 'label' => $t($fr, $ar), 'url' => '/page/'.$slug, 'sort' => $i]);
            }
        }

        if (! ShippingZone::exists()) {
            ShippingZone::create(['name' => $t('Casablanca & Mohammedia', 'الدار البيضاء والمحمدية'), 'cities' => ['Casablanca', 'Mohammedia', 'Bouskoura', 'Dar Bouazza'], 'price' => '30.00', 'free_over' => '1000.00', 'delivery_days' => 1, 'sort' => 1]);
            ShippingZone::create(['name' => $t('Rabat, Salé, Kénitra', 'الرباط، سلا، القنيطرة'), 'cities' => ['Rabat', 'Salé', 'Temara', 'Kénitra'], 'price' => '45.00', 'free_over' => '1500.00', 'delivery_days' => 2, 'sort' => 2]);
            ShippingZone::create(['name' => $t('Grandes villes', 'المدن الكبرى'), 'cities' => ['Marrakech', 'Fès', 'Tanger', 'Agadir', 'Meknès', 'Oujda', 'Tétouan', 'El Jadida', 'Safi', 'Béni Mellal'], 'price' => '60.00', 'free_over' => '2000.00', 'delivery_days' => 3, 'carrier' => 'Transporteur national', 'sort' => 3]);
            ShippingZone::create(['name' => $t('Reste du Maroc', 'باقي المغرب'), 'cities' => [], 'price' => '80.00', 'free_over' => null, 'delivery_days' => 4, 'carrier' => 'Transporteur national', 'is_default' => true, 'sort' => 4]);
        }

        // Fixed-date public holidays only; religious holidays (lunar calendar) must be added each year by the owner.
        foreach ([now()->year, now()->year + 1] as $y) {
            foreach (['01-01' => 'Nouvel an', '01-11' => 'Manifeste de l\'indépendance', '01-14' => 'Nouvel an amazigh', '05-01' => 'Fête du travail', '07-30' => 'Fête du Trône',
                '08-14' => 'Oued Eddahab', '08-20' => 'Révolution du roi et du peuple', '08-21' => 'Fête de la jeunesse', '11-06' => 'Marche verte', '11-18' => 'Fête de l\'indépendance'] as $md => $name) {
                Holiday::firstOrCreate(['date' => "$y-$md"], ['name' => $name]);
            }
        }
    }
}
