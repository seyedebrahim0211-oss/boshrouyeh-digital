/* "Edit with Khesht Builder" entry in the block editor's post status panel. */
( function ( wp, cfg ) {
	'use strict';
	if ( ! wp || ! wp.plugins || ! cfg ) { return; }
	var el = wp.element.createElement;
	var Slot = ( wp.editor && wp.editor.PluginPostStatusInfo ) || ( wp.editPost && wp.editPost.PluginPostStatusInfo );
	if ( ! Slot ) { return; }
	wp.plugins.registerPlugin( 'khesht-builder-button', {
		render: function () {
			var id = wp.data.select( 'core/editor' ).getCurrentPostId();
			return el( Slot, null, el( wp.components.Button, {
				variant: 'primary',
				href: cfg.url + id,
				style: { width: '100%', justifyContent: 'center', background: '#16737A' }
			}, 'ویرایش با صفحه‌ساز خشت' ) );
		}
	} );
}( window.wp, window.KBGutenberg ) );
