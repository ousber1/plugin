/**
 * Relay Stream Portal — front-end behaviour (no dependencies besides the bundled QR library).
 * Credentials are fetched only on explicit user action from an authenticated REST endpoint and
 * are never written to storage.
 */
( function () {
	'use strict';

	var cfg = window.RSP || { rest: '', nonce: '', i18n: {} };
	var t = function ( k ) { return ( cfg.i18n && cfg.i18n[ k ] ) || k; };
	var STORE = 'rsp-theme';

	function store( key, val ) {
		try {
			if ( undefined === val ) { return window.localStorage.getItem( key ); }
			window.localStorage.setItem( key, val );
		} catch ( e ) { return null; }
		return null;
	}

	function api( path, method ) {
		return fetch( cfg.rest + path, {
			method: method || 'GET',
			credentials: 'same-origin',
			cache: 'no-store',
			headers: { 'X-WP-Nonce': cfg.nonce, 'Accept': 'application/json' }
		} ).then( function ( r ) {
			return r.json().then( function ( body ) {
				if ( ! r.ok ) { throw new Error( ( body && body.message ) || t( 'error' ) ); }
				return body;
			} );
		} );
	}

	function copyText( text, btn ) {
		var done = function () {
			if ( ! btn ) { return; }
			var label = btn.querySelector( 'span' );
			var old = label ? label.textContent : '';
			btn.classList.add( 'is-copied' );
			if ( label ) { label.textContent = t( 'copied' ); }
			setTimeout( function () { btn.classList.remove( 'is-copied' ); if ( label ) { label.textContent = old; } }, 1600 );
		};
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( done, fallback );
		} else { fallback(); }
		function fallback() {
			var ta = document.createElement( 'textarea' );
			ta.value = text; ta.setAttribute( 'readonly', '' ); ta.style.position = 'fixed'; ta.style.opacity = '0';
			document.body.appendChild( ta ); ta.select();
			try { document.execCommand( 'copy' ); done(); } catch ( e ) {}
			document.body.removeChild( ta );
		}
	}

	function effectiveTheme( root ) {
		var v = root.getAttribute( 'data-rsp-theme' );
		if ( 'auto' !== v ) { return v; }
		return window.matchMedia && window.matchMedia( '(prefers-color-scheme: light)' ).matches ? 'light' : 'dark';
	}

	function applyTheme( theme ) {
		document.querySelectorAll( '.rsp-root' ).forEach( function ( r ) {
			if ( '0' !== r.getAttribute( 'data-rsp-toggle' ) || r.classList.contains( 'rsp-floating' ) ) {
				r.setAttribute( 'data-rsp-theme', theme );
			}
		} );
	}

	function initTheme() {
		var saved = store( STORE );
		if ( 'dark' === saved || 'light' === saved ) { applyTheme( saved ); }
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-rsp-theme-toggle]' );
			if ( ! btn ) { return; }
			var root = btn.closest( '.rsp-root' );
			var next = 'dark' === effectiveTheme( root ) ? 'light' : 'dark';
			applyTheme( next );
			store( STORE, next );
		} );
	}

	function initPopovers() {
		document.addEventListener( 'click', function ( e ) {
			var trigger = e.target.closest( '[data-rsp-pop]' );
			document.querySelectorAll( '.rsp-pop-panel' ).forEach( function ( p ) {
				var own = trigger && trigger.parentNode.contains( p );
				if ( ! own && ! p.contains( e.target ) ) {
					p.hidden = true;
					var b = p.parentNode.querySelector( '[data-rsp-pop]' );
					if ( b ) { b.setAttribute( 'aria-expanded', 'false' ); }
				}
			} );
			if ( trigger ) {
				var panel = trigger.parentNode.querySelector( '.rsp-pop-panel' );
				var open = panel.hidden;
				panel.hidden = ! open;
				trigger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			}
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' !== e.key ) { return; }
			document.querySelectorAll( '.rsp-pop-panel' ).forEach( function ( p ) { p.hidden = true; } );
		} );
	}

	function initCopy() {
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-rsp-copy]' );
			if ( btn ) { e.preventDefault(); copyText( btn.getAttribute( 'data-rsp-copy' ), btn ); return; }
			var f = e.target.closest( '[data-rsp-copy-field]' );
			if ( f && f.dataset.value ) { e.preventDefault(); copyText( f.dataset.value, f ); }
		} );
	}

	function initReveal() {
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-rsp-reveal]' );
			if ( ! btn || btn.disabled ) { return; }
			var uuid = btn.getAttribute( 'data-rsp-reveal' );
			var grid = document.querySelector( '[data-rsp-creds="' + uuid + '"]' );
			var label = btn.querySelector( 'span' );
			if ( btn.classList.contains( 'is-revealed' ) ) {
				grid.querySelectorAll( '.rsp-cred' ).forEach( function ( row ) {
					row.querySelector( '.rsp-cred-value' ).textContent = '••••••••';
					var c = row.querySelector( '[data-rsp-copy-field]' ); c.disabled = true; delete c.dataset.value;
				} );
				btn.classList.remove( 'is-revealed' );
				if ( label ) { label.textContent = t( 'reveal' ); }
				return;
			}
			btn.disabled = true;
			api( '/accounts/' + encodeURIComponent( uuid ) + '/credentials', 'POST' ).then( function ( data ) {
				grid.querySelectorAll( '.rsp-cred' ).forEach( function ( row ) {
					var key = row.getAttribute( 'data-field' );
					var val = data[ key ] || '';
					row.hidden = ! val;
					row.querySelector( '.rsp-cred-value' ).textContent = val || '—';
					var c = row.querySelector( '[data-rsp-copy-field]' );
					c.disabled = ! val; if ( val ) { c.dataset.value = val; }
				} );
				btn.classList.add( 'is-revealed' );
				if ( label ) { label.textContent = t( 'hide' ); }
			} ).catch( function ( err ) { window.alert( err.message ); } ).then( function () { btn.disabled = false; } );
		} );
	}

	function initDismiss() {
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-rsp-dismiss]' );
			if ( btn ) {
				var box = btn.closest( '[data-rsp-announcement]' );
				if ( box ) { box.remove(); }
				api( '/announcements/' + encodeURIComponent( btn.getAttribute( 'data-rsp-dismiss' ) ) + '/dismiss', 'POST' ).catch( function () {} );
			}
			var read = e.target.closest( '[data-rsp-read-all]' );
			if ( read ) {
				api( '/notifications/read', 'POST' ).then( function () {
					document.querySelectorAll( '[data-rsp-unread]' ).forEach( function ( d ) { d.remove(); } );
					document.querySelectorAll( '.rsp-notes .is-unread' ).forEach( function ( li ) { li.classList.remove( 'is-unread' ); } );
					read.remove();
				} ).catch( function () {} );
			}
		} );
	}

	function initTabs() {
		document.querySelectorAll( '[data-rsp-tabs]' ).forEach( function ( wrap ) {
			if ( wrap.dataset.bound ) { return; }
			wrap.dataset.bound = '1';
			var tabs = Array.prototype.slice.call( wrap.querySelectorAll( '[role="tab"]' ) );
			function select( tab ) {
				tabs.forEach( function ( x ) {
					var on = x === tab;
					x.classList.toggle( 'is-current', on );
					x.setAttribute( 'aria-selected', on ? 'true' : 'false' );
					x.tabIndex = on ? 0 : -1;
					var panel = document.getElementById( x.getAttribute( 'aria-controls' ) );
					if ( panel ) { panel.hidden = ! on; }
				} );
				renderQr( wrap );
			}
			tabs.forEach( function ( tab, i ) {
				tab.addEventListener( 'click', function () { select( tab ); } );
				tab.addEventListener( 'keydown', function ( e ) {
					var rtl = 'rtl' === getComputedStyle( wrap ).direction;
					var d = { ArrowRight: rtl ? -1 : 1, ArrowLeft: rtl ? 1 : -1, Home: -i, End: tabs.length - 1 - i }[ e.key ];
					if ( undefined === d ) { return; }
					e.preventDefault();
					var next = tabs[ ( i + d + tabs.length ) % tabs.length ];
					next.focus(); select( next );
				} );
			} );
		} );
	}

	function renderQr( scope ) {
		if ( 'function' !== typeof window.qrcode ) { return; }
		( scope || document ).querySelectorAll( '[data-rsp-qr]' ).forEach( function ( el ) {
			if ( el.dataset.done || null === el.offsetParent ) { return; }
			var url = el.getAttribute( 'data-rsp-qr' );
			if ( ! /^https?:\/\//i.test( url ) ) { return; }
			try {
				var qr = window.qrcode( 0, 'M' );
				qr.addData( url ); qr.make();
				el.innerHTML = qr.createSvgTag( { cellSize: 4, margin: 0, scalable: true } );
				el.dataset.done = '1';
			} catch ( e ) {}
		} );
	}

	function initTargetToggle() {
		document.querySelectorAll( '[data-rsp-target]' ).forEach( function ( sel ) {
			if ( sel.dataset.bound ) { return; }
			sel.dataset.bound = '1';
			var form = sel.closest( 'form' );
			var sync = function () {
				form.querySelectorAll( '[data-rsp-new-only]' ).forEach( function ( el ) { el.hidden = 'new' !== sel.value; } );
			};
			sel.addEventListener( 'change', sync ); sync();
		} );
	}

	function initPoll() {
		if ( ! document.querySelector( '[data-rsp-poll]' ) || ! cfg.nonce ) { return; }
		var tries = 0;
		var timer = setInterval( function () {
			tries++;
			if ( tries > 40 || document.hidden ) { if ( tries > 40 ) { clearInterval( timer ); } return; }
			api( '/accounts' ).then( function ( list ) {
				var changed = list.some( function ( a ) {
					var card = document.querySelector( '[data-rsp-account="' + a.uuid + '"]' );
					return card && ! card.classList.contains( 'is-' + a.status );
				} );
				if ( changed ) { clearInterval( timer ); window.location.reload(); }
			} ).catch( function () { clearInterval( timer ); } );
		}, 15000 );
	}

	function ready( fn ) {
		if ( 'loading' !== document.readyState ) { fn(); } else { document.addEventListener( 'DOMContentLoaded', fn ); }
	}

	ready( function () {
		initTheme(); initPopovers(); initCopy(); initReveal(); initDismiss(); initTabs(); initTargetToggle(); initPoll();
		renderQr( document );
	} );

	// Elementor editor re-renders widgets dynamically.
	window.addEventListener( 'elementor/frontend/init', function () {
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/global', function () { initTabs(); initTargetToggle(); renderQr( document ); } );
		}
	} );
}() );
