@extends('layouts.admin')
@section('title', 'طلب '.$order->reference)
@section('content')
@php use App\Support\Shop; $isPdf = $order->receipt_path && str_ends_with(strtolower($order->receipt_path), '.pdf'); @endphp
<div class="page-head"><h1>طلب <span class="ltr">{{ $order->reference }}</span></h1><span class="badge badge--{{ $order->statusColor() }}" style="font-size:1rem">{{ $order->statusLabel() }}</span></div>
<div class="dash-grid">
    <div class="stack">
        <section class="card card--pad">
            <h2 class="card__title">المنتجات</h2>
            <div class="table-wrap"><table class="table"><thead><tr><th>المنتج</th><th>السعر</th><th>الترخيص</th></tr></thead><tbody>
                @foreach ($order->items as $it)
                    <tr><td dir="auto">{{ $it->title }}@if ($it->billing !== 'once')<br><small class="muted">اشتراك {{ $it->billing === 'year' ? 'سنوي' : 'شهري' }}</small>@endif</td>
                        <td class="ltr">{{ Shop::money($it->price_cents, $order->currency) }}</td>
                        <td>@if ($it->license_key)<code class="ltr">{{ $it->license_key }}</code>@if ($it->expires_at)<br><small class="muted">حتى {{ $it->expires_at->format('Y-m-d') }}</small>@endif @else<span class="muted small">يُنشأ عند تأكيد الدفع</span>@endif</td></tr>
                @endforeach
            </tbody></table></div>
            <dl class="pinfo" style="margin-top:12px">
                <dt>المجموع الفرعي</dt><dd class="ltr">{{ Shop::money($order->subtotal_cents ?: $order->amount_cents, $order->currency) }}</dd>
                @if ($order->discount_cents)<dt>الخصم ({{ $order->coupon_code }})</dt><dd class="ltr">− {{ Shop::money($order->discount_cents, $order->currency) }}</dd>@endif
                <dt><strong>المجموع</strong></dt><dd class="ltr"><strong>{{ $order->totalLabel() }}</strong></dd>
                <dt>طريقة الدفع</dt><dd>{{ $order->methodLabel() }}</dd>
            </dl>
        </section>
        <section class="card card--pad">
            <h2 class="card__title">وصل الدفع</h2>
            @if ($order->receipt_path)
                @if ($isPdf)
                    <a class="btn btn--outline" href="{{ route('admin.orders.receipt', $order) }}" target="_blank"><x-icon name="file-text" size="18"/> فتح الوصل (PDF)</a>
                @else
                    <a href="{{ route('admin.orders.receipt', $order) }}" target="_blank"><img src="{{ route('admin.orders.receipt', $order) }}" alt="وصل الدفع" style="max-width:100%;max-height:460px;border-radius:12px;border:1px solid var(--border)"></a>
                @endif
                @if ($order->transfer_reference)<p class="small">رقم العملية: <strong class="ltr">{{ $order->transfer_reference }}</strong></p>@endif
            @else
                <p class="muted">لم يرفع الزبون أي وصل بعد.</p>
            @endif
        </section>
    </div>
    <div class="stack">
        <section class="card card--pad">
            <h2 class="card__title">الزبون</h2>
            <ul class="list-plain">
                <li><x-icon name="user" size="16"/> {{ $order->name ?: $order->user?->name }}</li>
                @if ($order->phone)<li><x-icon name="phone" size="16"/> <span class="ltr">{{ $order->phone }}</span></li>@endif
                <li><x-icon name="mail" size="16"/> <span class="ltr">{{ $order->email ?: $order->user?->email }}</span></li>
            </ul>
            @if ($order->customer_note)<p class="small" style="background:var(--ivory);padding:10px;border-radius:10px" dir="auto">{{ $order->customer_note }}</p>@endif
            @if ($wa)<a class="btn btn--whatsapp btn--block" href="{{ $wa }}?text={{ rawurlencode('السلام عليكم '.$order->name.'، بخصوص طلبك '.$order->reference.' في متجرنا.') }}" target="_blank" rel="noopener"><x-icon name="whatsapp" size="18"/> مراسلة الزبون</a>@endif
        </section>
        <form class="card card--pad" method="POST" action="{{ route('admin.orders.update', $order) }}">@csrf @method('PUT')
            <h2 class="card__title">تحديث الحالة</h2>
            <div class="field"><label for="status">الحالة</label><select class="select" id="status" name="status">@foreach (['pending' => 'بانتظار الدفع', 'review' => 'قيد مراجعة الدفع', 'paid' => 'مدفوع (يفعّل التحميل والترخيص)', 'cancelled' => 'ملغى'] as $k => $l)<option value="{{ $k }}" @selected($order->status === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label for="transfer_reference">مرجع التحويل</label><input class="input ltr" id="transfer_reference" name="transfer_reference" value="{{ $order->transfer_reference }}"></div>
            <div class="field"><label for="admin_note">ملاحظة داخلية</label><textarea class="textarea" id="admin_note" name="admin_note" rows="2">{{ $order->admin_note }}</textarea></div>
            <button class="btn btn--primary btn--block" type="submit">حفظ</button>
            <p class="hint">عند اختيار «مدفوع» تُنشأ مفاتيح الترخيص (أو تُجدَّد للاشتراكات) ويستطيع الزبون التحميل فورًا من حسابه.</p>
        </form>
    </div>
</div>
@endsection
