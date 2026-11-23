<?php

namespace Tests\Feature;

use App\Models\CertificateRequest;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminUsersAndGuestCertificateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaseSeeder::class);
        Settings::set(['whatsapp_number' => '212611223344']);
    }

    public function test_visitor_without_account_goes_straight_to_whatsapp(): void
    {
        $course = $this->makeCourse(['title' => ['ar' => 'دورة التسويق']]);
        $this->makeLesson($course);

        $this->get(route('courses.show', $course))->assertOk()->assertSee(route('certificates.guest', $course), false)
            ->assertSee('اطلب شهادتك عبر واتساب');

        $res = $this->post(route('certificates.guest', $course));
        $location = $res->headers->get('Location');
        $this->assertStringStartsWith('https://wa.me/212611223344?text=', $location);
        $req = CertificateRequest::firstOrFail();
        $this->assertNull($req->user_id);
        $this->assertStringContainsString(rawurlencode($req->reference), $location);
        $this->assertStringContainsString(rawurlencode('دورة التسويق'), $location);

        // The same visitor clicking again reuses the reference.
        $this->post(route('certificates.guest', $course));
        $this->assertSame(1, CertificateRequest::count());

        // Staff see it as a visitor request and can process it.
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.certificate-requests.index'))->assertOk()->assertSee('زائر (واتساب)');
        $this->get(route('admin.certificate-requests.show', $req))->assertOk()->assertSee('زائر بدون حساب');
        $this->put(route('admin.certificate-requests.update', $req), ['status' => 'received', 'printed_name' => 'Yassine'])->assertSessionHas('success');
        $this->get(route('admin.certificates.create', ['request' => $req->id]))->assertOk()->assertSee('Yassine');
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('زائر طلب شهادة');

        // When completion is required, visitors are sent to register instead.
        auth()->logout();
        session()->flush();
        Settings::set(['certificate_requires_completion' => true]);
        $this->post(route('certificates.guest', $course))->assertRedirect();
        $this->assertSame(1, CertificateRequest::count());
    }

    public function test_admin_adds_teachers_and_students(): void
    {
        $course = $this->makeCourse(['title' => ['ar' => 'دورة إكسل']]);
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertSee('إضافة مدرّس')->assertSee('إضافة طالب');
        $this->get(route('admin.users.create', ['role' => 'instructor']))->assertOk()->assertSee('إضافة مدرّس');

        $this->post(route('admin.users.store'), ['name' => 'أستاذة سارة', 'email' => 'Sara@Example.com', 'phone' => '0612345678', 'role' => 'instructor', 'password' => 'teach-pass-1', 'courses' => [$course->id], 'headline' => 'مدرّسة إكسل'])
            ->assertRedirect()->assertSessionHas('new_credentials');
        $teacher = User::where('email', 'sara@example.com')->firstOrFail();
        $this->assertSame('instructor', $teacher->role->value);
        $this->assertTrue($teacher->hasVerifiedEmail());
        $this->assertTrue(Hash::check('teach-pass-1', $teacher->password));
        $this->assertTrue($teacher->canManageCourse($course));
        $this->get(route('admin.users.edit', $teacher))->assertSee('بيانات الدخول')->assertSee('teach-pass-1')->assertSee('wa.me/212612345678', false);

        // Student with generated password and direct enrolment.
        $this->post(route('admin.users.store'), ['name' => 'Karim', 'email' => 'karim@example.com', 'role' => 'student', 'enroll' => [$course->id]])->assertRedirect();
        $student = User::where('email', 'karim@example.com')->firstOrFail();
        $this->assertTrue(Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->exists());
        $password = session('new_credentials')['password'];
        $this->assertTrue(Hash::check($password, $student->password));

        $this->post(route('admin.users.store'), ['name' => 'Dup', 'email' => 'karim@example.com', 'role' => 'student'])->assertSessionHasErrors('email');

        // Reset password from the edit page.
        $this->put(route('admin.users.update', $student), ['role' => 'student', 'is_active' => 1, 'new_password' => 'new-pass-123'])->assertSessionHas('success');
        $this->assertTrue(Hash::check('new-pass-123', $student->fresh()->password));

        $this->actingAs($student)->post(route('admin.users.store'), ['name' => 'X', 'email' => 'x@example.com', 'role' => 'admin'])->assertForbidden();
    }

    public function test_footer_logo_can_be_changed(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin())->put(route('admin.layout.update'), [
            'header_cta_action' => 'whatsapp', 'footer_show_logo' => 1, 'footer_logo_height' => 60,
            'footer_logo' => UploadedFile::fake()->image('white-logo.png', 300, 90),
            'columns' => [['type' => 'categories']],
        ])->assertSessionHas('success');
        $path = Settings::get('footer_logo_path');
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->get(route('home'))->assertSee('class="footer-logo"', false)->assertSee(basename($path))->assertSee('height:60px', false);

        $this->put(route('admin.layout.update'), ['header_cta_action' => 'whatsapp', 'columns' => [['type' => 'categories']]]);
        $this->get(route('home'))->assertDontSee('class="footer-logo"', false);
    }

    public function test_accreditation_section_is_managed_from_settings(): void
    {
        $this->get(route('home'))->assertDontSee('class="accr"', false);
        Storage::fake('public');
        $this->actingAs($this->admin())->get(route('admin.settings.edit', 'accreditation'))->assertOk()->assertSee('معلومات الاعتماد');
        $this->put(route('admin.settings.update', 'accreditation'), [
            'accreditation_enabled' => 1, 'accreditation_on_verify' => 1, 'accreditation_verify_button' => 1,
            'accreditation_badge' => ['ar' => 'اعتماد دولي'], 'accreditation_title' => ['ar' => 'شهاداتنا معتمدة من جهة دولية'],
            'accreditation_text' => ['ar' => 'نص الاعتماد'], 'accreditation_doc_label' => ['ar' => 'اطّلع على شهادة الاعتماد'],
            'accreditation_logo' => UploadedFile::fake()->image('seal.png', 400, 400),
            'accreditation_doc' => UploadedFile::fake()->create('accreditation.pdf', 120, 'application/pdf'),
            'facts' => [['icon' => 'hash', 'label' => ['ar' => 'رقم التسجيل'], 'value' => ['ar' => 'N° 15636127']], ['icon' => 'globe', 'label' => ['ar' => 'بلد التسجيل'], 'value' => ['ar' => '']]],
        ])->assertSessionHas('success');
        $this->assertCount(1, Settings::get('accreditation_facts'));
        auth()->logout();
        $this->get(route('home'))->assertSee('class="accr"', false)->assertSee('شهاداتنا معتمدة من جهة دولية')->assertSee('N° 15636127')
            ->assertSee('اطّلع على شهادة الاعتماد')->assertSee(route('verify.index'));
        $this->get(route('verify.index'))->assertSee('شهاداتنا معتمدة من جهة دولية');
        $this->get(route('tools.show', 'certificate-designer'))->assertOk()->assertSee('js/tools/cert.js', false);
    }
}
