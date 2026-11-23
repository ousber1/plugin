<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Page;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaseSeeder::class);
    }

    public function test_dashboard_shows_kpis_charts_activity_and_health(): void
    {
        $course = $this->makeCourse(['title' => ['ar' => 'دورة الإحصاء']]);
        $this->makeLesson($course);
        $student = $this->student();
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id]);

        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()
            ->assertSee('تسجيلات جديدة')->assertSee('طلاب جدد')->assertSee('data-linechart', false)
            ->assertSee('الدورات الأكثر تسجيلًا')->assertSee('دورة الإحصاء')->assertSee('آخر النشاطات')
            ->assertSee('صحة الموقع')->assertSee('عرض البيانات كجدول');

        $instructor = User::factory()->instructor()->create();
        $course->assignees()->attach($instructor);
        $this->actingAs($instructor)->get(route('admin.dashboard'))->assertOk()->assertSee('دورة الإحصاء')->assertDontSee('صحة الموقع');
    }

    public function test_home_builder_saves_sanitized_blocks_and_publishes(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.builder.edit', 'home'))->assertOk()->assertSee('window.BUILDER', false)->assertSee('حفظ ونشر');

        $blocks = [
            ['id' => 'abc123defg', 'type' => 'hero', 'content' => ['title' => ['ar' => 'مرحبًا بكم <script>alert(1)</script>', 'fr' => 'Bienvenue'], 'btn1_label' => ['ar' => 'ابدأ'], 'btn1_url' => 'javascript:alert(1)', 'height' => 'm'], 'style' => ['bg' => 'gradient', 'pad' => 'xl']],
            ['type' => 'text', 'content' => ['body' => ['ar' => '<p>نص <b>عريض</b></p><script>bad()</script><img src=x onerror=alert(1)>']]],
            ['type' => 'evil-type', 'content' => []],
            ['type' => 'faq', 'content' => ['title' => ['ar' => 'أسئلة'], 'items' => [['q' => ['ar' => 'هل الدورات مجانية؟'], 'a' => ['ar' => 'نعم']]]]],
            ['type' => 'features', 'content' => ['items' => [['icon' => 'not-an-icon', 'title' => ['ar' => 'ميزة']]]], 'hidden' => true],
        ];
        $res = $this->putJson(route('admin.builder.save', 'home'), ['blocks' => $blocks])->assertOk()->json('blocks');
        $this->assertCount(4, $res);
        $this->assertStringNotContainsString('<script', json_encode($res));
        $this->assertSame('', $res[0]['content']['btn1_url']);
        $this->assertSame('check-circle', $res[3]['content']['items'][0]['icon']);
        $this->assertStringNotContainsString('onerror', $res[1]['content']['body']['ar']);
        $this->assertTrue(Settings::get('home_builder_enabled'));

        auth()->logout();
        $this->get(route('home'))->assertOk()->assertSee('مرحبًا بكم')->assertDontSee('ميزة')
            ->assertSee('هل الدورات مجانية؟')->assertSee('<b>عريض</b>', false)->assertDontSee('<script>bad', false);
        $this->get(route('locale.switch', 'fr'));
        $this->get(route('home'))->assertSee('Bienvenue');

        // Back to the default design
        $this->actingAs($admin)->post(route('admin.builder.disable', 'home'))->assertRedirect();
        auth()->logout();
        $this->get(route('home'))->assertDontSee('هل الدورات مجانية؟');
    }

    public function test_page_builder_preview_upload_and_permissions(): void
    {
        $page = Page::where('slug', 'about')->first();
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.builder.edit', $page->id))->assertOk();

        $up = $this->postJson(route('admin.builder.upload'), ['image' => UploadedFile::fake()->image('hero.jpg', 800, 400)])->assertOk();
        Storage::disk('public')->assertExists($up->json('path'));
        $this->assertStringContainsString('/storage/builder/', $up->json('url'));
        $this->postJson(route('admin.builder.upload'), ['image' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertStatus(422);

        $blocks = [['type' => 'image_text', 'content' => ['image' => $up->json('path'), 'title' => ['ar' => 'قصتنا'], 'body' => ['ar' => '<p>بدأنا سنة 2020</p>']]],
            ['type' => 'whatsapp', 'content' => ['title' => ['ar' => 'اسألنا']]], ['type' => 'video', 'content' => ['url' => 'https://youtu.be/dQw4w9WgXcQ']]];
        $this->post(route('admin.builder.preview', $page->id), ['blocks' => json_encode($blocks), 'lang' => 'ar'])->assertOk()
            ->assertSee('قصتنا')->assertSee('data-block-id', false)->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);
        $this->putJson(route('admin.builder.save', $page->id), ['blocks' => $blocks])->assertOk();
        $this->assertTrue($page->fresh()->use_builder);

        auth()->logout();
        $this->get(route('about'))->assertOk()->assertSee('قصتنا')->assertSee('اسألنا')->assertSee('wa.me', false)->assertDontSee('data-block-id', false);

        // Students and guests cannot use the builder.
        $this->actingAs($this->student())->get(route('admin.builder.edit', 'home'))->assertForbidden();
        $this->putJson(route('admin.builder.save', 'home'), ['blocks' => []])->assertForbidden();
        $instructor = User::factory()->instructor()->create();
        $this->actingAs($instructor)->get(route('admin.builder.edit', 'home'))->assertForbidden();
    }

    public function test_uploaded_images_are_served_without_storage_symlink(): void
    {
        Storage::disk('public')->put('courses/thumb.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
        $this->get('/storage/courses/thumb.png')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/storage/courses/missing.png')->assertNotFound();
        $this->get('/storage/../.env')->assertNotFound();
        Storage::disk('public')->put('notes.php', '<?php echo 1;');
        $this->get('/storage/notes.php')->assertNotFound();

        $course = $this->makeCourse();
        $course->forceFill(['thumbnail_path' => 'courses/thumb.png'])->save();
        $this->assertSame(url('storage/courses/thumb.png'), $course->thumbnailUrl());
        $this->get(route('courses.show', $course))->assertSee(url('storage/courses/thumb.png'), false);
    }

    public function test_whatsapp_float_appears_everywhere_and_is_configurable(): void
    {
        Settings::set(['whatsapp_number' => '212611223344']);
        $course = $this->makeCourse();
        $lesson = $this->makeLesson($course, ['is_preview' => true]);
        foreach ([route('home'), route('courses.index'), route('courses.show', $course), route('learn.show', [$course, $lesson]), route('tools.index'), route('contact'), route('verify.index')] as $url) {
            $this->get($url)->assertOk()->assertSee('class="wa-float', false)->assertSee('wa.me/212611223344', false);
        }
        Settings::set(['wa_float_side' => 'left', 'wa_float_label' => ['ar' => 'اسألنا الآن']]);
        $this->get(route('home'))->assertSee('wa-float--left', false)->assertSee('اسألنا الآن');
        Settings::set(['wa_float_enabled' => false]);
        $this->get(route('courses.index'))->assertDontSee('class="wa-float', false);
    }

    public function test_appearance_maintenance_and_custom_code(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('admin.settings.update', 'appearance'), [
            'theme_primary' => '#0f766e', 'theme_accent' => '#f59e0b', 'theme_bg' => '#ffffff', 'theme_footer' => '#111827',
            'theme_radius' => 'round', 'theme_font' => 'tajawal', 'custom_css' => '.hero{letter-spacing:1px}', 'sticky_header' => 1,
        ])->assertSessionHas('success');
        $this->put(route('admin.settings.update', 'advanced'), [
            'head_code' => '<meta name="google-site-verification" content="abc">', 'maintenance_enabled' => 1,
            'maintenance_message' => ['ar' => 'عودة قريبة جدًا'], 'cookie_banner' => 1, 'cookie_text' => ['ar' => 'نستعمل الكوكيز'],
        ])->assertSessionHas('success');

        // Staff still see the site, with theme and code applied.
        $this->get(route('home'))->assertOk()->assertSee('--indigo-700: #0f766e', false)->assertSee('letter-spacing:1px', false)
            ->assertSee('google-site-verification', false)->assertDontSee('<div class="topbar">', false)->assertSee('نستعمل الكوكيز');
        auth()->logout();
        $this->get(route('home'))->assertStatus(503)->assertSee('عودة قريبة جدًا');
        $this->get(route('login'))->assertOk();
        Settings::set(['maintenance_enabled' => false]);
        $this->get(route('courses.index'))->assertOk();
    }

    public function test_pending_database_update_banner_and_one_click_migrate(): void
    {
        $admin = $this->admin();
        \Illuminate\Support\Facades\DB::table('migrations')->where('migration', '2026_11_23_000400_add_builder_to_pages')->delete();
        \Illuminate\Support\Facades\Schema::table('pages', fn ($t) => $t->dropColumn(['blocks', 'use_builder']));

        $this->actingAs($admin)->get(route('admin.courses.index'))->assertSee('تحديث قاعدة البيانات');
        $this->post(route('admin.system.migrate'))->assertSessionHas('success');
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('pages', 'blocks'));
        $this->get(route('admin.courses.index'))->assertDontSee('تحتاج قاعدة البيانات إلى تحديث');
        $this->actingAs($this->student())->post(route('admin.system.migrate'))->assertForbidden();
    }
}
