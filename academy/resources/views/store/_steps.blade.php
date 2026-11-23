<ol class="csteps" aria-label="{{ __('مراحل الطلب') }}">
    @foreach ([1 => __('السلة'), 2 => __('معلوماتك والدفع'), 3 => __('التأكيد')] as $n => $label)
        <li class="{{ $n < $step ? 'is-done' : '' }} {{ $n === $step ? 'is-current' : '' }}" @if ($n === $step) aria-current="step" @endif>
            <span class="csteps__n">@if ($n < $step)<x-icon name="check" size="14"/>@else{{ $n }}@endif</span> {{ $label }}
        </li>
    @endforeach
</ol>
