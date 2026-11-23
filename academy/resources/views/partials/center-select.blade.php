{{-- Optional "nearest centre" select for register/profile. $selected --}}
@if (\App\Support\Settings::feature('centers') && \App\Models\Center::hasPublic())
    <div class="field"><label for="center_id">{{ __('المركز الأقرب إليك (اختياري)') }}</label>
        <select class="select" id="center_id" name="center_id">
            <option value="">{{ __('— أتعلّم عن بُعد —') }}</option>
            @foreach (\App\Models\Center::active()->get() as $c)
                <option value="{{ $c->id }}" @selected((int) old('center_id', $selected ?? null) === $c->id)>{{ $c->tr('city') }} — {{ $c->tr('name') }}</option>
            @endforeach
        </select>
        <p class="hint">{{ __('يساعدنا على إعلامك بالأنشطة الحضورية ويمكنك استلام شهادتك منه.') }}</p></div>
@endif
