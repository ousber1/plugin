<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Services\LessonFileReader;
use App\Services\QuizGenerator;
use App\Support\Settings;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\TestCase;

class ToolsQuizGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private array $questions = [['question' => 'ما عاصمة المغرب؟', 'options' => ['فاس', 'الرباط', 'مراكش'], 'correct' => [1], 'explanation' => 'الرباط هي العاصمة.']];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaseSeeder::class);
    }

    private function fakeGenerator(bool $enabled = true): Mockery\MockInterface
    {
        $mock = Mockery::mock(QuizGenerator::class);
        $mock->shouldReceive('enabled')->andReturn($enabled);
        $this->app->instance(QuizGenerator::class, $mock);

        return $mock;
    }

    private function docx(string $text): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', '<w:document xmlns:w="w"><w:body><w:p><w:r><w:t>'.htmlspecialchars($text).'</w:t></w:r></w:p><w:p><w:r><w:t>سطر ثانٍ</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        return new UploadedFile($path, 'lesson.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
    }

    public function test_tools_page_matches_expected_structure(): void
    {
        $this->get(route('tools.index'))->assertOk()
            ->assertSee('محرر الأكواد')->assertSee('منشئ السيرة الذاتية')->assertSee('مصمم الفواتير')
            ->assertSee('مصمم الشهادات')->assertSee('مولّد الاختبارات بالذكاء الاصطناعي')->assertSee('جرّب الأداة')
            ->assertSee('أدوات جديدة قريبًا')->assertSee('اقترح علينا أداة جديدة')->assertSee('0</span> / 1500', false)
            ->assertSee('بياناتك عندك')->assertSee('سريعة وبدون تثبيت');
    }

    public function test_ai_panel_only_appears_when_configured(): void
    {
        $this->fakeGenerator(false);
        $this->get(route('tools.show', 'quiz-builder'))->assertOk()->assertDontSee('id="ai-form"', false)->assertSee('التوليد بالذكاء الاصطناعي غير متاح حاليًا')->assertSee('محرر الاختبار');

        $this->fakeGenerator(true);
        $this->get(route('tools.show', 'quiz-builder'))->assertSee('سجّل الدخول لاستعمال المولّد');
        $this->actingAs($this->student())->get(route('tools.show', 'quiz-builder'))->assertSee('id="ai-form"', false)->assertSee('Anthropic');

        Settings::set(['ai_public_tool' => false]);
        $this->get(route('tools.show', 'quiz-builder'))->assertDontSee('id="ai-form"', false);
    }

    public function test_guests_and_disabled_state_are_refused(): void
    {
        $this->fakeGenerator(true);
        $this->postJson(route('tools.quiz.generate'), [])->assertUnauthorized();

        $this->fakeGenerator(false);
        $this->actingAs($this->student())->postJson(route('tools.quiz.generate'), ['text' => 'x', 'count' => 5, 'language' => 'ar', 'difficulty' => 'easy', 'consent' => 1])->assertForbidden();
    }

    public function test_generates_from_pasted_text(): void
    {
        $this->fakeGenerator()->shouldReceive('generate')->once()
            ->with(Mockery::on(fn ($t) => str_contains($t, 'الرباط')), 5, 'ar', null, 'easy')->andReturn($this->questions);

        $this->actingAs($this->student())->postJson(route('tools.quiz.generate'), [
            'text' => str_repeat('الرباط عاصمة المغرب. ', 20), 'count' => 5, 'language' => 'ar', 'difficulty' => 'easy', 'consent' => 1,
        ])->assertOk()->assertJsonPath('questions.0.correct', [1])->assertJsonPath('remaining', 9);
    }

    public function test_generates_from_word_pdf_and_subtitles(): void
    {
        $mock = $this->fakeGenerator();
        $mock->shouldReceive('generate')->once()->with(Mockery::on(fn ($t) => str_contains($t, 'درس عن الماء') && str_contains($t, 'سطر ثانٍ')), 5, 'fr', null, 'medium')->andReturn($this->questions);
        $mock->shouldReceive('generate')->once()->with('', 5, 'ar', base64_encode('%PDF-1.4 fake'), 'hard')->andReturn($this->questions);
        $mock->shouldReceive('generate')->once()->with('Hello class. Today we learn fractions.', 5, 'en', null, 'medium')->andReturn($this->questions);
        $this->actingAs($this->student());

        $this->post(route('tools.quiz.generate'), ['file' => $this->docx('درس عن الماء'), 'count' => 5, 'language' => 'fr', 'difficulty' => 'medium', 'consent' => 1], ['Accept' => 'application/json'])->assertOk();
        $this->post(route('tools.quiz.generate'), ['file' => UploadedFile::fake()->createWithContent('lesson.pdf', '%PDF-1.4 fake'), 'count' => 5, 'language' => 'ar', 'difficulty' => 'hard', 'consent' => 1], ['Accept' => 'application/json'])->assertOk();
        $srt = "1\n00:00:01,000 --> 00:00:03,000\nHello class.\n\n2\n00:00:03,500 --> 00:00:06,000\nToday we learn fractions.\n";
        $this->post(route('tools.quiz.generate'), ['file' => UploadedFile::fake()->createWithContent('lesson.srt', $srt), 'count' => 5, 'language' => 'en', 'difficulty' => 'medium', 'consent' => 1], ['Accept' => 'application/json'])->assertOk();
    }

    public function test_validation_consent_file_type_and_daily_limit(): void
    {
        $this->fakeGenerator()->shouldReceive('generate')->andReturn($this->questions);
        $user = $this->student();
        $this->actingAs($user);
        $base = ['count' => 5, 'language' => 'ar', 'difficulty' => 'easy'];

        $this->postJson(route('tools.quiz.generate'), $base + ['text' => 'نص'])->assertStatus(422)->assertJsonValidationErrors('consent');
        $this->postJson(route('tools.quiz.generate'), $base + ['consent' => 1])->assertStatus(422)->assertJsonPath('message', 'الصق نص الدرس أو ارفع ملفًا.');
        $this->post(route('tools.quiz.generate'), $base + ['consent' => 1, 'file' => UploadedFile::fake()->create('movie.mp4', 100, 'video/mp4')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('file');

        Settings::set(['ai_public_daily_limit' => 2]);
        RateLimiter::clear('ai-tool:'.$user->id);
        $this->postJson(route('tools.quiz.generate'), $base + ['consent' => 1, 'text' => 'نص الدرس'])->assertOk();
        $this->postJson(route('tools.quiz.generate'), $base + ['consent' => 1, 'text' => 'نص الدرس'])->assertOk();
        $this->postJson(route('tools.quiz.generate'), $base + ['consent' => 1, 'text' => 'نص الدرس'])->assertStatus(429);
    }

    public function test_invoice_and_cv_designers_load_their_scripts(): void
    {
        $this->get(route('tools.show', 'invoice'))->assertOk()->assertSee('صمّم فاتورتك الاحترافية في دقائق')
            ->assertSee('id="tool-app"', false)->assertSee('js/tools/engine.js', false)->assertSee('js/tools/invoice.js', false)->assertSee('css/tools.css', false);
        $this->get(route('tools.show', 'cv-builder'))->assertOk()->assertSee('أنشئ سيرتك الذاتية في دقائق')
            ->assertSee('js/tools/cv.js', false);
        foreach (['js/tools/engine.js', 'js/tools/invoice.js', 'js/tools/cv.js', 'css/tools.css'] as $asset) {
            $this->assertFileExists(public_path($asset));
        }
    }

    public function test_subtitle_reader_strips_timestamps(): void
    {
        $vtt = "WEBVTT\n\n00:00.000 --> 00:02.000\n<v Teacher>Bonjour</v>\n\n00:02.000 --> 00:04.000\nBonjour\n\n00:04.000 --> 00:06.000\nOn commence.";
        $this->assertSame('Bonjour On commence.', app(LessonFileReader::class)->subtitles($vtt));
    }

    public function test_admin_can_rename_tools(): void
    {
        $tool = Tool::where('key', 'invoice')->first();
        $this->actingAs($this->admin())->put(route('admin.tools.update'), ['tools' => [$tool->id => [
            'is_enabled' => 1, 'sort_order' => 3, 'name' => ['ar' => 'فواتير المقاولين', 'fr' => '', 'en' => ''], 'description' => ['ar' => 'وصف جديد', 'fr' => '', 'en' => ''],
        ]]])->assertSessionHas('success');
        $this->get(route('tools.index'))->assertSee('فواتير المقاولين')->assertSee('وصف جديد');
    }
}
