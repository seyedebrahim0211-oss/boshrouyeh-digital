/**
 * Editor registration for Boshrouyeh dynamic blocks (server-rendered previews).
 */
( function ( wp, cfg ) {
	'use strict';
	if ( ! wp || ! cfg ) { return; }
	var el = wp.element.createElement;
	var SSR = wp.serverSideRender;
	var be = wp.blockEditor;
	var C = wp.components;

	cfg.blocks.forEach( function ( def ) {
		if ( wp.blocks.getBlockType( def.name ) ) { return; }
		var attrs = Object.assign( {}, def.attributes, { align: { type: 'string', default: '' } } );
		wp.blocks.registerBlockType( def.name, {
			apiVersion: 3,
			title: def.title,
			icon: def.icon,
			category: 'boshrouyeh',
			attributes: attrs,
			supports: { align: [ 'wide', 'full' ], html: false },
			edit: function ( props ) {
				var controls = Object.keys( def.attributes ).map( function ( key ) {
					var a = def.attributes[ key ];
					if ( a.type === 'number' ) {
						return el( C.RangeControl, { key: key, label: key === 'count' ? 'تعداد' : key, min: 1, max: 50, value: props.attributes[ key ], onChange: function ( v ) { var o = {}; o[ key ] = v; props.setAttributes( o ); } } );
					}
					if ( key === 'layout' ) {
						return el( C.SelectControl, { key: key, label: 'چیدمان', value: props.attributes[ key ], options: [ { label: 'فشرده', value: 'compact' }, { label: 'کامل', value: 'full' } ], onChange: function ( v ) { props.setAttributes( { layout: v } ); } } );
					}
					return el( C.TextControl, { key: key, label: key, value: props.attributes[ key ] || '', onChange: function ( v ) { var o = {}; o[ key ] = v; props.setAttributes( o ); } } );
				} );
				return el( 'div', be.useBlockProps(),
					controls.length ? el( be.InspectorControls, null, el( C.PanelBody, { title: 'تنظیمات' }, controls ) ) : null,
					el( C.Disabled, null, el( SSR, { block: def.name, attributes: props.attributes, EmptyResponsePlaceholder: function () { return el( C.Placeholder, { icon: def.icon, label: def.title, instructions: 'پیش‌نمایش در صفحهٔ واقعی نمایش داده می‌شود.' } ); } } ) )
				);
			},
			save: function () { return null; }
		} );
	} );
}( window.wp, window.BMNBlocks ) );
