{{-- Physical certificate request through WhatsApp. Expects $course, $state. --}}
@php
    $req = $state['request']; $elig = $state['eligibility'];
    $certLabel = \App\Support\Settings::tr('cert_button_label') ?: __('اطلب شهادتك عبر واتساب');
    $centers = \App\Support\Settings::feature('centers') && \App\Models\Center::hasPublic() ? \App\Models\Center::active()->get() : collect();
    $myCenter = auth()->user()?->center_id;
@endphp
<div class="cert-box" id="certificate">
    <h3><x-icon name="award"/> {{ __('الشهادة الورقية') }}</h3>
    @if (! $req && $course->isPaid() && ! ($state['enrolled'] ?? false))
        <p class="small">{{ __('تحصل على الشهادة الورقية بعد شراء الدورة، ويمكنك طلبها عبر واتساب من هذه الصفحة.') }}</p>
        <button class="btn btn--whatsapp btn--block" type="button" disabled><x-icon name="whatsapp"/> {{ $certLabel }}</button>
    @elseif ($req)
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
        @if ($req->isPickup() && $req->center)
            <p class="small" style="margin:10px 0 0"><x-icon name="map-pin" size="16"/> {{ __('الاستلام من:') }} <a href="{{ route('centers.show', $req->center) }}">{{ $req->center->tr('name') }} — {{ $req->center->tr('city') }}</a></p>
        @endif
        @if ($req->status === \App\Enums\CertificateStatus::PendingContact)
            <p class="small muted" style="margin:12px 0 8px">{{ __('لم نستلم رسالتك بعد؟ أرسلها عبر واتساب مع رقم الطلب، وسنؤكد معك الاسم والعنوان.') }}</p>
            <form method="POST" action="{{ route('certificates.request', $course) }}" target="_blank">@csrf
                <button class="btn btn--whatsapp btn--block" type="submit"><x-icon name="whatsapp"/> {{ __('افتح واتساب مجددًا') }}</button>
            </form>
        @endif
    @elseif ($elig && $elig['eligible'])
        <p class="small">{{ $elig['requires_completion']
            ? __('أكملت متطلبات الدورة! اطلب شهادتك الورقية، وسيؤكد معك فريقنا الاسم المطبوع وعنوان التوصيل عبر واتساب.')
            : __('اطلب شهادتك الورقية لهذه الدورة، وسيؤكد معك فريقنا الاسم المطبوع وعنوان التوصيل عبر واتساب.') }}</p>
        <form method="POST" action="{{ route('certificates.request', $course) }}" target="_blank">@csrf
            @if ($centers->isNotEmpty())
                <fieldset class="cert-delivery">
                    <legend class="small"><strong>{{ __('طريقة استلام الشهادة') }}</strong></legend>
                    <label class="check small"><input type="radio" name="delivery" value="shipping" @checked(! $myCenter) data-delivery> {{ __('توصيل إلى عنواني') }}</label>
                    <label class="check small"><input type="radio" name="delivery" value="pickup" @checked($myCenter) data-delivery> {{ __('استلام من أحد مراكزنا') }}</label>
                    <label class="sr-only" for="cert-center">{{ __('المركز') }}</label>
                    <select class="select" id="cert-center" name="center_id" data-delivery-center @if (! $myCenter) hidden @endif>
                        @foreach ($centers as $c)<option value="{{ $c->id }}" @selected($myCenter === $c->id)>{{ $c->tr('city') }} — {{ $c->tr('name') }}</option>@endforeach
                    </select>
                </fieldset>
            @endif
            <button class="btn btn--whatsapp btn--block btn--lg" type="submit"><x-icon name="whatsapp"/> {{ $certLabel }}</button>
        </form>
        <p class="small muted" style="margin:8px 0 0">{{ __('لا تُرسل عنوانك في الموقع؛ نؤكده معك مباشرة في المحادثة.') }}</p>
    @elseif (! $elig && ! \App\Support\Settings::get('certificate_requires_completion'))
        {{-- Visitor without an account: straight to WhatsApp (a reference is created for tracking). --}}
        <p class="small">{{ __('اطلب شهادتك الورقية لهذه الدورة مباشرة عبر واتساب، وسيؤكد معك فريقنا الاسم المطبوع وعنوان التوصيل.') }}</p>
        <form method="POST" action="{{ route('certificates.guest', $course) }}" target="_blank">@csrf
            @if ($centers->isNotEmpty())
                <fieldset class="cert-delivery">
                    <legend class="small"><strong>{{ __('طريقة استلام الشهادة') }}</strong></legend>
                    <label class="check small"><input type="radio" name="delivery" value="shipping" checked data-delivery> {{ __('توصيل إلى عنواني') }}</label>
                    <label class="check small"><input type="radio" name="delivery" value="pickup" data-delivery> {{ __('استلام من أحد مراكزنا') }}</label>
                    <label class="sr-only" for="cert-center-g">{{ __('المركز') }}</label>
                    <select class="select" id="cert-center-g" name="center_id" data-delivery-center hidden>
                        @foreach ($centers as $c)<option value="{{ $c->id }}">{{ $c->tr('city') }} — {{ $c->tr('name') }}</option>@endforeach
                    </select>
                </fieldset>
            @endif
            <button class="btn btn--whatsapp btn--block btn--lg" type="submit"><x-icon name="whatsapp"/> {{ $certLabel }}</button>
        </form>
        <p class="small muted" style="margin:8px 0 0">{{ __('تريد متابعة طلبك وتقدّمك؟') }} <a href="{{ route('register', ['intended' => route('courses.show', $course).'#certificate']) }}">{{ __('أنشئ حسابًا مجانيًا') }}</a> {{ __('أو') }} <a href="{{ route('login', ['intended' => route('courses.show', $course).'#certificate']) }}">{{ __('سجّل الدخول') }}</a></p>
    @else
        <p class="small">{{ __('بعد إتمام متطلبات الدورة يمكنك طلب شهادة ورقية تُرسل إلى عنوانك.') }}</p>
        <button class="btn btn--whatsapp btn--block" type="button" disabled aria-describedby="cert-missing"><x-icon name="whatsapp"/> {{ $certLabel }}</button>
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
