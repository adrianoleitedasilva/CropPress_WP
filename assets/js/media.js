/* CropPress na biblioteca de mídia: botão "Restaurar original" (janela da biblioteca e tela do anexo). */
( function ( $ ) {
	'use strict';
	$( document ).on( 'click', '.croppress-restore', function ( event ) {
		var $button = $( this );
		event.preventDefault();
		if ( ! window.confirm( window.croppressMedia.confirm ) ) {
			return;
		}
		$button.prop( 'disabled', true );
		$.post( window.croppressMedia.ajax, { action: 'croppress_restore', attachment: $button.data( 'id' ), nonce: $button.data( 'nonce' ) } )
			.done( function ( response ) {
				if ( response && response.success ) {
					window.alert( window.croppressMedia.done );
					window.location.reload();
				} else {
					window.alert( ( response && response.data && response.data.message ) || 'Erro' );
					$button.prop( 'disabled', false );
				}
			} )
			.fail( function () {
				$button.prop( 'disabled', false );
			} );
	} );
}( jQuery ) );
