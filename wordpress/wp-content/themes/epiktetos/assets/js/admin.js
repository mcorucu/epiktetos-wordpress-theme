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
		var activeApplySelection = null;

		document.addEventListener( 'click', function ( event ) {
			if ( ! activeApplySelection || ! event.target.closest( '.media-modal .media-button-select' ) ) { return; }
			if ( activeApplySelection() ) {
				event.preventDefault();
				event.stopPropagation();
			}
		}, true );

		fields.forEach( function ( field ) {
			var input = field.querySelector( '[data-epi-media-input]' );
			var preview = field.querySelector( '[data-epi-media-preview]' );
			var name = field.querySelector( '[data-epi-media-name]' );
			var detail = field.querySelector( '[data-epi-media-detail]' );
			var select = field.querySelector( '[data-epi-media-select]' );
			var remove = field.querySelector( '[data-epi-media-remove]' );
			var library = ( field.getAttribute( 'data-library' ) || 'image' ).split( ',' ).filter( Boolean );
			var frame;
			if ( ! input || ! select ) { return; }

			function applySelection() {
				if ( ! frame || ! frame.state ) { return false; }
				var selection = frame.state().get( 'selection' );
				var selected = selection && selection.first ? selection.first() : null;
				var attachment = selected && selected.toJSON ? selected.toJSON() : null;
				if ( ! attachment ) {
					var selectedNode = document.querySelector( '.media-modal li.attachment.selected' );
					var selectedId = selectedNode ? selectedNode.getAttribute( 'data-id' ) : '';
					var selectedModel = selectedId && window.wp && wp.media && wp.media.attachment ? wp.media.attachment( selectedId ) : null;
					attachment = selectedModel && selectedModel.toJSON ? selectedModel.toJSON() : null;
				}
				if ( ! attachment ) { return false; }
				var url = attachment.url || '';
				input.value = attachment.id || '';
				if ( preview ) {
					preview.innerHTML = url ? '<img src="' + escapeAttr( url ) + '" alt="' + escapeAttr( attachment.alt || attachment.title || 'Selected asset' ) + '" />' : '';
				}
				if ( name ) {
					name.textContent = attachment.filename || attachment.title || 'Selected asset';
				}
				if ( detail ) {
					detail.textContent = mediaDetail( attachment );
				}
				if ( remove ) {
					remove.hidden = false;
				}
				if ( select ) {
					select.textContent = select.getAttribute( 'data-label-replace' ) || 'Replace';
				}
				if ( frame.close ) {
					frame.close();
				}
				return true;
			}

			function bindToolbarFallback() {
				[ 0, 250, 750 ].forEach( function ( delay ) {
					window.setTimeout( function () {
						var button = document.querySelector( '.media-modal .media-button-select' );
						if ( ! button ) { return; }
						if ( button._epiMediaHandler ) {
							button.removeEventListener( 'click', button._epiMediaHandler, true );
						}
						button._epiMediaHandler = function ( event ) {
							if ( applySelection() ) {
								event.preventDefault();
								event.stopPropagation();
							}
						};
						button.addEventListener( 'click', button._epiMediaHandler, true );
					}, delay );
				} );
			}

			select.addEventListener( 'click', function () {
				if ( frame ) {
					activeApplySelection = applySelection;
					frame.open();
					bindToolbarFallback();
					return;
				}

				frame = wp.media( {
					title: 'Select branding asset',
					frame: 'select',
					state: 'library',
					button: { text: 'Use this asset' },
					library: { type: library },
					multiple: false
				} );

				frame.on( 'select', applySelection );
				frame.on( 'open', bindToolbarFallback );
				frame.on( 'close', function () {
					if ( activeApplySelection === applySelection ) {
						activeApplySelection = null;
					}
				} );

				activeApplySelection = applySelection;
				frame.open();
				bindToolbarFallback();
			} );

			if ( remove ) {
				remove.addEventListener( 'click', function () {
					input.value = '';
					if ( preview ) { preview.innerHTML = ''; }
					if ( name ) { name.textContent = 'No asset selected'; }
					if ( detail ) { detail.textContent = ''; }
					remove.hidden = true;
					if ( select ) {
						select.textContent = select.getAttribute( 'data-label-empty' ) || 'Upload / Select';
					}
				} );
			}
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
