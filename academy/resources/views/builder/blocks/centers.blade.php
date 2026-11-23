@php $centers = \App\Support\Settings::feature('centers') && \App\Models\Center::hasPublic() ? \App\Models\Center::active()->limit((int) ($c['count'] ?? 6))->get() : collect(); @endphp
@include('builder.blocks._head', ['title' => $t('title'), 'sub' => $t('subtitle')])
@if ($centers->isEmpty())
    @if (! empty($editing))<p class="muted" style="text-align:center">أضف مراكز من لوحة التحكم › مراكز التكوين لتظهر هنا.</p>@endif
@else
    <div class="center-grid">@foreach ($centers as $center)@include('centers._card', ['center' => $center])@endforeach</div>
    <p style="text-align:center;margin-top:20px"><a class="btn btn--outline" href="{{ route('centers.index') }}">{{ __('كل المراكز') }}</a></p>
@endif
