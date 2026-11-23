<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\Resource;
use App\Models\Section;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo content, flagged with is_demo so it is clearly identified in the admin
 * dashboard. Demo accounts get random passwords printed once to the console
 * (or DEMO_PASSWORD when set, for local testing only).
 */
class DemoSeeder extends Seeder
{
    private string $disk;

    public function run(): void
    {
        $this->disk = config('academy.media_disk', 'local');
        $password = env('DEMO_PASSWORD') ?: Str::password(14, symbols: false);

        $instructor = $this->user('demo.instructor@example.com', 'أستاذة سلمى (تجريبي)', 'instructor', $password, 'مكوّنة في البرمجة والتصميم الرقمي');
        $student = $this->user('demo.student@example.com', 'طالب تجريبي', 'student', $password);

        $cat = fn ($slug, $type = 'course') => Category::where('type', $type)->where('slug', $slug)->value('id');
        $videoSource = database_path('seeders/assets/demo-lesson.mp4');

        $courses = [
            [
                'slug' => 'python-basics', 'cat' => 'programming', 'level' => 'beginner', 'lang' => 'ar', 'featured' => true, 'quiz_required' => true,
                'title' => ['ar' => 'أساسيات البرمجة بلغة Python', 'fr' => 'Les bases de la programmation avec Python', 'en' => 'Programming Basics with Python'],
                'subtitle' => ['ar' => 'ابدأ البرمجة من الصفر بأمثلة بسيطة من الحياة اليومية، بشرح عربي واضح.', 'fr' => 'Commencez à programmer à partir de zéro avec des exemples concrets.', 'en' => 'Start coding from scratch with everyday examples.'],
                'desc' => '<p>دورة تمهيدية لكل من يريد دخول عالم البرمجة. نبدأ بتثبيت Python، ثم المتغيرات والشروط والحلقات، وننتهي بمشروع صغير: <strong>آلة حاسبة لمصاريف الشهر</strong>.</p><p>كل درس فيديو قصير يليه تمرين عملي قابل للتحميل.</p>',
                'outcomes' => ['فهم المتغيرات وأنواع البيانات', 'كتابة الشروط والحلقات', 'تنظيم الكود في دوال', 'إنجاز مشروع صغير كامل'],
                'prereq' => ['حاسوب متصل بالإنترنت', 'لا يلزم أي مستوى سابق'],
                'audience' => ['المبتدئون في البرمجة', 'التلاميذ والطلبة', 'كل من يريد تغيير مساره المهني'],
                'sections' => [
                    [['ar' => 'البداية', 'fr' => 'Démarrage', 'en' => 'Getting started'], [
                        ['video', ['ar' => 'ما هي البرمجة؟ ولماذا Python؟', 'en' => 'What is programming? Why Python?'], true],
                        ['text', ['ar' => 'تثبيت Python ومحرر الأكواد', 'en' => 'Installing Python and an editor'], false, '<p>حمّل Python من الموقع الرسمي، ثم ثبّت محرر VS Code. تأكد من تفعيل خيار <code>Add Python to PATH</code>.</p><pre><code>python --version</code></pre>'],
                    ]],
                    [['ar' => 'الأساسيات', 'fr' => 'Les bases', 'en' => 'Fundamentals'], [
                        ['video', ['ar' => 'المتغيرات وأنواع البيانات', 'en' => 'Variables and data types'], false],
                        ['exercise', ['ar' => 'تمرين: حاسبة المصاريف', 'en' => 'Exercise: expense calculator'], false, '<p>حمّل ملف التمرين، أكمل الدالة <code>total()</code> ثم شغّل البرنامج.</p>'],
                        ['quiz', ['ar' => 'اختبار: أساسيات Python', 'en' => 'Quiz: Python basics'], false],
                    ]],
                ],
                'quiz' => [
                    ['ما ناتج print(2 + 3 * 2)؟', ['10', '8', '12'], [1], 'الضرب يُنفَّذ قبل الجمع.'],
                    ['أي من التالي اسم متغير صالح في Python؟', ['2total', 'total_2', 'total-2'], [1], 'لا يبدأ الاسم برقم ولا يحتوي على "-".'],
                    ['ما نوع القيمة "3.5"؟', ['float', 'str', 'int'], [1], 'القيمة بين علامتي تنصيص فهي نص.'],
                ],
            ],
            [
                'slug' => 'canva-graphic-design', 'cat' => 'design', 'level' => 'beginner', 'lang' => 'ar', 'featured' => true,
                'title' => ['ar' => 'التصميم الجرافيكي باستعمال Canva', 'fr' => 'Design graphique avec Canva', 'en' => 'Graphic Design with Canva'],
                'subtitle' => ['ar' => 'صمّم منشورات احترافية لمواقع التواصل وشعارات بسيطة دون خبرة سابقة.'],
                'desc' => '<p>تعلّم مبادئ التصميم: الألوان، الخطوط، التوازن والمساحات. ثم طبّقها في Canva لإنشاء منشورات وإعلانات وسير ذاتية.</p>',
                'outcomes' => ['اختيار ألوان وخطوط متناسقة', 'تصميم منشور لإنستغرام', 'إنشاء هوية بصرية بسيطة'],
                'prereq' => ['حساب مجاني على Canva'],
                'audience' => ['أصحاب المشاريع الصغيرة', 'صنّاع المحتوى'],
                'sections' => [
                    [['ar' => 'مبادئ التصميم'], [
                        ['video', ['ar' => 'الألوان والخطوط'], true],
                        ['video', ['ar' => 'التوازن والمساحات البيضاء'], false],
                    ]],
                    [['ar' => 'تطبيق عملي'], [
                        ['text', ['ar' => 'مشروع: منشور إعلاني لمحل محلي'], false, '<p>صمّم منشورًا بمقاس 1080×1080 لمحل حلويات في حيّك. استعمل لونين رئيسيين وخطًا عربيًا واضحًا.</p>'],
                    ]],
                ],
            ],
            [
                'slug' => 'english-a1-everyday', 'cat' => 'languages', 'level' => 'beginner', 'lang' => 'en',
                'title' => ['en' => 'English A1 — Everyday Conversations', 'ar' => 'الإنجليزية A1 — محادثات يومية', 'fr' => 'Anglais A1 — Conversations du quotidien'],
                'subtitle' => ['en' => 'Greetings, introductions and daily routines, explained step by step.', 'ar' => 'التحية والتعارف والروتين اليومي، بشرح مبسّط خطوة بخطوة.'],
                'desc' => '<p>A friendly beginner course. Each lesson includes a short video, key phrases and a practice sheet.</p>',
                'outcomes' => ['Greet people and introduce yourself', 'Talk about your family', 'Describe your daily routine'],
                'prereq' => ['No prior English needed'],
                'audience' => ['Absolute beginners', 'Students preparing for A1'],
                'sections' => [
                    [['en' => 'Lesson 1: Nice to meet you', 'ar' => 'الدرس 1: تشرفت بمعرفتك'], [
                        ['video', ['en' => 'Greetings & introductions', 'ar' => 'التحية وتقديم النفس'], true],
                        ['video', ['en' => 'Real situations & practice', 'ar' => 'مواقف حقيقية وتطبيق'], false],
                    ]],
                    [['en' => 'Lesson 2: All about me', 'ar' => 'الدرس 2: عرّف بنفسك'], [
                        ['text', ['en' => 'Family vocabulary', 'ar' => 'مفردات العائلة'], false, '<p dir="ltr">mother, father, sister, brother, grandparents…</p>'],
                    ]],
                ],
            ],
            [
                'slug' => 'excel-gestion-debutant', 'cat' => 'office', 'level' => 'intermediate', 'lang' => 'fr',
                'title' => ['fr' => 'Excel pour la gestion — niveau débutant', 'ar' => 'إكسل للتسيير — مستوى مبتدئ', 'en' => 'Excel for Business — Beginner'],
                'subtitle' => ['fr' => 'Tableaux, formules et graphiques pour suivre un petit budget.', 'ar' => 'جداول وصيغ ورسوم بيانية لتتبع ميزانية صغيرة.'],
                'desc' => '<p>Apprenez à créer un tableau de suivi de dépenses, à utiliser SOMME, SI et RECHERCHEV, puis à présenter vos résultats avec des graphiques.</p>',
                'outcomes' => ['Créer un tableau propre', 'Utiliser les formules essentielles', 'Construire un graphique'],
                'prereq' => ['Excel ou LibreOffice Calc'],
                'audience' => ['Commerçants', 'Étudiants en gestion'],
                'sections' => [
                    [['fr' => 'Prise en main', 'ar' => 'البداية'], [
                        ['video', ['fr' => 'Interface et premières saisies', 'ar' => 'الواجهة وأولى الإدخالات'], true],
                        ['video', ['fr' => 'Formules SOMME et SI', 'ar' => 'صيغ SOMME و SI'], false],
                    ]],
                ],
            ],
            [
                'slug' => 'generative-ai-intro', 'cat' => 'ai', 'level' => 'all', 'lang' => 'ar', 'featured' => true, 'quiz_required' => true,
                'title' => ['ar' => 'مدخل إلى الذكاء الاصطناعي التوليدي', 'fr' => 'Introduction à l’IA générative', 'en' => 'Introduction to Generative AI'],
                'subtitle' => ['ar' => 'افهم كيف تعمل أدوات الذكاء الاصطناعي وكيف تستعملها بمسؤولية في الدراسة والعمل.'],
                'desc' => '<p>نشرح المفاهيم الأساسية دون تعقيد: ما هو النموذج اللغوي؟ كيف تكتب تعليمات واضحة؟ وما حدود هذه الأدوات وأخطاؤها الشائعة؟</p>',
                'outcomes' => ['فهم المفاهيم الأساسية', 'كتابة تعليمات واضحة', 'التحقق من المعلومات وتجنّب الأخطاء'],
                'prereq' => ['لا يلزم أي مستوى تقني'],
                'audience' => ['الطلبة', 'الموظفون', 'أصحاب المشاريع'],
                'sections' => [
                    [['ar' => 'المفاهيم'], [
                        ['video', ['ar' => 'ما هو الذكاء الاصطناعي التوليدي؟'], true],
                        ['text', ['ar' => 'كتابة تعليمات واضحة'], false, '<ul><li>حدّد الهدف بدقة</li><li>أعطِ السياق والأمثلة</li><li>اطلب الشكل الذي تريده للنتيجة</li></ul>'],
                        ['quiz', ['ar' => 'اختبار المفاهيم'], false],
                    ]],
                ],
                'quiz' => [
                    ['ما أفضل طريقة للتعامل مع معلومة قدّمتها أداة ذكاء اصطناعي؟', ['اعتمادها مباشرة', 'التحقق منها من مصادر موثوقة', 'تجاهلها دائمًا'], [1], 'الأدوات قد تخطئ، لذا تحقّق دائمًا.'],
                    ['ما الذي يحسّن جودة الإجابة؟ (اختر كل ما ينطبق)', ['تعليمات واضحة', 'إعطاء سياق وأمثلة', 'كتابة سؤال مبهم'], [0, 1], 'الوضوح والسياق يحسّنان النتيجة.'],
                ],
            ],
            [
                'slug' => 'freelancing-online', 'cat' => 'online-work', 'level' => 'beginner', 'lang' => 'ar', 'draft' => true,
                'title' => ['ar' => 'مهارات العمل الحر عبر الإنترنت (مسودة)', 'fr' => 'Freelance en ligne', 'en' => 'Freelancing Online'],
                'subtitle' => ['ar' => 'دورة قيد الإعداد — مثال على مسودة لا تظهر للزوار.'],
                'desc' => '<p>مسودة.</p>', 'outcomes' => [], 'prereq' => [], 'audience' => [],
                'sections' => [[['ar' => 'قريبًا'], [['text', ['ar' => 'مقدمة'], false, '<p>قريبًا.</p>']]]],
            ],
        ];

        foreach ($courses as $i => $spec) {
            $course = Course::where('slug', $spec['slug'])->first() ?? new Course;
            $course->fill([
                'category_id' => $cat($spec['cat']),
                'title' => $spec['title'],
                'subtitle' => $spec['subtitle'],
                'description' => [$spec['lang'] => $spec['desc']],
                'outcomes' => [$spec['lang'] => $spec['outcomes']],
                'prerequisites' => [$spec['lang'] => $spec['prereq']],
                'audience' => [$spec['lang'] => $spec['audience']],
                'level' => $spec['level'],
                'language' => $spec['lang'],
                'is_featured' => $spec['featured'] ?? false,
                'featured_order' => $i,
                'requires_quiz_pass' => $spec['quiz_required'] ?? false,
            ]);
            $course->slug = $spec['slug'];
            $course->instructor_id = $instructor->id;
            $course->is_demo = true;
            $course->status = ($spec['draft'] ?? false) ? 'draft' : 'published';
            $course->published_at = ($spec['draft'] ?? false) ? null : now()->subDays(40 - $i * 7);
            $course->save();
            $course->assignees()->syncWithoutDetaching([$instructor->id]);

            if ($course->sections()->exists()) {
                continue; // keep existing curriculum on re-seed
            }
            foreach ($spec['sections'] as $s => [$sectionTitle, $lessons]) {
                $section = Section::create(['course_id' => $course->id, 'title' => $sectionTitle, 'sort_order' => $s + 1]);
                foreach ($lessons as $l => $row) {
                    [$type, $title, $preview] = $row;
                    $lesson = new Lesson([
                        'course_id' => $course->id, 'section_id' => $section->id, 'title' => $title, 'type' => $type,
                        'body' => isset($row[3]) ? [$spec['lang'] === 'en' && ! isset($title['ar']) ? 'en' : 'ar' => $row[3]] : null,
                        'is_preview' => $preview, 'is_required' => true, 'status' => 'published', 'sort_order' => $l + 1,
                    ]);
                    $lesson->save();
                    if ($type === 'video' && is_file($videoSource)) {
                        $path = 'videos/'.$course->id.'/demo-'.$lesson->id.'.mp4';
                        Storage::disk($this->disk)->put($path, fopen($videoSource, 'rb'));
                        $lesson->forceFill(['video_disk' => $this->disk, 'video_path' => $path, 'video_size' => filesize($videoSource), 'duration_seconds' => 12])->save();
                    }
                    if ($type === 'exercise') {
                        $path = 'attachments/'.$course->id.'/demo-exercise-'.$lesson->id.'.txt';
                        Storage::disk($this->disk)->put($path, "# Demo exercise\ndef total(expenses):\n    # TODO: return the sum of all expenses\n    pass\n\nprint(total([120, 45.5, 300]))\n");
                        $lesson->attachments()->create(['title' => 'ملف التمرين (تجريبي)', 'disk' => $this->disk, 'path' => $path, 'original_name' => 'exercise.py.txt', 'mime' => 'text/plain', 'size' => Storage::disk($this->disk)->size($path)]);
                    }
                    if ($type === 'quiz') {
                        $quiz = Quiz::create(['course_id' => $course->id, 'lesson_id' => $lesson->id, 'pass_score' => 60]);
                        foreach ($spec['quiz'] ?? [] as $n => [$q, $options, $correct, $explanation]) {
                            $quiz->questions()->create(['question' => $q, 'options' => $options, 'correct' => $correct, 'explanation' => $explanation, 'sort_order' => $n + 1]);
                        }
                    }
                }
            }
        }

        Enrollment::firstOrCreate(['user_id' => $student->id, 'course_id' => Course::where('slug', 'python-basics')->value('id')]);

        $posts = [
            ['news', 'launch-free-courses', ['ar' => 'إطلاق الأكاديمية: كل الدورات مجانية', 'fr' => 'Lancement : toutes les formations sont gratuites', 'en' => 'Launch: every course is free'], '<p>يسعدنا الإعلان عن إطلاق منصتنا. كل الدورات مجانية، ويمكنك طلب شهادة ورقية تصلك إلى عنوانك بعد إتمام الدورة.</p>'],
            ['learning', 'how-to-learn-online', ['ar' => '7 نصائح لتتعلّم عبر الإنترنت دون أن تفقد حماسك'], '<h2>1. حدّد هدفًا صغيرًا</h2><p>درس واحد يوميًا أفضل من خمسة دروس مرة في الأسبوع.</p><h2>2. طبّق مباشرة</h2><p>التعلّم الحقيقي يحدث عند التطبيق.</p>'],
            ['programming', 'roadmap-programming-2026', ['ar' => 'خارطة تعلم البرمجة من الصفر: بأي لغة تبدأ؟'], '<p>إن كنت مبتدئًا، فـ Python خيار ممتاز للبداية بسبب بساطة كتابتها وكثرة مواردها.</p>'],
            ['ai', 'ai-common-mistakes', ['ar' => '10 أخطاء شائعة عند استخدام أدوات الذكاء الاصطناعي'], '<p>من أكثر الأخطاء شيوعًا: الاعتماد على الإجابة دون تحقق، وكتابة تعليمات مبهمة.</p>'],
        ];
        foreach ($posts as $i => [$catSlug, $slug, $title, $body]) {
            $post = Post::firstOrNew(['slug' => $slug]);
            $post->fill(['category_id' => $cat($catSlug, 'post'), 'title' => $title, 'excerpt' => ['ar' => Str::limit(strip_tags($body), 140)], 'body' => ['ar' => $body], 'status' => 'published', 'published_at' => now()->subDays(3 + $i * 5)]);
            $post->author_id = $instructor->id;
            $post->is_demo = true;
            $post->save();
        }

        foreach ([
            ['python-cheatsheet', 'summaries', 'public', null, ['ar' => 'ملخص أوامر Python الأساسية', 'en' => 'Python basics cheat sheet']],
            ['python-exercises-pack', 'exercises', 'enrolled', 'python-basics', ['ar' => 'حزمة تمارين Python (للمسجلين)']],
        ] as [$slug, $catSlug, $access, $courseSlug, $title]) {
            if (Resource::where('slug', $slug)->exists()) {
                continue;
            }
            $path = 'library/demo-'.$slug.'.pdf';
            Storage::disk($this->disk)->put($path, $this->tinyPdf($title['en'] ?? 'Demo resource'));
            $r = new Resource(['category_id' => $cat($catSlug, 'resource'), 'title' => $title, 'description' => ['ar' => 'ملف تجريبي للتوضيح.'], 'access' => $access, 'status' => 'published']);
            $r->slug = $slug;
            $r->course_id = $courseSlug ? Course::where('slug', $courseSlug)->value('id') : null;
            $r->forceFill(['disk' => $this->disk, 'path' => $path, 'original_name' => $slug.'.pdf', 'mime' => 'application/pdf', 'size' => Storage::disk($this->disk)->size($path), 'is_demo' => true])->save();
        }

        // Demo testimonials are NOT approved: an administrator must replace them with real,
        // consented testimonials before anything is shown publicly.
        foreach ([['مثال: سارة، الدار البيضاء', 'نص تجريبي — استبدله برأي طالب حقيقي بعد موافقته.'], ['مثال: يوسف، فاس', 'نص تجريبي — استبدله برأي طالب حقيقي بعد موافقته.']] as [$name, $body]) {
            $t = Testimonial::firstOrNew(['name' => $name]);
            $t->fill(['body' => ['ar' => $body], 'rating' => 5, 'is_approved' => false]);
            $t->is_demo = true;
            $t->save();
        }

        if ($this->command) {
            $this->command->warn('Demo accounts (local testing only — delete before production):');
            $this->command->line("  instructor: demo.instructor@example.com / {$password}");
            $this->command->line("  student:    demo.student@example.com / {$password}");
        }
    }

    private function user(string $email, string $name, string $role, string $password, ?string $headline = null): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->fill(['name' => $name, 'password' => $password, 'headline' => $headline, 'locale' => 'ar']);
        $user->role = $role;
        $user->is_demo = true;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    /** Minimal valid one-page PDF so library downloads work out of the box. */
    private function tinyPdf(string $text): string
    {
        $text = preg_replace('/[^A-Za-z0-9 .,:\-]/', '', $text);
        $stream = "BT /F1 24 Tf 72 720 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$obj}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
