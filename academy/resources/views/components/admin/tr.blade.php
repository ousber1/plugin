{{-- Translatable field: renders one input per locale (ar required when $required). --}}
@props(['name', 'label', 'value' => [], 'type' => 'input', 'required' => false, 'rich' => false, 'hint' => null, 'rows' => 4, 'max' => null])
@php
    $locales = \App\Support\Locales::all();
    $value = is_array($value) ? $value : [];
    $id = 'f-'.str_replace(['[', ']', '.'], '-', $name);
@endphp
<div class="tr-field" role="group" aria-labelledby="{{ $id }}-l">
    <div class="label" id="{{ $id }}-l">{{ $label }} @if ($required)<span class="req">*</span>@endif</div>
    <div class="tr-tabs" role="tablist">
        @foreach ($locales as $code => $m)
            <button type="button" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" aria-controls="{{ $id }}-{{ $code }}">{{ $m['short'] }}</button>
        @endforeach
    </div>
    @foreach ($locales as $code => $m)
        @php
            $fieldName = $name.'['.$code.']';
            $old = old(str_replace(['[', ']'], ['.', ''], $name).'.'.$code, $value[$code] ?? '');
            if (is_array($old)) { $old = implode("\n", $old); }
        @endphp
        <div class="tr-panel" id="{{ $id }}-{{ $code }}" role="tabpanel">
            <label class="lang-tag" for="{{ $id }}-{{ $code }}-i">{{ $m['name'] }}</label>
            @if ($type === 'textarea' || $type === 'list')
                <textarea class="textarea" id="{{ $id }}-{{ $code }}-i" name="{{ $fieldName }}" dir="{{ $m['dir'] }}" rows="{{ $rows }}" aria-label="{{ $label }} ({{ $m['name'] }})" @if ($rich) data-rich @endif @if ($required && $loop->first) required @endif>{{ $old }}</textarea>
            @else
                <input class="input" id="{{ $id }}-{{ $code }}-i" name="{{ $fieldName }}" value="{{ $old }}" dir="{{ $m['dir'] }}" aria-label="{{ $label }} ({{ $m['name'] }})" @if ($max) maxlength="{{ $max }}" @endif @if ($required && $loop->first) required @endif>
            @endif
        </div>
    @endforeach
    @if ($hint || $type === 'list')<p class="hint">{{ $hint ?? 'عنصر واحد في كل سطر. الحقول الفارغة تستعمل النسخة العربية.' }}</p>@endif
    @error($name.'.ar')<p class="error">{{ $message }}</p>@enderror
</div>
