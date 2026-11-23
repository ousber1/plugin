@extends('layouts.admin')
@section('title', 'طلب '.$req->reference)
@section('content')
<div class="page-head"><h1>طلب <span class="ltr">{{ $req->reference }}</span></h1><span class="badge badge--{{ $req->status->color() }}" style="font-size:1rem">{{ $req->status->label() }}</span></div>
<div class="dash-grid">
    <form class="card card--pad" method="POST" action="{{ route('admin.certificate-requests.update', $req) }}">
        @csrf @method('PUT')
        <div class="alert alert--info small"><x-icon name="info"/> <span>فتح الطالب لواتساب لا يعني أن الرسالة وصلت. غيّر الحالة إلى «تم الاستلام» فقط بعد وصول رسالته وتأكيد الاسم والعنوان معه.</span></div>
        <div class="field"><label for="status">الحالة</label><select class="select" id="status" name="status">@foreach (\App\Enums\CertificateStatus::cases() as $st)<option value="{{ $st->value }}" @selected($req->status === $st)>{{ $st->label() }}</option>@endforeach</select></div>
        <fieldset class="field" style="border:1px dashed var(--border-strong);border-radius:12px;padding:12px"><legend class="label">بيانات خاصة (لا تظهر للعموم)</legend>
            <div class="field"><label for="printed_name">الاسم المطبوع على الشهادة (كما أكّده الطالب)</label><input class="input" id="printed_name" name="printed_name" value="{{ old('printed_name', $req->printed_name) }}" dir="auto"></div>
            @php $allCenters = \App\Models\Center::orderBy('sort_order')->get(); @endphp
            @if ($allCenters->isNotEmpty() || $req->isPickup())
                <div class="form-grid">
                    <div class="field"><label for="delivery_method">طريقة الاستلام</label><select class="select" id="delivery_method" name="delivery_method" data-toggle-target="dm">
                        <option value="shipping" @selected(! $req->isPickup())>توصيل إلى العنوان</option><option value="pickup" @selected($req->isPickup())>استلام من مركز</option></select></div>
                    <div class="field" data-show-when="dm=pickup"><label for="center_id">المركز</label><select class="select" id="center_id" name="center_id">@foreach ($allCenters as $c)<option value="{{ $c->id }}" @selected($req->center_id === $c->id)>{{ $c->tr('city') }} — {{ $c->tr('name') }}</option>@endforeach</select></div>
                </div>
            @endif
            <div class="field"><label for="shipping_address">عنوان التوصيل</label><textarea class="textarea" id="shipping_address" name="shipping_address" rows="3" dir="auto">{{ old('shipping_address', $req->shipping_address) }}</textarea></div>
            <div class="field"><label for="private_notes">ملاحظات داخلية</label><textarea class="textarea" id="private_notes" name="private_notes" rows="3" dir="auto">{{ old('private_notes', $req->private_notes) }}</textarea></div>
        </fieldset>
        <div class="form-grid">
            <div class="field"><label for="carrier">شركة الشحن</label><input class="input" id="carrier" name="carrier" value="{{ old('carrier', $req->carrier) }}" placeholder="مثال: أمانة، بريد المغرب"></div>
            <div class="field"><label for="tracking_number">رقم التتبع</label><input class="input ltr" id="tracking_number" name="tracking_number" value="{{ old('tracking_number', $req->tracking_number) }}"></div>
            <div class="field"><label for="shipped_on">تاريخ الشحن</label><input class="input" type="date" id="shipped_on" name="shipped_on" value="{{ old('shipped_on', $req->shipped_on?->format('Y-m-d')) }}"></div>
        </div>
        <label class="check field"><input type="checkbox" name="show_tracking" value="1" @checked(old('show_tracking', $req->show_tracking))> إظهار شركة الشحن ورقم التتبع للطالب</label>
        <button class="btn btn--primary btn--lg" type="submit">حفظ وتحديث الحالة</button>
    </form>
    <div class="stack">
        <div class="card card--pad">
            <h2 class="card__title">الطالب والدورة</h2>
            <p><strong>{{ $req->user->name }}</strong><br><span class="ltr small">{{ $req->user->email }}</span> @if ($req->user->phone)<br><span class="ltr small">{{ $req->user->phone }}</span>@endif</p>
            <p class="small" dir="auto">{{ $req->course->tr('title') }}</p>
            <p class="small">تقدّم الطالب في الدورة: <span class="badge {{ $progress['percent'] === 100 ? 'badge--green' : 'badge--amber' }}">{{ $progress['percent'] }}% ({{ $progress['completed'] }}/{{ $progress['total'] }})</span></p>
            <p class="small muted">أُنشئ: {{ $req->created_at->format('Y-m-d H:i') }} @if ($req->received_at)<br>استُلم: {{ $req->received_at->format('Y-m-d H:i') }}@endif @if ($req->delivered_at)<br>سُلّم: {{ $req->delivered_at->format('Y-m-d') }}@endif</p>
            @if ($studentWhatsapp)<a class="btn btn--whatsapp btn--sm" href="{{ $studentWhatsapp }}" target="_blank" rel="noopener"><x-icon name="whatsapp" size="16"/> مراسلة الطالب</a>@endif
        </div>
        <div class="card card--pad small"><h2 class="card__title">الرسالة المتوقعة من الطالب</h2><pre style="white-space:pre-wrap;font-family:inherit;margin:0" dir="rtl">{{ $message }}</pre></div>
        <div class="card card--pad">
            <h2 class="card__title"><x-icon name="shield"/> التحقق العام</h2>
            @if ($req->issuedCertificate)
                <p class="small">الرمز: <a class="ltr" href="{{ route('admin.certificates.show', $req->issuedCertificate) }}">{{ $req->issuedCertificate->code }}</a></p>
            @else
                <p class="small muted">بعد تجهيز الشهادة، سجّلها لتحصل على رمز تحقق ورمز QR للطباعة.</p>
                <a class="btn btn--amber btn--sm" href="{{ route('admin.certificates.create', ['request' => $req->id]) }}">تسجيل شهادة للتحقق</a>
            @endif
        </div>
    </div>
</div>
@endsection
