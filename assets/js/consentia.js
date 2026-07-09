/**
 * Consentia — banner de consentimiento.
 * Integra la WP Consent API (wp_set_consent) y, opcionalmente, Google
 * Consent Mode v2 directo (gtag consent update). Vanilla JS, sin dependencias.
 */
( function () {
	'use strict';

	var D = window.ConsentiaData || {};
	var CATS = D.categories || {};
	var GCM = D.gcmMap || {};

	document.addEventListener( 'DOMContentLoaded', init );

	function init() {
		var root = document.getElementById( 'consentia' );
		var reopen = document.querySelector( '.consentia__reopen' );
		if ( ! root ) {
			return;
		}

		bindActions( root, reopen );

		var stored = readStored();
		if ( stored && stored.v === D.version ) {
			// Ya hay decisión válida: reafírmala en cada carga y oculta el banner.
			applyConsent( stored.c, false );
			showReopen( reopen );
		} else {
			// Sin decisión (o versión nueva): estado por defecto + mostrar banner.
			applyDefault();
			openBanner( root );
		}
	}

	/* -------------------------------------------------- Acciones -------- */
	function bindActions( root, reopen ) {
		document.addEventListener( 'click', function ( e ) {
			var el = e.target.closest( '[data-consentia-action]' );
			if ( ! el ) {
				return;
			}
			var action = el.getAttribute( 'data-consentia-action' );

			if ( 'accept' === action ) {
				choose( allMap( 'allow' ), root, reopen );
			} else if ( 'reject' === action ) {
				choose( allMap( 'deny' ), root, reopen );
			} else if ( 'save' === action ) {
				choose( readToggles( root ), root, reopen );
			} else if ( 'prefs' === action ) {
				openModal( root );
			} else if ( 'close-prefs' === action ) {
				closeModal( root );
			}
		} );
	}

	function choose( map, root, reopen ) {
		applyConsent( map, true );
		store( map );
		logConsent( map );
		closeModal( root );
		closeBanner( root );
		showReopen( reopen );
	}

	/* --------------------------------------- Estado por defecto --------- */
	function applyDefault() {
		// functional siempre concedido; el resto según el modelo.
		var optout = ( 'optout' === D.consentType );
		var map = {};
		Object.keys( CATS ).forEach( function ( cat ) {
			map[ cat ] = ( CATS[ cat ].required || optout ) ? 'allow' : 'deny';
		} );
		applyConsent( map, false );
	}

	/* ------------------------------- Aplicar a las APIs ----------------- */
	function applyConsent( map, isUpdate ) {
		// functional siempre allow.
		map.functional = 'allow';

		// 1) WP Consent API (Site Kit lee esto y mapea a Consent Mode).
		if ( typeof window.wp_set_consent === 'function' ) {
			Object.keys( map ).forEach( function ( cat ) {
				try {
					window.wp_set_consent( cat, map[ cat ] );
				} catch ( err ) {}
			} );
		}

		// 2) Consent Mode v2 directo (solo si está activado en ajustes).
		if ( D.emitConsentMode && typeof window.gtag === 'function' ) {
			var signals = {};
			Object.keys( GCM ).forEach( function ( cat ) {
				var granted = ( 'allow' === map[ cat ] );
				GCM[ cat ].forEach( function ( sig ) {
					signals[ sig ] = granted ? 'granted' : 'denied';
				} );
			} );
			if ( Object.keys( signals ).length ) {
				window.gtag( 'consent', 'update', signals );
			}
		}
	}

	/* --------------------------------------------- Utilidades UI -------- */
	function allMap( value ) {
		var map = {};
		Object.keys( CATS ).forEach( function ( cat ) {
			map[ cat ] = CATS[ cat ].required ? 'allow' : value;
		} );
		return map;
	}

	function readToggles( root ) {
		var map = {};
		Object.keys( CATS ).forEach( function ( cat ) {
			if ( CATS[ cat ].required ) {
				map[ cat ] = 'allow';
				return;
			}
			var input = root.querySelector( '[data-consentia-cat="' + cat + '"]' );
			map[ cat ] = ( input && input.checked ) ? 'allow' : 'deny';
		} );
		return map;
	}

	function syncToggles( root, map ) {
		Object.keys( map ).forEach( function ( cat ) {
			var input = root.querySelector( '[data-consentia-cat="' + cat + '"]' );
			if ( input && ! input.disabled ) {
				input.checked = ( 'allow' === map[ cat ] );
			}
		} );
	}

	function openBanner( root ) {
		root.hidden = false;
		root.classList.add( 'is-open' );
	}
	function closeBanner( root ) {
		root.classList.remove( 'is-open' );
		root.hidden = true;
	}
	function openModal( root ) {
		var modal = root.querySelector( '[data-consentia-modal]' );
		var stored = readStored();
		syncToggles( root, ( stored && stored.c ) ? stored.c : allMap( 'deny' ) );
		root.hidden = false;
		if ( modal ) {
			modal.hidden = false;
		}
		document.body.classList.add( 'consentia-modal-open' );
	}
	function closeModal( root ) {
		var modal = root.querySelector( '[data-consentia-modal]' );
		if ( modal ) {
			modal.hidden = true;
		}
		document.body.classList.remove( 'consentia-modal-open' );
	}
	function showReopen( reopen ) {
		if ( reopen ) {
			reopen.hidden = false;
		}
	}

	/* ------------------------------------------ Cookie + registro ------- */
	function store( map ) {
		var payload = { v: D.version, c: map, t: Math.round( Date.now() / 1000 ) };
		setCookie( D.cookieName || 'consentia_status', JSON.stringify( payload ), D.cookieDays || 180 );
	}

	function readStored() {
		var raw = getCookie( D.cookieName || 'consentia_status' );
		if ( ! raw ) {
			return null;
		}
		try {
			var obj = JSON.parse( decodeURIComponent( raw ) );
			return ( obj && obj.c ) ? obj : null;
		} catch ( e ) {
			return null;
		}
	}

	function logConsent( map ) {
		if ( ! D.logEnabled || ! D.ajaxUrl ) {
			return;
		}
		var body = new FormData();
		body.append( 'action', 'consentia_log' );
		body.append( 'nonce', D.nonce || '' );
		body.append( 'version', D.version );
		body.append( 'consent', JSON.stringify( map ) );
		fetch( D.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } ).catch( function () {} );
	}

	function setCookie( name, value, days ) {
		var d = new Date();
		d.setTime( d.getTime() + days * 864e5 );
		var secure = ( 'https:' === location.protocol ) ? '; Secure' : '';
		document.cookie = name + '=' + encodeURIComponent( value ) + '; expires=' + d.toUTCString() + '; path=/; SameSite=Lax' + secure;
	}

	function getCookie( name ) {
		var m = document.cookie.match( '(?:^|; )' + name.replace( /([.*+?^${}()|[\]\\])/g, '\\$1' ) + '=([^;]*)' );
		return m ? m[ 1 ] : null;
	}
} )();
