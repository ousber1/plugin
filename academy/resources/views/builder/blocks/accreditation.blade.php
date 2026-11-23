@include('partials.accreditation', ['bare' => true])
@if (! empty($editing) && ! \App\Support\Settings::get('accreditation_enabled'))
    <p class="muted" style="text-align:center">قسم الاعتماد غير مفعّل. فعّله واملأه من الإعدادات › الاعتماد.</p>
@endif
