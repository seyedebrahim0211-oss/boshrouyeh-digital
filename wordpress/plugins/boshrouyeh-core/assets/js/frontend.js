/**
 * Boshrouyeh Core front-end widgets: service directory filter, tabs, waste schedule, deceased search.
 */
( function () {
	'use strict';
	var cfg = window.BMNFront || {};
	function $$( s, r ) { return Array.prototype.slice.call( ( r || document ).querySelectorAll( s ) ); }
	function norm( s ) {
		return String( s || '' ).toLowerCase()
			.replace( /[يى]/g, 'ی' ).replace( /ك/g, 'ک' ).replace( /[ً-ْ‌]/g, '' ).replace( /\s+/g, ' ' ).trim();
	}
	function el( tag, cls, text ) { var e = document.createElement( tag ); if ( cls ) { e.className = cls; } if ( text !== undefined ) { e.textContent = text; } return e; }

	/* Service directory */
	$$( '[data-bmn-dir]' ).forEach( function ( root ) {
		var q = root.querySelector( '[data-bmn-dir-q]' );
		var empty = root.querySelector( '[data-bmn-dir-empty]' );
		var items = $$( '.bmn-svc', root );
		var cat = ( $$( '[data-bmn-cat][aria-pressed="true"]', root )[ 0 ] || {} ).getAttribute ? root.querySelector( '[data-bmn-cat][aria-pressed="true"]' ).getAttribute( 'data-bmn-cat' ) : '';
		function apply() {
			var term = norm( q ? q.value : '' ), shown = 0;
			items.forEach( function ( it ) {
				var okCat = ! cat || ( ' ' + it.getAttribute( 'data-cats' ) + ' ' ).indexOf( ' ' + cat + ' ' ) > -1;
				var okText = ! term || norm( it.getAttribute( 'data-text' ) ).indexOf( term ) > -1;
				it.hidden = ! ( okCat && okText );
				if ( ! it.hidden ) { shown++; }
			} );
			if ( empty ) { empty.hidden = shown > 0; }
		}
		$$( '[data-bmn-cat]', root ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				cat = b.getAttribute( 'data-bmn-cat' );
				$$( '[data-bmn-cat]', root ).forEach( function ( x ) { x.setAttribute( 'aria-pressed', x === b ? 'true' : 'false' ); } );
				apply();
			} );
		} );
		if ( q ) { q.addEventListener( 'input', apply ); }
		var param = new URLSearchParams( window.location.search ).get( 'cat' );
		if ( param ) { var btn = root.querySelector( '[data-bmn-cat="' + CSS.escape( param ) + '"]' ); if ( btn ) { btn.click(); } }
	} );

	/* Generic tabs filter (data-kind on rows/cards) */
	$$( '[data-bmn-tabs]' ).forEach( function ( root ) {
		var tabs = $$( '[data-bmn-tab]', root );
		tabs.forEach( function ( t ) {
			t.addEventListener( 'click', function () {
				var k = t.getAttribute( 'data-bmn-tab' );
				tabs.forEach( function ( x ) { x.setAttribute( 'aria-selected', x === t ? 'true' : 'false' ); } );
				$$( '[data-kind]', root ).forEach( function ( row ) {
					row.hidden = !! k && ( ' ' + row.getAttribute( 'data-kind' ) + ' ' ).indexOf( ' ' + k + ' ' ) === -1;
				} );
			} );
		} );
	} );

	/* Waste schedule */
	$$( '[data-bmn-waste]' ).forEach( function ( root ) {
		var data = {};
		try { data = JSON.parse( root.getAttribute( 'data-bmn-waste' ) ) || {}; } catch ( e ) {}
		var sel = root.querySelector( '[data-bmn-waste-select]' );
		var week = root.querySelector( '[data-bmn-waste-week]' );
		var days = cfg.weekdays || [];
		var today = ( new Date().getDay() + 1 ) % 7; // JS Sunday=0 → Saturday-first index.
		function render() {
			week.textContent = '';
			var d = data[ sel.value ];
			if ( ! d ) { return; }
			days.forEach( function ( name, i ) {
				var cell = el( 'div', 'bmn-day' );
				if ( d.days.indexOf( i ) > -1 ) { cell.classList.add( 'is-waste' ); }
				else if ( d.recycle.indexOf( i ) > -1 ) { cell.classList.add( 'is-recycle' ); }
				if ( i === today ) { cell.classList.add( 'is-today' ); cell.setAttribute( 'aria-current', 'date' ); }
				cell.appendChild( el( 'b', '', name.charAt( 0 ) ) );
				cell.appendChild( document.createTextNode( name ) );
				if ( d.time && ( d.days.indexOf( i ) > -1 || d.recycle.indexOf( i ) > -1 ) ) { cell.appendChild( el( 'small', '', ' ' + d.time ) ); }
				week.appendChild( cell );
			} );
		}
		sel.addEventListener( 'change', function () {
			try { localStorage.setItem( 'bmn-hood', sel.value ); } catch ( e ) {}
			render();
		} );
		try { var saved = localStorage.getItem( 'bmn-hood' ); if ( saved && data[ saved ] ) { sel.value = saved; } } catch ( e ) {}
		render();
	} );

	/* Deceased search */
	$$( '[data-bmn-deceased]' ).forEach( function ( form ) {
		var out = form.parentNode.querySelector( '.bmn-deceased__results' );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var q = form.q.value.trim();
			out.textContent = '';
			if ( q.length < 3 ) { out.appendChild( el( 'p', 'kh-alert kh-alert--warn', cfg.minChars ) ); return; }
			form.querySelector( 'button' ).disabled = true;
			fetch( cfg.rest + 'deceased?q=' + encodeURIComponent( q ), { credentials: 'same-origin' } )
				.then( function ( r ) {
					if ( r.status === 429 ) { throw new Error( 'rate' ); }
					if ( ! r.ok ) { throw new Error( 'http' ); }
					return r.json();
				} )
				.then( function ( rows ) {
					if ( ! rows.length ) { out.appendChild( el( 'p', 'kh-empty', cfg.noResult ) ); return; }
					rows.forEach( function ( r ) {
						var card = el( 'article', 'bmn-dec-card' );
						card.appendChild( el( 'strong', '', r.name ) );
						var dl = el( 'dl' );
						[ [ cfg.father, r.father ], [ cfg.death, r.death ], [ cfg.plot, [ r.cemetery, r.plot ].filter( Boolean ).join( ' — ' ) ] ].forEach( function ( p ) {
							if ( ! p[ 1 ] ) { return; }
							var w = el( 'div' ); w.appendChild( el( 'dt', '', p[ 0 ] + ':' ) ); w.appendChild( el( 'dd', '', p[ 1 ] ) ); dl.appendChild( w );
						} );
						card.appendChild( dl );
						if ( r.ceremony ) { card.appendChild( el( 'p', 'kh-help', r.ceremony ) ); }
						out.appendChild( card );
					} );
				} )
				.catch( function ( err ) { out.appendChild( el( 'p', 'kh-alert kh-alert--error', err.message === 'rate' ? cfg.tooMany : cfg.error ) ); } )
				.finally( function () { form.querySelector( 'button' ).disabled = false; } );
		} );
	} );
}() );
