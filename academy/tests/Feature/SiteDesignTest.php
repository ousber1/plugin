<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\CertificateRequest;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteDesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaseSeeder::class);
        Settings::set(['whatsapp_number' => '212611223344']);
    }

    private function center(array $attrs = []): Center
    {
        return Center::create($attrs + [
            'name' => ['ar' => 'مركز المعاريف', 'fr' => 'Centre Maârif'], 'slug' => 'casa-maarif',
            'city' => ['ar' => 'الدار البيضاء', 'fr' => 'Casablanca'], 'address' => ['ar' => 'شارع الزرقطوني'],
            'hours' => ['ar' => 'الإثنين – السبت 9:00–19:00'], 'phone' => '0522000000', 'whatsapp' => '212655443322', 'is_active' => true,
        ]);
    }

    public function test_header_certificate_button_is_editable_from_the_dashboard(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('header-cta', false)->assertSee('اطلب شهادتك الآن')->assertSee('wa.me/212611223344', false);

        $this->actingAs($this->admin())->get(route('admin.layout.edit'))->assertOk()->assertSee('زر الدعوة في الهيدر');
        $this->put(route('admin.layout.update'), [
            'header_cta_enabled' => 1, 'header_cta_label' => ['ar' => 'احصل على شهادتك'], 'header_cta_action' => 'whatsapp',
            'header_cta_message' => ['ar' => 'مرحبا، شهادتي من فضلكم'], 'header_show_auth' => 1, 'header_show_lang' => 1,
            'footer_cta_enabled' => 1, 'footer_cta_title' => ['ar' => 'انضم إلينا'], 'footer_cta_button' => ['ar' => 'ابدأ'], 'footer_cta_url' => '/courses',
            'footer_copyright' => ['ar' => '© {year} {site} — كل الحقوق'],
            'columns' => [
                ['type' => 'links', 'title' => ['ar' => 'روابطنا'], 'links' => [['url' => 'https://example.com/a', 'label' => ['ar' => 'رابط خارجي']], ['url' => 'javascript:alert(1)', 'label' => ['ar' => 'سيئ']]]],
                ['type' => 'none'], ['type' => 'contact', 'title' => ['ar' => 'اتصل بنا']],
            ],
            'bottom_links' => [['url' => '/privacy', 'label' => ['ar' => 'الخصوصية']]],
        ])->assertSessionHasErrors('columns.0.links.1.url');

        $this->put(route('admin.layout.update'), [
            'header_cta_enabled' => 1, 'header_cta_label' => ['ar' => 'احصل على شهادتك'], 'header_cta_action' => 'whatsapp',
            'header_cta_message' => ['ar' => 'مرحبا، شهادتي من فضلكم'], 'header_show_auth' => 1, 'header_show_lang' => 1,
            'footer_cta_enabled' => 1, 'footer_cta_title' => ['ar' => 'انضم إلينا'], 'footer_cta_button' => ['ar' => 'ابدأ'], 'footer_cta_url' => '/courses',
            'footer_copyright' => ['ar' => '© {year} {site} — كل الحقوق'],
            'columns' => [
                ['type' => 'links', 'title' => ['ar' => 'روابطنا'], 'links' => [['url' => 'https://example.com/a', 'label' => ['ar' => 'رابط خارجي']]]],
                ['type' => 'none'], ['type' => 'contact', 'title' => ['ar' => 'اتصل بنا']],
            ],
            'bottom_links' => [['url' => '/privacy', 'label' => ['ar' => 'الخصوصية']]],
        ])->assertSessionHas('success');
        $this->assertCount(2, Settings::get('footer_columns'));

        auth()->logout();
        $this->get(route('courses.index'))->assertOk()->assertSee('احصل على شهادتك')
            ->assertSee(rawurlencode('مرحبا، شهادتي من فضلكم'), false)
            ->assertSee('روابطنا')->assertSee('رابط خارجي')->assertSee('اتصل بنا')->assertSee('انضم إلينا')
            ->assertSee('© '.date('Y').' أكاديمية نبراس — كل الحقوق')->assertDontSee('مجالات الدورات</h2>', false);

        Settings::set(['header_cta_action' => 'url', 'header_cta_url' => '/verify']);
        $this->get(route('home'))->assertSee('href="/verify"', false);
        Settings::set(['header_cta_enabled' => false]);
        $this->get(route('home'))->assertDontSee('header-cta', false);
    }

    public function test_themes_apply_palette_and_structural_styles(): void
    {
        $this->actingAs($this->admin())->get(route('admin.settings.edit', 'appearance'))->assertOk()->assertSee('الثيمات الجاهزة')->assertSee('أطلس')->assertSee('ليلي');
        $this->assertGreaterThanOrEqual(8, count(\App\Support\Themes::all()));

        $this->post(route('admin.themes.apply', 'sahara'))->assertSessionHas('success');
        $this->assertSame('sahara', Settings::get('site_theme'));
        $this->get(route('home'))->assertSee('th-sahara', false)->assertSee('hdr-dark', false)->assertSee('hero-zellige', false)->assertSee('--indigo-700: #a6461f', false);

        $this->post(route('admin.themes.apply', 'minimal'));
        $this->get(route('home'))->assertSee('btns-square', false)->assertSee('cards-flat', false)->assertSee('ftr-light', false)
            ->assertSee('--on-accent: #ffffff', false); // blue accent gets white text
        $this->post(route('admin.themes.apply', 'nope'))->assertNotFound();
        $this->actingAs($this->student())->post(route('admin.themes.apply', 'atlas'))->assertForbidden();
    }

    public function test_centers_admin_public_pages_and_navigation(): void
    {
        $course = $this->makeCourse(['title' => ['ar' => 'دورة البرمجة']]);
        $this->get(route('home'))->assertDontSee(route('centers.index'));

        Storage::fake('public');
        $this->actingAs($this->admin())->post(route('admin.centers.store'), [
            'name' => ['ar' => 'مركز أكدال', 'fr' => 'Centre Agdal'], 'city' => ['ar' => 'الرباط', 'fr' => 'Rabat'],
            'address' => ['ar' => 'شارع فال ولد عمير'], 'hours' => ['ar' => 'كل الأيام'], 'description' => ['ar' => '<p>مركز حديث</p><script>x()</script>'],
            'phone' => '0537000000', 'whatsapp' => '+212 677 88 99 00', 'map_url' => 'https://maps.app.goo.gl/abc',
            'is_active' => 1, 'course_ids' => [$course->id], 'image' => UploadedFile::fake()->image('c.jpg', 2400, 1200),
        ])->assertRedirect(route('admin.centers.index'));
        $center = Center::firstOrFail();
        $this->assertSame('rabat-centre-agdal', $center->slug);
        $this->assertSame('212677889900', $center->whatsapp);
        $this->assertStringNotContainsString('<script', $center->tr('description'));
        $this->assertStringEndsWith('.webp', $center->image_path); // compressed on upload
        [$w] = getimagesize(Storage::disk('public')->path($center->image_path));
        $this->assertSame(1600, $w);
        $this->get(route('admin.centers.index'))->assertOk()->assertSee('مركز أكدال');

        auth()->logout();
        $this->get(route('home'))->assertSee(route('centers.index'))->assertSee('مراكزنا في المغرب');
        $this->get(route('centers.index'))->assertOk()->assertSee('مركز أكدال')->assertSee('الرباط');
        $this->get(route('centers.show', $center))->assertOk()->assertSee('شارع فال ولد عمير')->assertSee('دورة البرمجة')
            ->assertSee('wa.me/212677889900', false)->assertSee('maps.google.com/maps?q=', false)->assertSee('EducationalOrganization');
        $this->get(route('courses.show', $course))->assertSee('متوفرة أيضًا حضوريًا في:');
        $this->get(route('sitemap'))->assertSee(route('centers.show', $center));

        Settings::set(['features' => array_merge(Settings::get('features'), ['centers' => false])]);
        $this->get(route('centers.index'))->assertNotFound();
    }

    public function test_student_picks_center_and_requests_certificate_pickup(): void
    {
        $center = $this->center();
        $course = $this->makeCourse(['title' => ['ar' => 'دورة إكسل']]);
        $this->makeLesson($course);

        $this->post(route('register'), [
            'name' => 'سلمى', 'email' => 'salma@example.com', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
            'terms' => 1, 'center_id' => $center->id,
        ])->assertRedirect();
        $user = User::where('email', 'salma@example.com')->firstOrFail();
        $this->assertSame($center->id, $user->center_id);

        $this->get(route('courses.show', $course))->assertSee('استلام من أحد مراكزنا')->assertSee('مركز المعاريف');
        $res = $this->post(route('certificates.request', $course), ['delivery' => 'pickup', 'center_id' => $center->id]);
        $res->assertRedirect();
        $this->assertStringStartsWith('https://wa.me/212655443322', $res->headers->get('Location')); // the centre's own number
        $this->assertStringContainsString(rawurlencode('من مركز مركز المعاريف'), $res->headers->get('Location'));
        $req = CertificateRequest::firstOrFail();
        $this->assertTrue($req->isPickup());

        // Switching to home delivery while still pending updates the request and uses the main number.
        $res = $this->post(route('certificates.request', $course), ['delivery' => 'shipping']);
        $this->assertStringStartsWith('https://wa.me/212611223344', $res->headers->get('Location'));
        $this->assertFalse($req->fresh()->isPickup());

        $this->actingAs($this->admin())->get(route('admin.users.index', ['center' => $center->id]))->assertSee('سلمى');
        $this->get(route('admin.certificate-requests.index'))->assertOk()->assertSee('الاستلام من مركز');
        $this->get(route('admin.dashboard'))->assertSee('الطلاب حسب المركز');
    }

    public function test_optimization_page_tools_and_seo_settings(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.optimize.index'))->assertOk()->assertSee('نتيجة SEO')->assertSee('فحص SEO')->assertSee('ضغط الصور');

        $this->put(route('admin.optimize.update'), [
            'image_optimize' => 1, 'image_max_width' => 1400, 'image_quality' => 80, 'lazy_images' => 1, 'html_minify' => 1,
            'seo_title_suffix' => 1, 'org_schema' => 1, 'google_verification' => 'abcDEF123_-',
            'robots_extra' => "Disallow: /p/old\nnot a rule\nAllow: /courses",
        ])->assertSessionHas('success');
        $this->put(route('admin.optimize.update'), ['image_max_width' => 1400, 'image_quality' => 80, 'google_verification' => '<meta name="x">'])
            ->assertSessionHasErrors('google_verification');

        $robots = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString('Disallow: /p/old', $robots);
        $this->assertStringNotContainsString('not a rule', $robots);

        auth()->logout();
        $html = $this->get(route('home'))->assertOk()->assertSee('google-site-verification" content="abcDEF123_-"', false)
            ->assertSee('"@type":"EducationalOrganization"', false)->getContent();
        $this->assertStringNotContainsString("\n    <", $html); // minified

        // Each tool runs and reports back.
        Storage::fake('public');
        Storage::disk('public')->put('courses/big.jpg', UploadedFile::fake()->image('big.jpg', 3000, 2000)->size(900)->get());
        $this->actingAs($admin);
        foreach (['clear', 'cleanup', 'images'] as $action) {
            $this->post(route('admin.optimize.run', $action))->assertSessionHas('success');
        }
        $this->post(route('admin.optimize.run', 'nope'))->assertNotFound();
        $this->actingAs($this->student())->get(route('admin.optimize.index'))->assertForbidden();
    }
}
