<?php

namespace Tests\Feature;

use App\Enums\CertificateStatus;
use App\Models\CertificateRequest;
use App\Models\Enrollment;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function setupCourse(bool $requireQuiz = true): array
    {
        Settings::set(['whatsapp_number' => '212611223344', 'certificate_requires_completion' => true]);
        $course = $this->makeCourse(['slug' => 'ai-intro', 'title' => ['ar' => 'مدخل إلى الذكاء الاصطناعي'], 'requires_quiz_pass' => $requireQuiz]);
        $video = $this->makeLesson($course, ['type' => 'text', 'body' => ['ar' => '<p>نص</p>']]);
        $quizLesson = $this->makeLesson($course, ['type' => 'quiz', 'sort_order' => 2]);
        $this->addQuiz($quizLesson, 100);
        $student = $this->student();
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id]);

        return [$course, $video, $quizLesson, $student];
    }

    public function test_progress_and_quiz_results_determine_eligibility(): void
    {
        [$course, $text, $quizLesson, $student] = $this->setupCourse();
        $this->actingAs($student);

        // Nothing done yet: request refused with explanation.
        $this->post(route('certificates.request', $course))->assertRedirect(route('courses.show', $course))->assertSessionHas('error');
        $this->assertDatabaseCount('certificate_requests', 0);
        $this->get(route('courses.show', $course))->assertSee('المتطلبات المتبقية')->assertSee('0 من 2');

        $this->post(route('learn.complete', [$course, $text]))->assertRedirect();
        // Quiz lessons cannot be "completed" manually.
        $this->post(route('learn.complete', [$course, $quizLesson]))->assertSessionHas('error');

        // Failing attempt: 50% < 100% required.
        $q = $quizLesson->quiz->questions;
        $this->post(route('learn.quiz', [$course, $quizLesson]), ['answers' => [$q[0]->id => [1], $q[1]->id => [0]]])->assertSessionHas('error');
        $this->post(route('certificates.request', $course))->assertSessionHas('error');

        // Passing attempt marks the quiz lesson complete.
        $this->post(route('learn.quiz', [$course, $quizLesson]), ['answers' => [$q[0]->id => [1], $q[1]->id => [0, 2]]])->assertSessionHas('success');
        $this->get(route('courses.show', $course))->assertSee('اطلب شهادتك عبر واتساب')->assertSee('100%');
    }

    public function test_quiz_requirement_is_configurable(): void
    {
        [$course, $text, $quizLesson, $student] = $this->setupCourse(requireQuiz: false);
        $quizLesson->forceFill(['is_required' => false])->save();
        $this->actingAs($student)->post(route('learn.complete', [$course, $text]));
        $this->post(route('certificates.request', $course))->assertRedirectContains('https://wa.me/');
    }

    public function test_eligible_student_opens_whatsapp_and_repeat_clicks_reuse_reference(): void
    {
        [$course, $text, $quizLesson, $student] = $this->setupCourse(requireQuiz: false);
        $quizLesson->forceFill(['is_required' => false])->save();
        $this->actingAs($student)->post(route('learn.complete', [$course, $text]));

        $first = $this->post(route('certificates.request', $course));
        $request = CertificateRequest::firstOrFail();
        $this->assertSame(CertificateStatus::PendingContact, $request->status);
        $this->assertMatchesRegularExpression('/^CR-\d{2}-[A-Z0-9]{6}$/', $request->reference);

        $expected = 'https://wa.me/212611223344?text='.rawurlencode("السلام عليكم، أود طلب الشهادة الورقية لدورة مدخل إلى الذكاء الاصطناعي.\nرقم طلب الشهادة: {$request->reference}.");
        $first->assertRedirect($expected);
        $this->assertStringNotContainsString('address', strtolower($first->headers->get('Location')));

        $this->post(route('certificates.request', $course))->assertRedirect($expected);
        $this->post(route('certificates.request', $course))->assertRedirect($expected);
        $this->assertDatabaseCount('certificate_requests', 1);

        $this->get(route('dashboard'))->assertSee($request->reference)->assertSee('بانتظار التواصل');
    }

    public function test_verified_email_is_required_when_configured(): void
    {
        [$course, $text, $quizLesson, $student] = $this->setupCourse(requireQuiz: false);
        $quizLesson->forceFill(['is_required' => false])->save();
        Settings::set(['require_verified_email_for_certificate' => true]);
        $student->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($student)->post(route('learn.complete', [$course, $text]));
        $this->post(route('certificates.request', $course))->assertSessionHas('error');
        Settings::set(['require_verified_email_for_certificate' => false]);
        $this->post(route('certificates.request', $course))->assertRedirectContains('wa.me');
    }

    public function test_admin_ships_certificate_and_student_sees_status_and_tracking(): void
    {
        [$course, $text, $quizLesson, $student] = $this->setupCourse(requireQuiz: false);
        $quizLesson->forceFill(['is_required' => false])->save();
        $this->actingAs($student)->post(route('learn.complete', [$course, $text]));
        $this->post(route('certificates.request', $course));
        $request = CertificateRequest::firstOrFail();

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.certificate-requests.index', ['q' => $request->reference]))->assertSee($request->reference);
        $this->put(route('admin.certificate-requests.update', $request), [
            'status' => 'shipped', 'printed_name' => 'Sara El Amrani', 'shipping_address' => 'حي السلام، زنقة 5، الرباط',
            'carrier' => 'Amana', 'tracking_number' => 'AM123456789MA', 'show_tracking' => 1, 'private_notes' => 'دفع رسوم الشحن عند التسليم',
        ])->assertRedirect();

        $request->refresh();
        $this->assertSame(CertificateStatus::Shipped, $request->status);
        $this->assertNotNull($request->received_at);
        $this->assertNotNull($request->shipped_on);
        $this->assertDatabaseHas('audit_logs', ['action' => 'certificate_request.status_changed', 'subject_id' => $request->id]);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertSee('تم الشحن')->assertSee('AM123456789MA')->assertSee('Amana')
            ->assertDontSee('حي السلام')->assertDontSee('دفع رسوم الشحن');
        $this->get(route('courses.show', $course))->assertSee('تم الشحن')->assertSee('AM123456789MA');

        // Hidden tracking is not shown.
        $request->forceFill(['show_tracking' => false])->save();
        $this->get(route('dashboard'))->assertDontSee('AM123456789MA');
    }

    public function test_no_pdf_certificate_is_generated_on_completion(): void
    {
        [$course, $text, $quizLesson, $student] = $this->setupCourse(requireQuiz: false);
        $quizLesson->forceFill(['is_required' => false])->save();
        $this->actingAs($student)->post(route('learn.complete', [$course, $text]));
        $this->get(route('courses.show', $course))->assertDontSee('.pdf')->assertDontSee('تحميل الشهادة');
    }

    public function test_by_default_students_can_request_before_starting_the_course(): void
    {
        Settings::set(['whatsapp_number' => '212611223344']);
        $course = $this->makeCourse(['slug' => 'excel', 'title' => ['ar' => 'دورة إكسل'], 'requires_quiz_pass' => true]);
        $this->makeLesson($course);
        $quizLesson = $this->makeLesson($course, ['type' => 'quiz', 'sort_order' => 2]);
        $this->addQuiz($quizLesson);

        // Visitor: the WhatsApp button leads to sign-up, then back to the course.
        $this->get(route('courses.show', $course))->assertSee('اطلب شهادتك عبر واتساب')
            ->assertSee(e(route('register', ['intended' => route('courses.show', $course).'#certificate'])), false);

        // New, unverified student who never enrolled nor opened a lesson.
        $student = $this->student();
        $student->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($student)->get(route('courses.show', $course))->assertSee('اطلب شهادتك الورقية لهذه الدورة');

        $first = $this->post(route('certificates.request', $course));
        $request = CertificateRequest::firstOrFail();
        $first->assertRedirect('https://wa.me/212611223344?text='.rawurlencode("السلام عليكم، أود طلب الشهادة الورقية لدورة دورة إكسل.\nرقم طلب الشهادة: {$request->reference}."));
        $this->post(route('certificates.request', $course))->assertRedirectContains($request->reference);
        $this->assertDatabaseCount('certificate_requests', 1);

        // The course now appears in the student's dashboard; staff see 0% progress.
        $this->assertTrue($student->isEnrolledIn($course));
        $this->get(route('dashboard'))->assertSee($request->reference);
        $this->actingAs($this->admin())->get(route('admin.certificate-requests.show', $request))->assertSee('0% (0/2)');
    }

    public function test_admin_can_require_completion_again(): void
    {
        Settings::set(['whatsapp_number' => '212611223344']);
        $course = $this->makeCourse();
        $this->makeLesson($course);
        $this->actingAs($this->admin())->put(route('admin.settings.update', 'whatsapp'), [
            'whatsapp_number' => '212611223344', 'whatsapp_template' => 'طلب {course} رقم {reference}', 'certificate_requires_completion' => 1,
        ])->assertSessionHas('success');

        $this->actingAs($this->student())->post(route('certificates.request', $course))->assertSessionHas('error');
        $this->assertDatabaseCount('certificate_requests', 0);
    }
}
