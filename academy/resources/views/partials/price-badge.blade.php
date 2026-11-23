{{-- Course price: "مجانية" or the price (with old price). $course, optional $big --}}
@if ($course->isFree())
    <span class="badge badge--free" @if (! empty($big)) style="font-size:1rem" @endif>{{ __('مجانية') }}</span>
@else
    <span class="price-tag {{ ! empty($big) ? 'price-tag--big' : '' }}">@if ($c = $course->comparePrice())<del>{{ \App\Support\Shop::money($c) }}</del>@endif<strong>{{ $course->priceLabel() }}</strong></span>
@endif
