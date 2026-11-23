<?php

namespace App\Services;

use App\Enums\CertificateStatus;
use App\Models\CertificateRequest;
use App\Models\Course;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CertificateRequestService
{
    public function __construct(private ProgressService $progress) {}

    /**
     * Create (or reuse) the student's certificate request for a course.
     * Eligibility is always validated server-side.
     *
     * @throws \DomainException when the student is not eligible
     */
    public function requestFor(User $user, Course $course, ?\App\Models\Center $pickup = null): CertificateRequest
    {
        $existing = CertificateRequest::where('user_id', $user->id)->where('course_id', $course->id)->first();
        if ($existing) {
            // The student may still change pickup/shipping until staff confirm the request.
            if (func_num_args() >= 3 && $existing->status === CertificateStatus::PendingContact) {
                $existing->update(['delivery_method' => $pickup ? 'pickup' : 'shipping', 'center_id' => $pickup?->id]);
            }

            return $existing;
        }

        $eligibility = $this->progress->eligibility($user, $course);
        if (! $eligibility['eligible']) {
            throw new \DomainException(implode(' ', $eligibility['missing']));
        }

        // Requests are allowed before finishing (or starting) the course when the academy
        // does not require completion; enroll the student so the course appears in their dashboard.
        if (! $user->isEnrolledIn($course)) {
            \App\Models\Enrollment::firstOrCreate(['user_id' => $user->id, 'course_id' => $course->id]);
        }

        try {
            return DB::transaction(function () use ($user, $course, $pickup) {
                $request = new CertificateRequest([
                    'reference' => $this->newReference(),
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'status' => CertificateStatus::PendingContact,
                    'delivery_method' => $pickup ? 'pickup' : 'shipping',
                    'center_id' => $pickup?->id,
                ]);
                $request->save();

                return $request;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent click created it first: reuse that reference.
            return CertificateRequest::where('user_id', $user->id)->where('course_id', $course->id)->firstOrFail();
        }
    }

    public function whatsappMessage(CertificateRequest $request): string
    {
        $template = (string) Settings::get('whatsapp_template');

        $message = strtr($template, [
            '{course}' => $request->course->tr('title', 'ar'),
            '{reference}' => $request->reference,
        ]);
        if ($request->isPickup() && $request->center) {
            $message .= "\nطريقة الاستلام: من مركز ".$request->center->tr('name', 'ar').' ('.$request->center->tr('city', 'ar').').';
        }

        return $message;
    }

    /** Pickup requests go to the centre's own WhatsApp number when it has one. */
    public function whatsappUrl(CertificateRequest $request): ?string
    {
        if ($request->isPickup() && $request->center) {
            return $request->center->whatsappLink($this->whatsappMessage($request));
        }

        return Settings::whatsappLink($this->whatsappMessage($request));
    }

    private function newReference(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        do {
            $suffix = '';
            for ($i = 0; $i < 6; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $reference = 'CR-'.now()->format('y').'-'.$suffix;
        } while (CertificateRequest::where('reference', $reference)->exists());

        return $reference;
    }
}
