<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function notice(Request $request)
    {
        return $request->user()->hasVerifiedEmail() ? redirect()->route('dashboard') : view('auth.verify-email');
    }

    public function verify(Request $request, string $id, string $hash)
    {
        $user = $request->user();
        abort_unless(hash_equals((string) $user->getKey(), $id) && hash_equals(sha1($user->getEmailForVerification()), $hash), 403);
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->route('dashboard')->with('success', __('تم تأكيد بريدك الإلكتروني.'));
    }

    public function send(Request $request)
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', __('أرسلنا رابط تأكيد جديدًا إلى بريدك الإلكتروني.'));
    }
}
