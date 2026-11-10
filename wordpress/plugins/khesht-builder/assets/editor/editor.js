/**
 * Khesht Builder — editor app (vanilla JS).
 * The canvas is a same-origin iframe of the real front end; every section preview is rendered by
 * the server (/kb/v1/render) so what you see is exactly what visitors get.
 */
( function () {
	'use strict';
	var K = window.KB;
	var app = document.getElementById( 'kb-app' );
	if ( ! K || ! app ) { return; }

	/* ------------------------------------------------------------------ utils */
	var FA = '۰۱۲۳۴۵۶۷۸۹';
	function fa( s ) { return String( s ).replace( /\d/g, function ( d ) { return FA[ d ]; } ); }
	function uid() { return Math.random().toString( 36 ).slice( 2, 10 ).replace( /[^a-z0-9]/g, 'x' ).padEnd( 8, 'k' ); }
	function clone( o ) { return JSON.parse( JSON.stringify( o ) ); }
	function h( tag, attrs, kids ) {
		var e = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( k ) {
			var v = attrs[ k ];
			if ( v === null || v === undefined || v === false ) { return; }
			if ( k === 'class' ) { e.className = v; } else if ( k === 'text' ) { e.textContent = v; } else if ( k.slice( 0, 2 ) === 'on' ) { e.addEventListener( k.slice( 2 ), v ); } else { e.setAttribute( k, v === true ? '' : v ); }
		} );
		( kids || [] ).forEach( function ( c ) { if ( c ) { e.appendChild( typeof c === 'string' ? document.createTextNode( c ) : c ); } } );
		return e;
	}
	function icon( name, size ) {
		var s = size || 20;
		var span = document.createElement( 'span' );
		span.className = 'kb-ico';
		var inner = K.icons[ name ] || K.icons.grid || '<rect x="4" y="4" width="16" height="16" rx="3"/>';
		span.innerHTML = '<svg width="' + s + '" height="' + s + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + inner + '</svg>'; // Static plugin icon set.
		return span;
	}
	function api( path, body, method ) {
		return fetch( K.rest + path, {
			method: method || ( body ? 'POST' : 'GET' ),
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': K.nonce, 'Content-Type': 'application/json' },
			body: body ? JSON.stringify( body ) : undefined
		} ).then( function ( r ) {
			return r.json().then( function ( d ) { if ( ! r.ok ) { throw new Error( d.message || ( 'HTTP ' + r.status ) ); } return d; } );
		} );
	}
	function debounce( fn, ms ) { var t; return function () { var a = arguments; clearTimeout( t ); t = setTimeout( function () { fn.apply( null, a ); }, ms ); }; }

	/* Jalali (display only for timestamps coming from the server) */
	function g2j( gy, gm, gd ) {
		var gdm = [ 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 ], gy2 = gm > 2 ? gy + 1 : gy;
		var days = 355666 + 365 * gy + Math.floor( ( gy2 + 3 ) / 4 ) - Math.floor( ( gy2 + 99 ) / 100 ) + Math.floor( ( gy2 + 399 ) / 400 ) + gd + gdm[ gm - 1 ];
		var jy = -1595 + 33 * Math.floor( days / 12053 ); days %= 12053; jy += 4 * Math.floor( days / 1461 ); days %= 1461;
		if ( days > 365 ) { jy += Math.floor( ( days - 1 ) / 365 ); days = ( days - 1 ) % 365; }
		var jm = days < 186 ? 1 + Math.floor( days / 31 ) : 7 + Math.floor( ( days - 186 ) / 30 );
		var jd = 1 + ( days < 186 ? days % 31 : ( days - 186 ) % 30 );
		return [ jy, jm, jd ];
	}
	function tsToJalali( ts ) {
		var d = new Date( ts * 1000 ), j = g2j( d.getFullYear(), d.getMonth() + 1, d.getDate() ), p = function ( n ) { return ( n < 10 ? '0' : '' ) + n; };
		return j[ 0 ] + '/' + p( j[ 1 ] ) + '/' + p( j[ 2 ] ) + ' ' + p( d.getHours() ) + ':' + p( d.getMinutes() );
	}

	/* ------------------------------------------------------------------ state */
	var S = {
		layout: { version: 1, sections: [] },
		sel: null, device: 'desktop', tab: 'add', past: [], future: [], dirty: false, drag: null,
		title: K.title, status: K.status, saving: false
	};

	function defaults( fields ) {
		var o = {};
		Object.keys( fields ).forEach( function ( k ) { var d = fields[ k ].default; o[ k ] = d === undefined ? '' : clone( d ); } );
		return o;
	}
	function newWidget( type ) {
		var def = K.widgets[ type ];
		var s = defaults( def.fields );
		var c = defaults( K.common );
		Object.keys( c ).forEach( function ( k ) { s[ k ] = c[ k ]; } );
		return { id: uid(), type: type, settings: s };
	}
	function newSection( spans ) {
		return { id: uid(), settings: defaults( K.section ), columns: ( spans || [ 12 ] ).map( function ( sp ) { return { id: uid(), span: sp, widgets: [] }; } ) };
	}
	function findSection( id ) { return S.layout.sections.find( function ( s ) { return s.id === id; } ); }
	function findCol( id ) {
		for ( var i = 0; i < S.layout.sections.length; i++ ) {
			var sec = S.layout.sections[ i ];
			for ( var j = 0; j < sec.columns.length; j++ ) { if ( sec.columns[ j ].id === id ) { return { sec: sec, col: sec.columns[ j ] }; } }
		}
		return null;
	}
	function findWidget( id ) {
		for ( var i = 0; i < S.layout.sections.length; i++ ) {
			var sec = S.layout.sections[ i ];
			for ( var j = 0; j < sec.columns.length; j++ ) {
				var col = sec.columns[ j ], k = col.widgets.findIndex( function ( w ) { return w.id === id; } );
				if ( k > -1 ) { return { sec: sec, col: col, idx: k, w: col.widgets[ k ] }; }
			}
		}
		return null;
	}

	/* history */
	var lastPushKey = '', lastPushAt = 0;
	function snapshot( key ) {
		var now = Date.now();
		if ( key && key === lastPushKey && now - lastPushAt < 900 ) { lastPushAt = now; return; }
		lastPushKey = key || ''; lastPushAt = now;
		S.past.push( JSON.stringify( S.layout ) );
		if ( S.past.length > 80 ) { S.past.shift(); }
		S.future = [];
		setDirty( true );
	}
	function undo() { if ( ! S.past.length ) { return; } S.future.push( JSON.stringify( S.layout ) ); S.layout = JSON.parse( S.past.pop() ); lastPushKey = ''; setDirty( true ); renderAll(); }
	function redo() { if ( ! S.future.length ) { return; } S.past.push( JSON.stringify( S.layout ) ); S.layout = JSON.parse( S.future.pop() ); lastPushKey = ''; setDirty( true ); renderAll(); }
	function setDirty( v ) { S.dirty = v; ui.saveState.textContent = v ? 'تغییرات ذخیره نشده' : 'ذخیره شد'; ui.saveState.classList.toggle( 'is-dirty', v ); updateHistoryButtons(); }
	function updateHistoryButtons() { ui.undo.disabled = ! S.past.length; ui.redo.disabled = ! S.future.length; }

	/* ------------------------------------------------------------------ UI shell */
	var ui = {};
	app.textContent = '';
	var logo = K.logo ? h( 'img', { src: K.logo, alt: '', class: 'kb-top__logo' } ) : icon( 'star8', 28 );
	ui.title = h( 'input', { class: 'kb-top__title', value: S.title, 'aria-label': 'عنوان برگه', maxlength: 200 } );
	ui.title.addEventListener( 'input', function () { S.title = ui.title.value; setDirty( true ); } );
	function topBtn( label, ico, fn, cls ) { var b = h( 'button', { type: 'button', class: 'kb-tbtn ' + ( cls || '' ), title: label, 'aria-label': label, onclick: fn }, [ icon( ico, 18 ) ] ); return b; }
	ui.undo = topBtn( 'واگرد (Ctrl+Z)', 'arrow', undo, 'kb-flip' );
	ui.redo = topBtn( 'ازنو (Ctrl+Shift+Z)', 'arrow', redo );
	var devices = h( 'div', { class: 'kb-devices', role: 'group', 'aria-label': 'پیش‌نمایش دستگاه' } );
	[ [ 'desktop', 'دسکتاپ', 'grid' ], [ 'tablet', 'تبلت', 'permit' ], [ 'mobile', 'موبایل', 'phone' ] ].forEach( function ( d ) {
		var b = h( 'button', { type: 'button', class: 'kb-dev', 'aria-pressed': d[ 0 ] === 'desktop' ? 'true' : 'false', title: d[ 1 ], onclick: function () { setDevice( d[ 0 ] ); } }, [ icon( d[ 2 ], 18 ), h( 'span', { text: d[ 1 ] } ) ] );
		b.dataset.device = d[ 0 ]; devices.appendChild( b );
	} );
	ui.saveState = h( 'span', { class: 'kb-savestate', 'aria-live': 'polite', text: 'ذخیره شد' } );
	ui.saveDraft = h( 'button', { type: 'button', class: 'kb-btn kb-btn--ghost', onclick: function () { save( S.status === 'publish' ? 'keep' : 'draft' ); } }, [ S.status === 'publish' ? 'به‌روزرسانی' : 'ذخیرهٔ پیش‌نویس' ] );
	ui.publish = h( 'button', { type: 'button', class: 'kb-btn kb-btn--action', onclick: function () { save( 'publish' ); }, disabled: ! K.canPublish }, [ icon( 'check', 18 ), S.status === 'publish' ? 'انتشار تغییرات' : 'انتشار' ] );
	var menu = h( 'details', { class: 'kb-menu' }, [
		h( 'summary', { 'aria-label': 'گزینه‌ها' }, [ icon( 'menu', 20 ) ] ),
		h( 'div', { class: 'kb-menu__list' }, [
			h( 'a', { href: K.preview, target: '_blank', rel: 'noopener' }, [ icon( 'eye', 18 ), 'پیش‌نمایش' ] ),
			h( 'a', { href: K.view, target: '_blank', rel: 'noopener' }, [ icon( 'external', 18 ), 'مشاهدهٔ برگه' ] ),
			h( 'button', { type: 'button', onclick: openTemplates }, [ icon( 'grid', 18 ), 'کتابخانهٔ قالب‌ها' ] ),
			h( 'button', { type: 'button', onclick: disableBuilder }, [ icon( 'logout', 18 ), 'بازگشت به ویرایشگر وردپرس' ] ),
			h( 'a', { href: K.back }, [ icon( 'arrow', 18 ), 'خروج از صفحه‌ساز' ] )
		] )
	] );
	var top = h( 'header', { class: 'kb-top' }, [
		h( 'div', { class: 'kb-top__start' }, [ h( 'a', { href: K.back, class: 'kb-top__home', title: 'خروج' }, [ logo ] ), h( 'div', { class: 'kb-top__meta' }, [ ui.title, h( 'small', { text: 'صفحه‌ساز خشت · ' + K.type } ) ] ) ] ),
		devices,
		h( 'div', { class: 'kb-top__end' }, [ ui.undo, ui.redo, ui.saveState, topBtn( 'قالب‌های آماده', 'grid', openTemplates ), ui.saveDraft, ui.publish, menu ] )
	] );

	/* side panel */
	ui.tabs = h( 'div', { class: 'kb-tabs', role: 'tablist' } );
	ui.panels = {};
	[ [ 'add', 'افزودن', 'plus' ], [ 'settings', 'تنظیمات', 'filter' ], [ 'tree', 'ساختار', 'menu' ] ].forEach( function ( t ) {
		var b = h( 'button', { type: 'button', role: 'tab', id: 'kb-tab-' + t[ 0 ], 'aria-controls': 'kb-panel-' + t[ 0 ], 'aria-selected': t[ 0 ] === 'add' ? 'true' : 'false', onclick: function () { setTab( t[ 0 ] ); } }, [ icon( t[ 2 ], 16 ), t[ 1 ] ] );
		b.dataset.tab = t[ 0 ]; ui.tabs.appendChild( b );
		ui.panels[ t[ 0 ] ] = h( 'div', { class: 'kb-panel', role: 'tabpanel', id: 'kb-panel-' + t[ 0 ], 'aria-labelledby': 'kb-tab-' + t[ 0 ], hidden: t[ 0 ] !== 'add' } );
	} );
	var side = h( 'aside', { class: 'kb-side', 'aria-label': 'پنل صفحه‌ساز' }, [ ui.tabs, ui.panels.add, ui.panels.settings, ui.panels.tree ] );
	ui.frame = h( 'iframe', { class: 'kb-frame', title: 'پیش‌نمایش برگه', src: K.canvas } );
	ui.frameWrap = h( 'div', { class: 'kb-frame-wrap', 'data-device': 'desktop' }, [ ui.frame ] );
	var stage = h( 'main', { class: 'kb-stage' }, [ ui.frameWrap ] );
	ui.toast = h( 'div', { class: 'kb-toast', role: 'status', 'aria-live': 'polite' } );
	app.appendChild( top );
	app.appendChild( h( 'div', { class: 'kb-body' }, [ side, stage ] ) );
	app.appendChild( ui.toast );

	function toast( msg, bad ) { ui.toast.textContent = msg; ui.toast.className = 'kb-toast is-on' + ( bad ? ' is-bad' : '' ); clearTimeout( toast.t ); toast.t = setTimeout( function () { ui.toast.className = 'kb-toast'; }, 2600 ); }
	function setTab( t ) {
		S.tab = t;
		$$( '[role=tab]', ui.tabs ).forEach( function ( b ) { b.setAttribute( 'aria-selected', b.dataset.tab === t ? 'true' : 'false' ); } );
		Object.keys( ui.panels ).forEach( function ( k ) { ui.panels[ k ].hidden = k !== t; } );
		if ( t === 'tree' ) { renderTree(); }
	}
	function setDevice( d ) {
		S.device = d; ui.frameWrap.dataset.device = d;
		$$( '.kb-dev', devices ).forEach( function ( b ) { b.setAttribute( 'aria-pressed', b.dataset.device === d ? 'true' : 'false' ); } );
	}
	function $$( s, r ) { return Array.prototype.slice.call( ( r || document ).querySelectorAll( s ) ); }

	/* ------------------------------------------------------------------ palette */
	var PRESETS = [ [ 12 ], [ 6, 6 ], [ 4, 4, 4 ], [ 8, 4 ], [ 4, 8 ], [ 3, 3, 3, 3 ] ];
	function presetIcon( spans ) {
		var p = h( 'span', { class: 'kb-preset' } );
		spans.forEach( function ( s ) { p.appendChild( h( 'i', { style: 'flex:' + s } ) ); } );
		return p;
	}
	function buildPalette() {
		var pnl = ui.panels.add;
		var q = h( 'input', { type: 'search', class: 'kb-input kb-search', placeholder: 'جست‌وجوی ویجت…', 'aria-label': 'جست‌وجوی ویجت' } );
		pnl.appendChild( h( 'div', { class: 'kb-group' }, [ h( 'h3', { text: 'بخش جدید' } ), h( 'div', { class: 'kb-presets' }, PRESETS.map( function ( p ) {
			return h( 'button', { type: 'button', class: 'kb-preset-btn', title: 'بخش ' + p.map( fa ).join( '+' ), onclick: function () { addSection( p ); } }, [ presetIcon( p ) ] );
		} ) ) ] ) );
		pnl.appendChild( q );
		var groups = {};
		Object.keys( K.categories ).forEach( function ( c ) {
			groups[ c ] = h( 'div', { class: 'kb-tiles' } );
			pnl.appendChild( h( 'div', { class: 'kb-group', 'data-cat': c }, [ h( 'h3', { text: K.categories[ c ] } ), groups[ c ] ] ) );
		} );
		Object.keys( K.widgets ).forEach( function ( type ) {
			var w = K.widgets[ type ];
			var tile = h( 'button', { type: 'button', class: 'kb-tile', draggable: 'true', 'data-type': type, 'data-label': w.label, title: 'کشیدن یا کلیک برای افزودن' }, [ h( 'span', { class: 'kb-tile__ico' }, [ icon( w.icon, 22 ) ] ), h( 'span', { text: w.label } ) ] );
			tile.addEventListener( 'click', function () { addWidgetSmart( type ); } );
			tile.addEventListener( 'dragstart', function ( e ) { S.drag = { kind: 'new', type: type }; e.dataTransfer.effectAllowed = 'copy'; e.dataTransfer.setData( 'text/plain', type ); app.classList.add( 'is-dragging' ); } );
			tile.addEventListener( 'dragend', endDrag );
			( groups[ w.cat ] || groups.basic ).appendChild( tile );
		} );
		q.addEventListener( 'input', function () {
			var t = q.value.trim();
			$$( '.kb-tile', pnl ).forEach( function ( el ) { el.hidden = t && el.dataset.label.indexOf( t ) === -1; } );
			$$( '.kb-group[data-cat]', pnl ).forEach( function ( g ) { g.hidden = ! $$( '.kb-tile:not([hidden])', g ).length; } );
		} );
	}

	/* ------------------------------------------------------------------ mutations */
	function addSection( spans, at ) {
		snapshot();
		var sec = newSection( spans );
		if ( at === undefined ) { S.layout.sections.push( sec ); } else { S.layout.sections.splice( at, 0, sec ); }
		renderAll().then( function () { select( { kind: 'column', id: sec.columns[ 0 ].id } ); scrollTo( sec.id ); } );
		return sec;
	}
	function addWidgetSmart( type ) {
		var target = null, index = 0;
		if ( S.sel && S.sel.kind === 'widget' ) { var f = findWidget( S.sel.id ); if ( f ) { target = f.col; index = f.idx + 1; } }
		else if ( S.sel && S.sel.kind === 'column' ) { var c = findCol( S.sel.id ); if ( c ) { target = c.col; index = c.col.widgets.length; } }
		else if ( S.sel && S.sel.kind === 'section' ) { var s = findSection( S.sel.id ); if ( s ) { target = s.columns[ 0 ]; index = target.widgets.length; } }
		snapshot();
		var w = newWidget( type );
		if ( ! target ) {
			var sec = newSection( [ 12 ] );
			if ( type === 'hero' ) { sec.settings.width = 'full'; sec.settings.pad = 'none'; }
			S.layout.sections.push( sec ); target = sec.columns[ 0 ]; index = 0;
		}
		target.widgets.splice( index, 0, w );
		var owner = findCol( target.id ).sec;
		renderSection( owner.id ).then( function () { select( { kind: 'widget', id: w.id } ); scrollTo( w.id, true ); } );
		toast( '«' + K.widgets[ type ].label + '» افزوده شد' );
	}
	function removeSel() {
		if ( ! S.sel ) { return; }
		snapshot();
		if ( S.sel.kind === 'widget' ) {
			var f = findWidget( S.sel.id ); if ( ! f ) { return; }
			f.col.widgets.splice( f.idx, 1 ); select( null ); renderSection( f.sec.id );
		} else {
			var secId = S.sel.kind === 'section' ? S.sel.id : ( findCol( S.sel.id ) || {} ).sec.id;
			S.layout.sections = S.layout.sections.filter( function ( s ) { return s.id !== secId; } );
			select( null ); renderAll();
		}
	}
	function duplicateSel() {
		if ( ! S.sel ) { return; }
		snapshot();
		if ( S.sel.kind === 'widget' ) {
			var f = findWidget( S.sel.id ); var copy = clone( f.w ); copy.id = uid();
			f.col.widgets.splice( f.idx + 1, 0, copy );
			renderSection( f.sec.id ).then( function () { select( { kind: 'widget', id: copy.id } ); } );
		} else {
			var sec = S.sel.kind === 'section' ? findSection( S.sel.id ) : findCol( S.sel.id ).sec;
			var c = clone( sec ); c.id = uid(); c.columns.forEach( function ( col ) { col.id = uid(); col.widgets.forEach( function ( w ) { w.id = uid(); } ); } );
			S.layout.sections.splice( S.layout.sections.indexOf( sec ) + 1, 0, c );
			renderAll().then( function () { select( { kind: 'section', id: c.id } ); } );
		}
	}
	function moveSel( dir ) {
		if ( ! S.sel ) { return; }
		if ( S.sel.kind === 'widget' ) {
			var f = findWidget( S.sel.id ), j = f.idx + dir;
			if ( j < 0 || j >= f.col.widgets.length ) { return; }
			snapshot();
			f.col.widgets.splice( j, 0, f.col.widgets.splice( f.idx, 1 )[ 0 ] );
			renderSection( f.sec.id ).then( function () { select( S.sel ); } );
		} else {
			var sec = S.sel.kind === 'section' ? findSection( S.sel.id ) : findCol( S.sel.id ).sec;
			var i = S.layout.sections.indexOf( sec ), k = i + dir;
			if ( k < 0 || k >= S.layout.sections.length ) { return; }
			snapshot();
			S.layout.sections.splice( k, 0, S.layout.sections.splice( i, 1 )[ 0 ] );
			renderAll().then( function () { select( { kind: 'section', id: sec.id } ); scrollTo( sec.id ); } );
		}
	}
	function setColumns( sec, spans ) {
		snapshot();
		var cols = sec.columns;
		var next = spans.map( function ( sp, i ) { return cols[ i ] ? { id: cols[ i ].id, span: sp, widgets: cols[ i ].widgets } : { id: uid(), span: sp, widgets: [] }; } );
		for ( var i = spans.length; i < cols.length; i++ ) { next[ next.length - 1 ].widgets = next[ next.length - 1 ].widgets.concat( cols[ i ].widgets ); }
		sec.columns = next;
		renderSection( sec.id ).then( function () { select( { kind: 'section', id: sec.id } ); } );
	}
	function dropAt( colId, index ) {
		var c = findCol( colId );
		if ( ! c || ! S.drag ) { return; }
		snapshot();
		var w, from = null;
		if ( S.drag.kind === 'new' ) { w = newWidget( S.drag.type ); }
		else {
			var f = findWidget( S.drag.id ); if ( ! f ) { return; }
			from = f.sec; w = f.w;
			if ( f.col === c.col && f.idx < index ) { index--; }
			f.col.widgets.splice( f.idx, 1 );
		}
		c.col.widgets.splice( Math.max( 0, Math.min( index, c.col.widgets.length ) ), 0, w );
		var jobs = [ renderSection( c.sec.id ) ];
		if ( from && from !== c.sec ) { jobs.push( renderSection( from.id ) ); }
		Promise.all( jobs ).then( function () { select( { kind: 'widget', id: w.id } ); } );
	}

	/* ------------------------------------------------------------------ canvas */
	var doc = null, root = null, ready = null, dropLine = null;
	ready = new Promise( function ( resolve ) {
		ui.frame.addEventListener( 'load', function () {
			doc = ui.frame.contentDocument;
			root = doc.getElementById( 'kb-root' );
			if ( ! root ) { toast( 'بارگذاری بوم ناموفق بود.', true ); return; }
			bindCanvas();
			resolve();
		} );
	} );

	function canvasAddBar() {
		var bar = doc.createElement( 'div' );
		bar.className = 'kb-addbar';
		bar.setAttribute( 'data-kb-ui', '1' );
		var label = doc.createElement( 'span' ); label.textContent = 'افزودن بخش'; bar.appendChild( label );
		PRESETS.forEach( function ( p ) {
			var b = doc.createElement( 'button' ); b.type = 'button'; b.className = 'kb-addbar__btn'; b.title = p.map( fa ).join( '+' );
			p.forEach( function ( s ) { var i = doc.createElement( 'i' ); i.style.flex = s; b.appendChild( i ); } );
			b.addEventListener( 'click', function () { addSection( p ); } );
			bar.appendChild( b );
		} );
		var t = doc.createElement( 'button' ); t.type = 'button'; t.className = 'kb-addbar__tpl'; t.textContent = 'قالب آماده'; t.addEventListener( 'click', openTemplates ); bar.appendChild( t );
		return bar;
	}

	function renderAll() {
		return ready.then( function () {
			if ( ! S.layout.sections.length ) {
				root.textContent = '';
				var empty = doc.createElement( 'div' ); empty.className = 'kb-canvas-empty'; empty.setAttribute( 'data-kb-ui', '1' );
				var t1 = doc.createElement( 'h2' ); t1.textContent = 'برگهٔ خود را بسازید'; empty.appendChild( t1 );
				var t2 = doc.createElement( 'p' ); t2.textContent = 'یک قالب آماده انتخاب کنید یا بخش جدید بیفزایید و ویجت‌ها را از پنل کنار بکشید.'; empty.appendChild( t2 );
				root.appendChild( empty ); root.appendChild( canvasAddBar() );
				return;
			}
			return api( 'render', { post_id: K.post, sections: S.layout.sections } ).then( function ( d ) {
				root.textContent = '';
				d.sections.forEach( function ( s ) { root.appendChild( frag( s.html ) ); } );
				root.appendChild( canvasAddBar() );
				afterRender();
			} ).catch( function ( e ) { toast( e.message, true ); } );
		} );
	}
	function renderSection( id ) {
		return ready.then( function () {
			var sec = findSection( id ); if ( ! sec ) { return renderAll(); }
			var node = root.querySelector( '[data-kb-section="' + id + '"]' );
			if ( ! node ) { return renderAll(); }
			node.classList.add( 'kb-is-loading' );
			return api( 'render', { post_id: K.post, sections: [ sec ] } ).then( function ( d ) {
				var fresh = frag( d.sections[ 0 ].html );
				node.parentNode.replaceChild( fresh, node );
				afterRender();
			} ).catch( function ( e ) { node.classList.remove( 'kb-is-loading' ); toast( e.message, true ); } );
		} );
	}
	var renderSectionSoon = debounce( function ( id ) { renderSection( id ).then( function () { highlight(); } ); }, 280 );
	function frag( html ) { return doc.createRange().createContextualFragment( html ); } // Server-sanitized, same-origin preview markup.
	function afterRender() {
		// Show animated widgets immediately in the editor.
		Array.prototype.forEach.call( root.querySelectorAll( '.kb-anim' ), function ( el ) { el.classList.add( 'is-in' ); } );
		highlight();
		if ( S.tab === 'tree' ) { renderTree(); }
	}
	function scrollTo( id, isWidget ) {
		ready.then( function () {
			var el = root.querySelector( isWidget ? '[data-kb-widget="' + id + '"]' : '[data-kb-section="' + id + '"]' );
			if ( el ) { el.scrollIntoView( { behavior: 'smooth', block: 'center' } ); }
		} );
	}

	function bindCanvas() {
		doc.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-kb-ui]' ) && ! e.target.closest( '.kb-tools' ) ) { return; }
			var tool = e.target.closest( '.kb-tools button' );
			if ( tool ) { e.preventDefault(); e.stopPropagation(); return; }
			var a = e.target.closest( 'a, button, input[type=submit], summary, label' );
			if ( a && ! e.target.closest( '[data-kb-ui]' ) ) { e.preventDefault(); }
			var w = e.target.closest( '[data-kb-widget]' ), c = e.target.closest( '[data-kb-col]' ), s = e.target.closest( '[data-kb-section]' );
			if ( w ) { select( { kind: 'widget', id: w.dataset.kbWidget } ); }
			else if ( c ) { select( { kind: 'column', id: c.dataset.kbCol } ); }
			else if ( s ) { select( { kind: 'section', id: s.dataset.kbSection } ); }
			else { select( null ); }
		}, true );
		doc.addEventListener( 'submit', function ( e ) { e.preventDefault(); }, true );
		doc.addEventListener( 'keydown', onKey );
		doc.addEventListener( 'dragstart', function ( e ) {
			var w = e.target.closest && e.target.closest( '[data-kb-widget]' );
			if ( ! w ) { return; }
			S.drag = { kind: 'move', id: w.dataset.kbWidget };
			e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData( 'text/plain', w.dataset.kbWidget );
			w.classList.add( 'kb-is-ghost' );
		} );
		doc.addEventListener( 'dragover', function ( e ) {
			if ( ! S.drag ) { return; }
			var col = e.target.closest && e.target.closest( '[data-kb-col]' );
			if ( ! col ) { hideDropLine(); return; }
			e.preventDefault();
			e.dataTransfer.dropEffect = S.drag.kind === 'new' ? 'copy' : 'move';
			var kids = Array.prototype.filter.call( col.children, function ( k ) { return k.hasAttribute( 'data-kb-widget' ); } );
			var idx = kids.length;
			for ( var i = 0; i < kids.length; i++ ) { var r = kids[ i ].getBoundingClientRect(); if ( e.clientY < r.top + r.height / 2 ) { idx = i; break; } }
			showDropLine( col, kids[ idx ] || null );
			S.dropTarget = { col: col.dataset.kbCol, index: idx };
		} );
		doc.addEventListener( 'dragleave', function ( e ) { if ( ! e.relatedTarget ) { hideDropLine(); } } );
		doc.addEventListener( 'drop', function ( e ) {
			if ( ! S.drag ) { return; }
			e.preventDefault();
			var t = S.dropTarget;
			hideDropLine();
			if ( t ) { dropAt( t.col, t.index ); }
			endDrag();
		} );
		doc.addEventListener( 'dragend', endDrag );
	}
	function showDropLine( col, before ) {
		if ( ! dropLine ) { dropLine = doc.createElement( 'div' ); dropLine.className = 'kb-dropline'; dropLine.setAttribute( 'data-kb-ui', '1' ); }
		if ( before ) { col.insertBefore( dropLine, before ); } else { col.appendChild( dropLine ); }
		col.classList.add( 'kb-is-target' );
		Array.prototype.forEach.call( root.querySelectorAll( '.kb-is-target' ), function ( c ) { if ( c !== col ) { c.classList.remove( 'kb-is-target' ); } } );
	}
	function hideDropLine() {
		if ( dropLine && dropLine.parentNode ) { dropLine.parentNode.removeChild( dropLine ); }
		if ( root ) { Array.prototype.forEach.call( root.querySelectorAll( '.kb-is-target' ), function ( c ) { c.classList.remove( 'kb-is-target' ); } ); }
	}
	function endDrag() {
		S.drag = null; S.dropTarget = null; hideDropLine(); app.classList.remove( 'is-dragging' );
		if ( root ) { Array.prototype.forEach.call( root.querySelectorAll( '.kb-is-ghost' ), function ( c ) { c.classList.remove( 'kb-is-ghost' ); } ); }
	}

	/* ------------------------------------------------------------------ selection */
	function select( sel ) {
		S.sel = sel;
		highlight();
		buildInspector();
		if ( sel ) { setTab( 'settings' ); }
	}
	function highlight() {
		if ( ! root ) { return; }
		Array.prototype.forEach.call( root.querySelectorAll( '.kb-is-selected' ), function ( el ) { el.classList.remove( 'kb-is-selected' ); } );
		Array.prototype.forEach.call( root.querySelectorAll( '.kb-tools' ), function ( el ) { el.remove(); } );
		if ( ! S.sel ) { return; }
		var attr = S.sel.kind === 'widget' ? 'data-kb-widget' : ( S.sel.kind === 'column' ? 'data-kb-col' : 'data-kb-section' );
		var el = root.querySelector( '[' + attr + '="' + S.sel.id + '"]' );
		if ( ! el ) { return; }
		el.classList.add( 'kb-is-selected' );
		var bar = doc.createElement( 'div' );
		bar.className = 'kb-tools kb-tools--' + S.sel.kind;
		bar.setAttribute( 'data-kb-ui', '1' );
		var name = doc.createElement( 'span' );
		name.textContent = S.sel.kind === 'widget' ? K.widgets[ findWidget( S.sel.id ).w.type ].label : ( S.sel.kind === 'column' ? 'ستون' : 'بخش' );
		bar.appendChild( name );
		[ [ '↑', 'بالا', function () { moveSel( -1 ); } ], [ '↓', 'پایین', function () { moveSel( 1 ); } ], [ '⧉', 'تکثیر', duplicateSel ], [ '✕', 'حذف', removeSel ] ].forEach( function ( b ) {
			var btn = doc.createElement( 'button' ); btn.type = 'button'; btn.textContent = b[ 0 ]; btn.title = b[ 1 ]; btn.setAttribute( 'aria-label', b[ 1 ] );
			btn.addEventListener( 'click', function ( e ) { e.preventDefault(); e.stopPropagation(); b[ 2 ](); } );
			bar.appendChild( btn );
		} );
		el.appendChild( bar );
	}

	/* ------------------------------------------------------------------ inspector */
	function fieldRow( label, control, help ) {
		return h( 'div', { class: 'kb-field' }, [ h( 'label', { class: 'kb-label', text: label } ), control, help ? h( 'small', { class: 'kb-help', text: help } ) : null ] );
	}
	function mediaPick( opts, cb ) {
		if ( ! window.wp || ! wp.media ) { toast( 'کتابخانهٔ رسانه در دسترس نیست', true ); return; }
		var frame = wp.media( { title: opts.title || 'انتخاب', multiple: !! opts.multiple, library: { type: opts.type || 'image' } } );
		frame.on( 'select', function () { cb( frame.state().get( 'selection' ).toJSON() ); } );
		frame.open();
	}
	function control( def, value, set, key ) {
		var el;
		switch ( def.type ) {
			case 'textarea':
				el = h( 'textarea', { class: 'kb-input', rows: 3 } ); el.value = value || '';
				el.addEventListener( 'input', function () { set( el.value, key ); } );
				return el;
			case 'richtext':
				var ed = h( 'div', { class: 'kb-rich', contenteditable: 'true', role: 'textbox', 'aria-multiline': 'true' } );
				ed.innerHTML = value || ''; // Already kses-sanitized server-side.
				var bar = h( 'div', { class: 'kb-rich__bar' } );
				[ [ 'bold', 'B', 'پررنگ' ], [ 'italic', 'I', 'کج' ], [ 'insertUnorderedList', '•', 'فهرست' ], [ 'createLink', '🔗', 'پیوند' ], [ 'removeFormat', '⌫', 'پاک کردن قالب' ] ].forEach( function ( c ) {
					bar.appendChild( h( 'button', { type: 'button', title: c[ 2 ], 'aria-label': c[ 2 ], onclick: function () {
						ed.focus();
						if ( c[ 0 ] === 'createLink' ) { var u = window.prompt( 'نشانی پیوند:', 'https://' ); if ( u ) { document.execCommand( 'createLink', false, u ); } }
						else { document.execCommand( c[ 0 ], false, null ); }
						set( ed.innerHTML, key );
					} }, [ c[ 1 ] ] ) );
				} );
				ed.addEventListener( 'input', function () { set( ed.innerHTML, key ); } );
				return h( 'div', { class: 'kb-rich-wrap' }, [ bar, ed ] );
			case 'number':
				el = h( 'input', { type: 'number', class: 'kb-input kb-ltr', step: def.step || '1', min: def.min, max: def.max } ); el.value = value;
				el.addEventListener( 'input', function () { set( el.value === '' ? 0 : Number( el.value ), key ); } );
				return el;
			case 'toggle':
				el = h( 'input', { type: 'checkbox', class: 'kb-switch' } ); el.checked = !! value;
				el.addEventListener( 'change', function () { set( el.checked, key ); } );
				return el;
			case 'select':
				el = h( 'select', { class: 'kb-input' } );
				Object.keys( def.options ).forEach( function ( k ) { var o = h( 'option', { value: k, text: def.options[ k ] } ); if ( String( value ) === k ) { o.selected = true; } el.appendChild( o ); } );
				el.addEventListener( 'change', function () { set( el.value, key ); } );
				return el;
			case 'icon':
				var grid = h( 'div', { class: 'kb-icons', role: 'radiogroup' } );
				Object.keys( def.options ).forEach( function ( k ) {
					var b = h( 'button', { type: 'button', role: 'radio', 'aria-checked': String( value ) === k ? 'true' : 'false', title: k, class: 'kb-icon-opt' }, [ k ? icon( k, 18 ) : h( 'span', { text: '—' } ) ] );
					b.addEventListener( 'click', function () { $$( '.kb-icon-opt', grid ).forEach( function ( x ) { x.setAttribute( 'aria-checked', 'false' ); } ); b.setAttribute( 'aria-checked', 'true' ); set( k, key ); } );
					grid.appendChild( b );
				} );
				return h( 'details', { class: 'kb-icon-pick' }, [ h( 'summary', {}, [ value ? icon( value, 18 ) : h( 'span', { text: '—' } ), ' انتخاب آیکن' ] ), grid ] );
			case 'image':
				var wrap = h( 'div', { class: 'kb-media' } );
				var draw = function ( v, url ) {
					wrap.textContent = '';
					if ( v && url ) { wrap.appendChild( h( 'img', { src: url, alt: '' } ) ); }
					wrap.appendChild( h( 'button', { type: 'button', class: 'kb-btn kb-btn--ghost kb-btn--sm', onclick: function () {
						mediaPick( { type: def.mime || 'image' }, function ( sel ) { var a = sel[ 0 ]; draw( a.id, ( a.sizes && ( a.sizes.medium || a.sizes.full ) || a ).url ); set( a.id, key ); } );
					} }, [ v ? 'تغییر' : 'انتخاب از کتابخانه' ] ) );
					if ( v ) { wrap.appendChild( h( 'button', { type: 'button', class: 'kb-btn kb-btn--text kb-btn--sm', onclick: function () { draw( 0 ); set( 0, key ); } }, [ 'حذف' ] ) ); }
				};
				draw( value, value ? ( mediaCache[ value ] || '' ) : '' );
				if ( value && ! mediaCache[ value ] && window.wp && wp.media && wp.media.attachment ) {
					var att = wp.media.attachment( value ); att.fetch().then( function () { var u = att.get( 'sizes' ); mediaCache[ value ] = ( u && ( u.medium || u.full ) || att.attributes ).url; draw( value, mediaCache[ value ] ); } );
				}
				return wrap;
			case 'gallery':
				var g = h( 'div', { class: 'kb-media' } );
				var gdraw = function ( ids ) {
					g.textContent = '';
					g.appendChild( h( 'span', { class: 'kb-help', text: ( ids && ids.length ? fa( ids.length ) + ' تصویر' : 'بدون تصویر (نمایش تصویرسازی پیش‌فرض)' ) } ) );
					g.appendChild( h( 'button', { type: 'button', class: 'kb-btn kb-btn--ghost kb-btn--sm', onclick: function () { mediaPick( { multiple: true }, function ( sel ) { var v = sel.map( function ( a ) { return a.id; } ); gdraw( v ); set( v, key ); } ); } }, [ 'انتخاب تصاویر' ] ) );
				};
				gdraw( value );
				return g;
			case 'jdate':
				el = h( 'input', { type: 'text', class: 'kb-input kb-ltr', placeholder: '1405/09/30 23:59', inputmode: 'numeric' } );
				el.value = typeof value === 'number' && value > 0 ? tsToJalali( value ) : ( value || '' );
				el.addEventListener( 'change', function () { set( el.value, key ); } );
				return el;
			case 'repeater':
				return repeater( def, value || [], function ( v ) { set( v, key ); } );
			default:
				el = h( 'input', { type: def.type === 'url' ? 'text' : 'text', class: 'kb-input' + ( def.type === 'url' ? ' kb-ltr' : '' ), placeholder: def.type === 'url' ? '/services/ یا https://' : '' } ); el.value = value || '';
				el.addEventListener( 'input', function () { set( el.value, key ); } );
				return el;
		}
	}
	var mediaCache = {};
	function repeater( def, rows, set ) {
		var box = h( 'div', { class: 'kb-rep' } );
		function draw() {
			box.textContent = '';
			rows.forEach( function ( row, i ) {
				var firstKey = Object.keys( def.fields )[ 0 ];
				var title = row.title || row.label || row.q || row[ firstKey ] || ( 'مورد ' + fa( i + 1 ) );
				var body = h( 'div', { class: 'kb-rep__body' } );
				Object.keys( def.fields ).forEach( function ( k ) {
					body.appendChild( fieldRow( def.fields[ k ].label, control( def.fields[ k ], row[ k ], function ( v ) { row[ k ] = v; set( rows ); }, 'rep' + i + k ) ) );
				} );
				var item = h( 'details', { class: 'kb-rep__item' }, [
					h( 'summary', {}, [ h( 'span', { text: String( title ).slice( 0, 40 ) } ), h( 'span', { class: 'kb-rep__acts' }, [
						h( 'button', { type: 'button', title: 'بالا', onclick: function ( e ) { e.preventDefault(); if ( i > 0 ) { rows.splice( i - 1, 0, rows.splice( i, 1 )[ 0 ] ); set( rows ); draw(); } } }, [ '↑' ] ),
						h( 'button', { type: 'button', title: 'حذف', onclick: function ( e ) { e.preventDefault(); rows.splice( i, 1 ); set( rows ); draw(); } }, [ '✕' ] )
					] ) ] ),
					body
				] );
				box.appendChild( item );
			} );
			if ( ! def.max || rows.length < def.max ) {
				box.appendChild( h( 'button', { type: 'button', class: 'kb-btn kb-btn--ghost kb-btn--sm kb-rep__add', onclick: function () { rows.push( defaults( def.fields ) ); set( rows ); draw(); var last = box.querySelectorAll( '.kb-rep__item' ); if ( last.length ) { last[ last.length - 1 ].open = true; } } }, [ '+ افزودن مورد' ] ) );
			}
		}
		draw();
		return box;
	}

	function buildInspector() {
		var pnl = ui.panels.settings;
		pnl.textContent = '';
		if ( ! S.sel ) {
			pnl.appendChild( h( 'div', { class: 'kb-hint' }, [ icon( 'info', 28 ), h( 'p', { text: 'روی یک بخش، ستون یا ویجت در بوم کلیک کنید تا تنظیماتش اینجا نمایش داده شود.' } ) ] ) );
			return;
		}
		var target, fields, owner, title;
		if ( S.sel.kind === 'widget' ) {
			var f = findWidget( S.sel.id ); if ( ! f ) { return; }
			target = f.w.settings; owner = f.sec; title = K.widgets[ f.w.type ].label;
			fields = K.widgets[ f.w.type ].fields;
		} else {
			owner = S.sel.kind === 'section' ? findSection( S.sel.id ) : findCol( S.sel.id ).sec;
			if ( ! owner ) { return; }
			target = owner.settings; fields = K.section; title = 'بخش';
		}
		var crumbs = h( 'nav', { class: 'kb-crumbs', 'aria-label': 'مسیر انتخاب' } );
		crumbs.appendChild( h( 'button', { type: 'button', onclick: function () { select( { kind: 'section', id: owner.id } ); } }, [ 'بخش ' + fa( S.layout.sections.indexOf( owner ) + 1 ) ] ) );
		if ( S.sel.kind === 'widget' ) { crumbs.appendChild( h( 'span', { text: ' › ' + title } ) ); }
		pnl.appendChild( crumbs );

		var set = function ( v, key, path ) {
			snapshot( S.sel.id + ':' + path );
			target[ path ] = v;
			renderSectionSoon( owner.id );
		};
		if ( S.sel.kind !== 'widget' ) {
			pnl.appendChild( h( 'div', { class: 'kb-group' }, [ h( 'h3', { text: 'ستون‌بندی' } ), h( 'div', { class: 'kb-presets' }, PRESETS.map( function ( p ) {
				var cur = owner.columns.map( function ( c ) { return c.span; } ).join( ',' ) === p.join( ',' );
				return h( 'button', { type: 'button', class: 'kb-preset-btn' + ( cur ? ' is-on' : '' ), 'aria-pressed': cur ? 'true' : 'false', onclick: function () { setColumns( owner, p ); } }, [ presetIcon( p ) ] );
			} ) ) ] ) );
		}
		var main = h( 'div', { class: 'kb-group' }, [ h( 'h3', { text: S.sel.kind === 'widget' ? 'محتوا' : 'ظاهر بخش' } ) ] );
		Object.keys( fields ).forEach( function ( k ) {
			var d = fields[ k ];
			main.appendChild( d.type === 'toggle'
				? h( 'label', { class: 'kb-field kb-field--toggle' }, [ control( d, target[ k ], function ( v ) { set( v, k, k ); }, k ), h( 'span', { text: d.label } ) ] )
				: fieldRow( d.label, control( d, target[ k ], function ( v ) { set( v, k, k ); }, k ) ) );
		} );
		if ( ! Object.keys( fields ).length ) { main.appendChild( h( 'p', { class: 'kb-help', text: 'این ویجت محتوای خود را از بخش مدیریت (مثلاً اطلاعیه‌ها یا خدمات) می‌خواند.' } ) ); }
		pnl.appendChild( main );
		if ( S.sel.kind === 'widget' ) {
			var adv = h( 'details', { class: 'kb-group kb-adv' }, [ h( 'summary', { text: 'پیشرفته' } ) ] );
			Object.keys( K.common ).forEach( function ( k ) { adv.appendChild( fieldRow( K.common[ k ].label, control( K.common[ k ], target[ k ], function ( v ) { set( v, k, k ); }, k ) ) ); } );
			pnl.appendChild( adv );
		}
		pnl.appendChild( h( 'div', { class: 'kb-actions' }, [
			h( 'button', { type: 'button', class: 'kb-btn kb-btn--ghost kb-btn--sm', onclick: duplicateSel }, [ 'تکثیر' ] ),
			h( 'button', { type: 'button', class: 'kb-btn kb-btn--danger kb-btn--sm', onclick: removeSel }, [ 'حذف' ] )
		] ) );
	}

	/* ------------------------------------------------------------------ tree */
	function renderTree() {
		var pnl = ui.panels.tree;
		pnl.textContent = '';
		if ( ! S.layout.sections.length ) { pnl.appendChild( h( 'p', { class: 'kb-help', text: 'برگه هنوز خالی است.' } ) ); return; }
		var ol = h( 'ol', { class: 'kb-tree' } );
		S.layout.sections.forEach( function ( sec, i ) {
			var li = h( 'li', {}, [ h( 'button', { type: 'button', class: 'kb-tree__sec' + ( S.sel && S.sel.id === sec.id ? ' is-on' : '' ), onclick: function () { select( { kind: 'section', id: sec.id } ); scrollTo( sec.id ); } }, [ icon( 'grid', 16 ), 'بخش ' + fa( i + 1 ) + ' · ' + sec.columns.map( function ( c ) { return fa( c.span ); } ).join( '+' ) ] ) ] );
			var inner = h( 'ol', {} );
			sec.columns.forEach( function ( col ) {
				col.widgets.forEach( function ( w ) {
					inner.appendChild( h( 'li', {}, [ h( 'button', { type: 'button', class: S.sel && S.sel.id === w.id ? 'is-on' : '', onclick: function () { select( { kind: 'widget', id: w.id } ); scrollTo( w.id, true ); } }, [ icon( K.widgets[ w.type ].icon, 14 ), K.widgets[ w.type ].label ] ) ] ) );
				} );
			} );
			li.appendChild( inner );
			ol.appendChild( li );
		} );
		pnl.appendChild( ol );
	}

	/* ------------------------------------------------------------------ templates */
	var modal = null;
	function openTemplates() {
		if ( modal ) { modal.remove(); }
		modal = h( 'dialog', { class: 'kb-modal', 'aria-label': 'کتابخانهٔ قالب‌ها' } );
		var grid = h( 'div', { class: 'kb-tpls' }, [ h( 'p', { class: 'kb-help', text: 'در حال بارگذاری…' } ) ] );
		modal.appendChild( h( 'div', { class: 'kb-modal__head' }, [ h( 'h2', { text: 'قالب‌های آماده' } ), h( 'button', { type: 'button', class: 'kb-tbtn', 'aria-label': 'بستن', onclick: function () { modal.close(); } }, [ icon( 'close', 18 ) ] ) ] ) );
		modal.appendChild( grid );
		document.body.appendChild( modal );
		modal.showModal();
		api( 'templates' ).then( function ( list ) {
			grid.textContent = '';
			list.forEach( function ( t ) {
				grid.appendChild( h( 'div', { class: 'kb-tpl' }, [
					h( 'span', { class: 'kb-tpl__ico' }, [ icon( t.icon, 34 ) ] ),
					h( 'strong', { text: t.title } ), h( 'small', { text: t.desc } ),
					h( 'div', { class: 'kb-tpl__acts' }, [
						h( 'button', { type: 'button', class: 'kb-btn kb-btn--action kb-btn--sm', onclick: function () { useTemplate( t.key, false ); } }, [ 'جایگزینی' ] ),
						h( 'button', { type: 'button', class: 'kb-btn kb-btn--ghost kb-btn--sm', onclick: function () { useTemplate( t.key, true ); } }, [ 'افزودن به انتها' ] )
					] )
				] ) );
			} );
		} ).catch( function ( e ) { grid.textContent = e.message; } );
	}
	function useTemplate( key, append ) {
		if ( ! append && S.layout.sections.length && ! window.confirm( 'محتوای فعلی جایگزین شود؟ (با واگرد قابل بازگشت است)' ) ) { return; }
		api( 'templates/' + key ).then( function ( lay ) {
			snapshot();
			lay.sections.forEach( function ( s ) { s.id = uid(); s.columns.forEach( function ( c ) { c.id = uid(); c.widgets.forEach( function ( w ) { w.id = uid(); } ); } ); } );
			S.layout.sections = append ? S.layout.sections.concat( lay.sections ) : lay.sections;
			modal.close(); select( null );
			renderAll(); toast( 'قالب اعمال شد' );
		} ).catch( function ( e ) { toast( e.message, true ); } );
	}

	/* ------------------------------------------------------------------ save */
	function save( status ) {
		if ( S.saving ) { return; }
		S.saving = true;
		ui.saveState.textContent = 'در حال ذخیره…';
		api( 'layout/' + K.post, { layout: S.layout, status: status, title: S.title } ).then( function ( d ) {
			S.status = d.status; setDirty( false );
			ui.saveDraft.textContent = S.status === 'publish' ? 'به‌روزرسانی' : 'ذخیرهٔ پیش‌نویس';
			toast( status === 'publish' ? 'منتشر شد ✓' : 'ذخیره شد ✓' );
		} ).catch( function ( e ) { setDirty( true ); toast( e.message, true ); } ).finally( function () { S.saving = false; } );
	}
	function disableBuilder() {
		if ( ! window.confirm( 'صفحه‌ساز برای این برگه غیرفعال و ویرایشگر وردپرس باز شود؟ (طرح ذخیره‌شده حفظ می‌شود)' ) ) { return; }
		api( 'disable/' + K.post, {} ).then( function ( d ) { window.location.href = d.edit; } );
	}
	setInterval( function () { if ( S.dirty && ! S.saving && S.status !== 'publish' ) { save( 'keep' ); } }, 120000 );
	window.addEventListener( 'beforeunload', function ( e ) { if ( S.dirty ) { e.preventDefault(); e.returnValue = ''; } } );

	/* ------------------------------------------------------------------ keyboard */
	function onKey( e ) {
		var typing = /input|textarea|select/i.test( e.target.tagName ) || e.target.isContentEditable;
		var mod = e.ctrlKey || e.metaKey;
		if ( mod && e.key.toLowerCase() === 's' ) { e.preventDefault(); save( S.status === 'publish' ? 'keep' : 'draft' ); return; }
		if ( typing ) { return; }
		if ( mod && e.key.toLowerCase() === 'z' ) { e.preventDefault(); if ( e.shiftKey ) { redo(); } else { undo(); } }
		else if ( mod && e.key.toLowerCase() === 'y' ) { e.preventDefault(); redo(); }
		else if ( mod && e.key.toLowerCase() === 'd' ) { e.preventDefault(); duplicateSel(); }
		else if ( e.key === 'Delete' || e.key === 'Backspace' ) { if ( S.sel ) { e.preventDefault(); removeSel(); } }
		else if ( e.key === 'Escape' ) { select( null ); }
	}
	document.addEventListener( 'keydown', onKey );

	/* ------------------------------------------------------------------ boot */
	buildPalette();
	buildInspector();
	updateHistoryButtons();
	api( 'layout/' + K.post ).then( function ( d ) {
		S.layout = d.layout && d.layout.sections ? d.layout : { version: 1, sections: [] };
		return renderAll();
	} ).then( function () {
		setDirty( false );
		if ( ! S.layout.sections.length ) { openTemplates(); }
	} ).catch( function ( e ) { toast( e.message, true ); } );

	// Expose for e2e tests.
	window.KBEditor = { state: S, addWidget: addWidgetSmart, addSection: addSection, save: save, select: select };
}() );
