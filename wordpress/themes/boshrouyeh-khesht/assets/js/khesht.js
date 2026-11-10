/**
 * Khesht theme interactions — vanilla, deferred, progressive enhancement.
 */
( function () {
	'use strict';

	var doc = document.documentElement;
	var i18n = window.KheshtI18n || {};
	var FA = [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ];

	function fa( n ) {
		return String( n ).replace( /\d/g, function ( d ) { return FA[ d ]; } );
	}
	function store( key, val ) {
		try {
			if ( val === undefined ) { return window.localStorage.getItem( key ); }
			if ( val === null ) { window.localStorage.removeItem( key ); } else { window.localStorage.setItem( key, val ); }
		} catch ( e ) { /* storage unavailable */ }
		return null;
	}
	function $$( sel, root ) { return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) ); }
	function esc( s ) {
		return String( s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	/* ---------- Accessibility toolbar ---------- */
	var sizes = [ 'sm', '', 'lg', 'xl' ];
	$$( '[data-kh-font]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var cur = sizes.indexOf( doc.getAttribute( 'data-font' ) || '' );
			var next = Math.max( 0, Math.min( sizes.length - 1, cur + ( btn.getAttribute( 'data-kh-font' ) === 'up' ? 1 : -1 ) ) );
			if ( sizes[ next ] ) { doc.setAttribute( 'data-font', sizes[ next ] ); store( 'kh-font', sizes[ next ] ); } else { doc.removeAttribute( 'data-font' ); store( 'kh-font', null ); }
		} );
	} );
	function syncThemeButtons() {
		var dark = doc.getAttribute( 'data-theme' ) === 'dark';
		$$( '[data-kh-theme-toggle]' ).forEach( function ( b ) {
			b.setAttribute( 'aria-pressed', dark ? 'true' : 'false' );
			b.setAttribute( 'aria-label', dark ? ( i18n.light || 'Light' ) : ( i18n.dark || 'Dark' ) );
		} );
	}
	$$( '[data-kh-theme-toggle]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var dark = doc.getAttribute( 'data-theme' ) !== 'dark';
			if ( dark ) { doc.setAttribute( 'data-theme', 'dark' ); store( 'kh-theme', 'dark' ); } else { doc.removeAttribute( 'data-theme' ); store( 'kh-theme', 'light' ); }
			syncThemeButtons();
		} );
	} );
	syncThemeButtons();

	/* ---------- Search panel ---------- */
	var panel = document.getElementById( 'kh-search-panel' );
	var opener = document.querySelector( '[data-kh-search-open]' );
	function closePanel() {
		if ( ! panel || panel.hidden ) { return; }
		panel.hidden = true;
		if ( opener ) { opener.setAttribute( 'aria-expanded', 'false' ); opener.focus(); }
	}
	if ( panel && opener ) {
		opener.addEventListener( 'click', function () {
			var open = panel.hidden;
			panel.hidden = ! open;
			opener.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			if ( open ) { var inp = panel.querySelector( 'input' ); if ( inp ) { inp.focus(); } }
		} );
		$$( '[data-kh-search-close]', panel ).forEach( function ( b ) { b.addEventListener( 'click', closePanel ); } );
		document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' ) { closePanel(); } } );
	}

	/* ---------- Smart suggestions (services first) ---------- */
	var root = ( window.wpApiSettings && window.wpApiSettings.root ) || ( document.querySelector( 'link[rel="https://api.w.org/"]' ) || {} ).href || '/wp-json/';
	$$( 'input[data-kh-suggest]' ).forEach( function ( input ) {
		var list = input.form && input.form.querySelector( '.kh-suggest' );
		if ( ! list ) { return; }
		var timer, ctrl, active = -1;
		input.setAttribute( 'role', 'combobox' );
		input.setAttribute( 'aria-autocomplete', 'list' );
		input.setAttribute( 'aria-expanded', 'false' );
		list.id = list.id || 'kh-suggest-' + Math.random().toString( 36 ).slice( 2 );
		input.setAttribute( 'aria-controls', list.id );

		function hide() { list.hidden = true; input.setAttribute( 'aria-expanded', 'false' ); active = -1; }
		function render( items ) {
			if ( ! items.length ) { hide(); return; }
			list.innerHTML = items.slice( 0, 8 ).map( function ( it, i ) {
				return '<li role="option" id="' + list.id + '-' + i + '" aria-selected="false"><a href="' + esc( it.url ) + '">' + esc( it.title ) + '<small>' + esc( it.type_label || '' ) + '</small></a></li>';
			} ).join( '' );
			list.hidden = false;
			input.setAttribute( 'aria-expanded', 'true' );
		}
		input.addEventListener( 'input', function () {
			clearTimeout( timer );
			var q = input.value.trim();
			if ( q.length < 2 ) { hide(); return; }
			timer = setTimeout( function () {
				if ( ctrl ) { ctrl.abort(); }
				ctrl = 'AbortController' in window ? new AbortController() : null;
				fetch( root.replace( /\/$/, '' ) + '/bmn/v1/search?q=' + encodeURIComponent( q ), { signal: ctrl ? ctrl.signal : undefined, credentials: 'same-origin' } )
					.then( function ( r ) { if ( ! r.ok ) { throw new Error( r.status ); } return r.json(); } )
					.then( function ( d ) { render( Array.isArray( d ) ? d : [] ); } )
					.catch( function () {} );
			}, 220 );
		} );
		input.addEventListener( 'keydown', function ( e ) {
			var opts = $$( 'li', list );
			if ( list.hidden || ! opts.length ) { return; }
			if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
				e.preventDefault();
				active = ( active + ( e.key === 'ArrowDown' ? 1 : -1 ) + opts.length ) % opts.length;
				opts.forEach( function ( o, i ) { o.setAttribute( 'aria-selected', i === active ? 'true' : 'false' ); } );
				input.setAttribute( 'aria-activedescendant', opts[ active ].id );
			} else if ( e.key === 'Enter' && active > -1 ) {
				e.preventDefault();
				window.location.href = opts[ active ].querySelector( 'a' ).href;
			} else if ( e.key === 'Escape' ) { hide(); }
		} );
		document.addEventListener( 'click', function ( e ) { if ( ! input.form.contains( e.target ) ) { hide(); } } );
	} );

	/* ---------- Reveal on scroll ---------- */
	var revealables = $$( '.kh-quick__item, .kh-card, .kh-trio__item, .kh-concept, .kh-journey__step, .kh-tour, .kb-reveal' );
	if ( 'IntersectionObserver' in window && ! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
		var io = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( en ) {
				if ( en.isIntersecting ) { en.target.classList.add( 'is-in' ); io.unobserve( en.target ); }
			} );
		}, { rootMargin: '0px 0px -40px 0px' } );
		revealables.forEach( function ( el, i ) {
			el.classList.add( 'kh-reveal' );
			el.style.transitionDelay = Math.min( i % 8, 7 ) * 45 + 'ms';
			io.observe( el );
		} );
	}

	/* ---------- Countdown: <span data-kh-deadline="ISO"> ---------- */
	function tick() {
		$$( '[data-kh-deadline]' ).forEach( function ( el ) {
			var end = Date.parse( el.getAttribute( 'data-kh-deadline' ) );
			if ( isNaN( end ) ) { return; }
			var s = Math.max( 0, Math.floor( ( end - Date.now() ) / 1000 ) );
			if ( ! s ) { el.textContent = i18n.expired || ''; el.classList.add( 'is-expired' ); return; }
			var d = Math.floor( s / 86400 ), h = Math.floor( ( s % 86400 ) / 3600 ), m = Math.floor( ( s % 3600 ) / 60 );
			el.textContent = fa( d ) + ' ' + ( i18n.days || 'd' ) + ' · ' + fa( h ) + ' ' + ( i18n.hours || 'h' ) + ' · ' + fa( m ) + ' ' + ( i18n.minutes || 'm' );
		} );
	}
	if ( document.querySelector( '[data-kh-deadline]' ) ) { tick(); setInterval( tick, 30000 ); }

	/* ---------- Counters: <span data-kh-count="1250"> ---------- */
	var counters = $$( '[data-kh-count]' );
	if ( counters.length && 'IntersectionObserver' in window ) {
		var co = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( en ) {
				if ( ! en.isIntersecting ) { return; }
				co.unobserve( en.target );
				var el = en.target, to = parseInt( el.getAttribute( 'data-kh-count' ), 10 ) || 0, t0 = null;
				function step( ts ) {
					if ( ! t0 ) { t0 = ts; }
					var p = Math.min( 1, ( ts - t0 ) / 1400 );
					el.textContent = fa( Math.round( to * ( 1 - Math.pow( 1 - p, 3 ) ) ).toLocaleString( 'en-US' ) ).replace( /,/g, '٬' );
					if ( p < 1 ) { requestAnimationFrame( step ); }
				}
				requestAnimationFrame( step );
			} );
		} );
		counters.forEach( function ( c ) { co.observe( c ); } );
	}

	/* ---------- Copy / native share ---------- */
	document.addEventListener( 'click', function ( e ) {
		var copy = e.target.closest( '[data-kh-copy]' );
		if ( copy && navigator.clipboard ) {
			e.preventDefault();
			navigator.clipboard.writeText( copy.getAttribute( 'data-kh-copy' ) ).then( function () {
				var old = copy.getAttribute( 'aria-label' );
				copy.setAttribute( 'aria-label', i18n.copied || 'Copied' );
				copy.classList.add( 'is-copied' );
				setTimeout( function () { copy.classList.remove( 'is-copied' ); if ( old ) { copy.setAttribute( 'aria-label', old ); } }, 1600 );
			} );
		}
		var share = e.target.closest( '[data-kh-share]' );
		if ( share && navigator.share ) {
			e.preventDefault();
			navigator.share( { title: document.title, url: window.location.href } ).catch( function () {} );
		}
	} );

	/* ---------- PWA install prompt ---------- */
	var deferred = null;
	window.addEventListener( 'beforeinstallprompt', function ( e ) {
		e.preventDefault();
		deferred = e;
		$$( '[data-kh-pwa-install]' ).forEach( function ( b ) { b.classList.add( 'is-ready' ); } );
	} );
	document.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '[data-kh-pwa-install]' );
		if ( ! b ) { return; }
		e.preventDefault();
		if ( deferred ) { deferred.prompt(); deferred = null; }
	} );
}() );
