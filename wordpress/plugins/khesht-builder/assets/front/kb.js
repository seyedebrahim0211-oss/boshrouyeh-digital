/**
 * Khesht Builder front-end behaviours: reveal, counters, countdown, tabs, lightbox.
 */
( function () {
	'use strict';
	var FA = '۰۱۲۳۴۵۶۷۸۹';
	function fa( n ) { return String( n ).replace( /\d/g, function ( d ) { return FA[ d ]; } ); }
	function $$( s, r ) { return Array.prototype.slice.call( ( r || document ).querySelectorAll( s ) ); }
	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var editing = document.body.classList.contains( 'kb-canvas' );

	/* Reveal */
	var anims = $$( '.kb-anim' );
	if ( editing || reduce || ! ( 'IntersectionObserver' in window ) ) {
		anims.forEach( function ( el ) { el.classList.add( 'is-in' ); } );
	} else {
		var io = new IntersectionObserver( function ( es ) {
			es.forEach( function ( e ) { if ( e.isIntersecting ) { e.target.classList.add( 'is-in' ); io.unobserve( e.target ); } } );
		}, { rootMargin: '0px 0px -40px 0px' } );
		anims.forEach( function ( el ) { io.observe( el ); } );
	}

	/* Counters */
	var counters = $$( '[data-kb-count]' );
	function count( el ) {
		var to = parseInt( el.getAttribute( 'data-kb-count' ), 10 ) || 0, t0 = null;
		if ( reduce ) { return; }
		function step( ts ) {
			if ( ! t0 ) { t0 = ts; }
			var p = Math.min( 1, ( ts - t0 ) / 1400 );
			el.textContent = fa( Math.round( to * ( 1 - Math.pow( 1 - p, 3 ) ) ).toLocaleString( 'en-US' ) ).replace( /,/g, '٬' );
			if ( p < 1 ) { requestAnimationFrame( step ); }
		}
		requestAnimationFrame( step );
	}
	if ( counters.length && 'IntersectionObserver' in window && ! editing ) {
		var co = new IntersectionObserver( function ( es ) { es.forEach( function ( e ) { if ( e.isIntersecting ) { co.unobserve( e.target ); count( e.target ); } } ); } );
		counters.forEach( function ( c ) { co.observe( c ); } );
	}

	/* Countdown */
	function tick() {
		$$( '[data-kb-deadline]' ).forEach( function ( box ) {
			var end = Date.parse( box.getAttribute( 'data-kb-deadline' ) );
			if ( isNaN( end ) ) { return; }
			var s = Math.max( 0, Math.floor( ( end - Date.now() ) / 1000 ) );
			if ( ! s ) {
				box.classList.add( 'is-expired' );
				var d = box.querySelector( '.kb-countdown__date' );
				if ( d ) { d.textContent = box.getAttribute( 'data-kb-expired' ) || ''; }
				return;
			}
			var u = { d: Math.floor( s / 86400 ), h: Math.floor( ( s % 86400 ) / 3600 ), m: Math.floor( ( s % 3600 ) / 60 ), s: s % 60 };
			Object.keys( u ).forEach( function ( k ) { var b = box.querySelector( '[data-u="' + k + '"]' ); if ( b ) { b.textContent = fa( u[ k ] ); } } );
		} );
	}
	if ( document.querySelector( '[data-kb-deadline]' ) ) { tick(); setInterval( tick, 1000 ); }

	/* Tabs (WAI-ARIA, arrow keys) */
	$$( '[data-kb-tabs]' ).forEach( function ( root ) {
		var tabs = $$( '[role=tab]', root );
		function activate( t, focus ) {
			tabs.forEach( function ( x ) {
				var on = x === t;
				x.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				x.tabIndex = on ? 0 : -1;
				var p = document.getElementById( x.getAttribute( 'aria-controls' ) );
				if ( p ) { p.hidden = ! on; }
			} );
			if ( focus ) { t.focus(); }
		}
		tabs.forEach( function ( t, i ) {
			t.addEventListener( 'click', function () { activate( t ); } );
			t.addEventListener( 'keydown', function ( e ) {
				var dir = { ArrowLeft: 1, ArrowRight: -1, ArrowDown: 1, ArrowUp: -1 }[ e.key ]; // RTL.
				if ( e.key === 'Home' ) { activate( tabs[ 0 ], true ); e.preventDefault(); }
				else if ( e.key === 'End' ) { activate( tabs[ tabs.length - 1 ], true ); e.preventDefault(); }
				else if ( dir ) { activate( tabs[ ( i + dir + tabs.length ) % tabs.length ], true ); e.preventDefault(); }
			} );
		} );
	} );

	/* Lightbox */
	if ( ! editing ) {
		document.addEventListener( 'click', function ( e ) {
			var a = e.target.closest( '[data-kb-zoom]' );
			if ( ! a ) { return; }
			e.preventDefault();
			var dlg = document.createElement( 'dialog' );
			dlg.className = 'kb-lightbox';
			var img = document.createElement( 'img' );
			img.src = a.href;
			img.alt = ( a.querySelector( 'img' ) || {} ).alt || '';
			var close = document.createElement( 'button' );
			close.type = 'button'; close.textContent = '✕'; close.setAttribute( 'aria-label', 'بستن' );
			close.addEventListener( 'click', function () { dlg.close(); } );
			dlg.addEventListener( 'close', function () { dlg.remove(); a.focus(); } );
			dlg.addEventListener( 'click', function ( ev ) { if ( ev.target === dlg ) { dlg.close(); } } );
			dlg.appendChild( img ); dlg.appendChild( close );
			document.body.appendChild( dlg );
			dlg.showModal();
			close.focus();
		} );
	}
}() );
