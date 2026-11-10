/* Boshrouyeh admin fields: media picker + icon preview. */
jQuery( function ( $ ) {
	$( document ).on( 'click', '.bmn-media__pick', function ( e ) {
		e.preventDefault();
		var $wrap = $( this ).closest( '.bmn-media' );
		var frame = wp.media( { title: $( this ).text(), multiple: false, library: $( this ).data( 'mime' ) ? { type: $( this ).data( 'mime' ) } : {} } );
		frame.on( 'select', function () {
			var a = frame.state().get( 'selection' ).first().toJSON();
			$wrap.find( 'input[type=hidden]' ).val( a.id );
			$wrap.find( '.bmn-media__name' ).text( a.filename || a.title );
		} );
		frame.open();
	} );
	$( document ).on( 'click', '.bmn-media__clear', function ( e ) {
		e.preventDefault();
		var $wrap = $( this ).closest( '.bmn-media' );
		$wrap.find( 'input[type=hidden]' ).val( '' );
		$wrap.find( '.bmn-media__name' ).text( '' );
	} );
} );
