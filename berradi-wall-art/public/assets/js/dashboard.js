/* BERRADI WALL ART — dashboard charts (Chart.js 4, self-hosted).
 * Single-series charts: one brand mark color (#A98B52, ≥3:1 on white), ink text, recessive grid,
 * per-mark tooltips, and a table view under every chart (accessibility / no-canvas fallback).
 */
(function () {
    'use strict';
    const el = document.getElementById('dashboard-data');
    if (!el) return;
    const data = JSON.parse(el.textContent || '{}');
    const MARK = '#A98B52', MARK_HOVER = '#7C623A', NEG = '#6F6A62', INK = '#292929', MUTED = '#6F6A62', GRID = '#EEEBE5';
    const rtl = document.documentElement.dir === 'rtl';
    const nf = new Intl.NumberFormat(document.documentElement.lang === 'ar' ? 'ar-MA' : 'fr-MA', { maximumFractionDigits: 0 });
    const money = (v) => nf.format(v) + ' ' + (data.currency || 'DH');
    const isMoney = { monthlySales: true, monthlyProfit: true, revenueByCategory: true, netCash: true };
    const fmt = (key, v) => (isMoney[key] ? money(v) : nf.format(v));

    function series(key) {
        const d = data[key];
        if (!d) return null;
        if (Array.isArray(d)) return { labels: d.map(r => r.label), values: d.map(r => r.value) };
        return { labels: d.labels, values: d.values };
    }

    function table(key, s) {
        const box = document.querySelector('[data-chart-table="' + key + '"]');
        if (!box) return;
        const t = document.createElement('table');
        t.className = 'table table-sm mb-0';
        s.labels.forEach((label, i) => {
            const tr = t.insertRow();
            tr.insertCell().textContent = label;
            const td = tr.insertCell();
            td.className = 'text-end';
            td.textContent = fmt(key, s.values[i]);
        });
        box.appendChild(t);
    }

    if (typeof window.Chart !== 'undefined') {
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Chart.defaults.font.size = 12;
        Chart.defaults.color = MUTED;
    }

    document.querySelectorAll('canvas[data-chart]').forEach(function (canvas) {
        const key = canvas.dataset.chart;
        const s = series(key);
        if (!s) return;
        table(key, s);
        if (!s.values.length || s.values.every(v => v === 0)) {
            const p = document.createElement('div');
            p.className = 'chart-empty';
            p.textContent = data.i18n.empty;
            canvas.replaceWith(p);
            return;
        }
        if (typeof window.Chart === 'undefined') return; // table view remains
        const horizontal = canvas.dataset.type === 'hbar';
        const colors = s.values.map(v => (v < 0 ? NEG : MARK));
        const valueAxis = {
            beginAtZero: true,
            grid: { color: GRID, drawTicks: false },
            border: { display: false },
            ticks: { color: MUTED, padding: 6, callback: (v) => fmt(key, v), maxTicksLimit: 6, precision: isMoney[key] ? undefined : 0 },
        };
        const catAxis = {
            grid: { display: false },
            border: { color: '#D8D2C6' },
            ticks: { color: INK, autoSkip: !horizontal, maxRotation: 0, callback: function (v) { const l = this.getLabelForValue(v); return l.length > 26 ? l.slice(0, 25) + '…' : l; } },
            reverse: rtl && !horizontal,
        };
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: s.labels,
                datasets: [{
                    data: s.values,
                    backgroundColor: colors,
                    hoverBackgroundColor: s.values.map(v => (v < 0 ? INK : MARK_HOVER)),
                    borderRadius: 4,
                    borderSkipped: 'start',
                    maxBarThickness: horizontal ? 18 : 28,
                    categoryPercentage: 0.7,
                }],
            },
            options: {
                indexAxis: horizontal ? 'y' : 'x',
                maintainAspectRatio: false,
                animation: { duration: 300 },
                plugins: {
                    legend: { display: false }, // single series: the card title names it
                    tooltip: {
                        backgroundColor: '#171717', titleColor: '#fff', bodyColor: '#fff', padding: 10, displayColors: false, rtl: rtl,
                        callbacks: {
                            label: (ctx) => {
                                const lines = [fmt(key, ctx.parsed[horizontal ? 'x' : 'y'])];
                                if (key === 'monthlySales' && data.monthlySales.orders) lines.push(data.monthlySales.orders[ctx.dataIndex] + ' ' + data.i18n.orders);
                                return lines;
                            },
                        },
                    },
                },
                scales: horizontal ? { x: Object.assign({ position: rtl ? 'top' : 'bottom', reverse: rtl }, valueAxis), y: Object.assign({}, catAxis, { position: rtl ? 'right' : 'left' }) }
                    : { x: catAxis, y: Object.assign({ position: rtl ? 'right' : 'left' }, valueAxis) },
            },
        });
    });
})();
