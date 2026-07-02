/**
 * Epiktetos — Article Voiceover player.
 *
 * Progressive enhancement: the markup ships a native <audio controls> element.
 * This script builds a calm custom UI (play/pause, timeline, times, mute,
 * speed) and only then hides the native controls, so if the script fails the
 * native player keeps working. No external libraries. No autoplay.
 */
( function () {
	'use strict';

	var L = window.EpiktetosVoiceoverL10n || {};
	var SPEEDS = [ 1, 1.25, 1.5, 2 ];

	var ICON_PLAY  = '<svg viewBox="0 0 16 16" width="15" height="15" aria-hidden="true" focusable="false"><path d="M4 2.8v10.4c0 .5.6.8 1 .5l8-5.2c.4-.3.4-.8 0-1L5 2.3c-.4-.3-1 0-1 .5z" fill="currentColor"/></svg>';
	var ICON_PAUSE = '<svg viewBox="0 0 16 16" width="15" height="15" aria-hidden="true" focusable="false"><rect x="4" y="3" width="3" height="10" rx="0.5" fill="currentColor"/><rect x="9" y="3" width="3" height="10" rx="0.5" fill="currentColor"/></svg>';
	var ICON_VOL   = '<svg viewBox="0 0 18 18" width="16" height="16" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h2l3.5-3v10L6 11H4z"/><path d="M12 6.5a3 3 0 0 1 0 5"/></svg>';
	var ICON_MUTE  = '<svg viewBox="0 0 18 18" width="16" height="16" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h2l3.5-3v10L6 11H4z"/><path d="M12 7l3 4M15 7l-3 4"/></svg>';

	function t( key, fallback ) { return L[ key ] || fallback; }

	function fmt( sec ) {
		if ( ! isFinite( sec ) || sec < 0 ) { sec = 0; }
		var m = Math.floor( sec / 60 );
		var s = Math.floor( sec % 60 );
		return m + ':' + ( s < 10 ? '0' : '' ) + s;
	}

	function speedLabel( r ) { return String( r ) + '×'; } // e.g. 1.25×

	function btn( cls, label ) {
		var b = document.createElement( 'button' );
		b.type = 'button';
		b.className = cls;
		b.setAttribute( 'aria-label', label );
		return b;
	}

	function timeSpan( cls, label ) {
		var s = document.createElement( 'span' );
		s.className = cls;
		s.textContent = '0:00';
		s.setAttribute( 'aria-label', label );
		return s;
	}

	function enhance( root ) {
		var audio = root.querySelector( '.ts-voiceover__audio' );
		if ( ! audio ) { return; }

		var speedIndex = 0;

		var controls = document.createElement( 'div' );
		controls.className = 'ts-voiceover__controls';

		var play = btn( 'ts-voiceover__btn ts-voiceover__play', t( 'play', 'Play' ) );
		play.setAttribute( 'aria-pressed', 'false' );
		play.innerHTML = ICON_PLAY;

		var current = timeSpan( 'ts-voiceover__time ts-voiceover__time--current', t( 'current', 'Current time' ) );

		var range = document.createElement( 'input' );
		range.type = 'range';
		range.min = '0';
		range.max = '100';
		range.value = '0';
		range.step = '1';
		range.className = 'ts-voiceover__timeline';
		range.setAttribute( 'aria-label', t( 'seek', 'Seek' ) );
		range.setAttribute( 'aria-valuetext', '0:00' );

		var duration = timeSpan( 'ts-voiceover__time ts-voiceover__time--duration', t( 'duration', 'Duration' ) );

		var mute = btn( 'ts-voiceover__btn ts-voiceover__mute', t( 'mute', 'Mute' ) );
		mute.setAttribute( 'aria-pressed', 'false' );
		mute.innerHTML = ICON_VOL;

		var speed = btn( 'ts-voiceover__btn ts-voiceover__speed', t( 'speed', 'Playback speed' ) + ': ' + speedLabel( 1 ) );
		speed.textContent = speedLabel( 1 );

		controls.appendChild( play );
		controls.appendChild( current );
		controls.appendChild( range );
		controls.appendChild( duration );
		controls.appendChild( mute );
		controls.appendChild( speed );

		var seeking = false;

		function fill() {
			var max = parseFloat( range.max ) || 1;
			var pct = ( parseFloat( range.value ) / max ) * 100;
			range.style.setProperty( '--ts-voiceover-fill', pct + '%' );
		}

		function setPlayUI( playing ) {
			play.setAttribute( 'aria-pressed', playing ? 'true' : 'false' );
			play.setAttribute( 'aria-label', playing ? t( 'pause', 'Pause' ) : t( 'play', 'Play' ) );
			play.innerHTML = playing ? ICON_PAUSE : ICON_PLAY;
		}

		play.addEventListener( 'click', function () {
			if ( audio.paused ) { audio.play(); } else { audio.pause(); }
		} );
		audio.addEventListener( 'play', function () { setPlayUI( true ); } );
		audio.addEventListener( 'pause', function () { setPlayUI( false ); } );
		audio.addEventListener( 'ended', function () { setPlayUI( false ); } );

		audio.addEventListener( 'loadedmetadata', function () {
			duration.textContent = fmt( audio.duration );
			range.max = String( Math.max( 1, Math.floor( audio.duration || 0 ) ) );
			fill();
		} );

		audio.addEventListener( 'timeupdate', function () {
			if ( seeking ) { return; }
			current.textContent = fmt( audio.currentTime );
			range.value = String( audio.currentTime );
			range.setAttribute( 'aria-valuetext', fmt( audio.currentTime ) );
			fill();
		} );

		range.addEventListener( 'input', function () {
			seeking = true;
			var v = parseFloat( range.value );
			current.textContent = fmt( v );
			range.setAttribute( 'aria-valuetext', fmt( v ) );
			fill();
		} );
		range.addEventListener( 'change', function () {
			audio.currentTime = parseFloat( range.value );
			seeking = false;
		} );

		mute.addEventListener( 'click', function () {
			audio.muted = ! audio.muted;
			mute.setAttribute( 'aria-pressed', audio.muted ? 'true' : 'false' );
			mute.setAttribute( 'aria-label', audio.muted ? t( 'unmute', 'Unmute' ) : t( 'mute', 'Mute' ) );
			mute.innerHTML = audio.muted ? ICON_MUTE : ICON_VOL;
		} );

		speed.addEventListener( 'click', function () {
			speedIndex = ( speedIndex + 1 ) % SPEEDS.length;
			var r = SPEEDS[ speedIndex ];
			audio.playbackRate = r;
			speed.textContent = speedLabel( r );
			speed.setAttribute( 'aria-label', t( 'speed', 'Playback speed' ) + ': ' + speedLabel( r ) );
		} );

		// Enhance last: append the UI, then drop the native controls so a mid-way
		// script failure still leaves a usable native player.
		root.appendChild( controls );
		audio.removeAttribute( 'controls' );
		root.classList.add( 'is-enhanced' );

		// If metadata is already available (cached), sync now.
		if ( audio.readyState >= 1 ) {
			duration.textContent = fmt( audio.duration );
			range.max = String( Math.max( 1, Math.floor( audio.duration || 0 ) ) );
			fill();
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var players = document.querySelectorAll( '[data-ts-voiceover]' );
		Array.prototype.forEach.call( players, enhance );
	} );
} )();
