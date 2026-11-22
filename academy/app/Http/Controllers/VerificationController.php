<?php

namespace App\Http\Controllers;

use App\Models\IssuedCertificate;
use App\Support\Settings;
use Illuminate\Http\Request;

/**
 * Public certificate verification. Only fields approved by the academy are
 * shown; shipping records and private notes are never exposed.
 */
class VerificationController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['code' => ['nullable', 'string', 'max:40']]);
        if ($request->filled('code')) {
            return redirect()->route('verify.show', IssuedCertificate::normalizeCode($request->input('code')) ?: '-');
        }

        return view('verify.index');
    }

    public function show(string $code)
    {
        $code = IssuedCertificate::normalizeCode($code);
        $certificate = $code !== '' && strlen($code) <= 32 ? IssuedCertificate::where('code', $code)->first() : null;

        $public = null;
        if ($certificate) {
            $fields = Settings::get('verify_public_fields') ?: [];
            $public = [
                'code' => $certificate->code,
                'status' => $certificate->status,
                'holder_name' => in_array('holder_name', $fields, true) ? $certificate->holder_name : null,
                'course_title' => in_array('course_title', $fields, true) ? $certificate->course_title : null,
                'issued_on' => in_array('issued_on', $fields, true) ? $certificate->issued_on : null,
                'public_note' => $certificate->public_note,
                'revoked_at' => $certificate->revoked_at,
            ];
        }

        return response()->view('verify.show', ['code' => $code, 'result' => $public], $certificate ? 200 : 404)
            ->header('X-Robots-Tag', 'noindex');
    }
}
