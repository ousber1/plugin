@extends('layouts.app')
@section('title', $tool->tr('name'))
@section('description', $tool->tr('description'))
@section('content')
@include('tools._head')
<div class="container section--tight">
    <div class="tool-shell overlap">
        <div class="card card--pad no-print">
            <div class="field"><label for="qb-title">{{ __('عنوان الاختبار') }}</label><input class="input" id="qb-title" value="{{ __('اختبار قصير') }}" dir="auto"></div>
            <div id="qb-questions"></div>
            <div class="row">
                <button class="btn btn--outline btn--sm" type="button" id="qb-add"><x-icon name="plus" size="16"/> {{ __('إضافة سؤال') }}</button>
                <button class="btn btn--primary btn--sm" type="button" id="qb-play"><x-icon name="play" size="16"/> {{ __('جرّب الاختبار') }}</button>
                <button class="btn btn--ghost btn--sm" type="button" onclick="window.print()">{{ __('طباعة') }}</button>
                <button class="btn btn--ghost btn--sm" type="button" id="qb-export">{{ __('تصدير JSON') }}</button>
            </div>
            <p class="small muted" style="margin-top:10px">{{ __('حدّد الإجابة الصحيحة بعلامة ✓. كل شيء يُحفظ في متصفحك.') }}</p>
        </div>
        <div class="paper printable" id="qb-out"></div>
    </div>
</div>
@endsection
@push('scripts')
<script>
(function () {
    var KEY = 'tool:quiz', list = document.getElementById('qb-questions'), out = document.getElementById('qb-out'), titleEl = document.getElementById('qb-title');
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; };
    var state; try { state = JSON.parse(localStorage.getItem(KEY)); } catch (e) {}
    state = state || { title: titleEl.value, questions: [{ q: '', options: ['', '', ''], correct: 0 }] };
    titleEl.value = state.title;
    function save() { state.title = titleEl.value; try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {} }
    function draw() {
        list.innerHTML = '';
        state.questions.forEach(function (item, qi) {
            var box = document.createElement('fieldset'); box.className = 'quiz-q';
            box.innerHTML = '<legend>{{ __('سؤال') }} ' + (qi + 1) + '</legend>';
            var q = document.createElement('input'); q.className = 'input'; q.dir = 'auto'; q.value = item.q; q.setAttribute('aria-label', '{{ __('نص السؤال') }} ' + (qi + 1));
            q.oninput = function () { item.q = q.value; save(); preview(); }; box.appendChild(q);
            item.options.forEach(function (opt, oi) {
                var row = document.createElement('div'); row.className = 'row'; row.style.marginTop = '8px';
                row.innerHTML = '<input type="radio" name="c' + qi + '" aria-label="{{ __('الإجابة الصحيحة') }}" ' + (item.correct === oi ? 'checked' : '') + '><input class="input" style="flex:1" dir="auto" aria-label="{{ __('خيار') }} ' + (oi + 1) + '">';
                row.children[0].onchange = function () { item.correct = oi; save(); preview(); };
                row.children[1].value = opt; row.children[1].oninput = function () { item.options[oi] = this.value; save(); preview(); };
                box.appendChild(row);
            });
            var tools = document.createElement('div'); tools.className = 'row'; tools.style.marginTop = '8px';
            tools.innerHTML = '<button type="button" class="btn btn--ghost btn--sm">+ {{ __('خيار') }}</button><button type="button" class="btn btn--ghost btn--sm">{{ __('حذف السؤال') }}</button>';
            tools.children[0].onclick = function () { item.options.push(''); save(); draw(); };
            tools.children[1].onclick = function () { state.questions.splice(qi, 1); save(); draw(); };
            box.appendChild(tools); list.appendChild(box);
        });
        preview();
    }
    function preview(play) {
        out.innerHTML = '<h2 dir="auto">' + esc(titleEl.value) + '</h2>' + state.questions.map(function (item, qi) {
            return '<div style="margin-bottom:18px" dir="auto"><strong>' + (qi + 1) + '. ' + esc(item.q) + '</strong>' + item.options.map(function (o, oi) {
                return '<label style="display:block;margin:6px 0"><input type="radio" name="p' + qi + '" value="' + oi + '"> ' + esc(o) + '</label>';
            }).join('') + '</div>';
        }).join('') + '<button class="btn btn--amber no-print" type="button" id="qb-check">{{ __('تحقق من الإجابات') }}</button><p id="qb-score" role="status" style="font-weight:800"></p>';
        document.getElementById('qb-check').onclick = function () {
            var ok = 0; state.questions.forEach(function (item, qi) { var s = out.querySelector('input[name=p' + qi + ']:checked'); if (s && +s.value === item.correct) ok++; });
            document.getElementById('qb-score').textContent = '{{ __('النتيجة') }}: ' + ok + ' / ' + state.questions.length;
        };
    }
    titleEl.oninput = function () { save(); preview(); };
    document.getElementById('qb-add').onclick = function () { state.questions.push({ q: '', options: ['', '', ''], correct: 0 }); save(); draw(); };
    document.getElementById('qb-play').onclick = function () { out.scrollIntoView({ behavior: 'smooth' }); };
    document.getElementById('qb-export').onclick = function () { var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([JSON.stringify(state, null, 2)], { type: 'application/json' })); a.download = 'quiz.json'; a.click(); };
    draw();
})();
</script>
@endpush
