<?php

namespace Tests\Feature;

use App\Models\CertificateRequest;
use App\Models\IssuedCertificate;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    private function issue(): IssuedCertificate
    {
        $course = $this->makeCourse(['title' => ['ar' => 'دورة Excel']]);
        $student = $this->student();
        $req = new CertificateRequest(['reference' => 'CR-26-ABCDEF', 'user_id' => $student->id, 'course_id' => $course->id, 'status' => 'shipped']);
        $req->forceFill(['shipping_address' => 'عنوان سري جدًا', 'private_notes' => 'ملاحظة داخلية', 'tracking_number' => 'TRK999'])->save();

        $this->actingAs($this->admin())->post(route('admin.certificates.store'), [
            'certificate_request_id' => $req->id, 'holder_name' => 'Youssef Benali', 'course_title' => 'دورة Excel', 'issued_on' => now()->toDateString(),
        ])->assertRedirect();
        auth()->logout();

        return IssuedCertificate::firstOrFail();
    }

    public function test_valid_code_shows_only_public_details(): void
    {
        $cert = $this->issue();
        $this->assertMatchesRegularExpression('/^NB-[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}$/', $cert->code);

        $this->get(route('verify.show', strtolower($cert->code)))->assertOk()
            ->assertSee('شهادة صالحة')->assertSee('Youssef Benali')->assertSee('دورة Excel')
            ->assertDontSee('عنوان سري')->assertDontSee('ملاحظة داخلية')->assertDontSee('TRK999')->assertDontSee('CR-26-ABCDEF');
        $this->get(route('verify.index', ['code' => ' '.$cert->code.' ']))->assertRedirect(route('verify.show', $cert->code));
    }

    public function test_missing_code_returns_not_found_result(): void
    {
        $this->get(route('verify.show', 'NB-0000-0000'))->assertNotFound()->assertSee('لم نجد شهادة بهذا الرمز');
    }

    public function test_revoked_code_is_reported_without_private_reason(): void
    {
        $cert = $this->issue();
        $this->actingAs($this->admin())->post(route('admin.certificates.revoke', $cert), ['reason' => 'خطأ في الاسم الداخلي'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'certificate.revoked']);
        auth()->logout();

        $this->get(route('verify.show', $cert->code))->assertOk()->assertSee('هذه الشهادة ملغاة')
            ->assertDontSee('خطأ في الاسم الداخلي')->assertDontSee('Youssef Benali');
    }

    public function test_public_fields_follow_settings_and_qr_is_available(): void
    {
        $cert = $this->issue();
        Settings::set(['verify_public_fields' => ['course_title']]);
        $this->get(route('verify.show', $cert->code))->assertSee('دورة Excel')->assertDontSee('Youssef Benali');

        $this->actingAs($this->admin())->get(route('admin.certificates.qr', $cert))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_verification_is_rate_limited(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->get(route('verify.show', 'NB-AAAA-AAA'.$i));
        }
        $this->get(route('verify.show', 'NB-AAAA-BBBB'))->assertStatus(429);
    }
}
