/* HO CREATIVE — scripts communs de l'interface */
(function () {
  'use strict';

  var shell = document.getElementById('hocShell');
  var toggle = document.getElementById('hocToggle');
  var backdrop = document.getElementById('hocBackdrop');
  var themeBtn = document.getElementById('hocTheme');
  var isMobile = function () { return window.matchMedia('(max-width: 991.98px)').matches; };

  function store(key, value) { try { localStorage.setItem(key, value); } catch (e) { /* stockage indisponible */ } }
  function read(key) { try { return localStorage.getItem(key); } catch (e) { return null; } }

  // Barre latérale : repli sur bureau, tiroir sur mobile
  if (shell && read('hoc-sidebar') === 'collapsed' && !isMobile()) { shell.classList.add('collapsed'); }
  if (toggle && shell) {
    toggle.addEventListener('click', function () {
      if (isMobile()) {
        shell.classList.toggle('mobile-open');
      } else {
        shell.classList.toggle('collapsed');
        store('hoc-sidebar', shell.classList.contains('collapsed') ? 'collapsed' : 'expanded');
      }
    });
  }
  if (backdrop && shell) { backdrop.addEventListener('click', function () { shell.classList.remove('mobile-open'); }); }

  // Thème clair / sombre
  function applyThemeIcon() {
    if (!themeBtn) { return; }
    var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    themeBtn.firstElementChild.className = dark ? 'bi bi-sun' : 'bi bi-moon-stars';
  }
  if (themeBtn) {
    applyThemeIcon();
    themeBtn.addEventListener('click', function () {
      var next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-bs-theme', next);
      store('hoc-theme', next);
      applyThemeIcon();
    });
  }

  // Assistant AJAX : envoie automatiquement le jeton CSRF
  window.HOC = window.HOC || {};
  window.HOC.csrf = function () {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  };
  window.HOC.fetchJson = function (url, options) {
    options = options || {};
    options.headers = Object.assign({
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json',
      'X-CSRF-Token': window.HOC.csrf()
    }, options.headers || {});
    options.credentials = 'same-origin';
    return fetch(url, options).then(function (r) {
      return r.json().then(function (data) {
        if (!r.ok) { var err = new Error(data.message || 'Erreur ' + r.status); err.data = data; throw err; }
        return data;
      });
    });
  };

  // Confirmation avant action sensible : <form data-confirm="Supprimer ce client ?">
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { e.preventDefault(); }
  });
})();
