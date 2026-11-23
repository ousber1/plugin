<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Existing installs: give the quiz tool its new AI-generator name, unless an admin renamed it. */
return new class extends Migration
{
    public function up(): void
    {
        $tool = DB::table('tools')->where('key', 'quiz-builder')->first();
        if (! $tool || ! str_contains((string) $tool->name, 'منشئ الاختبارات')) {
            return;
        }
        DB::table('tools')->where('id', $tool->id)->update([
            'name' => json_encode(['ar' => 'مولّد الاختبارات بالذكاء الاصطناعي', 'fr' => 'Générateur de quiz par IA', 'en' => 'AI quiz generator'], JSON_UNESCAPED_UNICODE),
            'description' => json_encode([
                'ar' => 'حوّل الدروس وملفات PDF وWord إلى اختبارات تفاعلية بسهولة، أو أنشئ اختبارك يدويًا، جرّبه مباشرة واطبعه.',
                'fr' => 'Transformez vos leçons, PDF et documents Word en quiz interactifs, ou créez-les à la main.',
                'en' => 'Turn lessons, PDF and Word files into interactive quizzes, or build one by hand.',
            ], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void {}
};
