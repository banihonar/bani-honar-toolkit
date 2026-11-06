/* Bani Honar Toolkit - QR code generator. Runs entirely in the browser. */
( function () {
	'use strict';

	var cfg = window.bhtkQr;
	var lib = window.qrcode;
	var root = document.getElementById( 'bhtk-qr' );
	if ( ! cfg || ! lib || ! root ) {
		return;
	}
	var t = cfg.i18n || {};

	// Persian and other non-Latin text must be encoded as UTF-8.
	if ( lib.stringToBytesFuncs && lib.stringToBytesFuncs[ 'UTF-8' ] ) {
		lib.stringToBytes = lib.stringToBytesFuncs[ 'UTF-8' ];
	}

	var $ = function ( id ) {
		return document.getElementById( id );
	};
	var linkInput = $( 'bhtk_qr_link' );
	var textInput = $( 'bhtk_qr_text' );
	var pick = $( 'bhtk_qr_pick' );
	var sizeSel = $( 'bhtk_qr_size' );
	var levelSel = $( 'bhtk_qr_level' );
	var marginInput = $( 'bhtk_qr_margin' );
	var fgInput = $( 'bhtk_qr_fg' );
	var bgInput = $( 'bhtk_qr_bg' );
	var canvas = $( 'bhtk-qr-canvas' );
	var msg = $( 'bhtk-qr-msg' );
	var btnPng = $( 'bhtk-qr-png' );
	var btnSvg = $( 'bhtk-qr-svg' );
	var btnCopy = $( 'bhtk-qr-copy' );
	var mode = cfg.mode === 'text' ? 'text' : 'link';
	var current = null; // { qr, fg, bg, margin }

	function currentValue() {
		var value;
		var added = false;
		if ( mode === 'text' ) {
			value = textInput.value;
		} else {
			value = linkInput.value.trim();
			// Without a scheme phones treat the code as plain text, so add https://.
			if ( value !== '' && ! /^[a-z][a-z0-9+.\-]*:\/\//i.test( value ) && ! /^(mailto|tel|sms|smsto|geo|wifi):/i.test( value ) ) {
				value = 'https://' + value;
				added = true;
			}
		}
		return { value: value, added: added };
	}

	function luminance( hex ) {
		var n = parseInt( hex.slice( 1 ), 16 );
		var ch = [ ( n >> 16 ) & 255, ( n >> 8 ) & 255, n & 255 ].map( function ( v ) {
			v = v / 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
		} );
		return 0.2126 * ch[ 0 ] + 0.7152 * ch[ 1 ] + 0.0722 * ch[ 2 ];
	}

	function contrastOk( fg, bg ) {
		var a = luminance( fg );
		var b = luminance( bg );
		if ( a >= b ) {
			return false; // Light code on a darker background: many scanners fail.
		}
		return ( b + 0.05 ) / ( a + 0.05 ) >= 3;
	}

	function setButtons( on ) {
		btnPng.disabled = btnSvg.disabled = btnCopy.disabled = ! on;
	}

	function draw( target, qr, px, margin, fg, bg ) {
		var count = qr.getModuleCount();
		var total = count + margin * 2;
		var cell = px / total;
		var ctx;
		var r;
		var c;
		var x0;
		var y0;
		target.width = px;
		target.height = px;
		ctx = target.getContext( '2d' );
		ctx.fillStyle = bg;
		ctx.fillRect( 0, 0, px, px );
		ctx.fillStyle = fg;
		for ( r = 0; r < count; r++ ) {
			for ( c = 0; c < count; c++ ) {
				if ( qr.isDark( r, c ) ) {
					x0 = Math.round( ( c + margin ) * cell );
					y0 = Math.round( ( r + margin ) * cell );
					ctx.fillRect( x0, y0, Math.round( ( c + margin + 1 ) * cell ) - x0, Math.round( ( r + margin + 1 ) * cell ) - y0 );
				}
			}
		}
	}

	function toSvg( qr, px, margin, fg, bg ) {
		var count = qr.getModuleCount();
		var total = count + margin * 2;
		var d = '';
		var r;
		var c;
		var start;
		for ( r = 0; r < count; r++ ) {
			c = 0;
			while ( c < count ) {
				if ( qr.isDark( r, c ) ) {
					start = c;
					while ( c < count && qr.isDark( r, c ) ) {
						c++;
					}
					d += 'M' + ( start + margin ) + ' ' + ( r + margin ) + 'h' + ( c - start ) + 'v1h-' + ( c - start ) + 'z';
				} else {
					c++;
				}
			}
		}
		return '<?xml version="1.0" encoding="UTF-8"?>\n<svg xmlns="http://www.w3.org/2000/svg" width="' + px + '" height="' + px + '" viewBox="0 0 ' + total + ' ' + total + '" shape-rendering="crispEdges">' +
			'<rect width="' + total + '" height="' + total + '" fill="' + bg + '"/>' +
			'<path fill="' + fg + '" d="' + d + '"/></svg>';
	}

	function showMessage( text, isError ) {
		msg.textContent = text || '';
		msg.className = 'bhtk-qr__msg' + ( text ? ( isError ? ' is-error' : ' is-warn' ) : '' );
	}

	function update() {
		var info = currentValue();
		var margin = Math.max( 0, Math.min( 10, parseInt( marginInput.value, 10 ) || 0 ) );
		var fg = fgInput.value;
		var bg = bgInput.value;
		var qr;
		var notes = [];

		current = null;
		setButtons( false );
		canvas.classList.add( 'is-empty' );

		if ( info.value === '' ) {
			showMessage( t.empty, false );
			return;
		}

		try {
			qr = lib( 0, levelSel.value );
			qr.addData( info.value );
			qr.make();
		} catch ( err ) {
			showMessage( t.tooLong, true );
			return;
		}

		draw( canvas, qr, parseInt( sizeSel.value, 10 ) || 512, margin, fg, bg );
		canvas.classList.remove( 'is-empty' );
		current = { qr: qr, fg: fg, bg: bg, margin: margin };
		setButtons( true );

		if ( info.added && t.httpAdded ) {
			notes.push( t.httpAdded );
		}
		if ( ! contrastOk( fg, bg ) && t.lowContrast ) {
			notes.push( t.lowContrast );
		}
		showMessage( notes.join( ' ' ), false );
	}

	function save( blob, ext ) {
		var a = document.createElement( 'a' );
		var url = URL.createObjectURL( blob );
		a.href = url;
		a.download = ( cfg.filename || 'qr-code' ) + '.' + ext;
		document.body.appendChild( a );
		a.click();
		document.body.removeChild( a );
		setTimeout( function () {
			URL.revokeObjectURL( url );
		}, 1000 );
	}

	function setMode( next ) {
		var btns = root.querySelectorAll( '[data-qr-mode]' );
		var panes = root.querySelectorAll( '[data-qr-pane]' );
		var i;
		mode = next;
		for ( i = 0; i < btns.length; i++ ) {
			btns[ i ].classList.toggle( 'is-on', btns[ i ].getAttribute( 'data-qr-mode' ) === mode );
		}
		for ( i = 0; i < panes.length; i++ ) {
			panes[ i ].hidden = panes[ i ].getAttribute( 'data-qr-pane' ) !== mode;
		}
		update();
	}

	root.addEventListener( 'click', function ( e ) {
		var b = e.target.closest ? e.target.closest( '[data-qr-mode]' ) : null;
		if ( b ) {
			setMode( b.getAttribute( 'data-qr-mode' ) );
		}
	} );

	[ linkInput, textInput, sizeSel, levelSel, marginInput, fgInput, bgInput ].forEach( function ( el ) {
		if ( el ) {
			el.addEventListener( 'input', update );
			el.addEventListener( 'change', update );
		}
	} );

	if ( pick ) {
		pick.addEventListener( 'change', function () {
			if ( pick.value ) {
				linkInput.value = pick.value;
				update();
			}
		} );
	}

	btnPng.addEventListener( 'click', function () {
		if ( current ) {
			canvas.toBlob( function ( blob ) {
				if ( blob ) {
					save( blob, 'png' );
				}
			}, 'image/png' );
		}
	} );

	btnSvg.addEventListener( 'click', function () {
		if ( current ) {
			save( new Blob( [ toSvg( current.qr, parseInt( sizeSel.value, 10 ) || 512, current.margin, current.fg, current.bg ) ], { type: 'image/svg+xml' } ), 'svg' );
		}
	} );

	btnCopy.addEventListener( 'click', function () {
		if ( ! current ) {
			return;
		}
		var old = btnCopy.getAttribute( 'data-old' ) || btnCopy.textContent;
		btnCopy.setAttribute( 'data-old', old );
		if ( ! ( navigator.clipboard && window.ClipboardItem && window.isSecureContext ) ) {
			showMessage( t.copyFailed, true );
			return;
		}
		canvas.toBlob( function ( blob ) {
			navigator.clipboard.write( [ new window.ClipboardItem( { 'image/png': blob } ) ] ).then(
				function () {
					btnCopy.textContent = t.copiedImage || 'Copied';
					setTimeout( function () {
						btnCopy.textContent = old;
					}, 1600 );
				},
				function () {
					showMessage( t.copyFailed, true );
				}
			);
		}, 'image/png' );
	} );

	// Start with the values from the address, if any.
	if ( cfg.text ) {
		if ( mode === 'text' && textInput ) {
			textInput.value = cfg.text;
		} else if ( linkInput ) {
			linkInput.value = cfg.text;
		}
	}
	update();
}() );
