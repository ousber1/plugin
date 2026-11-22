<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Services\QuizGenerator;
use App\Support\Settings;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaseSeeder::class);
    }

    public function test_public_pages_render_in_rtl_and_ltr(): void
    {
        $this->makeCourse(['title' => ['ar' => 'دورة عربية', 'en' => 'English title']]);
        foreach (['home', 'courses.index', 'blog.index', 'library.index', 'tools.index', 'verify.index', 'contact', 'about', 'privacy', 'login', 'register'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('dir="rtl"', false);
        }
        $this->get(route('locale.switch', 'fr'))->assertRedirect();
        $this->get(route('courses.index'))->assertSee('lang="fr" dir="ltr"', false)->assertSee('Gratuit')->assertSee('دورة عربية'); // no French title: Arabic fallback
        $this->get(route('locale.switch', 'en'));
        $this->get(route('home'))->assertSee('lang="en" dir="ltr"', false);
        $this->get(route('courses.index'))->assertSee('English title')->assertSee('Free');
        // Missing translation falls back to Arabic
        $this->makeCourse(['slug' => 'only-ar', 'title' => ['ar' => 'عنوان عربي فقط']]);
        $this->get(route('courses.index'))->assertSee('عنوان عربي فقط');
    }

    public function test_seo_endpoints(): void
    {
        $this->get(route('robots'))->assertOk()->assertSee('Disallow: /admin')->assertSee('Sitemap:');
        $this->get(route('home'))->assertSee('<link rel="canonical"', false)->assertSee('og:title', false);
    }

    public function test_disabled_modules_are_hidden(): void
    {
        $this->get(route('store.index'))->assertNotFound(); // store disabled by default
        $this->get(route('home'))->assertDontSee(route('store.index'));
        Settings::set(['features' => ['blog' => false, 'library' => true, 'tools' => true, 'store' => true, 'verification' => true]]);
        $this->get(route('blog.index'))->assertNotFound();
        $this->get(route('store.index'))->assertOk();
        $this->get(route('home'))->assertDontSee(route('blog.index').'"', false);
    }

    public function test_reviews_require_enrollment_and_approval(): void
    {
        $course = $this->makeCourse();
        $student = $this->student();
        $this->actingAs($student)->post(route('courses.review', $course), ['rating' => 5, 'body' => 'رائعة'])->assertForbidden();
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id]);
        $this->post(route('courses.review', $course), ['rating' => 5, 'body' => 'دورة رائعة'])->assertRedirect();
        $this->post(route('courses.review', $course), ['rating' => 4, 'body' => 'دورة رائعة جدًا'])->assertRedirect();
        $this->assertSame(1, Review::count());
        $this->get(route('courses.show', $course))->assertDontSee('<p class="small" style="margin:6px 0 0" dir="auto">دورة رائعة جدًا</p>', false);

        $review = Review::first();
        $this->actingAs($this->admin())->post(route('admin.reviews.approve', $review));
        $this->get(route('courses.show', $course))->assertSee('دورة رائعة جدًا')->assertSee('4 / 5');
    }

    public function test_contact_and_tool_suggestions_are_stored_for_moderation(): void
    {
        $this->post(route('contact.store'), ['name' => 'Ali', 'email' => 'ali@example.com', 'body' => 'سؤال حول الشهادة الورقية'])->assertSessionHas('success');
        $this->assertDatabaseCount('contact_messages', 1);
        $this->post(route('tools.suggest'), ['idea' => 'أداة لتحويل الصور إلى PDF'])->assertSessionHas('success');
        $this->assertDatabaseHas('tool_suggestions', ['status' => 'pending']);
        $this->actingAs($this->admin())->get(route('admin.tools.index'))->assertSee('أداة لتحويل الصور');
    }

    public function test_code_editor_preview_is_sandboxed(): void
    {
        $html = $this->get(route('tools.show', 'code-editor'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<iframe[^>]+sandbox="allow-scripts allow-modals"/', $html);
        $this->assertStringNotContainsString('allow-same-origin', $html);
    }

    public function test_store_orders_are_independent_and_downloads_need_payment(): void
    {
        Settings::set(['features' => array_merge(Settings::get('features'), ['store' => true])]);
        $product = new Product(['title' => ['ar' => 'كتاب رقمي'], 'price_cents' => 4900, 'status' => 'published']);
        $product->slug = 'ebook';
        Storage::disk('local')->put('products/e.pdf', '%PDF');
        $product->forceFill(['disk' => 'local', 'file_path' => 'products/e.pdf', 'original_name' => 'e.pdf'])->save();

        $buyer = $this->student();
        $this->actingAs($buyer)->post(route('store.checkout', $product), ['accept' => 1])->assertRedirect();
        $order = Order::firstOrFail();
        $this->get(route('store.download', $order))->assertForbidden();
        $this->actingAs($this->admin())->put(route('admin.orders.update', $order), ['status' => 'paid']);
        $this->actingAs($buyer)->get(route('store.download', $order))->assertOk();
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('certificate_requests', 0);
    }

    public function test_ai_generator_is_disabled_without_key_and_validates_responses(): void
    {
        $gen = app(QuizGenerator::class);
        putenv('ANTHROPIC_API_KEY=');
        $this->assertFalse($gen->enabled());

        $clean = $gen->validate(['questions' => [
            ['question' => 'ما 2+2؟', 'options' => ['3', '4', ''], 'correct' => [1], 'explanation' => 'جمع'],
            ['question' => 'سؤال سيئ', 'options' => ['واحد'], 'correct' => [0], 'explanation' => ''],
            ['question' => 'فهرس خاطئ', 'options' => ['أ', 'ب'], 'correct' => [5], 'explanation' => ''],
            'not-an-array',
        ]]);
        $this->assertCount(1, $clean);
        $this->assertSame(['3', '4'], $clean[0]['options']);

        $this->expectException(\RuntimeException::class);
        $gen->validate(['nope' => true]);
    }

    public function test_admin_can_save_quiz_manually_and_ai_is_gated(): void
    {
        putenv('ANTHROPIC_API_KEY=');
        $course = $this->makeCourse();
        $lesson = $this->makeLesson($course, ['type' => 'quiz']);
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.quizzes.edit', $lesson))->assertOk()->assertSee('غير مفعّل');
        $this->put(route('admin.quizzes.update', $lesson), ['pass_score' => 70, 'questions' => [
            ['question' => 'عاصمة المغرب؟', 'options' => "الدار البيضاء\nالرباط\nفاس", 'correct' => '2'],
        ]])->assertRedirect();
        $this->assertSame([1], $lesson->quiz->questions()->first()->correct);
        $this->post(route('admin.quizzes.generate', $lesson), ['source_text' => str_repeat('نص ', 100), 'count' => 3, 'language' => 'ar', 'consent' => 1])
            ->assertSessionHas('error');
    }

    public function test_settings_and_homepage_sections(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('admin.settings.update', 'whatsapp'), ['whatsapp_number' => '+212 600 111 222', 'whatsapp_template' => 'بدون متغيرات'])
            ->assertSessionHasErrors('whatsapp_template');
        $this->put(route('admin.settings.update', 'whatsapp'), ['whatsapp_number' => '+212 600 111 222', 'whatsapp_template' => 'طلب {course} رقم {reference}'])->assertSessionHas('success');
        $this->assertSame('212600111222', Settings::get('whatsapp_number'));

        $sections = collect(Settings::homeSections())->map(fn ($s, $i) => ['key' => $s['key'], 'enabled' => $s['key'] === 'how' ? null : 1, 'order' => $i])->all();
        $this->put(route('admin.settings.update', 'home'), ['sections' => $sections])->assertSessionHas('success');
        auth()->logout();
        $this->get(route('home'))->assertDontSee('من التسجيل إلى الشهادة في أربع خطوات');

        $this->actingAs($admin)->put(route('admin.users.update', $admin), ['role' => 'student'])->assertSessionHas('error');
        $this->put(route('admin.settings.update', 'ai'), ['ai_api_key' => 'sk-ant-test']);
        $this->assertTrue(app(QuizGenerator::class)->enabled());
        $this->assertStringNotContainsString('sk-ant-test', Setting::find('ai_api_key')->value); // encrypted at rest
    }
}
