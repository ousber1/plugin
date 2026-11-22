<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Page;
use App\Models\Tool;
use Illuminate\Database\Seeder;

/** Required base data: static pages, tools directory and taxonomies. Idempotent. */
class BaseSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            'about' => [
                ['ar' => 'من نحن', 'fr' => 'À propos', 'en' => 'About us'],
                [
                    'ar' => '<p>أكاديميتنا منصة تعليمية مغربية تقدّم دورات مجانية عملية لكل من يريد تعلّم مهارة جديدة، من البرمجة والتصميم إلى اللغات والمهارات المكتبية.</p><h2>رسالتنا</h2><p>أن يكون التعلّم الجيد متاحًا للجميع، باللغة الأقرب إليهم، وبدون رسوم.</p><h2>الشهادات</h2><p>بعد إتمام متطلبات الدورة، يمكنك طلب شهادة ورقية عبر واتساب. نؤكد معك الاسم والعنوان ثم نرسلها إليك، وتحمل كل شهادة رمزًا للتحقق.</p>',
                    'fr' => '<p>Notre académie est une plateforme marocaine qui propose des formations pratiques et gratuites : programmation, design, langues et bureautique.</p><h2>Notre mission</h2><p>Rendre un apprentissage de qualité accessible à tous, gratuitement.</p>',
                    'en' => '<p>We are a Moroccan learning platform offering free, practical courses in programming, design, languages and office skills.</p><h2>Our mission</h2><p>Make quality learning accessible to everyone, for free.</p>',
                ],
            ],
            'privacy' => [
                ['ar' => 'سياسة الخصوصية', 'fr' => 'Politique de confidentialité', 'en' => 'Privacy policy'],
                [
                    'ar' => '<p>نجمع فقط البيانات اللازمة لتشغيل حسابك: الاسم، البريد الإلكتروني، الهاتف (اختياري)، وتقدّمك في الدورات.</p><h2>الشهادات الورقية</h2><p>يُؤكَّد الاسم المطبوع وعنوان التوصيل مع فريقنا مباشرة عبر واتساب، ويُحفظ داخليًا لغرض الشحن فقط، ولا يظهر في أي صفحة عامة.</p><h2>التحقق من الشهادة</h2><p>تُظهر صفحة التحقق العامة فقط البيانات التي تعتمدها الأكاديمية (مثل الاسم وعنوان الدورة وتاريخ الإصدار).</p><h2>حقوقك</h2><p>يمكنك طلب تعديل بياناتك أو حذف حسابك عبر صفحة «تواصل معنا».</p><p><em>نص تجريبي — يرجى مراجعته مع مستشار قانوني وفق القانون 09-08 المتعلق بحماية المعطيات الشخصية.</em></p>',
                    'fr' => '<p>Nous collectons uniquement les données nécessaires à votre compte : nom, e-mail, téléphone (facultatif) et progression.</p><p><em>Texte d’exemple — à faire valider conformément à la loi 09-08.</em></p>',
                    'en' => '<p>We only collect the data needed to run your account: name, email, optional phone and course progress.</p><p><em>Sample text — have it reviewed under Moroccan law 09-08.</em></p>',
                ],
            ],
            'terms' => [
                ['ar' => 'شروط الاستخدام', 'fr' => 'Conditions d’utilisation', 'en' => 'Terms of use'],
                ['ar' => '<p>باستعمالك للموقع فإنك توافق على استعمال المحتوى لأغراض تعليمية شخصية، وعدم إعادة نشر الفيديوهات أو الملفات.</p><p><em>نص تجريبي يحتاج إلى مراجعة.</em></p>', 'fr' => '', 'en' => ''],
            ],
        ];
        foreach ($pages as $slug => [$title, $body]) {
            Page::firstOrCreate(['slug' => $slug], ['title' => $title, 'body' => $body, 'status' => 'published', 'show_in_footer' => $slug === 'terms']);
        }

        $tools = [
            ['code-editor', ['ar' => 'محرر الأكواد', 'fr' => 'Éditeur de code', 'en' => 'Code editor'], ['ar' => 'اكتب HTML وCSS وJavaScript وشاهد النتيجة مباشرة في معاينة معزولة، مع نماذج جاهزة وتحميل الملف.', 'fr' => 'Écrivez HTML, CSS et JavaScript avec un aperçu isolé en direct.', 'en' => 'Write HTML, CSS and JavaScript with a live, isolated preview.'], 'برمجة'],
            ['cv-builder', ['ar' => 'منشئ السيرة الذاتية', 'fr' => 'Créateur de CV', 'en' => 'CV builder'], ['ar' => 'أنشئ سيرة ذاتية احترافية بالفرنسية أو الإنجليزية أو العربية، واطبعها أو احفظها PDF.', 'fr' => 'Créez un CV professionnel en français, anglais ou arabe et exportez-le en PDF.', 'en' => 'Create a professional CV in French, English or Arabic and save it as PDF.'], 'مهني'],
            ['invoice', ['ar' => 'مولّد الفواتير', 'fr' => 'Générateur de factures', 'en' => 'Invoice generator'], ['ar' => 'صمّم فاتورة أو عرض سعر احترافي مع حساب تلقائي للضريبة والمجموع بالدرهم.', 'fr' => 'Factures et devis avec calcul automatique de la TVA.', 'en' => 'Invoices and quotes with automatic tax totals.'], 'أعمال'],
            ['certificate-designer', ['ar' => 'مصمم الشهادات', 'fr' => 'Concepteur de certificats', 'en' => 'Certificate designer'], ['ar' => 'صمّم شهادات مشاركة عامة لورشاتك وفعالياتك واطبعها لكل المشاركين دفعة واحدة.', 'fr' => 'Concevez des certificats de participation génériques pour vos ateliers.', 'en' => 'Design generic participation certificates for your workshops.'], 'تعليم'],
            ['quiz-builder', ['ar' => 'منشئ الاختبارات', 'fr' => 'Créateur de quiz', 'en' => 'Quiz builder'], ['ar' => 'أنشئ اختبارًا قصيرًا متعدد الخيارات، جرّبه مباشرة، أو اطبعه لطلابك.', 'fr' => 'Créez un QCM, testez-le ou imprimez-le.', 'en' => 'Build a multiple-choice quiz, try it or print it.'], 'تعليم'],
        ];
        foreach ($tools as $i => [$key, $name, $desc, $badge]) {
            Tool::firstOrCreate(['key' => $key], ['name' => $name, 'description' => $desc, 'badge' => $badge, 'sort_order' => $i + 1, 'is_enabled' => true]);
        }

        $categories = [
            'course' => [
                ['programming', 'code', 'البرمجة', 'Programmation', 'Programming'],
                ['design', 'sparkles', 'التصميم', 'Design', 'Design'],
                ['languages', 'globe', 'اللغات', 'Langues', 'Languages'],
                ['office', 'briefcase', 'المكتبيات والحاسوب', 'Bureautique', 'Office skills'],
                ['online-work', 'store', 'العمل عبر الإنترنت', 'Travail en ligne', 'Online work'],
                ['ai', 'lamp', 'الذكاء الاصطناعي', 'Intelligence artificielle', 'Artificial intelligence'],
            ],
            'post' => [
                ['news', 'file-text', 'أخبار الأكاديمية', 'Actualités', 'News'],
                ['learning', 'book', 'التعلّم والتكوين', 'Apprentissage', 'Learning'],
                ['programming', 'code', 'البرمجة', 'Programmation', 'Programming'],
                ['ai', 'lamp', 'الذكاء الاصطناعي', 'IA', 'AI'],
            ],
            'resource' => [
                ['summaries', 'file-text', 'ملخصات', 'Résumés', 'Summaries'],
                ['templates', 'layers', 'قوالب', 'Modèles', 'Templates'],
                ['exercises', 'check-square', 'تمارين', 'Exercices', 'Exercises'],
            ],
        ];
        foreach ($categories as $type => $rows) {
            foreach ($rows as $i => [$slug, $icon, $ar, $fr, $en]) {
                Category::firstOrCreate(['type' => $type, 'slug' => $slug], ['name' => compact('ar', 'fr', 'en'), 'icon' => $icon, 'sort_order' => $i + 1, 'is_active' => true]);
            }
        }
    }
}
