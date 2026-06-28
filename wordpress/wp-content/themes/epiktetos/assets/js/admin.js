/**
 * Epiktetos admin control center.
 * Tabs, safe action confirmations, and settings export helpers.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		initTabs();
		initConfirmations();
		initExport();
		initMediaFields();
	} );

	function initTabs() {
		var root = document.querySelector( '[data-epi-admin]' );
		if ( ! root ) { return; }

		var buttons = Array.prototype.slice.call( root.querySelectorAll( '[data-epi-tab]' ) );
		var panels = Array.prototype.slice.call( root.querySelectorAll( '[data-epi-panel]' ) );
		var submit = root.querySelector( '[data-epi-submit]' );

		function show( tab ) {
			buttons.forEach( function ( button ) {
				var active = button.getAttribute( 'data-epi-tab' ) === tab;
				button.classList.toggle( 'is-active', active );
				button.setAttribute( 'aria-selected', active ? 'true' : 'false' );
			} );
			panels.forEach( function ( panel ) {
				var active = panel.getAttribute( 'data-epi-panel' ) === tab;
				panel.classList.toggle( 'is-active', active );
				panel.hidden = ! active;
			} );
				if ( submit ) {
					// Custom (non-settings-form) tabs hide the Save button.
					submit.hidden = [ 'general', 'sample', 'system', 'about-theme', 'validator' ].indexOf( tab ) !== -1;
				}
			if ( window.history && window.history.replaceState ) {
				window.history.replaceState( null, '', '#tab-' + tab );
			}
		}

		buttons.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				show( button.getAttribute( 'data-epi-tab' ) );
			} );
			button.addEventListener( 'keydown', function ( event ) {
				var index = buttons.indexOf( button );
				var next = null;
				if ( event.key === 'ArrowRight' ) {
					next = buttons[ ( index + 1 ) % buttons.length ];
				} else if ( event.key === 'ArrowLeft' ) {
					next = buttons[ ( index - 1 + buttons.length ) % buttons.length ];
				}
				if ( next ) {
					event.preventDefault();
					next.focus();
					show( next.getAttribute( 'data-epi-tab' ) );
				}
			} );
		} );

		var initial = window.location.hash.indexOf( '#tab-' ) === 0 ? window.location.hash.replace( '#tab-', '' ) : 'general';
		if ( ! root.querySelector( '[data-epi-tab="' + initial + '"]' ) ) {
			initial = 'general';
		}
		show( initial );
	}

	function initConfirmations() {
		function confirmAction( control, event ) {
			var message = control ? control.getAttribute( 'data-epi-confirm' ) : '';
			if ( message && ! window.confirm( message ) ) {
				event.preventDefault();
				event.stopPropagation();
				return false;
			}
			return true;
		}

		Array.prototype.slice.call( document.querySelectorAll( '[data-epi-confirm]' ) ).forEach( function ( control ) {
			control.addEventListener( 'click', function ( event ) {
				confirmAction( control, event );
			} );
		} );

		Array.prototype.slice.call( document.querySelectorAll( 'form' ) ).forEach( function ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				var submitter = event.submitter || document.activeElement;
				if ( submitter && submitter.matches && submitter.matches( '[data-epi-confirm]' ) ) {
					confirmAction( submitter, event );
				}
			} );
		} );
	}

	function initExport() {
		var textarea = document.querySelector( '[data-epi-export]' );
		var copy = document.querySelector( '[data-epi-copy-export]' );
		var download = document.querySelector( '[data-epi-download-export]' );
		var status = document.querySelector( '[data-epi-export-status]' );
		if ( ! textarea ) { return; }

		function setStatus( text ) {
			if ( status ) { status.textContent = text; }
		}

		if ( copy ) {
			copy.addEventListener( 'click', function () {
				var value = textarea.value;
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( value ).then( function () {
						setStatus( 'Copied.' );
					} ).catch( function () {
						textarea.select();
						document.execCommand( 'copy' );
						setStatus( 'Copied.' );
					} );
				} else {
					textarea.select();
					document.execCommand( 'copy' );
					setStatus( 'Copied.' );
				}
			} );
		}

		if ( download ) {
			download.addEventListener( 'click', function () {
				var blob = new Blob( [ textarea.value ], { type: 'application/json' } );
				var url = URL.createObjectURL( blob );
				var link = document.createElement( 'a' );
				link.href = url;
				link.download = download.getAttribute( 'data-filename' ) || 'epiktetos-settings.json';
				document.body.appendChild( link );
				link.click();
				document.body.removeChild( link );
				URL.revokeObjectURL( url );
				setStatus( 'Download prepared.' );
			} );
		}
	}

	function initMediaFields() {
		var fields = Array.prototype.slice.call( document.querySelectorAll( '[data-epi-media-field]' ) );
		if ( ! fields.length || ! window.wp || ! wp.media ) { return; }

		document.addEventListener( 'click', function ( event ) {
			var select = event.target.closest( '[data-epi-media-select]' );
			var remove = event.target.closest( '[data-epi-media-remove]' );
			if ( select ) {
				event.preventDefault();
				openMediaFrame( select );
			} else if ( remove ) {
				event.preventDefault();
				clearMediaField( remove );
			}
		} );

		function getParts( field ) {
			return {
				input: field.querySelector( '[data-epi-media-input]' ),
				preview: field.querySelector( '[data-epi-media-preview]' ),
				name: field.querySelector( '[data-epi-media-name]' ),
				detail: field.querySelector( '[data-epi-media-detail]' ),
				select: field.querySelector( '[data-epi-media-select]' ),
				remove: field.querySelector( '[data-epi-media-remove]' )
			};
		}

		function openMediaFrame( select ) {
			var field = select.closest( '[data-epi-media-field]' );
			if ( ! field ) { return; }
			var parts = getParts( field );
			if ( ! parts.input ) { return; }
			var library = ( field.getAttribute( 'data-library' ) || 'image' ).split( ',' ).filter( Boolean );
			var frame = field._epiMediaFrame;

			if ( ! frame ) {
				frame = wp.media( {
					title: select.getAttribute( 'data-title' ) || 'Select image',
					frame: 'select',
					button: { text: select.getAttribute( 'data-button' ) || 'Use this image' },
					library: { type: library.length > 1 ? library : library[0] },
					multiple: false
				} );

				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first();
					if ( attachment ) {
						updateMediaField( field, attachment.toJSON() );
					}
				} );

				frame.on( 'open', function () {
					var selection = frame.state().get( 'selection' );
					var id = parseInt( parts.input.value, 10 );
					selection.reset();
					if ( id && wp.media.attachment ) {
						var attachment = wp.media.attachment( id );
						attachment.fetch();
						selection.add( attachment );
					}
				} );

				field._epiMediaFrame = frame;
			}

			frame.open();
		}

		function updateMediaField( field, attachment ) {
			var parts = getParts( field );
			if ( ! parts.input || ! attachment || ! attachment.id ) { return; }
			var url = attachment.url || ( attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url ) || '';
			parts.input.value = parseInt( attachment.id, 10 ) || '';
			if ( parts.preview ) {
				parts.preview.innerHTML = url ? '<img src="' + escapeAttr( url ) + '" alt="' + escapeAttr( attachment.alt || attachment.title || 'Selected asset' ) + '" />' : '';
			}
			if ( parts.name ) {
				parts.name.textContent = attachment.filename || attachment.title || 'Selected asset';
			}
			if ( parts.detail ) {
				parts.detail.textContent = mediaDetail( attachment );
			}
			if ( parts.remove ) {
				parts.remove.hidden = false;
			}
			if ( parts.select ) {
				parts.select.textContent = parts.select.getAttribute( 'data-label-replace' ) || 'Replace';
			}
		}

		function clearMediaField( remove ) {
			var field = remove.closest( '[data-epi-media-field]' );
			if ( ! field ) { return; }
			var parts = getParts( field );
			if ( parts.input ) { parts.input.value = ''; }
			if ( parts.preview ) { parts.preview.innerHTML = ''; }
			if ( parts.name ) { parts.name.textContent = 'No asset selected'; }
			if ( parts.detail ) { parts.detail.textContent = ''; }
			remove.hidden = true;
			if ( parts.select ) {
				parts.select.textContent = parts.select.getAttribute( 'data-label-empty' ) || 'Upload / Select';
			}
		}

		fields.forEach( function ( field ) {
			var input = field.querySelector( '[data-epi-media-input]' );
			var select = field.querySelector( '[data-epi-media-select]' );
			if ( ! input || ! select ) { return; }
			field._epiMediaFrame = null;
		} );
	}

	function mediaDetail( attachment ) {
		var parts = [];
		if ( attachment.mime ) {
			parts.push( attachment.mime );
		}
		if ( attachment.width && attachment.height ) {
			parts.push( attachment.width + '×' + attachment.height );
		}
		if ( ! parts.length && attachment.subtype ) {
			parts.push( attachment.subtype );
		}
		return parts.join( ' · ' );
	}

	function escapeAttr( value ) {
		return String( value ).replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
	}
} )();
