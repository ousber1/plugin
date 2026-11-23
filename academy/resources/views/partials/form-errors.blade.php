@if ($errors->any())
    <div class="alert alert--error" role="alert">
        <x-icon name="alert"/>
        <div>
            <strong>{{ __('يرجى تصحيح الأخطاء التالية:') }}</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    </div>
@endif
