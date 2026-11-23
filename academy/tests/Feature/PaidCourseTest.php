<?php

namespace Tests\Feature;

use App\Models\CertificateRequest;
use App\Models\Course;
use App\Models\Order;
use App\Support\Settings;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaidCourseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaseSeeder::class);
        Settings::set(['whatsapp_number' => '212611223344']); // store module stays disabled
    }

    public function test_admin_sets_a_course_as_paid_and_free_courses_still_enroll_freely(): void
    {
        $free = $this->makeCourse(['title' => ['ar' => 'دورة مجانية']]);
        $paid = $this->makeCourse(['title' => ['ar' => 'دورة احترافية'], 'slug' => 'pro']);
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.courses.edit', $paid))->assertOk()->assertSee('مدفوعة');

        $payload = ['title' => ['ar' => 'دورة احترافية'], 'level' => 'beginner', 'language' => 'ar', 'pricing' => 'paid', 'compare_price' => 799];
        $this->put(route('admin.courses.update', $paid), $payload)->assertSessionHasErrors('price');
        $this->put(route('admin.courses.update', $paid), $payload + ['price' => 499])->assertSessionHasNoErrors();
        $paid->refresh();
        $this->assertTrue($paid->isPaid());
        $this->assertSame(49900, $paid->price_cents);

        // Instructors cannot change prices.
        $teacher = \App\Models\User::factory()->instructor()->create();
        $paid->assignees()->attach($teacher);
        $this->actingAs($teacher)->put(route('admin.courses.update', $paid), $payload + ['price' => 1])->assertSessionHasErrors('pricing');

        auth()->logout();
        $this->get(route('courses.index'))->assertSee('499 درهم')->assertSee('مجانية')->assertSee('السعر: من الأقل');
        $this->get(route('home'))->assertSee('دورات مجانية وأخرى احترافية مدفوعة');
        $this->get(route('courses.index', ['price' => 'paid']))->assertSee('دورة احترافية')->assertDontSee('دورة مجانية</a>', false);
        $this->get(route('courses.index', ['price' => 'free']))->assertDontSee('دورة احترافية');
        $this->get(route('courses.show', $paid))->assertSee('اشترِ الدورة')->assertSee('799 درهم')->assertDontSee('سجّل مجانًا');

        $student = $this->student();
        $this->actingAs($student)->post(route('courses.enroll', $free))->assertRedirect();
        $this->assertTrue($student->isEnrolledIn($free));
    }

    public function test_paid_course_is_bought_through_checkout_and_unlocked_after_payment(): void
    {
        $course = $this->makeCourse(['title' => ['ar' => 'دورة التسويق الرقمي'], 'slug' => 'marketing']);
        $course->forceFill(['price_cents' => 29900])->save();
        $lesson = $this->makeLesson($course);
        $preview = $this->makeLesson($course, ['title' => ['ar' => 'درس معاينة'], 'is_preview' => true, 'sort_order' => 0]);
        $student = $this->student();

        // Not enrolled: lessons locked (except preview), free enrolment refused, certificate blocked.
        $this->actingAs($student)->get(route('learn.show', [$course, $lesson]))->assertRedirect(route('courses.show', $course));
        $this->get(route('learn.show', [$course, $preview]))->assertOk();
        $this->post(route('courses.enroll', $course))->assertRedirect(route('store.checkout'));
        $this->assertFalse($student->isEnrolledIn($course));
        $this->post(route('certificates.request', $course))->assertRedirect(route('courses.show', $course));
        $this->assertSame(0, CertificateRequest::count());
        $this->get(route('courses.show', $course))->assertSee('تحصل على الشهادة الورقية بعد شراء الدورة');

        // Checkout works even though the products store module is disabled.
        $this->post(route('courses.buy', $course), ['buy_now' => 1])->assertRedirect(route('store.checkout'));
        $this->get(route('store.checkout'))->assertOk()->assertSee('دورة التسويق الرقمي')->assertSee('299 درهم');
        $this->post(route('store.place'), ['name' => 'Amal', 'phone' => '0612345678', 'email' => 'amal@example.com', 'payment_method' => 'agency', 'accept' => 1])->assertRedirect();
        $order = Order::with('items')->firstOrFail();
        $this->assertSame($course->id, $order->items[0]->course_id);
        $this->assertSame(29900, $order->amount_cents);
        $this->assertFalse($student->fresh()->isEnrolledIn($course));

        $this->actingAs($this->admin())->put(route('admin.orders.update', $order), ['status' => 'paid'])->assertSessionHas('success');
        $this->assertTrue($student->fresh()->isEnrolledIn($course));

        $this->actingAs($student->fresh())->get(route('learn.show', [$course, $lesson]))->assertOk();
        $this->get(route('store.order', $order))->assertSee('ابدأ الدورة');
        $this->get(route('store.account'))->assertSee('دوراتي المشتراة')->assertSee('دورة التسويق الرقمي');
        $this->get(route('courses.show', $course))->assertSee('تابع التعلم');
        $this->post(route('certificates.request', $course))->assertRedirect();
        $this->assertSame(1, CertificateRequest::count());

        // Visitors cannot request a certificate for a paid course on WhatsApp.
        auth()->logout();
        $this->post(route('certificates.guest', $course))->assertRedirect(route('courses.show', $course));
        $this->assertSame(1, CertificateRequest::count());
    }

    public function test_cart_mixes_courses_and_products(): void
    {
        Settings::set(['features' => array_merge(Settings::get('features'), ['store' => true])]);
        $course = $this->makeCourse(['slug' => 'excel-pro', 'title' => ['ar' => 'إكسل احترافي']]);
        $course->forceFill(['price_cents' => 20000])->save();
        $product = new \App\Models\Product(['title' => ['ar' => 'قالب'], 'price_cents' => 10000, 'status' => 'published']);
        $product->slug = 'tpl';
        $product->save();
        $free = $this->makeCourse(['slug' => 'free-one']);

        $this->post(route('courses.buy', $course));
        $this->post(route('store.cart.add', $product));
        $this->post(route('courses.buy', $free))->assertNotFound(); // free courses are not sold
        $this->get(route('store.cart'))->assertSee('إكسل احترافي')->assertSee('قالب')->assertSee('300 درهم');
        $this->delete(route('store.cart.remove', 'c:'.$course->id));
        $this->get(route('store.cart'))->assertDontSee('إكسل احترافي')->assertSee('100 درهم');
        $this->delete(route('store.cart.remove', 'bad'))->assertNotFound();
    }
}
