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
  // Remplace l'identifiant fictif « /0 » d'une URL générée côté serveur (adresses simplifiées ou index.php?route=…)
  window.HOC.withId = function (url, id) { return url.replace(/\/0(?=\/|&|\?|#|$)/, '/' + encodeURIComponent(id)); };
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
      window.HOC.fetchJson(window.HOC.withId(notif.dataset.read, a.dataset.id), { method: 'POST' })
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

/* Sélecteur avec recherche distante (produits, clients, matières…).
   <div class="hoc-lookup" data-lookup="URL JSON">
     <input data-lookup-input> <input type="hidden" data-lookup-value>
   </div>
   L'URL renvoie {items:[{id, name, code, detail}]}. Événement « hoc:lookup » émis à la sélection. */
(function () {
  'use strict';
  var esc = function (t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; };
  function initBox(box) {
    if (box.dataset.ready || box.closest('[data-template]')) { return; }
    box.dataset.ready = '1';
    var stale = box.querySelector('.hoc-lookup-menu');
    if (stale) { stale.remove(); }
    var input = box.querySelector('[data-lookup-input]'), hidden = box.querySelector('[data-lookup-value]');
    var menu = document.createElement('div'), timer = null, items = [], active = -1;
    menu.className = 'hoc-lookup-menu shadow d-none';
    box.appendChild(menu);
    var close = function () { menu.classList.add('d-none'); active = -1; };
    var choose = function (it) {
      input.value = (it.code ? it.code + ' — ' : '') + it.name;
      hidden.value = it.id;
      close();
      box.dispatchEvent(new CustomEvent('hoc:lookup', { detail: it, bubbles: true }));
    };
    var render = function () {
      if (!items.length) { menu.innerHTML = '<div class="p-2 small text-body-secondary">Aucun résultat</div>'; }
      else {
        menu.innerHTML = items.map(function (it, i) {
          return '<button type="button" class="hoc-lookup-item' + (i === active ? ' active' : '') + '" data-i="' + i + '"><strong>' + esc(it.name) + '</strong>'
            + (it.code ? ' <code>' + esc(it.code) + '</code>' : '') + (it.detail ? '<small class="d-block text-body-secondary">' + esc(it.detail) + '</small>' : '') + '</button>';
        }).join('');
      }
      menu.classList.remove('d-none');
    };
    input.addEventListener('input', function () {
      hidden.value = '';
      clearTimeout(timer);
      var q = input.value.trim();
      if (q.length < 1) { close(); return; }
      timer = setTimeout(function () {
        var url = box.dataset.lookup + (box.dataset.lookup.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(q);
        window.HOC.fetchJson(url).then(function (d) { items = d.items || []; active = -1; render(); }).catch(close);
      }, 200);
    });
    input.addEventListener('keydown', function (e) {
      if (menu.classList.contains('d-none')) { return; }
      if (e.key === 'ArrowDown') { active = Math.min(items.length - 1, active + 1); render(); e.preventDefault(); }
      else if (e.key === 'ArrowUp') { active = Math.max(0, active - 1); render(); e.preventDefault(); }
      else if (e.key === 'Enter' && active >= 0) { choose(items[active]); e.preventDefault(); }
      else if (e.key === 'Escape') { close(); }
    });
    menu.addEventListener('mousedown', function (e) {
      var b = e.target.closest('.hoc-lookup-item');
      if (b) { e.preventDefault(); choose(items[+b.dataset.i]); }
    });
    input.addEventListener('blur', function () { setTimeout(close, 150); });
    // Un formulaire ne peut pas être envoyé sans sélection valide
    var form = box.closest('form');
    if (form && hidden.hasAttribute('required')) {
      form.addEventListener('submit', function (e) {
        if (!hidden.value) { e.preventDefault(); input.classList.add('is-invalid'); input.focus(); }
      });
    }
  }
  window.HOC = window.HOC || {};
  window.HOC.initLookups = function (root) { (root || document).querySelectorAll('.hoc-lookup').forEach(initBox); if (root && root.classList && root.classList.contains('hoc-lookup')) { initBox(root); } };
  window.HOC.initLookups(document);
})();

/* Recherche globale de la barre supérieure (résultats instantanés, Ctrl+K) */
(function () {
  'use strict';
  var form = document.getElementById('hocSearch');
  if (!form) { return; }
  var input = document.getElementById('hocSearchInput'), box = document.getElementById('hocSearchResults'), timer = null, seq = 0;
  var esc = function (t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; };
  var close = function () { box.classList.add('d-none'); };
  input.addEventListener('input', function () {
    clearTimeout(timer);
    var q = input.value.trim();
    if (q.length < 2) { close(); return; }
    timer = setTimeout(function () {
      var my = ++seq, url = form.dataset.url + (form.dataset.url.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(q);
      window.HOC.fetchJson(url).then(function (d) {
        if (my !== seq) { return; }
        if (!d.groups.length) { box.innerHTML = '<div class="p-3 small text-body-secondary">Aucun résultat pour « ' + esc(q) + ' »</div>'; }
        else {
          box.innerHTML = d.groups.map(function (g) {
            return '<div class="hoc-search-group"><i class="bi ' + esc(g.icon) + '"></i> ' + esc(g.label) + '</div>'
              + g.items.slice(0, 5).map(function (it) {
                return '<a class="hoc-search-item" href="' + esc(it.url) + '"><strong>' + esc(it.title) + '</strong><small>' + esc(it.subtitle) + '</small></a>';
              }).join('');
          }).join('') + '<button class="hoc-search-all" type="submit">Voir tous les résultats</button>';
        }
        box.classList.remove('d-none');
      }).catch(close);
    }, 220);
  });
  input.addEventListener('keydown', function (e) {
    var items = Array.prototype.slice.call(box.querySelectorAll('.hoc-search-item'));
    var idx = items.indexOf(document.activeElement);
    if (e.key === 'ArrowDown' && items.length) { items[0].focus(); e.preventDefault(); }
    if (e.key === 'Escape') { close(); input.blur(); }
    void idx;
  });
  box.addEventListener('keydown', function (e) {
    var items = Array.prototype.slice.call(box.querySelectorAll('.hoc-search-item'));
    var i = items.indexOf(document.activeElement);
    if (e.key === 'ArrowDown' && i < items.length - 1) { items[i + 1].focus(); e.preventDefault(); }
    if (e.key === 'ArrowUp') { (i > 0 ? items[i - 1] : input).focus(); e.preventDefault(); }
    if (e.key === 'Escape') { close(); input.focus(); }
  });
  document.addEventListener('click', function (e) { if (!form.contains(e.target)) { close(); } });
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); input.focus(); input.select(); }
  });
})();
