<?php

namespace App\Http\Controllers\Admin;

use App\Models\CertificateRequest;
use App\Models\Course;
use App\Models\IssuedCertificate;
use App\Support\Audit;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;

/** Registry of printed certificates for public verification. */
class IssuedCertificateController extends AdminController
{
    public function index(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', 'in:valid,revoked']]);
        $items = IssuedCertificate::query()
            ->when($request->input('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('code', 'like', "%$t%")->orWhere('holder_name', 'like', "%$t%")->orWhere('course_title', 'like', "%$t%")))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.certificates.index', compact('items'));
    }

    public function create(Request $request)
    {
        $fromRequest = $request->filled('request') ? CertificateRequest::with(['user', 'course', 'issuedCertificate'])->find($request->input('request')) : null;
        if ($fromRequest?->issuedCertificate) {
            return redirect()->route('admin.certificates.show', $fromRequest->issuedCertificate)->with('info', 'هذه الشهادة مسجلة مسبقًا.');
        }

        return view('admin.certificates.create', [
            'fromRequest' => $fromRequest,
            'courses' => Course::orderBy('slug')->get(['id', 'title', 'slug']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'certificate_request_id' => ['nullable', 'exists:certificate_requests,id', 'unique:issued_certificates,certificate_request_id'],
            'holder_name' => ['required', 'string', 'max:190'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'course_title' => ['required', 'string', 'max:190'],
            'issued_on' => ['required', 'date', 'before_or_equal:today'],
            'public_note' => ['nullable', 'string', 'max:255'],
        ]);
        $req = isset($data['certificate_request_id']) ? CertificateRequest::find($data['certificate_request_id']) : null;

        $cert = new IssuedCertificate($data + [
            'code' => IssuedCertificate::generateCode(),
            'user_id' => $req?->user_id,
            'created_by' => $request->user()->id,
        ]);
        $cert->course_id = $data['course_id'] ?? $req?->course_id;
        $cert->save();
        Audit::log('certificate.issued', $cert, ['code' => $cert->code]);

        return redirect()->route('admin.certificates.show', $cert)->with('success', 'تم تسجيل الشهادة. اطبع رمز التحقق ورمز QR عليها.');
    }

    public function show(IssuedCertificate $certificate)
    {
        return view('admin.certificates.show', ['cert' => $certificate->load('request.user')]);
    }

    public function revoke(Request $request, IssuedCertificate $certificate)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $certificate->forceFill(['status' => 'revoked', 'revoked_at' => now(), 'revocation_reason' => $data['reason']])->save();
        Audit::log('certificate.revoked', $certificate, ['code' => $certificate->code, 'reason' => $data['reason']]);

        return back()->with('success', 'تم إلغاء الشهادة. ستظهر كملغاة في صفحة التحقق.');
    }

    public function restore(IssuedCertificate $certificate)
    {
        $certificate->forceFill(['status' => 'valid', 'revoked_at' => null, 'revocation_reason' => null])->save();
        Audit::log('certificate.restored', $certificate, ['code' => $certificate->code]);

        return back()->with('success', 'أُعيد تفعيل الشهادة.');
    }

    public function qr(IssuedCertificate $certificate)
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(320, 1), new SvgImageBackEnd));

        return response($writer->writeString($certificate->verifyUrl()), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="qr-'.$certificate->code.'.svg"',
        ]);
    }
}
