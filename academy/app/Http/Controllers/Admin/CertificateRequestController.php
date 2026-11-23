<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CertificateStatus;
use App\Models\CertificateRequest;
use App\Services\CertificateRequestService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CertificateRequestController extends AdminController
{
    public function index(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', Rule::enum(CertificateStatus::class)], 'center' => ['nullable', 'integer']]);
        $requests = CertificateRequest::with(['user:id,name,email,phone', 'course:id,slug,title', 'issuedCertificate', 'center'])
            ->when($request->input('center'), fn ($q, $c) => $q->where('center_id', $c))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('q'), function ($q, $t) {
                $q->where(fn ($w) => $w->where('reference', 'like', "%$t%")->orWhere('tracking_number', 'like', "%$t%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%$t%")->orWhere('email', 'like', "%$t%")->orWhere('phone', 'like', "%$t%")));
            })
            ->latest()->paginate(25)->withQueryString();

        $counts = CertificateRequest::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $progress = app(\App\Services\ProgressService::class);
        $percents = $requests->getCollection()->mapWithKeys(fn ($r) => [$r->id => $r->user && $r->course ? $progress->progress($r->user, $r->course)['percent'] : null]);

        $centers = \App\Models\Center::orderBy('sort_order')->get();

        return view('admin.certificate-requests.index', compact('requests', 'counts', 'percents', 'centers'));
    }

    public function show(CertificateRequest $certificateRequest, CertificateRequestService $service)
    {
        $certificateRequest->load(['user', 'course', 'issuedCertificate', 'center']);

        return view('admin.certificate-requests.show', [
            'req' => $certificateRequest,
            'message' => $service->whatsappMessage($certificateRequest),
            'studentWhatsapp' => $certificateRequest->user?->phone ? 'https://wa.me/'.preg_replace('/\D+/', '', $certificateRequest->user->phone) : null,
            'progress' => $certificateRequest->user ? app(\App\Services\ProgressService::class)->progress($certificateRequest->user, $certificateRequest->course) : null,
        ]);
    }

    public function update(Request $request, CertificateRequest $certificateRequest)
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(CertificateStatus::class)],
            'printed_name' => ['nullable', 'string', 'max:190'],
            'shipping_address' => ['nullable', 'string', 'max:1000'],
            'carrier' => ['nullable', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'shipped_on' => ['nullable', 'date'],
            'show_tracking' => ['nullable', 'boolean'],
            'private_notes' => ['nullable', 'string', 'max:5000'],
            'delivery_method' => ['nullable', 'in:shipping,pickup'],
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
        ]);
        $old = $certificateRequest->status;
        $new = CertificateStatus::from($data['status']);

        $certificateRequest->forceFill([
            'status' => $new,
            'printed_name' => $data['printed_name'] ?? null,
            'shipping_address' => $data['shipping_address'] ?? null,
            'carrier' => $data['carrier'] ?? null,
            'tracking_number' => $data['tracking_number'] ?? null,
            'shipped_on' => $data['shipped_on'] ?? ($new === CertificateStatus::Shipped && ! $certificateRequest->shipped_on ? now()->toDateString() : $certificateRequest->shipped_on),
            'show_tracking' => $request->boolean('show_tracking'),
            'private_notes' => $data['private_notes'] ?? null,
            'delivery_method' => ($data['delivery_method'] ?? 'shipping') === 'pickup' && ! empty($data['center_id']) ? 'pickup' : 'shipping',
            'center_id' => ($data['delivery_method'] ?? 'shipping') === 'pickup' ? ($data['center_id'] ?? null) : null,
        ]);
        if ($new !== CertificateStatus::PendingContact && ! $certificateRequest->received_at) {
            $certificateRequest->received_at = now();
        }
        if ($new === CertificateStatus::Delivered && ! $certificateRequest->delivered_at) {
            $certificateRequest->delivered_at = now();
        }
        $certificateRequest->save();

        if ($old !== $new) {
            Audit::log('certificate_request.status_changed', $certificateRequest, ['reference' => $certificateRequest->reference, 'from' => $old->value, 'to' => $new->value]);
        }

        return back()->with('success', 'تم تحديث الطلب '.$certificateRequest->reference.'.');
    }
}
