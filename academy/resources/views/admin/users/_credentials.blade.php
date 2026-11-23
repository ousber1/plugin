@if ($cred = session('new_credentials'))
    @php
        $text = "مرحبًا،\nتم إنشاء حسابك في ".$siteName.".\nرابط الدخول: ".route('login')."\nالبريد: ".$cred['email']."\nكلمة المرور: ".$cred['password']."\nننصحك بتغيير كلمة المرور بعد أول دخول.";
        $phone = preg_replace('/\D+/', '', preg_replace('/^0/', '212', trim((string) $cred['phone'])));
    @endphp
    <div class="card card--pad cred-box" role="status">
        <h2 class="card__title"><x-icon name="lock" size="18"/> بيانات الدخول (تظهر مرة واحدة)</h2>
        <dl class="pinfo"><dt>البريد</dt><dd class="ltr">{{ $cred['email'] }}</dd><dt>كلمة المرور</dt><dd class="ltr"><code>{{ $cred['password'] }}</code></dd></dl>
        <div class="row" style="margin-top:10px">
            <button class="btn btn--outline btn--sm" type="button" data-copy="{{ $text }}" data-copied="تم النسخ"><x-icon name="copy" size="16"/> نسخ الرسالة</button>
            <a class="btn btn--whatsapp btn--sm" href="https://wa.me/{{ $phone }}?text={{ rawurlencode($text) }}" target="_blank" rel="noopener"><x-icon name="whatsapp" size="16"/> إرسالها عبر واتساب</a>
        </div>
        <p class="hint">لا نحفظ كلمة المرور بشكل مقروء. انسخها الآن أو أرسلها للمستخدم.</p>
    </div>
@endif
