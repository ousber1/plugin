@extends('layouts.app')
@section('title', $tool->tr('name'))
@section('description', $tool->tr('description'))
@section('content')
@php $aiEnabled = \App\Http\Controllers\ToolController::aiToolEnabled(); @endphp
@include('tools._head')
<div class="container section--tight">
    @if ($aiEnabled)
        <section class="card card--pad overlap no-print" aria-labelledby="ai-h" style="margin-bottom:22px">
            <h2 id="ai-h" class="card__title row" style="gap:8px"><x-icon name="sparkles"/> {{ __('حوّل درسك إلى اختبار تفاعلي') }}</h2>
            <p class="small muted">{{ __('الصق نص الدرس أو ارفع ملف PDF أو Word. للفيديوهات: ارفع ملف الترجمة (SRT / VTT) أو الصق نص الشرح.') }}</p>
            @guest
                <div class="alert alert--info"><x-icon name="lock"/> <span>{{ __('سجّل الدخول لاستعمال المولّد بالذكاء الاصطناعي (مجاني).') }} <a href="{{ route('login', ['intended' => route('tools.show', $tool)]) }}">{{ __('تسجيل الدخول') }}</a> — <a href="{{ route('register', ['intended' => route('tools.show', $tool)]) }}">{{ __('حساب جديد') }}</a></span></div>
            @else
                <form id="ai-form" enctype="multipart/form-data" novalidate>
                    <div class="form-grid">
                        <div class="field"><label for="ai-text">{{ __('نص الدرس') }}</label>
                            <textarea class="textarea" id="ai-text" name="text" rows="6" dir="auto" maxlength="{{ config('academy.ai.max_input_chars') }}" placeholder="{{ __('الصق هنا محتوى الدرس…') }}"></textarea></div>
                        <div>
                            <div class="field"><label for="ai-file">{{ __('أو ارفع ملفًا') }}</label>
                                <input class="input" type="file" id="ai-file" name="file" accept=".pdf,.docx,.txt,.md,.srt,.vtt">
                                <p class="hint">PDF · Word (.docx) · TXT · SRT/VTT — {{ __('حتى 10 م.ب') }}</p></div>
                            <div class="form-grid" style="gap:0 10px">
                                <div class="field"><label for="ai-count">{{ __('عدد الأسئلة') }}</label><input class="input" type="number" id="ai-count" name="count" min="3" max="15" value="5"></div>
                                <div class="field"><label for="ai-lang">{{ __('اللغة') }}</label><select class="select" id="ai-lang" name="language"><option value="ar">العربية</option><option value="fr">Français</option><option value="en">English</option></select></div>
                                <div class="field"><label for="ai-diff">{{ __('المستوى') }}</label><select class="select" id="ai-diff" name="difficulty"><option value="easy">{{ __('سهل') }}</option><option value="medium" selected>{{ __('متوسط') }}</option><option value="hard">{{ __('صعب') }}</option></select></div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert--warning small"><x-icon name="info"/> <span>{{ __('سيُرسَل المحتوى الذي تضعه هنا إلى مزوّد الذكاء الاصطناعي (Anthropic) لتوليد الأسئلة. لا تضع بيانات شخصية. الأسئلة تُحفظ في متصفحك فقط.') }}</span></div>
                    <label class="check field"><input type="checkbox" name="consent" value="1" required> {{ __('أوافق على إرسال المحتوى إلى المزوّد') }}</label>
                    <div class="row">
                        <button class="btn btn--amber btn--lg" type="submit" id="ai-submit"><x-icon name="sparkles" size="18"/> {{ __('ولّد الاختبار') }}</button>
                        <p id="ai-status" class="small" role="status" aria-live="polite" style="margin:0"></p>
                    </div>
                </form>
            @endauth
        </section>
    @else
        <div class="alert alert--info overlap no-print"><x-icon name="info"/> <span>{{ __('التوليد بالذكاء الاصطناعي غير متاح حاليًا. يمكنك إنشاء اختبارك يدويًا وتجربته وطباعته.') }}</span></div>
    @endif

    <div class="tool-shell">
        <div class="card card--pad no-print">
            <h2 class="card__title">{{ __('محرر الاختبار') }}</h2>
            <div class="field"><label for="qb-title">{{ __('عنوان الاختبار') }}</label><input class="input" id="qb-title" value="{{ __('اختبار قصير') }}" dir="auto"></div>
            <div id="qb-questions"></div>
            <div class="row">
                <button class="btn btn--outline btn--sm" type="button" id="qb-add"><x-icon name="plus" size="16"/> {{ __('إضافة سؤال') }}</button>
                <button class="btn btn--ghost btn--sm" type="button" onclick="window.print()">{{ __('طباعة') }}</button>
                <button class="btn btn--ghost btn--sm" type="button" id="qb-export">{{ __('تصدير JSON') }}</button>
                <label class="btn btn--ghost btn--sm" for="qb-import">{{ __('استيراد JSON') }}</label><input class="sr-only" type="file" id="qb-import" accept=".json,application/json">
                <button class="btn btn--ghost btn--sm" type="button" id="qb-clear">{{ __('مسح') }}</button>
            </div>
            <p class="small muted" style="margin-top:10px">{{ __('حدّد الإجابة الصحيحة بعلامة ✓ (يمكن اختيار أكثر من إجابة). كل شيء يُحفظ في متصفحك.') }}</p>
        </div>
        <div class="paper printable" id="qb-out" aria-live="polite"></div>
    </div>
</div>
@endsection
@push('scripts')
@php
    $quizStrings = [
        'q' => __('سؤال'), 'qtext' => __('نص السؤال'), 'opt' => __('خيار'), 'correct' => __('إجابة صحيحة'), 'explain' => __('شرح الإجابة (اختياري)'),
        'addOpt' => __('+ خيار'), 'delQ' => __('حذف السؤال'), 'delOpt' => __('حذف الخيار'), 'check' => __('تحقق من الإجابات'), 'retry' => __('أعد المحاولة'),
        'score' => __('النتيجة'), 'empty' => __('أضف سؤالًا أو ولّد اختبارًا لتبدأ.'), 'working' => __('جارٍ قراءة الدرس وتوليد الأسئلة… قد يستغرق ذلك دقيقة.'),
        'done' => __('تم توليد :n سؤال. يمكنك تعديلها أو تجربتها الآن.'), 'failed' => __('تعذّر توليد الاختبار. حاول مجددًا.'), 'replace' => __('استبدال الأسئلة الحالية بالأسئلة الجديدة؟'),
        'remaining' => __('المتبقي اليوم: :n'), 'badFile' => __('ملف غير صالح.'), 'multi' => __('اختر كل الإجابات الصحيحة'),
    ];
@endphp
<script>
(function () {
    var T = {!! json_encode($quizStrings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!};
    var KEY = 'tool:quiz:v2', list = document.getElementById('qb-questions'), out = document.getElementById('qb-out'), titleEl = document.getElementById('qb-title');
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
    var blank = function () { return { q: '', options: ['', '', ''], correct: [0], explanation: '' }; };
    var state; try { state = JSON.parse(localStorage.getItem(KEY)); } catch (e) {}
    if (!state || !Array.isArray(state.questions)) {
        var old; try { old = JSON.parse(localStorage.getItem('tool:quiz')); } catch (e) {}
        state = old && Array.isArray(old.questions)
            ? { title: old.title, questions: old.questions.map(function (q) { return { q: q.q, options: q.options, correct: [q.correct || 0], explanation: '' }; }) }
            : { title: titleEl.value, questions: [blank()] };
    }
    titleEl.value = state.title || titleEl.value;
    function save() { state.title = titleEl.value; try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {} }
    function el(tag, attrs, html) { var n = document.createElement(tag); Object.keys(attrs || {}).forEach(function (k) { n.setAttribute(k, attrs[k]); }); if (html != null) n.innerHTML = html; return n; }

    function draw() {
        list.innerHTML = '';
        state.questions.forEach(function (item, qi) {
            var box = el('fieldset', { class: 'quiz-q' }, '<legend>' + esc(T.q) + ' ' + (qi + 1) + '</legend>');
            var q = el('textarea', { class: 'textarea', rows: '2', dir: 'auto', 'aria-label': T.qtext + ' ' + (qi + 1) }); q.style.minHeight = '60px'; q.value = item.q;
            q.oninput = function () { item.q = q.value; save(); preview(); }; box.appendChild(q);
            item.options.forEach(function (opt, oi) {
                var row = el('div', { class: 'row' }); row.style.marginTop = '8px'; row.style.flexWrap = 'nowrap';
                var cb = el('input', { type: 'checkbox', 'aria-label': T.correct + ' — ' + T.opt + ' ' + (oi + 1) }); cb.checked = item.correct.indexOf(oi) !== -1;
                cb.onchange = function () {
                    item.correct = item.correct.filter(function (c) { return c !== oi; }); if (cb.checked) item.correct.push(oi);
                    item.correct.sort(); save(); preview();
                };
                var inp = el('input', { class: 'input', dir: 'auto', 'aria-label': T.opt + ' ' + (oi + 1) }); inp.style.flex = '1'; inp.value = opt;
                inp.oninput = function () { item.options[oi] = inp.value; save(); preview(); };
                var del = el('button', { type: 'button', class: 'icon-btn', 'aria-label': T.delOpt }, '×');
                del.onclick = function () {
                    if (item.options.length <= 2) return;
                    item.options.splice(oi, 1);
                    item.correct = item.correct.filter(function (c) { return c !== oi; }).map(function (c) { return c > oi ? c - 1 : c; });
                    save(); draw();
                };
                row.appendChild(cb); row.appendChild(inp); row.appendChild(del); box.appendChild(row);
            });
            var ex = el('input', { class: 'input', dir: 'auto', placeholder: T.explain, 'aria-label': T.explain }); ex.style.marginTop = '8px'; ex.value = item.explanation || '';
            ex.oninput = function () { item.explanation = ex.value; save(); }; box.appendChild(ex);
            var tools = el('div', { class: 'row' }); tools.style.marginTop = '8px';
            var addO = el('button', { type: 'button', class: 'btn btn--ghost btn--sm' }, esc(T.addOpt)); addO.onclick = function () { if (item.options.length < 6) { item.options.push(''); save(); draw(); } };
            var delQ = el('button', { type: 'button', class: 'btn btn--ghost btn--sm' }, esc(T.delQ)); delQ.onclick = function () { state.questions.splice(qi, 1); save(); draw(); };
            tools.appendChild(addO); tools.appendChild(delQ); box.appendChild(tools);
            list.appendChild(box);
        });
        preview();
    }

    function preview() {
        var qs = state.questions.filter(function (x) { return x.q.trim() && x.options.filter(function (o) { return o.trim(); }).length >= 2; });
        if (!qs.length) { out.innerHTML = '<h2 dir="auto">' + esc(titleEl.value) + '</h2><p style="color:#666">' + esc(T.empty) + '</p>'; return; }
        out.innerHTML = '<h2 dir="auto">' + esc(titleEl.value) + '</h2>' + qs.map(function (item, qi) {
            var multi = item.correct.length > 1;
            return '<div class="pq" data-i="' + qi + '" style="margin-bottom:18px" dir="auto"><strong>' + (qi + 1) + '. ' + esc(item.q) + '</strong>'
                + (multi ? ' <span style="color:#666;font-size:13px">(' + esc(T.multi) + ')</span>' : '')
                + item.options.map(function (o, oi) {
                    return o.trim() ? '<label class="quiz-opt" style="margin-top:6px"><input type="' + (multi ? 'checkbox' : 'radio') + '" name="p' + qi + '" value="' + oi + '"> <span>' + esc(o) + '</span></label>' : '';
                }).join('') + '<p class="pq-ex" hidden style="font-size:13px;color:#444;margin:6px 0 0"></p></div>';
        }).join('') + '<div class="no-print row"><button class="btn btn--amber" type="button" id="qb-check">' + esc(T.check) + '</button><button class="btn btn--ghost" type="button" id="qb-retry">' + esc(T.retry) + '</button></div><p id="qb-score" role="status" style="font-weight:800;font-size:1.1rem"></p>';

        document.getElementById('qb-check').onclick = function () {
            var ok = 0;
            qs.forEach(function (item, qi) {
                var box = out.querySelector('.pq[data-i="' + qi + '"]');
                var given = Array.prototype.map.call(box.querySelectorAll('input:checked'), function (i) { return +i.value; }).sort();
                var right = item.correct.slice().sort();
                var good = given.length === right.length && given.every(function (v, i) { return v === right[i]; });
                if (good) ok++;
                box.querySelectorAll('.quiz-opt').forEach(function (lab) {
                    var v = +lab.querySelector('input').value;
                    lab.classList.toggle('is-correct', right.indexOf(v) !== -1);
                    lab.classList.toggle('is-wrong', given.indexOf(v) !== -1 && right.indexOf(v) === -1);
                });
                var ex = box.querySelector('.pq-ex'); if (item.explanation) { ex.textContent = '💡 ' + item.explanation; ex.hidden = false; }
            });
            document.getElementById('qb-score').textContent = T.score + ': ' + ok + ' / ' + qs.length + ' (' + Math.round(ok * 100 / qs.length) + '%)';
        };
        document.getElementById('qb-retry').onclick = preview;
    }

    function load(questions) {
        state.questions = questions.map(function (q) {
            return { q: String(q.question || q.q || ''), options: (q.options || []).map(String).slice(0, 6), correct: (q.correct || [0]).map(Number), explanation: String(q.explanation || '') };
        }).filter(function (q) { return q.q && q.options.length >= 2; });
        if (!state.questions.length) state.questions = [blank()];
        save(); draw(); out.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    titleEl.oninput = function () { save(); preview(); };
    document.getElementById('qb-add').onclick = function () { state.questions.push(blank()); save(); draw(); };
    document.getElementById('qb-clear').onclick = function () { state.questions = [blank()]; save(); draw(); };
    document.getElementById('qb-export').onclick = function () {
        var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([JSON.stringify(state, null, 2)], { type: 'application/json' })); a.download = 'quiz.json'; a.click();
    };
    document.getElementById('qb-import').onchange = function () {
        var f = this.files[0]; if (!f) return;
        f.text().then(function (txt) { try { var d = JSON.parse(txt); if (d.title) titleEl.value = d.title; load(d.questions || []); } catch (e) { alert(T.badFile); } });
        this.value = '';
    };

    var form = document.getElementById('ai-form');
    if (form) {
        var status = document.getElementById('ai-status'), btn = document.getElementById('ai-submit');
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var hasContent = state.questions.some(function (q) { return q.q.trim(); });
            if (hasContent && !confirm(T.replace)) return;
            btn.disabled = true; status.className = 'small muted'; status.style.color = ''; status.textContent = T.working;
            fetch(@json(route('tools.quiz.generate')), {
                method: 'POST', body: new FormData(form), credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
            }).then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; }); })
              .then(function (res) {
                  if (!res.ok) {
                      var msg = res.d.message || T.failed;
                      if (res.d.errors) msg = Object.values(res.d.errors).map(function (v) { return v[0]; }).join(' ');
                      throw new Error(msg);
                  }
                  load(res.d.questions);
                  status.className = 'small'; status.style.color = 'var(--green)';
                  status.textContent = T.done.replace(':n', res.d.questions.length) + (typeof res.d.remaining === 'number' ? ' — ' + T.remaining.replace(':n', res.d.remaining) : '');
              })
              .catch(function (err) { status.className = 'small error'; status.textContent = err.message || T.failed; })
              .finally(function () { btn.disabled = false; });
        });
    }
    draw();
})();
</script>
@endpush
