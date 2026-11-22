@extends('layouts.app')
@section('title', $tool->tr('name'))
@section('description', $tool->tr('description'))
@section('content')
@include('tools._head')
<div class="container section--tight">
    <div class="tool-shell overlap">
        <form class="card card--pad no-print" id="inv-form" onsubmit="return false">
            <div class="form-grid">
                <div class="field"><label for="i-type">{{ __('النوع') }}</label><select class="select" id="i-type" name="type"><option value="invoice">{{ __('فاتورة') }}</option><option value="quote">{{ __('عرض سعر') }}</option></select></div>
                <div class="field"><label for="i-lang">{{ __('اللغة') }}</label><select class="select" id="i-lang" name="lang"><option value="fr">Français</option><option value="ar">العربية</option><option value="en">English</option></select></div>
                <div class="field"><label for="i-num">{{ __('الرقم') }}</label><input class="input input-ltr" id="i-num" name="number" value="F-0001"></div>
                <div class="field"><label for="i-date">{{ __('التاريخ') }}</label><input class="input" type="date" id="i-date" name="date"></div>
                <div class="field"><label for="i-cur">{{ __('العملة') }}</label><input class="input input-ltr" id="i-cur" name="currency" value="MAD"></div>
                <div class="field"><label for="i-tax">{{ __('الضريبة %') }}</label><input class="input input-ltr" type="number" id="i-tax" name="tax" value="20" min="0" max="100" step="0.01"></div>
            </div>
            <div class="field"><label for="i-from">{{ __('المُصدِر (الاسم، العنوان، ICE…)') }}</label><textarea class="textarea" id="i-from" name="from" style="min-height:80px" dir="auto"></textarea></div>
            <div class="field"><label for="i-to">{{ __('الزبون') }}</label><textarea class="textarea" id="i-to" name="to" style="min-height:80px" dir="auto"></textarea></div>
            <fieldset style="border:0;padding:0;margin:0 0 12px"><legend class="label">{{ __('البنود') }}</legend>
                <div id="items"></div>
                <button class="btn btn--outline btn--sm" type="button" id="add-item"><x-icon name="plus" size="16"/> {{ __('إضافة بند') }}</button>
            </fieldset>
            <div class="field"><label for="i-notes">{{ __('ملاحظات / شروط الأداء') }}</label><textarea class="textarea" id="i-notes" name="notes" style="min-height:70px" dir="auto"></textarea></div>
            <div class="field"><label for="i-color">{{ __('اللون') }}</label><input class="input" type="color" id="i-color" name="color" value="#0f766e"></div>
            <button class="btn btn--primary" type="button" onclick="window.print()"><x-icon name="download" size="18"/> {{ __('طباعة / حفظ PDF') }}</button>
        </form>
        <div class="paper printable" id="inv-out"></div>
    </div>
</div>
@endsection
@push('scripts')
<script>
(function () {
    var form = document.getElementById('inv-form'), out = document.getElementById('inv-out'), items = document.getElementById('items'), KEY = 'tool:invoice';
    var T = {
        fr: { invoice: 'FACTURE', quote: 'DEVIS', to: 'Client', desc: 'Désignation', qty: 'Qté', price: 'P.U. HT', total: 'Total HT', sub: 'Total HT', tax: 'TVA', ttc: 'Total TTC', date: 'Date' },
        en: { invoice: 'INVOICE', quote: 'QUOTE', to: 'Bill to', desc: 'Description', qty: 'Qty', price: 'Unit price', total: 'Amount', sub: 'Subtotal', tax: 'Tax', ttc: 'Total', date: 'Date' },
        ar: { invoice: 'فاتورة', quote: 'عرض سعر', to: 'الزبون', desc: 'البيان', qty: 'الكمية', price: 'ثمن الوحدة', total: 'المجموع', sub: 'المجموع دون ضريبة', tax: 'الضريبة', ttc: 'المجموع الكلي', date: 'التاريخ' }
    };
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
    function addItem(v) {
        v = v || { d: '', q: 1, p: 0 };
        var row = document.createElement('div'); row.className = 'row item'; row.style.marginBottom = '8px';
        row.innerHTML = '<input class="input" style="flex:3" placeholder="{{ __('الوصف') }}" aria-label="{{ __('الوصف') }}" data-k="d" dir="auto"><input class="input input-ltr" style="flex:1;min-width:60px" type="number" min="0" step="any" aria-label="{{ __('الكمية') }}" data-k="q"><input class="input input-ltr" style="flex:1;min-width:80px" type="number" min="0" step="any" aria-label="{{ __('الثمن') }}" data-k="p"><button class="icon-btn" type="button" aria-label="{{ __('حذف البند') }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg></button>';
        row.querySelector('[data-k=d]').value = v.d; row.querySelector('[data-k=q]').value = v.q; row.querySelector('[data-k=p]').value = v.p;
        row.querySelector('button').onclick = function () { row.remove(); render(); };
        items.appendChild(row);
    }
    function data() {
        var d = Object.fromEntries(new FormData(form));
        d.items = Array.prototype.map.call(items.querySelectorAll('.item'), function (r) { return { d: r.querySelector('[data-k=d]').value, q: parseFloat(r.querySelector('[data-k=q]').value) || 0, p: parseFloat(r.querySelector('[data-k=p]').value) || 0 }; });
        return d;
    }
    function money(n, cur) { return n.toLocaleString('fr-MA', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + esc(cur); }
    function render() {
        var d = data(), t = T[d.lang] || T.fr, c = d.color, sub = 0;
        out.setAttribute('dir', d.lang === 'ar' ? 'rtl' : 'ltr'); out.lang = d.lang;
        var rows = d.items.map(function (i) { var tot = i.q * i.p; sub += tot; return '<tr><td style="padding:8px;border-bottom:1px solid #eee">' + esc(i.d) + '</td><td style="padding:8px;border-bottom:1px solid #eee;text-align:center">' + i.q + '</td><td style="padding:8px;border-bottom:1px solid #eee">' + money(i.p, d.currency) + '</td><td style="padding:8px;border-bottom:1px solid #eee">' + money(tot, d.currency) + '</td></tr>'; }).join('');
        var tax = sub * (parseFloat(d.tax) || 0) / 100;
        out.innerHTML = '<div style="display:flex;justify-content:space-between;gap:20px;align-items:flex-start"><div style="white-space:pre-line;font-size:13px">' + esc(d.from) + '</div><div style="text-align:end"><h2 style="margin:0;color:' + c + ';letter-spacing:.08em">' + t[d.type] + '</h2><p style="margin:4px 0;font-size:13px">N° ' + esc(d.number) + '<br>' + t.date + ': ' + esc(d.date) + '</p></div></div>'
            + '<div style="margin:24px 0;padding:12px;background:#f7f7f7;border-radius:6px;font-size:13px"><strong>' + t.to + '</strong><div style="white-space:pre-line">' + esc(d.to) + '</div></div>'
            + '<table style="width:100%;border-collapse:collapse;font-size:13px"><thead><tr style="background:' + c + ';color:#fff"><th style="padding:8px;text-align:start">' + t.desc + '</th><th style="padding:8px">' + t.qty + '</th><th style="padding:8px;text-align:start">' + t.price + '</th><th style="padding:8px;text-align:start">' + t.total + '</th></tr></thead><tbody>' + rows + '</tbody></table>'
            + '<table style="margin-inline-start:auto;margin-top:16px;font-size:14px"><tr><td style="padding:4px 16px">' + t.sub + '</td><td>' + money(sub, d.currency) + '</td></tr><tr><td style="padding:4px 16px">' + t.tax + ' (' + esc(d.tax) + '%)</td><td>' + money(tax, d.currency) + '</td></tr><tr><td style="padding:8px 16px;font-weight:800;color:' + c + '">' + t.ttc + '</td><td style="font-weight:800;color:' + c + '">' + money(sub + tax, d.currency) + '</td></tr></table>'
            + (d.notes ? '<p style="margin-top:28px;font-size:12px;color:#555;white-space:pre-line">' + esc(d.notes) + '</p>' : '');
        try { localStorage.setItem(KEY, JSON.stringify(d)); } catch (e) {}
    }
    var saved = {}; try { saved = JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) {}
    Object.keys(saved).forEach(function (k) { if (form.elements[k] && k !== 'items') form.elements[k].value = saved[k]; });
    if (!form.elements.date.value) form.elements.date.value = new Date().toISOString().slice(0, 10);
    (saved.items && saved.items.length ? saved.items : [{ d: '', q: 1, p: 0 }]).forEach(addItem);
    document.getElementById('add-item').onclick = function () { addItem(); render(); };
    form.addEventListener('input', render); render();
})();
</script>
@endpush
