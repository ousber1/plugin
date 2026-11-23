<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\CertificateRequestService;
use Illuminate\Http\Request;

class CertificateRequestController extends Controller
{
    /**
     * Validates eligibility, creates or reuses the request reference and
     * redirects to WhatsApp with the prefilled Arabic message.
     * Opening WhatsApp is not proof the message was sent: the request stays
     * "pending contact" until staff confirm receipt.
     */
    public function store(Request $request, Course $course, CertificateRequestService $service)
    {
        abort_unless($course->isPublished(), 404);

        $request->validate(['delivery' => ['nullable', 'in:shipping,pickup'], 'center_id' => ['nullable', 'integer']]);
        $pickup = null;
        if ($request->input('delivery') === 'pickup') {
            $pickup = \App\Models\Center::where('is_active', true)->find($request->integer('center_id'));
            if (! $pickup) {
                return redirect()->route('courses.show', $course)->with('error', __('اختر المركز الذي تريد استلام الشهادة منه.'));
            }
        }

        try {
            $certificateRequest = $request->has('delivery')
                ? $service->requestFor($request->user(), $course, $pickup)
                : $service->requestFor($request->user(), $course);
        } catch (\DomainException $e) {
            return redirect()->route('courses.show', $course)->with('error', $e->getMessage());
        }

        $url = $service->whatsappUrl($certificateRequest->setRelation('course', $course));
        if (! $url) {
            return redirect()->route('dashboard')->with('info', __('رقم طلبك :ref. لم تُضبط بعد وسيلة التواصل عبر واتساب، سيتواصل معك فريق الأكاديمية.', ['ref' => $certificateRequest->reference]));
        }

        return redirect()->away($url);
    }
}
