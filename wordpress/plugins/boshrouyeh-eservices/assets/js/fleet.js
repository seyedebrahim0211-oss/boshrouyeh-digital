/* Live minibus map — polls /bmn/v1/fleet/live every 10 s. */
( function () {
	'use strict';
	var cfg = window.BMN_FLEET;
	var root = document.querySelector( '[data-bmn-fleet]' );
	if ( ! cfg || ! root || ! window.L ) { return; }

	var fa = function ( s ) { return String( s ).replace( /\d/g, function ( d ) { return '۰۱۲۳۴۵۶۷۸۹'[ d ]; } ); };
	var esc = function ( s ) { var d = document.createElement( 'div' ); d.textContent = s == null ? '' : String( s ); return d.innerHTML; };

	var map = L.map( root.querySelector( '.bmn-fleet__map' ), { zoomControl: true } ).setView( cfg.center, 14 );
	L.tileLayer( cfg.tiles, { maxZoom: 19, attribution: '&copy; OpenStreetMap' } ).addTo( map );
	var list = root.querySelector( '.bmn-fleet__list' );
	var routeLayers = {}, busMarkers = {}, fitted = false;

	function busIcon( color, online, heading ) {
		return L.divIcon( {
			className: 'bmn-bus' + ( online ? '' : ' is-offline' ),
			html: '<span style="background:' + esc( color ) + ';transform:rotate(' + ( heading | 0 ) + 'deg)">▲</span>',
			iconSize: [ 30, 30 ],
			iconAnchor: [ 15, 15 ]
		} );
	}

	function drawRoutes( routes ) {
		routes.forEach( function ( r ) {
			if ( routeLayers[ r.id ] || ! r.stops.length ) { return; }
			var pts = r.stops.map( function ( s ) { return [ s.lat, s.lng ]; } );
			var g = L.layerGroup().addTo( map );
			L.polyline( pts, { color: r.color, weight: 5, opacity: 0.7 } ).addTo( g );
			r.stops.forEach( function ( s, i ) {
				L.circleMarker( [ s.lat, s.lng ], { radius: 6, color: r.color, fillColor: '#fff', fillOpacity: 1, weight: 3 } )
					.bindTooltip( fa( i + 1 ) + '. ' + esc( s.name ) ).addTo( g );
			} );
			routeLayers[ r.id ] = g;
			if ( ! fitted ) { map.fitBounds( L.latLngBounds( pts ).pad( 0.15 ) ); fitted = true; }
		} );
	}

	function render( data ) {
		drawRoutes( data.routes );
		var seen = {};
		var html = '';
		data.routes.forEach( function ( r ) {
			html += '<section class="bmn-fleet__route"><h3><i style="background:' + esc( r.color ) + '"></i>' + esc( r.name ) + '</h3>';
			var meta = [ r.hours && ( 'ساعات کار: ' + r.hours ), r.headway, r.fare && ( 'کرایه: ' + r.fare ) ].filter( Boolean );
			if ( meta.length ) { html += '<p class="bmn-fleet__meta">' + esc( meta.join( ' | ' ) ) + '</p>'; }
			if ( ! r.vehicles.length ) { html += '<p>خودرویی برای این خط ثبت نشده است.</p>'; }
			r.vehicles.forEach( function ( v ) {
				var status;
				if ( v.lat === null ) {
					status = 'هنوز موقعیتی ارسال نشده';
				} else if ( ! v.online ) {
					status = 'آفلاین (آخرین موقعیت ' + fa( Math.round( v.ago / 60 ) ) + ' دقیقه پیش)';
				} else if ( v.at_stop ) {
					status = 'در ایستگاه «' + v.at_stop + '»' + ( v.next ? ' — بعدی: ' + v.next : '' );
				} else if ( v.next ) {
					status = 'از «' + ( v.last || '—' ) + '» به سمت «' + v.next + '»' + ( v.eta_min ? ' — حدود ' + fa( v.eta_min ) + ' دقیقه' : '' );
				} else {
					status = 'در مسیر';
				}
				html += '<div class="bmn-fleet__bus' + ( v.online ? ' is-online' : '' ) + '"><strong>' + esc( v.name ) + '</strong>' + ( v.plate ? ' <small>' + esc( v.plate ) + '</small>' : '' ) + '<br><span>' + esc( status ) + '</span></div>';
				if ( v.lat !== null ) {
					seen[ v.id ] = true;
					var ll = [ v.lat, v.lng ];
					var tip = esc( v.name ) + '<br>' + esc( status );
					if ( busMarkers[ v.id ] ) {
						busMarkers[ v.id ].setLatLng( ll ).setIcon( busIcon( r.color, v.online, v.heading ) ).setPopupContent( tip );
					} else {
						busMarkers[ v.id ] = L.marker( ll, { icon: busIcon( r.color, v.online, v.heading ), title: v.name } ).bindPopup( tip ).addTo( map );
					}
				}
			} );
			html += '</section>';
		} );
		Object.keys( busMarkers ).forEach( function ( id ) {
			if ( ! seen[ id ] ) { map.removeLayer( busMarkers[ id ] ); delete busMarkers[ id ]; }
		} );
		list.innerHTML = html || '<p>هنوز خطی تعریف نشده است.</p>';
	}

	function tick() {
		if ( document.hidden ) { return; }
		fetch( cfg.api, { credentials: 'omit' } )
			.then( function ( r ) { if ( ! r.ok ) { throw new Error( r.status ); } return r.json(); } )
			.then( render )
			.catch( function () { list.insertAdjacentHTML( 'afterbegin', '' ); } );
	}
	tick();
	setInterval( tick, 10000 );
	document.addEventListener( 'visibilitychange', tick );
}() );
