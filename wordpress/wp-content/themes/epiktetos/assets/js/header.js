/**
 * Epiktetos — runtime interaction layer.
 *
 *  1. Theme toggle      — Light ↔ Dark, persisted, soft cross-fade, multi-button.
 *  2. Search panel      — open/close, ESC, click-outside, autofocus, "/" hotkey.
 *  3. Mobile navigation — editorial dropdown panel: ESC, outside-click, focus trap.
 *  4. Hero slider       — fade/translate, auto-rotate, pause on hover/focus/hidden.
 *  5. Header scroll      — soft shadow + hide-on-scroll-down.
 *
 * Initial theme paint is handled by an inline <head> script (no FOUC); this
 * file only reacts to user interaction.
 */
( function () {
	'use strict';

	var THEME_KEY = 'ts-theme';
	var reduceMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	var app = window.EpiktetosHeader || {};
	var strings = app.strings || {};

	document.addEventListener( 'DOMContentLoaded', function () {
		initThemeToggle();
		initSearchPanel();
		initMobileNav();
		initHeroSlider();
		initNewsletter();
		initFooterCollapse();
		initScrollStates();
	} );

	function focusables( container ) {
		return Array.prototype.slice.call(
			container.querySelectorAll( 'a[href],button:not([disabled]),input,select,textarea,[tabindex]:not([tabindex="-1"])' )
		).filter( function ( el ) { return el.offsetParent !== null; } );
	}

	function text( key, fallback ) {
		return strings[ key ] || fallback;
	}

	/* ============================================================
	   1. Theme toggle (Light ↔ Dark) — soft cross-fade
	   ============================================================ */
	function initThemeToggle() {
		var toggles = Array.prototype.slice.call( document.querySelectorAll( '[data-ts-theme-toggle]' ) );
		if ( ! toggles.length ) { return; }
		var animTimer = null;

		function current() {
			return document.documentElement.getAttribute( 'data-theme' ) === 'dark' ? 'dark' : 'light';
		}

		function sweep() {
			if ( reduceMotion.matches ) { return; }
			var root = document.documentElement;
			root.classList.add( 'ts-theme-anim' );
			window.clearTimeout( animTimer );
			animTimer = window.setTimeout( function () { root.classList.remove( 'ts-theme-anim' ); }, 350 );
		}

		function apply( mode, persist ) {
			if ( mode !== 'dark' ) { mode = 'light'; }
			if ( persist ) { sweep(); }
			var root = document.documentElement;
			root.setAttribute( 'data-theme', mode );
			root.style.colorScheme = mode;
			toggles.forEach( function ( t ) {
				t.setAttribute( 'aria-pressed', mode === 'dark' ? 'true' : 'false' );
				var l = mode === 'dark'
					? ( t.getAttribute( 'data-label-dark' ) || text( 'switchLight', 'Switch to light mode' ) )
					: ( t.getAttribute( 'data-label-light' ) || text( 'switchDark', 'Switch to dark mode' ) );
				t.setAttribute( 'aria-label', l );
			} );
			if ( persist ) {
				try { localStorage.setItem( THEME_KEY, mode ); } catch ( e ) {}
			}
		}

		apply( current(), false ); // sync ARIA to no-FOUC paint

		toggles.forEach( function ( t ) {
			t.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				apply( current() === 'dark' ? 'light' : 'dark', true );
			} );
		} );
	}

	/* ============================================================
	   2. Search panel
	   ============================================================ */
	function initSearchPanel() {
		var toggle = document.querySelector( '.ts-search-toggle' );
		var panel  = document.getElementById( 'ts-search-panel' );
		var input  = panel ? panel.querySelector( '.ts-search-input' ) : null;
		if ( ! toggle || ! panel || ! input ) { return; }

		var endpoint = panel.getAttribute( 'data-ts-search-endpoint' ) || '';
		var defaults = panel.querySelector( '[data-ts-search-default]' );
		var results  = panel.querySelector( '[data-ts-search-results]' );
		var status   = panel.querySelector( '[data-ts-search-status]' );
		var timer    = null;
		var request  = null;

		function isOpen() { return panel.classList.contains( 'is-open' ); }
		function open() {
			panel.classList.add( 'is-open' );
			panel.setAttribute( 'aria-hidden', 'false' );
			toggle.setAttribute( 'aria-expanded', 'true' );
			window.setTimeout( function () { input.focus(); input.select(); }, 60 );
		}
		function close( focusToggle ) {
			panel.classList.remove( 'is-open' );
			panel.setAttribute( 'aria-hidden', 'true' );
			toggle.setAttribute( 'aria-expanded', 'false' );
			if ( focusToggle ) { toggle.focus(); }
		}

		function setStatus( text ) {
			if ( status ) { status.textContent = text || ''; }
		}

		function resetResults() {
			window.clearTimeout( timer );
			if ( request && request.abort ) { request.abort(); }
			request = null;
			if ( results ) {
				results.hidden = true;
				results.innerHTML = '';
			}
			if ( defaults ) { defaults.hidden = false; }
			input.setAttribute( 'aria-expanded', 'false' );
			setStatus( '' );
		}

		function resultLinks() {
			return results ? Array.prototype.slice.call( results.querySelectorAll( '.ts-live-result' ) ) : [];
		}

		function focusResult( index ) {
			var links = resultLinks();
			if ( ! links.length ) { return; }
			index = Math.max( 0, Math.min( links.length - 1, index ) );
			links.forEach( function ( link, i ) {
				link.setAttribute( 'aria-selected', i === index ? 'true' : 'false' );
				link.setAttribute( 'tabindex', i === index ? '0' : '-1' );
			} );
			links[ index ].focus();
		}

		function renderEmpty() {
			if ( ! results ) { return; }
			results.innerHTML = '';
			var empty = document.createElement( 'div' );
			empty.className = 'ts-live-empty';
			var p = document.createElement( 'p' );
			p.textContent = text( 'noResults', 'No articles found.' );
			empty.appendChild( p );
			results.appendChild( empty );
			results.hidden = false;
			if ( defaults ) { defaults.hidden = true; }
			input.setAttribute( 'aria-expanded', 'true' );
			setStatus( text( 'noResults', 'No articles found.' ) );
		}

		function renderResults( items ) {
			if ( ! results ) { return; }
			results.innerHTML = '';
			if ( ! items || ! items.length ) {
				renderEmpty();
				return;
			}

			items.forEach( function ( item, index ) {
				var link = document.createElement( 'a' );
				link.className = 'ts-live-result';
				link.href = item.permalink || '#';
				link.setAttribute( 'role', 'option' );
				link.setAttribute( 'aria-selected', 'false' );
				link.setAttribute( 'tabindex', index === 0 ? '0' : '-1' );

				var meta = document.createElement( 'span' );
				meta.className = 'ts-live-result__meta';
				if ( item.category ) {
					var cat = document.createElement( 'span' );
					cat.className = 'ts-live-result__category';
					cat.textContent = item.category;
					meta.appendChild( cat );
				}
				if ( item.readingTime ) {
					var rt = document.createElement( 'span' );
					rt.className = 'ts-live-result__readtime';
					rt.textContent = item.readingTime;
					meta.appendChild( rt );
				}

				var title = document.createElement( 'span' );
				title.className = 'ts-live-result__title';
				title.textContent = item.title || '';

				var excerpt = document.createElement( 'span' );
				excerpt.className = 'ts-live-result__excerpt';
				excerpt.textContent = item.excerpt || '';

				link.appendChild( meta );
				link.appendChild( title );
				link.appendChild( excerpt );
				results.appendChild( link );
			} );

			results.hidden = false;
			if ( defaults ) { defaults.hidden = true; }
			input.setAttribute( 'aria-expanded', 'true' );
			setStatus( items.length + ' ' + ( items.length === 1 ? text( 'resultSingular', 'result found.' ) : text( 'resultPlural', 'results found.' ) ) );
		}

		function runSearch( query ) {
			query = query.trim();
			if ( query.length < 2 || ! endpoint || ! window.fetch ) {
				resetResults();
				return;
			}

			if ( request && request.abort ) { request.abort(); }
			request = window.AbortController ? new window.AbortController() : null;
			setStatus( text( 'searching', 'Searching...' ) );

			var url = endpoint + ( endpoint.indexOf( '?' ) === -1 ? '?' : '&' ) + 'search=' + encodeURIComponent( query );
			window.fetch( url, {
				credentials: 'same-origin',
				signal: request ? request.signal : undefined
			} )
				.then( function ( response ) {
					if ( ! response.ok ) { throw new Error( 'Search request failed' ); }
					return response.json();
				} )
				.then( function ( data ) {
					renderResults( Array.isArray( data ) ? data : [] );
				} )
				.catch( function ( error ) {
					if ( error && error.name === 'AbortError' ) { return; }
					renderEmpty();
				} );
		}

		toggle.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( isOpen() ) { close( true ); } else { open(); }
		} );
		input.addEventListener( 'input', function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( function () {
				runSearch( input.value || '' );
			}, 250 );
		} );
		input.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'ArrowDown' && results && ! results.hidden && resultLinks().length ) {
				e.preventDefault();
				focusResult( 0 );
			}
			if ( e.key === 'Escape' && isOpen() ) {
				e.preventDefault();
				close( true );
			}
		} );
		if ( results ) {
			results.addEventListener( 'keydown', function ( e ) {
				var links = resultLinks();
				var index = links.indexOf( document.activeElement );
				if ( e.key === 'ArrowDown' && links.length ) {
					e.preventDefault();
					focusResult( index + 1 >= links.length ? 0 : index + 1 );
				} else if ( e.key === 'ArrowUp' && links.length ) {
					e.preventDefault();
					if ( index <= 0 ) { input.focus(); }
					else { focusResult( index - 1 ); }
				} else if ( e.key === 'Escape' ) {
					e.preventDefault();
					close( true );
				}
			} );
		}
		input.addEventListener( 'focus', function () {
			if ( ( input.value || '' ).trim().length < 2 ) { resetResults(); }
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && isOpen() ) { e.preventDefault(); close( true ); return; }
			if ( e.key === '/' ) {
				var t = e.target;
				var typing = t && ( t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable );
				if ( ! typing && ! isOpen() ) { e.preventDefault(); open(); }
			}
		} );
		document.addEventListener( 'click', function ( e ) {
			if ( ! isOpen() ) { return; }
			if ( panel.contains( e.target ) || toggle.contains( e.target ) ) { return; }
			close( false );
		} );
	}

	/* ============================================================
	   3. Mobile navigation panel
	   ============================================================ */
	function initMobileNav() {
		var nav    = document.querySelector( '.ts-header__nav' );
		var toggle = nav ? nav.querySelector( '.ts-nav-toggle' ) : null;
		var panel  = document.getElementById( 'ts-nav-panel' );
		if ( ! nav || ! toggle || ! panel ) { return; }

		function isOpen() { return panel.classList.contains( 'is-open' ); }
		function open() {
			panel.classList.add( 'is-open' );
			toggle.setAttribute( 'aria-expanded', 'true' );
			toggle.setAttribute( 'aria-label', text( 'closeMenu', 'Close menu' ) );
			var f = focusables( panel )[ 0 ];
			if ( f ) { window.setTimeout( function () { f.focus(); }, 60 ); }
		}
		function close( focusToggle ) {
			panel.classList.remove( 'is-open' );
			toggle.setAttribute( 'aria-expanded', 'false' );
			toggle.setAttribute( 'aria-label', text( 'openMenu', 'Open menu' ) );
			if ( focusToggle ) { toggle.focus(); }
		}

		toggle.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( isOpen() ) { close( true ); } else { open(); }
		} );

		// Close after following a link.
		panel.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( 'a' ) ) { close( false ); }
		} );

		// ESC + focus trap.
		document.addEventListener( 'keydown', function ( e ) {
			if ( ! isOpen() ) { return; }
			if ( e.key === 'Escape' ) { e.preventDefault(); close( true ); return; }
			if ( e.key === 'Tab' ) {
				var items = [ toggle ].concat( focusables( panel ) );
				var first = items[ 0 ], last = items[ items.length - 1 ];
				if ( e.shiftKey && document.activeElement === first ) { e.preventDefault(); last.focus(); }
				else if ( ! e.shiftKey && document.activeElement === last ) { e.preventDefault(); first.focus(); }
			}
		} );

		// Outside click.
		document.addEventListener( 'click', function ( e ) {
			if ( isOpen() && ! nav.contains( e.target ) ) { close( false ); }
		} );

		// Reset when returning to desktop width.
		var mq = window.matchMedia( '(min-width: 901px)' );
		var onChange = function () { if ( mq.matches && isOpen() ) { close( false ); } };
		if ( mq.addEventListener ) { mq.addEventListener( 'change', onChange ); }
		else if ( mq.addListener ) { mq.addListener( onChange ); }
	}

	/* ============================================================
	   4. Hero slider — fade + slight vertical motion
	   ============================================================ */
	function initHeroSlider() {
		var hero = document.querySelector( '[data-ts-hero]' );
		if ( ! hero ) { return; }
		var slides = Array.prototype.slice.call( hero.querySelectorAll( '.ts-hero__slide' ) );
		var dots   = Array.prototype.slice.call( hero.querySelectorAll( '[data-ts-hero-dot]' ) );
		if ( slides.length < 2 ) { return; }

		var INTERVAL = 7000;
		var index = 0, timer = null, paused = false;

		function show( i ) {
			index = ( i + slides.length ) % slides.length;
			slides.forEach( function ( s, n ) {
				var on = n === index;
				s.classList.toggle( 'is-active', on );
				s.setAttribute( 'aria-hidden', on ? 'false' : 'true' );
			} );
			dots.forEach( function ( d, n ) {
				var on = n === index;
				d.classList.toggle( 'is-active', on );
				d.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				d.setAttribute( 'aria-current', on ? 'true' : 'false' );
				d.setAttribute( 'tabindex', on ? '0' : '-1' );
			} );
		}
		function next() { show( index + 1 ); }
		function stop() { if ( timer ) { window.clearInterval( timer ); timer = null; } }
		function start() { stop(); if ( ! paused && ! document.hidden && ! reduceMotion.matches ) { timer = window.setInterval( next, INTERVAL ); } }
		function pause() { paused = true; stop(); }
		function resume() { paused = false; start(); }

		// Dots.
		dots.forEach( function ( d, n ) {
			d.addEventListener( 'click', function () { show( n ); start(); } );
			d.addEventListener( 'keydown', function ( e ) {
				var t = null;
				if ( e.key === 'ArrowRight' || e.key === 'ArrowDown' ) { t = ( n + 1 ) % dots.length; }
				else if ( e.key === 'ArrowLeft' || e.key === 'ArrowUp' ) { t = ( n - 1 + dots.length ) % dots.length; }
				else if ( e.key === 'Home' ) { t = 0; }
				else if ( e.key === 'End' ) { t = dots.length - 1; }
				if ( t !== null ) { e.preventDefault(); show( t ); dots[ t ].focus(); start(); }
			} );
		} );

		// Pause on hover / focus / tab-hidden.
		hero.addEventListener( 'mouseenter', pause );
		hero.addEventListener( 'mouseleave', resume );
		hero.addEventListener( 'focusin', pause );
		hero.addEventListener( 'focusout', function ( e ) { if ( ! hero.contains( e.relatedTarget ) ) { resume(); } } );
		document.addEventListener( 'visibilitychange', function () { if ( document.hidden ) { stop(); } else { start(); } } );

		// Touch swipe (horizontal), kept subtle.
		var x0 = null;
		hero.addEventListener( 'touchstart', function ( e ) { x0 = e.touches[ 0 ].clientX; }, { passive: true } );
		hero.addEventListener( 'touchend', function ( e ) {
			if ( x0 === null ) { return; }
			var dx = e.changedTouches[ 0 ].clientX - x0;
			if ( Math.abs( dx ) > 50 ) { show( index + ( dx < 0 ? 1 : -1 ) ); start(); }
			x0 = null;
		}, { passive: true } );

		show( 0 );
		start();
	}

	/* ============================================================
	   Newsletter form shell — non-functional; shows a quiet inline note
	   ============================================================ */
	function initNewsletter() {
		var forms = document.querySelectorAll( '.ts-news' );
		Array.prototype.forEach.call( forms, function ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var note = form.parentNode.querySelector( '.ts-news__note' );
				if ( note ) {
					note.textContent = text( 'newsletterOffline', 'Subscribe via RSS while email delivery is offline.' );
					note.classList.add( 'is-active' );
				}
			} );
		} );
	}

	/* ============================================================
	   Footer collapsible menus (mobile only)
	   ============================================================ */
	function initFooterCollapse() {
		var cols = Array.prototype.slice.call( document.querySelectorAll( '.ts-footer__col--collapsible' ) );
		if ( ! cols.length ) { return; }
		var mq = window.matchMedia( '(max-width: 600px)' );

		var items = cols.map( function ( col ) {
			var btn = col.querySelector( '.ts-footer__toggle' );
			return { col: col, btn: btn };
		} ).filter( function ( i ) { return i.btn; } );

		function setCollapsed( item, collapsed ) {
			item.col.classList.toggle( 'is-collapsed', collapsed );
			item.btn.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
		}

		// Apply the right initial state for the current viewport.
		function sync() {
			var mobile = mq.matches;
			items.forEach( function ( item ) {
				// On desktop always expanded; on mobile start collapsed.
				setCollapsed( item, mobile );
			} );
		}

		items.forEach( function ( item ) {
			item.btn.addEventListener( 'click', function () {
				// Only acts as a toggle on mobile; on desktop the list is
				// always shown by CSS so a click is a harmless no-op.
				if ( ! mq.matches ) { return; }
				setCollapsed( item, item.col.classList.contains( 'is-collapsed' ) ? false : true );
			} );
		} );

		sync();
		var onChange = function () { sync(); };
		if ( mq.addEventListener ) { mq.addEventListener( 'change', onChange ); }
		else if ( mq.addListener ) { mq.addListener( onChange ); }
	}

	/* ============================================================
	   5. Header scroll states
	   ============================================================ */
	function initScrollStates() {
		var header = document.querySelector( '.ts-header' );
		if ( ! header ) { return; }

		// Only a sticky header needs a scroll state; a static header just flows.
		if ( ! document.body.classList.contains( 'epiktetos-header-sticky' ) ) { return; }

		var ticking = false;

		function tick() {
			// A sticky header stays visible at all times — no hide-on-scroll.
			// This only drives the scroll shadow and the transparent → solid
			// background transition once the page has scrolled.
			header.classList.toggle( 'is-scrolled', ( window.scrollY || 0 ) > 4 );
			ticking = false;
		}

		tick();
		window.addEventListener( 'scroll', function () {
			if ( ! ticking ) { window.requestAnimationFrame( tick ); ticking = true; }
		}, { passive: true } );
	}
} )();
