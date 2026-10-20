/**
 * SportsArena front-end (vanilla JS, loaded with defer, no jQuery).
 *
 * - Dark / light toggle.
 * - Mobile navigation.
 * - Broken provider logos fall back to a neutral shield.
 * - Converts kickoff times to the visitor's timezone (original kept as tooltip).
 * - Sport filter tabs, date picker, quick search.
 * - Live scores: polls the REST endpoint and updates ONLY the score/status
 *   fragments (never reloads the page, so ads are never refreshed).
 */
( function () {
	'use strict';

	var cfg = window.SportsArena || {};
	var i18n = cfg.i18n || {};

	/* ---------- Theme toggle ---------- */
	var root = document.documentElement;
	var toggle = document.querySelector( '.sa-theme-toggle' );
	if ( toggle ) {
		var sync = function () {
			toggle.setAttribute( 'aria-pressed', root.getAttribute( 'data-theme' ) === 'dark' ? 'true' : 'false' );
		};
		sync();
		toggle.addEventListener( 'click', function () {
			var next = root.getAttribute( 'data-theme' ) === 'dark' ? 'light' : 'dark';
			root.setAttribute( 'data-theme', next );
			try {
				localStorage.setItem( 'sa-theme', next );
			} catch ( e ) {}
			sync();
		} );
	}

	/* ---------- Logo fallback ---------- */
	// A provider logo that fails to load becomes a neutral shield (same size, no layout shift).
	var shield = 'data:image/svg+xml,' + encodeURIComponent( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><path d="M16 1 3 6v9c0 8 5.6 13.6 13 16 7.4-2.4 13-8 13-16V6z" fill="#94a3b8"/></svg>' );
	var logoFallback = function ( img ) {
		if ( img.src !== shield ) {
			img.src = shield;
		}
	};
	document.addEventListener( 'error', function ( e ) {
		if ( e.target && e.target.matches && e.target.matches( 'img.sa-logo' ) ) {
			logoFallback( e.target );
		}
	}, true );
	document.querySelectorAll( 'img.sa-logo' ).forEach( function ( img ) {
		if ( img.complete && ! img.naturalWidth && img.src ) {
			logoFallback( img );
		}
	} );

	/* ---------- Mobile navigation ---------- */
	var navToggle = document.querySelector( '.sa-nav-toggle' );
	var nav = document.getElementById( 'sa-nav' );
	if ( navToggle && nav ) {
		navToggle.addEventListener( 'click', function () {
			var open = nav.classList.toggle( 'is-open' );
			navToggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
	}

	/* ---------- Local time ---------- */
	var lang = ( document.documentElement.lang || 'ar' ).split( '-' )[ 0 ];
	var locale = lang === 'ar' ? 'ar-u-nu-latn' : lang;
	function formatLocal( el ) {
		var iso = el.getAttribute( 'datetime' );
		if ( ! iso || el.dataset.saDone ) {
			return;
		}
		var date = new Date( iso );
		if ( isNaN( date.getTime() ) ) {
			return;
		}
		var opts = el.getAttribute( 'data-sa-local' ) === 'datetime' ?
			{ weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false } :
			{ hour: '2-digit', minute: '2-digit', hour12: false };
		try {
			var text = new Intl.DateTimeFormat( locale, opts ).format( date );
			if ( text !== el.textContent ) {
				el.textContent = text;
				if ( el.getAttribute( 'data-sa-local' ) === 'datetime' && i18n.yourTime ) {
					el.textContent += ' (' + i18n.yourTime + ')';
				}
			}
			el.dataset.saDone = '1';
		} catch ( e ) {}
	}
	function localizeAll( scope ) {
		( scope || document ).querySelectorAll( 'time[data-sa-local]' ).forEach( formatLocal );
	}
	localizeAll();

	/* ---------- Sport filter tabs (home) ---------- */
	document.querySelectorAll( '[data-sa-filter]' ).forEach( function ( tab ) {
		tab.addEventListener( 'click', function () {
			var sport = tab.getAttribute( 'data-sa-filter' );
			document.querySelectorAll( '[data-sa-filter]' ).forEach( function ( t ) {
				t.setAttribute( 'aria-selected', t === tab ? 'true' : 'false' );
			} );
			var target = document.querySelector( '[data-sa-filter-target]' );
			if ( ! target ) {
				return;
			}
			target.querySelectorAll( '.sa-group' ).forEach( function ( group ) {
				group.hidden = !! sport && group.getAttribute( 'data-sa-sport' ) !== sport;
			} );
		} );
	} );

	/* ---------- Date picker ---------- */
	document.querySelectorAll( 'input[data-sa-date]' ).forEach( function ( input ) {
		input.addEventListener( 'change', function () {
			if ( /^\d{4}-\d{2}-\d{2}$/.test( input.value ) && cfg.dayUrl ) {
				window.location.href = cfg.dayUrl.replace( '__DATE__', input.value );
			}
		} );
	} );

	/* ---------- Quick search ---------- */
	var qInput = document.getElementById( 'sa-qsearch-input' );
	var qList = document.getElementById( 'sa-qsearch-list' );
	if ( qInput && qList && cfg.search && window.fetch ) {
		var timer = null;
		var controller = null;
		var close = function () {
			qList.hidden = true;
			qInput.setAttribute( 'aria-expanded', 'false' );
		};
		var render = function ( items ) {
			qList.innerHTML = '';
			if ( ! items.length ) {
				var empty = document.createElement( 'li' );
				empty.className = 'sa-qsearch__empty';
				empty.textContent = i18n.noResults || '';
				qList.appendChild( empty );
			}
			items.forEach( function ( item, index ) {
				var li = document.createElement( 'li' );
				li.setAttribute( 'role', 'option' );
				li.id = 'sa-qs-' + index;
				var a = document.createElement( 'a' );
				a.href = item.url;
				a.textContent = item.title;
				var small = document.createElement( 'small' );
				small.textContent = item.type;
				a.appendChild( small );
				li.appendChild( a );
				qList.appendChild( li );
			} );
			qList.hidden = false;
			qInput.setAttribute( 'aria-expanded', 'true' );
		};
		qInput.addEventListener( 'input', function () {
			clearTimeout( timer );
			var q = qInput.value.trim();
			if ( q.length < 2 ) {
				close();
				return;
			}
			timer = setTimeout( function () {
				if ( controller && controller.abort ) {
					controller.abort();
				}
				controller = window.AbortController ? new AbortController() : null;
				fetch( cfg.search + ( cfg.search.indexOf( '?' ) > -1 ? '&' : '?' ) + 'q=' + encodeURIComponent( q ), {
					signal: controller ? controller.signal : undefined,
					credentials: 'same-origin',
				} )
					.then( function ( r ) {
						return r.ok ? r.json() : { items: [] };
					} )
					.then( function ( data ) {
						render( data.items || [] );
					} )
					.catch( function () {} );
			}, 250 );
		} );
		qInput.addEventListener( 'keydown', function ( e ) {
			var options = qList.querySelectorAll( '[role="option"]' );
			if ( ! options.length || qList.hidden ) {
				return;
			}
			var current = qList.querySelector( '[aria-selected="true"]' );
			var index = Array.prototype.indexOf.call( options, current );
			if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
				e.preventDefault();
				index = e.key === 'ArrowDown' ? Math.min( options.length - 1, index + 1 ) : Math.max( 0, index - 1 );
				options.forEach( function ( o ) {
					o.removeAttribute( 'aria-selected' );
				} );
				options[ index ].setAttribute( 'aria-selected', 'true' );
				qInput.setAttribute( 'aria-activedescendant', options[ index ].id );
			} else if ( e.key === 'Enter' && current ) {
				e.preventDefault();
				window.location.href = current.querySelector( 'a' ).href;
			} else if ( e.key === 'Escape' ) {
				close();
			}
		} );
		document.addEventListener( 'click', function ( e ) {
			if ( ! e.target.closest( '.sa-qsearch' ) ) {
				close();
			}
		} );
	}

	/* ---------- Live scores (fragment updates only) ---------- */
	var interval = Math.max( 60, parseInt( cfg.interval, 10 ) || 60 ) * 1000;
	var pollTimer = null;

	function pollIds() {
		var ids = {};
		document.querySelectorAll( '[data-sa-poll="1"][data-sa-match]' ).forEach( function ( el ) {
			ids[ el.getAttribute( 'data-sa-match' ) ] = true;
		} );
		return Object.keys( ids ).slice( 0, 60 );
	}

	function scoreHtml( match ) {
		var wrap = document.createElement( 'span' );
		wrap.className = 'sa-score';
		[ [ 'sa-score__home', match.home ], [ 'sa-score__sep', '-' ], [ 'sa-score__away', match.away === null ? 0 : match.away ] ].forEach( function ( part ) {
			var s = document.createElement( 'span' );
			s.className = part[ 0 ];
			s.textContent = String( part[ 1 ] );
			wrap.appendChild( s );
		} );
		return wrap;
	}

	function apply( match ) {
		document.querySelectorAll( '[data-sa-match="' + match.id + '"]' ).forEach( function ( el ) {
			var status = el.querySelector( '[data-sa-status]' );
			if ( status && status.textContent !== match.label ) {
				status.textContent = match.label;
			}
			if ( status ) {
				status.classList.toggle( 'is-live', !! match.live );
			}
			var score = el.querySelector( '[data-sa-score]' );
			if ( score && match.home !== null && match.status !== 'scheduled' ) {
				var current = score.querySelector( '.sa-score' );
				var homeEl = current && current.querySelector( '.sa-score__home' );
				var awayEl = current && current.querySelector( '.sa-score__away' );
				if ( homeEl && awayEl ) {
					if ( homeEl.textContent !== String( match.home ) ) {
						homeEl.textContent = String( match.home );
					}
					if ( awayEl.textContent !== String( match.away ) ) {
						awayEl.textContent = String( match.away );
					}
				} else {
					score.textContent = '';
					score.appendChild( scoreHtml( match ) );
				}
			}
			el.className = el.className.replace( /\bsa-(row|scoreboard)--[a-z]+\b/g, function ( m, kind ) {
				return 'sa-' + kind + '--' + match.status;
			} );
			if ( ! match.live && ( match.status === 'finished' || match.status === 'cancelled' || match.status === 'postponed' ) ) {
				el.removeAttribute( 'data-sa-poll' );
			}
		} );
	}

	function poll() {
		var ids = pollIds();
		if ( ! ids.length || ! cfg.live || ! window.fetch ) {
			return;
		}
		if ( document.hidden ) {
			schedule();
			return;
		}
		fetch( cfg.live + ( cfg.live.indexOf( '?' ) > -1 ? '&' : '?' ) + 'ids=' + ids.join( ',' ), { credentials: 'same-origin' } )
			.then( function ( r ) {
				return r.ok ? r.json() : null;
			} )
			.then( function ( data ) {
				if ( data && data.matches ) {
					data.matches.forEach( apply );
					if ( data.interval ) {
						interval = Math.max( 60, data.interval ) * 1000;
					}
				}
			} )
			.catch( function () {} )
			.then( schedule );
	}

	function schedule() {
		clearTimeout( pollTimer );
		if ( pollIds().length ) {
			pollTimer = setTimeout( poll, interval );
		}
	}

	schedule();
	document.addEventListener( 'visibilitychange', function () {
		if ( ! document.hidden ) {
			schedule();
		}
	} );
}() );
