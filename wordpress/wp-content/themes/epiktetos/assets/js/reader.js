/**
 * Epiktetos reader intelligence.
 * Local-only history, read later, completion, quote copy, image zoom, and shortcuts.
 */
( function () {
	'use strict';

	var app = window.EpiktetosReader || {};
	var settings = app.settings || {};
	var strings = app.strings || {};
	var reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	var keys = {
		history: 'epi:history',
		saved: 'epi:saved',
		streak: 'epi:streak',
		toc: 'epi:toc-open'
	};

	document.addEventListener( 'DOMContentLoaded', function () {
		var article = document.querySelector( '.ts-article[data-ts-article-id]' );
		initArticleState( article );
		initHistoryModules();
		initSavedButtons();
		initSavedPage();
		initQuoteCopy( article );
		initImageZoom( article );
		initTocPolish();
		initShortcuts();
		initFade();
	} );

	function enabled( key ) {
		return settings[ key ] !== false && settings[ key ] !== '0';
	}

	function text( key, fallback ) {
		return strings[ key ] || fallback;
	}

	function read( key, fallback ) {
		try {
			var raw = localStorage.getItem( key );
			return raw ? JSON.parse( raw ) : fallback;
		} catch ( e ) {
			return fallback;
		}
	}

	function write( key, value ) {
		try { localStorage.setItem( key, JSON.stringify( value ) ); } catch ( e ) {}
	}

	function todayKey() {
		return new Date().toISOString().slice( 0, 10 );
	}

	function articleData( article ) {
		if ( ! article ) { return null; }
		return {
			id: String( article.getAttribute( 'data-ts-article-id' ) || '' ),
			title: article.getAttribute( 'data-ts-article-title' ) || document.title,
			url: article.getAttribute( 'data-ts-article-url' ) || window.location.href,
			minutes: Math.max( 1, parseInt( article.getAttribute( 'data-ts-article-minutes' ) || '1', 10 ) )
		};
	}

	function articleProgress( article ) {
		if ( ! article ) { return 0; }
		var rect = article.getBoundingClientRect();
		var total = Math.max( rect.height - window.innerHeight, 1 );
		return Math.min( 1, Math.max( 0, -rect.top / total ) );
	}

	function initArticleState( article ) {
		if ( ! article ) { return; }
		var data = articleData( article );
		if ( ! data || ! data.id ) { return; }
			var completion = document.querySelector( '[data-ts-completion]' );
			var streakMarked = false;
			var ticking = false;
			var lastHistoryPct = -1;
			var lastHistoryWrite = 0;

		function update() {
			var pct = articleProgress( article );
			if ( enabled( 'completion' ) && completion ) {
				var left = Math.max( 0, Math.ceil( data.minutes * ( 1 - pct ) ) );
				completion.textContent = left > 0 ? left + ' ' + text( 'minLeft', 'min left' ) : text( 'done', 'Done' );
			}
				if ( enabled( 'history' ) ) {
					var pctWhole = Math.round( pct * 100 );
					var now = Date.now();
					if ( lastHistoryPct < 0 || Math.abs( pctWhole - lastHistoryPct ) >= 5 || now - lastHistoryWrite > 4000 || pctWhole >= 100 ) {
						saveHistory( data, pct );
						lastHistoryPct = pctWhole;
						lastHistoryWrite = now;
					}
				}
			if ( enabled( 'streak' ) && pct >= 0.82 && ! streakMarked ) {
				markRead( data );
				streakMarked = true;
			}
			updateFinished( pct );
			ticking = false;
		}

		update();
		window.addEventListener( 'scroll', function () {
			if ( ! ticking ) {
				window.requestAnimationFrame( update );
				ticking = true;
			}
		}, { passive: true } );
		window.addEventListener( 'beforeunload', update );
	}

	function saveHistory( data, pct ) {
		var list = read( keys.history, [] ).filter( function ( item ) {
			return String( item.id ) !== String( data.id );
		} );
		list.unshift( {
			id: data.id,
			title: data.title,
			url: data.url,
			timestamp: Date.now(),
			progress: Math.round( pct * 100 ),
			minutes: data.minutes
		} );
		write( keys.history, list.slice( 0, 10 ) );
		renderHistoryModules();
	}

	function markRead( data ) {
		var streak = read( keys.streak, {} );
		var day = todayKey();
		if ( ! streak[ day ] ) {
			streak[ day ] = { ids: [], minutes: 0 };
		}
		if ( streak[ day ].ids.indexOf( data.id ) === -1 ) {
			streak[ day ].ids.push( data.id );
			streak[ day ].minutes += data.minutes;
		}
		write( keys.streak, streak );
		renderStreak();
	}

	function initHistoryModules() {
		renderHistoryModules();
		renderStreak();
	}

	function renderHistoryModules() {
		if ( ! enabled( 'history' ) ) { return; }
		var modules = Array.prototype.slice.call( document.querySelectorAll( '[data-ts-history-module]' ) );
		if ( ! modules.length ) { return; }
		var history = read( keys.history, [] ).filter( function ( item ) {
			return item && item.url && item.title;
		} );
		modules.forEach( function ( module ) {
			var list = module.querySelector( '[data-ts-history-list]' );
			if ( ! list || ! history.length ) {
				module.hidden = true;
				return;
			}
			list.innerHTML = history.slice( 0, module.getAttribute( 'data-context' ) === 'home' ? 4 : 3 ).map( function ( item ) {
				var pct = Math.max( 0, Math.min( 100, parseInt( item.progress || 0, 10 ) ) );
				return '<a class="ts-reader-history__item" href="' + esc( item.url ) + '"><span>' + esc( item.title ) + '</span><small>' + pct + '% read</small></a>';
			} ).join( '' );
			module.hidden = false;
		} );
	}

	function renderStreak() {
		var badge = document.querySelector( '[data-ts-streak]' );
		var value = document.querySelector( '[data-ts-streak-value]' );
		if ( ! badge || ! value || ! enabled( 'streak' ) ) { return; }
		var day = read( keys.streak, {} )[ todayKey() ];
		if ( ! day || ! day.ids || ! day.ids.length ) {
			badge.hidden = true;
			return;
		}
		value.textContent = day.ids.length >= 2
			? day.ids.length + ' ' + text( 'articlesRead', 'articles' )
			: Math.max( 1, day.minutes || 1 ) + ' ' + text( 'minutesRead', 'minutes' );
		badge.hidden = false;
	}

	function initSavedButtons() {
		if ( ! enabled( 'readLater' ) ) { return; }
		Array.prototype.slice.call( document.querySelectorAll( '[data-ts-save]' ) ).forEach( function ( button ) {
			var id = String( button.getAttribute( 'data-article-id' ) || '' );
			if ( ! id ) { return; }
			function refresh() {
				var saved = read( keys.saved, [] ).some( function ( item ) { return String( item.id ) === id; } );
				var label = saved ? button.getAttribute( 'data-saved-label' ) || text( 'saved', 'Saved' ) : button.getAttribute( 'data-unsaved-label' ) || text( 'saveForLater', 'Save for later' );
				button.setAttribute( 'aria-pressed', saved ? 'true' : 'false' );
				button.setAttribute( 'aria-label', label );
				button.classList.toggle( 'is-saved', saved );
				var labelText = button.querySelector( '.ts-save-button__label' );
				var icon = button.querySelector( '.ts-save-button__icon' );
				if ( labelText ) { labelText.textContent = saved ? text( 'saved', 'Saved' ) : text( 'save', 'Save' ); }
				if ( icon ) { icon.textContent = saved ? '★' : '☆'; }
			}
			button.addEventListener( 'click', function () {
				var saved = read( keys.saved, [] );
				var exists = saved.some( function ( item ) { return String( item.id ) === id; } );
				if ( exists ) {
					saved = saved.filter( function ( item ) { return String( item.id ) !== id; } );
				} else {
					saved.unshift( {
						id: id,
						title: button.getAttribute( 'data-title' ) || document.title,
						url: button.getAttribute( 'data-url' ) || window.location.href,
						timestamp: Date.now()
					} );
				}
				write( keys.saved, saved.slice( 0, 50 ) );
				refresh();
				renderSavedPage();
			} );
			refresh();
		} );
	}

	function initSavedPage() {
		renderSavedPage();
	}

	function renderSavedPage() {
		var page = document.querySelector( '[data-ts-saved-page]' );
		if ( ! page ) { return; }
		var list = page.querySelector( '[data-ts-saved-list]' );
		var empty = page.querySelector( '[data-ts-saved-empty]' );
		var saved = read( keys.saved, [] ).filter( function ( item ) { return item && item.url && item.title; } );
		if ( ! saved.length ) {
			if ( list ) { list.innerHTML = ''; }
			if ( empty ) { empty.hidden = false; }
			return;
		}
		if ( empty ) { empty.hidden = true; }
		if ( list ) {
			list.innerHTML = saved.map( function ( item ) {
				return '<article class="ts-saved-item"><h2><a href="' + esc( item.url ) + '">' + esc( item.title ) + '</a></h2><button type="button" data-ts-remove-saved="' + esc( item.id ) + '">' + esc( text( 'remove', 'Remove' ) ) + '</button></article>';
			} ).join( '' );
			Array.prototype.slice.call( list.querySelectorAll( '[data-ts-remove-saved]' ) ).forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					var id = button.getAttribute( 'data-ts-remove-saved' );
					write( keys.saved, read( keys.saved, [] ).filter( function ( item ) { return String( item.id ) !== String( id ); } ) );
					renderSavedPage();
					initSavedButtons();
				} );
			} );
		}
	}

	function initQuoteCopy( article ) {
		if ( ! article || ! enabled( 'quoteCopy' ) ) { return; }
		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'ts-quote-copy';
		button.textContent = text( 'copyQuote', 'Copy Quote' );
		button.hidden = true;
		document.body.appendChild( button );

		document.addEventListener( 'selectionchange', function () {
			var selection = window.getSelection();
			var text = selection ? selection.toString().trim() : '';
			if ( ! text || text.length < 8 || ! article.contains( selection.anchorNode ) ) {
				button.hidden = true;
				return;
			}
			var range = selection.getRangeAt( 0 );
			var rect = range.getBoundingClientRect();
			button.style.left = Math.max( 12, rect.left + window.scrollX ) + 'px';
			button.style.top = Math.max( 12, rect.top + window.scrollY - 42 ) + 'px';
			button.hidden = false;
		} );
		button.addEventListener( 'click', function () {
			var selectedText = window.getSelection().toString().trim();
			if ( ! selectedText ) { return; }
			copyText( '“' + selectedText + '”\n\n' + window.location.href, function () {
				button.textContent = text( 'copied', 'Copied' );
				window.setTimeout( function () {
					button.textContent = text( 'copyQuote', 'Copy Quote' );
					button.hidden = true;
				}, 1400 );
			} );
		} );
	}

	function initImageZoom( article ) {
		if ( ! article || ! enabled( 'imageZoom' ) ) { return; }
		var images = Array.prototype.slice.call( article.querySelectorAll( '.ts-article__body img, .ts-article__media img' ) );
		if ( ! images.length ) { return; }
		var overlay = document.createElement( 'div' );
		overlay.className = 'ts-image-zoom';
		overlay.innerHTML = '<button type="button" class="ts-image-zoom__close" aria-label="' + esc( text( 'closeImage', 'Close image' ) ) + '">×</button>';
		overlay.hidden = true;
		document.body.appendChild( overlay );
		var close = overlay.querySelector( 'button' );
		var zoomImg = null;
		var lastFocus = null;

		images.forEach( function ( img ) {
			img.setAttribute( 'tabindex', '0' );
			img.setAttribute( 'role', 'button' );
			img.setAttribute( 'aria-label', img.getAttribute( 'alt' ) ? text( 'zoomImage', 'Zoom image' ) + ': ' + img.getAttribute( 'alt' ) : text( 'zoomImage', 'Zoom image' ) );
			img.addEventListener( 'click', function () { open( img ); } );
			img.addEventListener( 'keydown', function ( event ) {
				if ( event.key === 'Enter' || event.key === ' ' ) {
					event.preventDefault();
					open( img );
				}
			} );
		} );
		function open( img ) {
			lastFocus = document.activeElement;
			if ( ! zoomImg ) {
				zoomImg = document.createElement( 'img' );
				overlay.appendChild( zoomImg );
			}
			zoomImg.src = img.currentSrc || img.src;
			zoomImg.alt = img.alt || '';
			overlay.hidden = false;
			document.documentElement.classList.add( 'ts-modal-open' );
			close.focus();
		}
		function hide() {
			overlay.hidden = true;
			if ( zoomImg ) {
				zoomImg.remove();
				zoomImg = null;
			}
			document.documentElement.classList.remove( 'ts-modal-open' );
			if ( lastFocus && lastFocus.focus ) { lastFocus.focus(); }
		}
		close.addEventListener( 'click', hide );
		overlay.addEventListener( 'click', function ( event ) {
			if ( event.target === overlay ) { hide(); }
		} );
		document.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' && ! overlay.hidden ) { hide(); }
		} );
	}

	function initTocPolish() {
		var toc = document.querySelector( '.ts-toc' );
		if ( ! toc ) { return; }
		var title = toc.querySelector( '.ts-toc__title' );
		var list = toc.querySelector( '.ts-toc__list' );
		if ( title && list ) {
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'ts-toc__toggle';
			btn.setAttribute( 'aria-controls', 'ts-toc-list' );
			list.id = list.id || 'ts-toc-list';
			btn.innerHTML = '<span>' + title.textContent + '</span><span aria-hidden="true">⌄</span>';
			title.replaceWith( btn );
			var open = read( keys.toc, true );
			function apply() {
				btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				list.hidden = ! open && window.matchMedia( '(max-width: 760px)' ).matches;
				toc.classList.toggle( 'is-collapsed', ! open );
			}
			btn.addEventListener( 'click', function () {
				open = ! open;
				write( keys.toc, open );
				apply();
			} );
			window.addEventListener( 'resize', apply, { passive: true } );
			apply();
			}
			if ( 'IntersectionObserver' in window ) {
				var links = Array.prototype.slice.call( toc.querySelectorAll( 'a[href^="#"]' ) );
				var marker = document.createElement( 'span' );
				var markerTicking = false;
				marker.className = 'ts-toc__marker';
				marker.setAttribute( 'aria-hidden', 'true' );
			toc.appendChild( marker );
			function updateMarker( active ) {
				if ( ! active ) { return; }
				var rect = active.getBoundingClientRect();
				var parent = toc.getBoundingClientRect();
				marker.style.transform = 'translateY(' + ( rect.top - parent.top ) + 'px)';
				marker.style.height = rect.height + 'px';
			}
				links.forEach( function ( link ) {
					if ( link.classList.contains( 'is-active' ) ) { updateMarker( link ); }
				} );
				window.addEventListener( 'scroll', function () {
					if ( markerTicking ) { return; }
					markerTicking = true;
					window.requestAnimationFrame( function () {
						updateMarker( toc.querySelector( 'a.is-active' ) );
						markerTicking = false;
					} );
				}, { passive: true } );
			}
	}

	function initShortcuts() {
		var help;
		document.addEventListener( 'keydown', function ( event ) {
			if ( event.defaultPrevented || event.metaKey || event.ctrlKey || event.altKey ) { return; }
			var tag = document.activeElement && document.activeElement.tagName;
			var typing = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || document.activeElement.isContentEditable;
			if ( event.key === 'Escape' ) {
				closeHelp();
				return;
			}
			if ( typing ) { return; }
			if ( event.key === '?' ) {
				event.preventDefault();
				toggleHelp();
			} else if ( event.key === '/' ) {
				var search = document.querySelector( '#ts-search-input, #ts-search-page-input, input[type="search"]' );
				if ( search ) {
					event.preventDefault();
					search.focus();
				}
			} else if ( event.key === 'j' || event.key === 'k' ) {
				jumpHeading( event.key === 'j' ? 1 : -1 );
			}
		} );
		function toggleHelp() {
			if ( help && ! help.hidden ) {
				closeHelp();
				return;
			}
			if ( ! help ) {
				help = document.createElement( 'div' );
				help.className = 'ts-shortcuts';
				help.setAttribute( 'role', 'dialog' );
				help.setAttribute( 'aria-modal', 'true' );
				help.innerHTML = '<div class="ts-shortcuts__panel"><h2>' + esc( text( 'shortcutsTitle', 'Keyboard shortcuts' ) ) + '</h2><dl><div><dt>?</dt><dd>' + esc( text( 'shortcuts', 'Shortcuts' ) ) + '</dd></div><div><dt>j / k</dt><dd>' + esc( text( 'headingShortcut', 'Next / previous heading' ) ) + '</dd></div><div><dt>/</dt><dd>' + esc( text( 'search', 'Search' ) ) + '</dd></div><div><dt>Esc</dt><dd>' + esc( text( 'close', 'Close' ) ) + '</dd></div></dl></div>';
				document.body.appendChild( help );
			}
			help.hidden = false;
		}
		function closeHelp() {
			if ( help ) { help.hidden = true; }
		}
	}

	function jumpHeading( dir ) {
		var headings = Array.prototype.slice.call( document.querySelectorAll( '.ts-article__body h2[id]' ) );
		if ( ! headings.length ) { return; }
		var y = window.scrollY + 120;
		var current = headings.findIndex( function ( h, i ) {
			var next = headings[ i + 1 ];
			return h.offsetTop <= y && ( ! next || next.offsetTop > y );
		} );
		var target = headings[ Math.max( 0, Math.min( headings.length - 1, current + dir ) ) ];
		if ( target ) {
			target.scrollIntoView( { behavior: reduce.matches ? 'auto' : 'smooth', block: 'start' } );
		}
	}

	function updateFinished( pct ) {
		var box = document.querySelector( '[data-ts-finished]' );
		if ( box ) { box.classList.toggle( 'is-visible', pct >= 0.9 ); }
	}

	function initFade() {
		document.documentElement.classList.add( 'ts-reader-ready' );
	}

	function copyText( text, done ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( done ).catch( fallback );
		} else {
			fallback();
		}
		function fallback() {
			var ta = document.createElement( 'textarea' );
			ta.value = text;
			ta.setAttribute( 'readonly', '' );
			ta.style.position = 'fixed';
			ta.style.left = '-9999px';
			document.body.appendChild( ta );
			ta.select();
			try { document.execCommand( 'copy' ); } catch ( e ) {}
			document.body.removeChild( ta );
			done();
		}
	}

	function esc( value ) {
		return String( value ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}
} )();
