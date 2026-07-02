/**
 * Epiktetos — Article Voiceover meta box.
 * Media Library audio selector for the post editor. Stores the attachment ID.
 */
( function () {
	'use strict';

	var L = window.EpiktetosVoiceover || {};

	function init( field ) {
		var input  = field.querySelector( '[data-voiceover-input]' );
		var name   = field.querySelector( '[data-voiceover-name]' );
		var select = field.querySelector( '[data-voiceover-select]' );
		var remove = field.querySelector( '[data-voiceover-remove]' );
		if ( ! input || ! select || ! window.wp || ! wp.media ) {
			return;
		}

		var frame;

		select.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( frame ) {
				frame.open();
				return;
			}
			frame = wp.media( {
				title: L.title || 'Select article voiceover',
				button: { text: L.button || 'Use this audio' },
				library: { type: 'audio' },
				multiple: false
			} );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first();
				if ( ! att ) { return; }
				att = att.toJSON();
				if ( ! att.mime || att.mime.indexOf( 'audio/' ) !== 0 ) { return; }
				input.value = att.id;
				if ( name ) { name.textContent = att.filename || att.title || ''; }
				select.textContent = L.replace || 'Replace audio';
				if ( remove ) { remove.hidden = false; }
			} );
			frame.open();
		} );

		if ( remove ) {
			remove.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				input.value = '0';
				if ( name ) { name.textContent = L.empty || 'No audio selected'; }
				select.textContent = L.select || 'Select audio';
				remove.hidden = true;
			} );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var fields = document.querySelectorAll( '[data-epiktetos-voiceover]' );
		Array.prototype.forEach.call( fields, init );
	} );
} )();
