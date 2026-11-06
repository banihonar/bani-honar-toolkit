/* Bani Honar Toolkit - Page Shield. Deterrents only: a determined person can always read page code. */
( function () {
	'use strict';

	var c = window.bhtkShield;
	if ( ! c ) {
		return;
	}

	function isField( el ) {
		if ( ! el || ! el.closest ) {
			return false;
		}
		return !! el.closest( 'input, textarea, select, [contenteditable=""], [contenteditable="true"]' );
	}

	function stop( e ) {
		e.preventDefault();
		e.stopPropagation();
		return false;
	}

	if ( c.drag ) {
		var style = document.createElement( 'style' );
		style.appendChild( document.createTextNode( 'img{-webkit-user-drag:none;user-drag:none}' ) );
		( document.head || document.documentElement ).appendChild( style );

		document.addEventListener( 'dragstart', function ( e ) {
			if ( e.target && e.target.tagName === 'IMG' ) {
				stop( e );
			}
		}, true );
	}

	if ( c.menu ) {
		document.addEventListener( 'contextmenu', function ( e ) {
			if ( ! isField( e.target ) ) {
				stop( e );
			}
		}, true );
	}

	if ( c.keys ) {
		document.addEventListener( 'keydown', function ( e ) {
			var code = e.code || '';
			var key = ( e.key || '' ).toLowerCase();
			var ctrl = e.ctrlKey || e.metaKey;
			var pressed = function ( letter ) {
				return code === 'Key' + letter.toUpperCase() || key === letter;
			};

			if ( e.keyCode === 123 || key === 'f12' ) {
				return stop( e );
			}
			if ( ctrl && ( pressed( 'u' ) || pressed( 's' ) ) ) {
				return stop( e );
			}
			if ( ctrl && e.shiftKey && ( pressed( 'i' ) || pressed( 'j' ) || pressed( 'c' ) || pressed( 'k' ) ) ) {
				return stop( e );
			}
			// macOS: Command + Option + I / J / U / C.
			if ( e.metaKey && e.altKey && ( pressed( 'i' ) || pressed( 'j' ) || pressed( 'u' ) || pressed( 'c' ) ) ) {
				return stop( e );
			}
		}, true );
	}
}() );
