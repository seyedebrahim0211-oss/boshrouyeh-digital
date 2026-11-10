/**
 * Boshrouyeh e-services front-end (vanilla JS, no innerHTML with server/user data).
 */
( function () {
	'use strict';
	var C = window.BMNES || {};
	var T = C.i18n || {};
	var FA = '۰۱۲۳۴۵۶۷۸۹';

	function $( s, r ) { return ( r || document ).querySelector( s ); }
	function $$( s, r ) { return Array.prototype.slice.call( ( r || document ).querySelectorAll( s ) ); }
	function fa( s ) { return String( s ).replace( /\d/g, function ( d ) { return FA[ d ]; } ); }
	function en( s ) { return String( s ).replace( /[۰-۹]/g, function ( d ) { return FA.indexOf( d ); } ).replace( /[٠-٩]/g, function ( d ) { return d.charCodeAt( 0 ) - 1632; } ); }
	function el( tag, cls, text ) { var e = document.createElement( tag ); if ( cls ) { e.className = cls; } if ( text !== undefined && text !== null ) { e.textContent = text; } return e; }
	function fmt( tpl, v ) { return String( tpl || '' ).replace( '%s', v ); }

	function api( path, opts ) {
		opts = opts || {};
		var headers = { 'X-WP-Nonce': C.nonce };
		var body = opts.body;
		if ( body && ! ( body instanceof FormData ) ) { headers[ 'Content-Type' ] = 'application/json'; body = JSON.stringify( body ); }
		return fetch( C.rest + path, { method: opts.method || ( body ? 'POST' : 'GET' ), headers: headers, body: body, credentials: 'same-origin' } )
			.then( function ( r ) {
				return r.json().catch( function () { return {}; } ).then( function ( d ) {
					if ( ! r.ok ) { var e = new Error( d.message || T.error ); e.status = r.status; e.data = d; throw e; }
					return d;
				} );
			} );
	}
	function busy( btn, on ) {
		if ( ! btn ) { return; }
		if ( on ) { btn.dataset.label = btn.textContent; btn.disabled = true; btn.setAttribute( 'aria-busy', 'true' ); btn.textContent = T.sending; }
		else { btn.disabled = false; btn.removeAttribute( 'aria-busy' ); if ( btn.dataset.label ) { btn.textContent = btn.dataset.label; } }
	}
	function err( root, msg, ok ) { var p = $( '.bmn-err', root ); if ( p ) { p.textContent = msg || ''; p.classList.toggle( 'is-ok', !! ok ); } }

	/* ---------------- Login ---------------- */
	$$( '[data-bmn-login]' ).forEach( function ( box ) {
		var fPhone = $( '[data-step="phone"]', box ), fCode = $( '[data-step="code"]', box );
		var resend = $( '[data-resend]', box ), phone = '', timer;
		function countdown( sec ) {
			clearInterval( timer );
			resend.disabled = true;
			timer = setInterval( function () {
				sec--;
				if ( sec <= 0 ) { clearInterval( timer ); resend.disabled = false; resend.textContent = T.resend; return; }
				resend.textContent = fmt( T.resendIn, fa( sec ) );
			}, 1000 );
			resend.textContent = fmt( T.resendIn, fa( sec ) );
		}
		function send( btn ) {
			err( box, '' ); busy( btn, true );
			return api( 'auth/otp', { body: { phone: phone } } ).then( function ( d ) {
				fPhone.hidden = true; fCode.hidden = false;
				var msg = $( '[data-msg]', fCode );
				msg.textContent = fmt( T.codeSent, fa( phone ) ) + ( d.dev_code ? ' — ' + T.devCode + ' ' + fa( d.dev_code ) : '' );
				countdown( d.resend_in || 120 );
				$( 'input[name="code"]', fCode ).focus();
			} ).catch( function ( e ) {
				if ( e.status === 429 && e.data && e.data.resend_in ) { fPhone.hidden = true; fCode.hidden = false; countdown( e.data.resend_in ); }
				err( box, e.message );
			} ).finally( function () { busy( btn, false ); } );
		}
		fPhone.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			phone = en( fPhone.phone.value ).replace( /\D/g, '' );
			if ( ! /^(0?9\d{9}|989\d{9})$/.test( phone ) ) { err( box, fPhone.phone.placeholder ); fPhone.phone.setAttribute( 'aria-invalid', 'true' ); return; }
			fPhone.phone.removeAttribute( 'aria-invalid' );
			send( $( 'button[type=submit]', fPhone ) );
		} );
		resend.addEventListener( 'click', function () { send( resend ); } );
		$( '[data-edit]', box ).addEventListener( 'click', function () { fCode.hidden = true; fPhone.hidden = false; fPhone.phone.focus(); } );
		fCode.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var code = en( fCode.code.value ).replace( /\D/g, '' ), btn = $( 'button[type=submit]', fCode );
			if ( code.length !== 5 ) { fCode.code.setAttribute( 'aria-invalid', 'true' ); return; }
			err( box, '' ); busy( btn, true );
			api( 'auth/verify', { body: { phone: phone, code: code, mode: 'cookie' } } )
				.then( function () { window.location.reload(); } )
				.catch( function ( e2 ) { err( box, e2.message ); busy( btn, false ); } );
		} );
		$( 'input[name="code"]', fCode ).addEventListener( 'input', function ( e ) {
			var v = en( e.target.value ).replace( /\D/g, '' );
			if ( v.length === 5 ) { fCode.requestSubmit ? fCode.requestSubmit() : fCode.dispatchEvent( new Event( 'submit', { cancelable: true } ) ); }
		} );
	} );

	/* ---------------- Map picker (Leaflet) ---------------- */
	function initMap( form ) {
		var node = $( '[data-bmn-map]', form );
		if ( ! node || ! window.L ) { if ( node ) { node.hidden = true; } return; }
		var map = L.map( node, { zoomControl: true, attributionControl: true } ).setView( C.center, 15 );
		L.tileLayer( C.tiles, { maxZoom: 19, attribution: '© OpenStreetMap' } ).addTo( map );
		var marker = null;
		function set( lat, lng ) {
			form.lat.value = lat.toFixed( 6 ); form.lng.value = lng.toFixed( 6 );
			if ( marker ) { marker.setLatLng( [ lat, lng ] ); } else {
				marker = L.marker( [ lat, lng ], { draggable: true, keyboard: true } ).addTo( map );
				marker.on( 'dragend', function () { var p = marker.getLatLng(); set( p.lat, p.lng ); } );
			}
		}
		map.on( 'click', function ( e ) { set( e.latlng.lat, e.latlng.lng ); } );
		var btn = $( '[data-bmn-locate]', form );
		if ( btn ) {
			btn.addEventListener( 'click', function () {
				if ( ! navigator.geolocation ) { err( form, T.noGeo ); return; }
				navigator.geolocation.getCurrentPosition( function ( pos ) {
					set( pos.coords.latitude, pos.coords.longitude );
					map.setView( [ pos.coords.latitude, pos.coords.longitude ], 17 );
					err( form, T.located, true );
				}, function () { err( form, T.noGeo ); }, { enableHighAccuracy: true, timeout: 10000 } );
			} );
		}
		setTimeout( function () { map.invalidateSize(); }, 200 );
	}

	/* ---------------- Request forms ---------------- */
	$$( '[data-bmn-request]' ).forEach( function ( form ) {
		var type = form.getAttribute( 'data-bmn-request' );
		var success = form.nextElementSibling && form.nextElementSibling.classList.contains( 'bmn-success' ) ? form.nextElementSibling : null;
		initMap( form );
		var file = $( 'input[type=file]', form );
		var preview = $( '.bmn-photo img', form );
		if ( file ) {
			file.addEventListener( 'change', function () {
				var f = file.files[ 0 ];
				if ( f && f.size > 6 * 1024 * 1024 ) { err( form, T.tooBig ); file.value = ''; return; }
				if ( f && preview ) { preview.src = URL.createObjectURL( f ); preview.hidden = false; }
			} );
		}
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			if ( ! form.checkValidity() ) {
				var bad = $( ':invalid', form );
				if ( bad ) { bad.setAttribute( 'aria-invalid', 'true' ); bad.focus(); err( form, bad.validationMessage ); }
				return;
			}
			var fd = new FormData( form );
			fd.append( 'type', type );
			var btn = $( 'button[type=submit]', form );
			err( form, '' ); busy( btn, true );
			api( 'requests', { body: fd } ).then( function ( d ) {
				if ( success ) {
					$( '[data-code]', success ).textContent = fa( d.tracking );
					form.hidden = true; success.hidden = false; success.focus();
				}
			} ).catch( function ( e2 ) { err( form, e2.message ); } ).finally( function () { busy( btn, false ); } );
		} );
	} );

	/* ---------------- Tracking ---------------- */
	$$( '[data-bmn-track]' ).forEach( function ( form ) {
		var out = form.nextElementSibling;
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var code = en( form.code.value ).replace( /\D/g, '' );
			var q = 'requests/track?code=' + encodeURIComponent( code ) + ( form.phone4 ? '&phone4=' + encodeURIComponent( en( form.phone4.value ).replace( /\D/g, '' ) ) : '' );
			var btn = $( 'button[type=submit]', form );
			err( form, '' ); out.textContent = ''; busy( btn, true );
			api( q ).then( function ( d ) {
				var card = el( 'article', 'kh-panel bmn-track-card' );
				var top = el( 'div', 'bmn-file__top' );
				top.appendChild( el( 'strong', '', d.type_label + ': ' + d.title ) );
				top.appendChild( el( 'span', 'kh-chip', d.status_label ) );
				card.appendChild( top );
				card.appendChild( el( 'p', 'kh-help', fa( d.tracking ) + ' | ' + d.created_fa ) );
				if ( d.unit ) { card.appendChild( el( 'p', 'bmn-track-unit', 'واحد رسیدگی‌کننده: ' + d.unit ) ); }
				var bar = el( 'div', 'bmn-progress' );
				for ( var i = 1; i <= 4; i++ ) { bar.appendChild( el( 'span', i <= d.progress ? 'on' : '' ) ); }
				card.appendChild( bar );
				var ol = el( 'ol', 'bmn-timeline' );
				d.timeline.slice().reverse().forEach( function ( t ) {
					var li = el( 'li', t.kind === 'referral' ? 'is-referral' : '' );
					li.appendChild( el( 'strong', '', t.status_label ) );
					li.appendChild( el( 'small', '', t.date_fa ) );
					if ( t.note ) { li.appendChild( el( 'p', '', t.note ) ); }
					ol.appendChild( li );
				} );
				card.appendChild( ol );
				out.appendChild( card );
			} ).catch( function ( e2 ) { err( form, e2.message ); } ).finally( function () { busy( btn, false ); } );
		} );
	} );

	/* ---------------- Dues + payment ---------------- */
	function payRedirect( d ) {
		if ( d.method === 'POST' ) {
			var f = el( 'form' ); f.method = 'post'; f.action = d.url;
			Object.keys( d.fields || {} ).forEach( function ( k ) { var i = el( 'input' ); i.type = 'hidden'; i.name = k; i.value = d.fields[ k ]; f.appendChild( i ); } );
			document.body.appendChild( f ); f.submit();
		} else { window.location.assign( d.url ); }
	}
	$$( '[data-bmn-dues]' ).forEach( function ( form ) {
		var out = form.nextElementSibling, type = form.getAttribute( 'data-bmn-dues' );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var btn = $( 'button[type=submit]', form );
			err( form, '' ); out.textContent = ''; busy( btn, true );
			api( 'dues/inquiry', { body: { type: type, identifier: en( form.identifier.value ) } } ).then( function ( b ) {
				var card = el( 'article', 'bmn-bill-card' );
				var head = el( 'header', 'bmn-bill-card__head' );
				head.appendChild( el( 'small', '', b.title ) );
				head.appendChild( el( 'strong', '', b.subtitle ) );
				card.appendChild( head );
				var dl = el( 'dl' );
				function row( label, val, cls ) { var w = el( 'div', cls || '' ); w.appendChild( el( 'dt', '', label ) ); w.appendChild( el( 'dd', '', val ) ); dl.appendChild( w ); }
				b.items.forEach( function ( it ) { row( it.label, it.amount_fa ); } );
				if ( b.discount ) { row( b.discount_label, '−' + b.discount_fa, 'is-discount' ); }
				card.appendChild( dl );
				var total = el( 'p', 'bmn-bill-card__total' );
				total.appendChild( el( 'span', '', T.payable ) );
				var amt = el( 'b', '', b.total_fa ); amt.appendChild( el( 'small', '', ' ' + T.toman ) ); total.appendChild( amt );
				card.appendChild( total );
				if ( b.demo ) { card.appendChild( el( 'p', 'kh-alert kh-alert--warn', T.demo ) ); }
				var pay = el( 'button', 'kh-btn kh-btn--action kh-btn--block', T.payOnline ); pay.type = 'button';
				pay.addEventListener( 'click', function () {
					busy( pay, true );
					api( 'payments/start', { body: { bill_id: b.bill_id } } ).then( function ( d ) { pay.textContent = T.redirect; payRedirect( d ); } )
						.catch( function ( e2 ) { busy( pay, false ); err( form, e2.message ); } );
				} );
				card.appendChild( pay );
				var inst = form.getAttribute( 'data-installment' );
				if ( inst ) { var a = el( 'a', 'bmn-link bmn-bill-card__alt', T.installment + ' ‹' ); a.href = inst; card.appendChild( a ); }
				out.appendChild( card );
				pay.focus();
			} ).catch( function ( e2 ) { err( form, e2.message ); } ).finally( function () { busy( btn, false ); } );
		} );
	} );

	/* ---------------- Profile ---------------- */
	$$( '[data-bmn-profile]' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var btn = $( 'button[type=submit]', form );
			busy( btn, true );
			api( 'me', { body: { name: form.elements.name.value, neighborhood: form.elements.neighborhood.value } } )
				.then( function () { err( form, T.saved, true ); } )
				.catch( function ( e2 ) { err( form, e2.message ); } )
				.finally( function () { busy( btn, false ); } );
		} );
	} );
}() );
