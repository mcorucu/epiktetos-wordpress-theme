/**
 * Epiktetos — single post reading aids.
 *  1. Reading progress bar (scroll % of the article).
 *  2. TOC active-section highlight + smooth scroll.
 *  3. Copy link button ("Copied").
 * Vanilla JS, no dependencies. Reduced-motion aware.
 */
( function () {
	'use strict';

	var reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	var app = window.EpiktetosSingle || {};
	var strings = app.strings || {};

	document.addEventListener( 'DOMContentLoaded', function () {
		initProgress();
		initToc();
		initCopy();
		initNativeShare();
		initDiscussion();
		initBackTop();
	} );

	function text( key, fallback ) {
		return strings[ key ] || fallback;
	}

	/* ---- Reading progress ---- */
	function initProgress() {
		var bar     = document.querySelector( '[data-ts-progress]' );
		var article = document.querySelector( '.ts-article' );
		if ( ! bar || ! article ) { return; }

		var ticking = false;
		function update() {
			var rect   = article.getBoundingClientRect();
			var total  = rect.height - window.innerHeight;
			var passed = Math.min( Math.max( -rect.top, 0 ), Math.max( total, 0 ) );
			var pct    = total > 0 ? passed / total : 0;
			bar.style.transform = 'scaleY(' + pct.toFixed( 4 ) + ')';
			ticking = false;
		}
		update();
		window.addEventListener( 'scroll', function () {
			if ( ! ticking ) { window.requestAnimationFrame( update ); ticking = true; }
		}, { passive: true } );
		window.addEventListener( 'resize', update, { passive: true } );
	}

	/* ---- TOC ---- */
	function initToc() {
		var toc = document.querySelector( '.ts-toc' );
		if ( ! toc ) { return; }
		var links = Array.prototype.slice.call( toc.querySelectorAll( 'a[href^="#"]' ) );
		if ( ! links.length ) { return; }

		var map = {};
		var targets = [];
		links.forEach( function ( a ) {
			var id = decodeURIComponent( a.getAttribute( 'href' ).slice( 1 ) );
			var el = document.getElementById( id );
			if ( el ) { map[ id ] = a; targets.push( el ); }
		} );

		// Smooth scroll (unless reduced motion).
		links.forEach( function ( a ) {
			a.addEventListener( 'click', function ( e ) {
				var id = decodeURIComponent( a.getAttribute( 'href' ).slice( 1 ) );
				var el = document.getElementById( id );
				if ( ! el ) { return; }
				e.preventDefault();
				el.scrollIntoView( { behavior: reduce.matches ? 'auto' : 'smooth', block: 'start' } );
				if ( history.replaceState ) { history.replaceState( null, '', '#' + id ); }
			} );
		} );

		// Active highlight.
		if ( 'IntersectionObserver' in window ) {
			var obs = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						links.forEach( function ( l ) { l.classList.remove( 'is-active' ); } );
						var a = map[ entry.target.id ];
						if ( a ) { a.classList.add( 'is-active' ); }
					}
				} );
			}, { rootMargin: '-20% 0px -70% 0px' } );
			targets.forEach( function ( t ) { obs.observe( t ); } );
		}
	}

	/* ---- Copy link ---- */
	function initCopy() {
		var btn = document.querySelector( '[data-ts-copy]' );
		if ( ! btn ) { return; }
		var label = btn.querySelector( '.ts-tool__label' );
		var status = document.querySelector( '[data-ts-copy-status]' );
		var original = btn.getAttribute( 'data-copy-label' ) || ( label ? label.textContent : '' );
		var copied = btn.getAttribute( 'data-copied-label' ) || text( 'linkCopied', 'Link copied' );

		btn.addEventListener( 'click', function () {
			var url = btn.getAttribute( 'data-url' ) || window.location.href;
			var done = function () {
				btn.classList.add( 'is-copied' );
				btn.setAttribute( 'aria-label', copied );
				if ( label ) { label.textContent = copied; }
				if ( status ) { status.textContent = copied; }
				window.setTimeout( function () {
					btn.classList.remove( 'is-copied' );
					btn.setAttribute( 'aria-label', original );
					if ( label ) { label.textContent = original; }
					if ( status ) { status.textContent = ''; }
				}, 2200 );
			};
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( url ).then( done ).catch( fallback );
			} else { fallback(); }
			function fallback() {
				try {
					var ta = document.createElement( 'textarea' );
					ta.value = url; ta.setAttribute( 'readonly', '' );
					ta.style.position = 'absolute'; ta.style.left = '-9999px';
					document.body.appendChild( ta ); ta.select();
					document.execCommand( 'copy' );
					document.body.removeChild( ta );
					done();
				} catch ( e ) {}
			}
		} );
	}

	/* ---- Native share ---- */
	function initNativeShare() {
		var btn = document.querySelector( '[data-ts-native-share]' );
		if ( ! btn || ! navigator.share ) { return; }

		btn.hidden = false;
		btn.addEventListener( 'click', function () {
			navigator.share( {
				title: btn.getAttribute( 'data-share-title' ) || document.title,
				text: btn.getAttribute( 'data-share-text' ) || '',
				url: btn.getAttribute( 'data-share-url' ) || window.location.href
			} ).catch( function () {} );
		} );
	}

	/* ---- Discussion form ---- */
	function initDiscussion() {
		var textarea = document.querySelector( '[data-ts-comment-textarea]' );
		if ( ! textarea ) { return; }

		var counter = document.querySelector( '[data-ts-comment-counter]' );
		var status = document.querySelector( '[data-ts-comment-status]' );
		var form = textarea.closest( 'form' );
		var key = textarea.getAttribute( 'data-ts-comment-storage' );

		if ( key && window.localStorage && ( window.location.hash.indexOf( '#comment-' ) === 0 || window.location.search.indexOf( 'unapproved=' ) !== -1 ) ) {
			try { localStorage.removeItem( key ); } catch ( e ) {}
		}

		if ( key && window.localStorage && ! textarea.value ) {
			try { textarea.value = localStorage.getItem( key ) || ''; } catch ( e ) {}
		}

		function update() {
			textarea.style.height = 'auto';
			textarea.style.height = textarea.scrollHeight + 'px';
			if ( counter ) {
				counter.textContent = String( textarea.value.length );
			}
			if ( key && window.localStorage ) {
				try { localStorage.setItem( key, textarea.value ); } catch ( e ) {}
			}
		}

		update();
		textarea.addEventListener( 'input', update );

		if ( form ) {
			form.addEventListener( 'submit', function () {
				if ( status ) { status.textContent = text( 'sendingResponse', 'Sending your response...' ); }
			} );
		}
	}

	/* ---- Back to top ---- */
		function initBackTop() {
			var btn = document.querySelector( '[data-ts-backtop]' );
			if ( ! btn ) { return; }
			var ticking = false;

			function update() {
				btn.hidden = window.scrollY < 640;
				ticking = false;
			}
			update();
			window.addEventListener( 'scroll', function () {
				if ( ! ticking ) {
					window.requestAnimationFrame( update );
					ticking = true;
				}
			}, { passive: true } );
			btn.addEventListener( 'click', function () {
				window.scrollTo( { top: 0, behavior: reduce.matches ? 'auto' : 'smooth' } );
		} );
	}
} )();
