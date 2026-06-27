/**
 * Epiktetos — admin Category Order drag & drop.
 * Reorders the sortable list and serializes term IDs into the hidden field
 * that the Settings API saves.
 */
( function ( $ ) {
	'use strict';
	$( function () {
		var $list = $( '#epi-category-sortable' );
		var $value = $( '#epi-category-order-value' );
		if ( ! $list.length || ! $value.length ) { return; }

		function sync() {
			var ids = $list.children( '.epi-sortable__item' ).map( function () {
				return $( this ).data( 'id' );
			} ).get();
			$value.val( ids.join( ',' ) );
		}

		$list.sortable( {
			handle: '.epi-sortable__handle',
			axis: 'y',
			cursor: 'grabbing',
			placeholder: 'epi-sortable__placeholder',
			forcePlaceholderSize: true,
			update: sync
		} );

		sync();
	} );
} )( jQuery );
