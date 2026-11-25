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
      // Préférence conservée sur le compte (tous les appareils)
      var body = new URLSearchParams({ theme: next });
      if (window.HOC && window.HOC.fetchJson && themeBtn.dataset.url) {
        window.HOC.fetchJson(themeBtn.dataset.url, { method: 'POST', body: body }).catch(function () {});
      }
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

  // Notifications de la barre supérieure
  var notif = document.getElementById('hocNotif');
  if (notif) {
    var list = document.getElementById('hocNotifList');
    var badge = document.getElementById('hocNotifCount');
    var esc = function (t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; };
    var icons = { info: 'info-circle', success: 'check-circle', warning: 'exclamation-triangle', danger: 'exclamation-octagon' };
    var setBadge = function (n) {
      badge.textContent = n > 99 ? '99+' : String(n);
      badge.classList.toggle('d-none', !n);
    };
    var render = function (data) {
      setBadge(data.unread);
      if (!data.items.length) {
        list.innerHTML = '<div class="p-4 text-center text-body-secondary small"><i class="bi bi-bell-slash d-block fs-4 mb-1"></i>Aucune notification</div>';
        return;
      }
      list.innerHTML = data.items.map(function (n) {
        var sev = icons[n.severity] ? n.severity : 'info';
        return '<a href="' + esc(n.link || '#') + '" class="hoc-notif-item' + (n.read ? '' : ' unread') + '" data-id="' + n.id + '">'
          + '<i class="bi bi-' + icons[sev] + ' text-' + sev + '"></i><span><strong>' + esc(n.title) + '</strong>'
          + (n.message ? '<span class="d-block small text-body-secondary">' + esc(n.message) + '</span>' : '')
          + '<span class="d-block small text-body-secondary">' + esc(n.date) + '</span></span></a>';
      }).join('');
    };
    var load = function () {
      window.HOC.fetchJson(notif.dataset.feed).then(render).catch(function () {
        list.innerHTML = '<div class="p-3 text-center text-danger small">Impossible de charger les notifications.</div>';
      });
    };
    notif.addEventListener('show.bs.dropdown', load);
    list.addEventListener('click', function (e) {
      var a = e.target.closest('.hoc-notif-item');
      if (!a) { return; }
      var href = a.getAttribute('href');
      e.preventDefault();
      window.HOC.fetchJson(notif.dataset.read.replace('/0/', '/' + a.dataset.id + '/'), { method: 'POST' })
        .then(function (r) { setBadge(r.unread); a.classList.remove('unread'); if (href && href !== '#') { window.location.href = href; } })
        .catch(function () { if (href && href !== '#') { window.location.href = href; } });
    });
    document.getElementById('hocNotifReadAll').addEventListener('click', function () {
      window.HOC.fetchJson(notif.dataset.readAll, { method: 'POST' }).then(function () { setBadge(0); load(); });
    });
  }

  // Confirmation avant action sensible : <form data-confirm="Supprimer ce client ?">
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { e.preventDefault(); }
  });
})();
