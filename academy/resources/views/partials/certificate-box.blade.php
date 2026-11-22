{{-- Physical certificate request through WhatsApp. Expects $course, $state. --}}
@php $req = $state['request']; $elig = $state['eligibility']; @endphp
<div class="cert-box" id="certificate">
    <h3><x-icon name="award"/> {{ __('الشهادة الورقية') }}</h3>
    @if ($req)
        <p class="small" style="margin-bottom:6px">{{ __('رقم طلبك:') }} <strong class="ltr">{{ $req->reference }}</strong></p>
        <p style="margin:0"><span class="badge badge--{{ $req->status->color() }}">{{ $req->status->label() }}</span></p>
        <ol class="timeline" aria-label="{{ __('مراحل الطلب') }}">
            @foreach (\App\Enums\CertificateStatus::cases() as $st)
                <li class="{{ $st->step() < $req->status->step() ? 'is-done' : '' }} {{ $st === $req->status ? 'is-current' : '' }}" @if ($st === $req->status) aria-current="step" @endif>{{ $st->label() }}</li>
            @endforeach
        </ol>
        @if ($t = $req->visibleTracking())
            <p class="small" style="margin:12px 0 0"><x-icon name="truck" size="16"/>
                {{ $t['carrier'] }} @if ($t['number']) — {{ __('رقم التتبع:') }} <strong class="ltr">{{ $t['number'] }}</strong> @endif
                @if ($t['shipped_on'])<br>{{ __('تاريخ الشحن:') }} {{ $t['shipped_on']->translatedFormat('j F Y') }}@endif
            </p>
        @endif
        @if ($req->status === \App\Enums\CertificateStatus::PendingContact)
            <p class="small muted" style="margin:12px 0 8px">{{ __('لم نستلم رسالتك بعد؟ أرسلها عبر واتساب مع رقم الطلب، وسنؤكد معك الاسم والعنوان.') }}</p>
            <form method="POST" action="{{ route('certificates.request', $course) }}" target="_blank">@csrf
                <button class="btn btn--whatsapp btn--block" type="submit"><x-icon name="whatsapp"/> {{ __('افتح واتساب مجددًا') }}</button>
            </form>
        @endif
    @elseif ($elig && $elig['eligible'])
        <p class="small">{{ __('أكملت متطلبات الدورة! اطلب شهادتك الورقية، وسيؤكد معك فريقنا الاسم المطبوع وعنوان التوصيل عبر واتساب.') }}</p>
        <form method="POST" action="{{ route('certificates.request', $course) }}" target="_blank">@csrf
            <button class="btn btn--whatsapp btn--block btn--lg" type="submit"><x-icon name="whatsapp"/> {{ __('اطلب شهادتك عبر واتساب') }}</button>
        </form>
        <p class="small muted" style="margin:8px 0 0">{{ __('لا تُرسل عنوانك في الموقع؛ نؤكده معك مباشرة في المحادثة.') }}</p>
    @else
        <p class="small">{{ __('بعد إتمام متطلبات الدورة يمكنك طلب شهادة ورقية تُرسل إلى عنوانك.') }}</p>
        <button class="btn btn--whatsapp btn--block" type="button" disabled aria-describedby="cert-missing"><x-icon name="whatsapp"/> {{ __('اطلب شهادتك عبر واتساب') }}</button>
        @if ($elig)
            <div id="cert-missing" class="small" style="margin-top:10px">
                <strong>{{ __('المتطلبات المتبقية:') }}</strong>
                <ul style="margin:4px 0 0;padding-inline-start:18px">@foreach ($elig['missing'] as $m)<li>{{ $m }}</li>@endforeach</ul>
            </div>
        @else
            <p id="cert-missing" class="small muted" style="margin:8px 0 0">{{ __('سجّل في الدورة أولًا لتبدأ.') }}</p>
        @endif
    @endif
</div>
