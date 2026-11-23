<fieldset class="qq" data-item>
    <legend class="label">سؤال</legend>
    <div class="field"><label for="q-{{ $i }}-question">نص السؤال</label><input class="input" id="q-{{ $i }}-question" name="questions[{{ $i }}][question]" value="{{ $q['question'] }}" required dir="auto"></div>
    <div class="form-grid">
        <div class="field"><label for="q-{{ $i }}-options">الخيارات (خيار في كل سطر)</label><textarea class="textarea" id="q-{{ $i }}-options" name="questions[{{ $i }}][options]" rows="4" required dir="auto">{{ $q['options'] }}</textarea></div>
        <div>
            <div class="field"><label for="q-{{ $i }}-correct">رقم/أرقام الإجابة الصحيحة</label><input class="input ltr" id="q-{{ $i }}-correct" name="questions[{{ $i }}][correct]" value="{{ $q['correct'] }}" required></div>
            <div class="field"><label for="q-{{ $i }}-explanation">شرح الإجابة (اختياري)</label><input class="input" id="q-{{ $i }}-explanation" name="questions[{{ $i }}][explanation]" value="{{ $q['explanation'] }}" dir="auto"></div>
        </div>
    </div>
    <button class="btn btn--ghost btn--sm" type="button" data-remove><x-icon name="trash" size="16"/> حذف السؤال</button>
</fieldset>
