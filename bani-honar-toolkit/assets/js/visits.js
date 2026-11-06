/* Bani Honar Toolkit - Visitor Stats. Sends one small request per page opened. No cookies are set. */
( function () {
	'use strict';

	var cfg = window.bhtkVisits;
	if ( ! cfg || ! cfg.url ) {
		return;
	}

	var nav = window.navigator || {};

	// Robots, automation and (when the owner chose so) visitors who ask not to be tracked.
	try {
		if ( nav.webdriver ) {
			return;
		}
		if ( cfg.dnt && ( nav.doNotTrack === '1' || window.doNotTrack === '1' ) ) {
			return;
		}
		if ( /bot|crawl|spider|headless|lighthouse|pagespeed|gtmetrix|prerender|phantom/i.test( nav.userAgent || '' ) ) {
			return;
		}
	} catch ( e ) {
		return;
	}

	function rand( len ) {
		var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
		var out = '';
		var i;
		var buf;
		try {
			if ( window.crypto && window.crypto.getRandomValues ) {
				buf = new Uint8Array( len );
				window.crypto.getRandomValues( buf );
				for ( i = 0; i < len; i++ ) {
					out += chars.charAt( buf[ i ] % chars.length );
				}
				return out;
			}
		} catch ( e ) {
			out = '';
		}
		for ( i = 0; i < len; i++ ) {
			out += chars.charAt( Math.floor( Math.random() * chars.length ) );
		}
		return out;
	}

	function encode( data ) {
		var parts = [];
		var key;
		for ( key in data ) {
			if ( Object.prototype.hasOwnProperty.call( data, key ) && data[ key ] !== '' && data[ key ] !== null && typeof data[ key ] !== 'undefined' ) {
				parts.push( encodeURIComponent( key ) + '=' + encodeURIComponent( data[ key ] ) );
			}
		}
		return parts.join( '&' );
	}

	function send( data ) {
		var body = encode( data );
		try {
			if ( nav.sendBeacon ) {
				var blob = new Blob( [ body ], { type: 'application/x-www-form-urlencoded' } );
				if ( nav.sendBeacon( cfg.url + '?action=' + encodeURIComponent( cfg.action ), blob ) ) {
					return;
				}
			}
		} catch ( e ) {
			// Fall through to a normal request.
		}
		try {
			var xhr = new XMLHttpRequest();
			xhr.open( 'POST', cfg.url + '?action=' + encodeURIComponent( cfg.action ), true );
			xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
			xhr.send( body );
		} catch ( e2 ) {
			// Counting must never break the page.
		}
	}

	// A browser session lives in one tab. It ends after 30 quiet minutes.
	var sid = '';
	var isNew = '1';
	var now = new Date().getTime();
	var path = location.pathname || '/';
	try {
		var store = window.sessionStorage;
		var last = parseInt( store.getItem( 'bhtk_t' ) || '0', 10 );
		var saved = store.getItem( 'bhtk_sid' ) || '';
		if ( /^[a-z0-9]{8,24}$/.test( saved ) && last && now - last < 30 * 60 * 1000 ) {
			sid = saved;
			isNew = '0';
		}
		if ( sid === '' ) {
			sid = rand( 16 );
			store.setItem( 'bhtk_sid', sid );
		}
		// The same page twice within two seconds is one view (a double-loaded script).
		if ( store.getItem( 'bhtk_p' ) === path && last && now - last < 2000 ) {
			return;
		}
		store.setItem( 'bhtk_t', String( now ) );
		store.setItem( 'bhtk_p', path );
	} catch ( e3 ) {
		if ( sid === '' ) {
			sid = rand( 16 );
		}
	}

	function collect( done ) {
		var scr = window.screen || {};
		var data = {
			p: path,
			q: ( location.search || '' ).substring( 0, 400 ),
			t: ( document.title || '' ).substring( 0, 190 ),
			r: document.referrer || '',
			sid: sid,
			n: isNew,
			sw: scr.width || '',
			sh: scr.height || '',
			dpr: window.devicePixelRatio || '',
			l: nav.language || '',
			tp: nav.maxTouchPoints || 0
		};
		try {
			data.tz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
		} catch ( e ) {
			data.tz = '';
		}

		var finished = false;
		function finish() {
			if ( ! finished ) {
				finished = true;
				done( data );
			}
		}

		// Chrome hides the phone model and the real system version in the User-Agent; it hands them over here.
		var uad = nav.userAgentData;
		if ( uad && typeof uad.getHighEntropyValues === 'function' ) {
			data.hmob = uad.mobile ? '1' : '0';
			setTimeout( finish, 700 );
			try {
				uad.getHighEntropyValues( [ 'model', 'platformVersion', 'platform' ] ).then( function ( v ) {
					data.hm = v.model || '';
					data.hp = v.platform || uad.platform || '';
					data.hv = v.platformVersion || '';
					finish();
				}, finish );
			} catch ( e4 ) {
				finish();
			}
		} else {
			finish();
		}
	}

	function run() {
		collect( send );
	}

	// A page the browser prepared in the background counts only when it is really opened.
	if ( document.prerendering ) {
		document.addEventListener( 'prerenderingchange', run, { once: true } );
	} else if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
}() );
