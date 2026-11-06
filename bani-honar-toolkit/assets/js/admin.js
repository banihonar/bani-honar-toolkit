/* Bani Honar Toolkit - admin behaviour. */
( function () {
	'use strict';

	var i18n = window.bhtkAdmin || {};

	function copyText( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text );
		}
		return new Promise( function ( resolve, reject ) {
			var ta = document.createElement( 'textarea' );
			ta.value = text;
			ta.setAttribute( 'readonly', '' );
			ta.style.cssText = 'position:fixed;opacity:0;top:0;left:0';
			document.body.appendChild( ta );
			ta.select();
			var ok = false;
			try {
				ok = document.execCommand( 'copy' );
			} catch ( e ) {
				ok = false;
			}
			document.body.removeChild( ta );
			if ( ok ) {
				resolve();
			} else {
				reject();
			}
		} );
	}

	function flash( btn, text ) {
		var old = btn.getAttribute( 'data-label-old' ) || btn.textContent;
		btn.setAttribute( 'data-label-old', old );
		btn.textContent = text;
		btn.classList.add( 'is-done' );
		clearTimeout( btn.bhtkTimer );
		btn.bhtkTimer = setTimeout( function () {
			btn.textContent = old;
			btn.classList.remove( 'is-done' );
		}, 1600 );
	}

	function syncLength() {
		var input = document.getElementById( 'bhtk_length' );
		var custom = document.getElementById( 'bhtk_custom' );
		if ( ! input ) {
			return;
		}
		var form = input.closest( '.bhtk-form' );
		var buttons = document.querySelectorAll( '.bhtk-lens__btn' );
		var i;
		for ( i = 0; i < buttons.length; i++ ) {
			buttons[ i ].classList.toggle( 'is-on', buttons[ i ].getAttribute( 'data-len' ) === String( parseInt( input.value, 10 ) ) );
		}
		if ( form && custom ) {
			form.classList.toggle( 'is-custom', custom.value.trim() !== '' );
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var t = e.target;
		if ( ! t || ! t.closest ) {
			return;
		}

		var copyBtn = t.closest( '[data-copy]' );
		if ( copyBtn ) {
			e.preventDefault();
			var value = copyBtn.getAttribute( 'data-copy' );
			copyText( value ).then(
				function () {
					flash( copyBtn, i18n.copied || 'Copied' );
				},
				function () {
					window.prompt( i18n.copyPrompt || 'Copy this link:', value );
				}
			);
			return;
		}

		var lenBtn = t.closest( '[data-len]' );
		if ( lenBtn ) {
			var input = document.getElementById( 'bhtk_length' );
			if ( input ) {
				input.value = lenBtn.getAttribute( 'data-len' );
				syncLength();
			}
			return;
		}

		var toggle = t.closest( '[data-toggle-edit]' );
		if ( toggle ) {
			var row = document.getElementById( 'bhtk-edit-' + toggle.getAttribute( 'data-toggle-edit' ) );
			if ( row ) {
				row.hidden = ! row.hidden;
			}
		}
	} );

	// On/off switches on the module tabs are sent as soon as they are flipped.
	document.addEventListener( 'change', function ( e ) {
		var t = e.target;
		if ( t && t.getAttribute && t.getAttribute( 'data-autosubmit' ) && t.form ) {
			t.disabled = false;
			t.form.classList.add( 'is-busy' );
			t.form.submit();
		}
	} );

	document.addEventListener( 'submit', function ( e ) {
		var form = e.target;
		if ( form && form.getAttribute && form.getAttribute( 'data-confirm' ) ) {
			if ( ! window.confirm( form.getAttribute( 'data-confirm' ) ) ) {
				e.preventDefault();
			}
		}
	} );

	var lengthInput = document.getElementById( 'bhtk_length' );
	var customInput = document.getElementById( 'bhtk_custom' );
	if ( lengthInput ) {
		lengthInput.addEventListener( 'input', syncLength );
	}
	if ( customInput ) {
		customInput.addEventListener( 'input', syncLength );
	}
	syncLength();

	// Live preview of the chosen profile picture.
	var file = document.getElementById( 'bhtk_avatar' );
	var preview = document.getElementById( 'bhtk-avatar-preview' );
	if ( file && preview ) {
		file.addEventListener( 'change', function () {
			var chosen = file.files && file.files[ 0 ];
			if ( ! chosen ) {
				preview.src = preview.getAttribute( 'data-original' );
				return;
			}
			if ( chosen.size > 2 * 1024 * 1024 ) {
				window.alert( i18n.avatarTooBig || 'The image is larger than 2 MB.' );
				file.value = '';
				preview.src = preview.getAttribute( 'data-original' );
				return;
			}
			var reader = new FileReader();
			reader.onload = function ( ev ) {
				preview.src = ev.target.result;
			};
			reader.readAsDataURL( chosen );
		} );
	}
}() );
