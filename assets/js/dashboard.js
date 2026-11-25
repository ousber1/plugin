/* HO CREATIVE — graphiques du tableau de bord (Chart.js).
   Palette catégorielle validée (clair/sombre) lue depuis les variables CSS --series-N. */
(function () {
  'use strict';
  var el = document.getElementById('dashData');
  if (!el || typeof Chart === 'undefined') { return; }
  var data = JSON.parse(el.textContent);
  var charts = [];

  function css(name) { return getComputedStyle(document.documentElement).getPropertyValue(name).trim(); }
  function fmt(v) {
    return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(v).replace(/ /g, ' ') + ' ' + data.currency;
  }
  function fmtShort(v) {
    var a = Math.abs(v);
    if (a >= 1e6) { return (v / 1e6).toFixed(1).replace('.', ',') + ' M'; }
    if (a >= 1e4) { return Math.round(v / 1e3) + ' k'; }
    if (a >= 1e3) { return (v / 1e3).toFixed(1).replace('.0', '').replace('.', ',') + ' k'; }
    return String(Math.round(v));
  }

  function theme() {
    return {
      text: css('--bs-secondary-color') || '#666',
      grid: css('--hoc-border') || '#e4e4e7',
      surface: css('--hoc-surface') || '#fff',
      s1: css('--series-1'), s2: css('--series-2'), s3: css('--series-3')
    };
  }

  function baseOptions(t, horizontal) {
    var valueAxis = {
      beginAtZero: true, border: { display: false },
      grid: { color: t.grid, drawTicks: false }, ticks: { color: t.text, padding: 6, callback: fmtShort }
    };
    var catAxis = { grid: { display: false }, border: { color: t.grid }, ticks: { color: t.text, autoSkip: true, maxRotation: 0 } };
    return {
      responsive: true, maintainAspectRatio: false, indexAxis: horizontal ? 'y' : 'x',
      interaction: { mode: horizontal ? 'nearest' : 'index', intersect: false },
      plugins: {
        legend: { display: false, labels: { color: t.text, usePointStyle: true, pointStyle: 'rectRounded', boxWidth: 10, boxHeight: 10 } },
        tooltip: { callbacks: { label: function (c) { return (c.dataset.label ? c.dataset.label + ' : ' : '') + fmt(c.parsed[horizontal ? 'x' : 'y']); } } }
      },
      scales: horizontal ? { x: valueAxis, y: catAxis } : { x: catAxis, y: valueAxis }
    };
  }

  var barStyle = { borderRadius: 4, borderSkipped: 'start', maxBarThickness: 28, borderWidth: 0 };

  function build() {
    charts.forEach(function (c) { c.destroy(); });
    charts = [];
    var t = theme();
    Chart.defaults.font.family = 'Inter, system-ui, sans-serif';

    var m = document.getElementById('chartMonthly');
    if (m) {
      var o = baseOptions(t, false);
      o.plugins.legend.display = true;
      o.plugins.legend.position = 'top';
      o.plugins.legend.align = 'end';
      charts.push(new Chart(m, {
        data: {
          labels: data.monthly.labels,
          datasets: [
            Object.assign({ type: 'bar', label: 'Chiffre d\'affaires HT', data: data.monthly.revenue, backgroundColor: t.s1, order: 2 }, barStyle),
            Object.assign({ type: 'bar', label: 'Dépenses HT', data: data.monthly.expenses, backgroundColor: t.s2, order: 3 }, barStyle),
            { type: 'line', label: 'Bénéfice estimé', data: data.monthly.profit, borderColor: t.s3, backgroundColor: t.s3,
              borderWidth: 2, pointRadius: 4, pointHoverRadius: 6, pointBorderColor: t.surface, pointBorderWidth: 2, tension: 0.25, order: 1 }
          ]
        },
        options: o
      }));
    }

    var d = document.getElementById('chartDaily');
    if (d) {
      charts.push(new Chart(d, {
        type: 'bar',
        data: { labels: data.daily.labels, datasets: [Object.assign({ label: 'CA HT', data: data.daily.revenue, backgroundColor: t.s1 }, barStyle, { maxBarThickness: 16 })] },
        options: baseOptions(t, false)
      }));
    }

    [['chartCategory', data.byCategory], ['chartMachine', data.byMachine]].forEach(function (pair) {
      var c = document.getElementById(pair[0]);
      if (!c || !pair[1].length) { return; }
      charts.push(new Chart(c, {
        type: 'bar',
        data: {
          labels: pair[1].map(function (r) { return r.label; }),
          datasets: [Object.assign({ label: 'CA HT', data: pair[1].map(function (r) { return r.value; }), backgroundColor: t.s1 }, barStyle, { maxBarThickness: 22 })]
        },
        options: baseOptions(t, true)
      }));
    });
  }

  build();
  // Reconstruit les graphiques au changement de thème clair / sombre
  new MutationObserver(build).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
})();
