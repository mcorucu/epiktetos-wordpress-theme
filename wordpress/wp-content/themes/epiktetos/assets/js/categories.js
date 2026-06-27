/**
 * Epiktetos — Category Showcase secondary sliders.
 *
 * Each category's secondary posts are grouped into pairs; this fades between
 * groups. Right-arrow control, auto-advance, pause on hover/focus/hidden tab,
 * timer reset on manual advance, reduced-motion aware. Vanilla JS, no deps.
 */
( function () {
	'use strict';

	var INTERVAL = 7000;
	var reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' );

	document.addEventListener( 'DOMContentLoaded', function () {
		var sliders = document.querySelectorAll( '[data-ts-catslider]' );
		Array.prototype.forEach.call( sliders, initSlider );
	} );

	function initSlider( root ) {
		var slides  = Array.prototype.slice.call( root.querySelectorAll( '[data-ts-catslide]' ) );
		var next    = root.querySelector( '[data-ts-catnext]' );
		var counter = root.querySelector( '[data-ts-catcounter]' );
		if ( slides.length < 2 ) { return; }

		var index = 0, timer = null, paused = false;

		function show( i ) {
			index = ( i + slides.length ) % slides.length;
			slides.forEach( function ( s, n ) {
				var on = n === index;
				s.classList.toggle( 'is-active', on );
				s.setAttribute( 'aria-hidden', on ? 'false' : 'true' );
			} );
			if ( counter ) { counter.textContent = ( index + 1 ) + ' / ' + slides.length; }
		}
		function advance() { show( index + 1 ); }
		function stop() { if ( timer ) { window.clearInterval( timer ); timer = null; } }
		function start() { stop(); if ( ! paused && ! document.hidden && ! reduce.matches ) { timer = window.setInterval( advance, INTERVAL ); } }
		function pause() { paused = true; stop(); }
		function resume() { paused = false; start(); }

		if ( next ) {
			next.addEventListener( 'click', function () { advance(); start(); } );
		}

		root.addEventListener( 'mouseenter', pause );
		root.addEventListener( 'mouseleave', resume );
		root.addEventListener( 'focusin', pause );
		root.addEventListener( 'focusout', function ( e ) { if ( ! root.contains( e.relatedTarget ) ) { resume(); } } );
		document.addEventListener( 'visibilitychange', function () { if ( document.hidden ) { stop(); } else { start(); } } );

		show( 0 );
		start();
	}
} )();
