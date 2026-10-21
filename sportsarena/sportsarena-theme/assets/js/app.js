/**
 * SportsArena front-end (vanilla JS, loaded with defer, no jQuery).
 *
 * - Dark / light toggle.
 * - Header pop-overs (search, language, profile).
 * - Favourites («مفضلتي»): stars on matches and competitions, saved in
 *   localStorage only (no account, nothing sent to the server).
 * - Match centre: status tabs (all / live / finished / upcoming / favourites)
 *   and sport chips.
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
	// A provider logo that fails to load becomes the country flag when one is
	// given (competitions), otherwise a neutral shield (same size, no layout shift).
	var shield = 'data:image/svg+xml,' + encodeURIComponent( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><path d="M16 1 3 6v9c0 8 5.6 13.6 13 16 7.4-2.4 13-8 13-16V6z" fill="#94a3b8"/></svg>' );
	var logoFallback = function ( img ) {
		var flag = img.getAttribute( 'data-sa-fallback' );
		if ( flag ) {
			img.removeAttribute( 'data-sa-fallback' );
			img.classList.add( 'sa-logo--flag' );
			img.src = flag;
		} else if ( img.src !== shield ) {
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

	/* ---------- Favourites («مفضلتي») ---------- */
	var FAV_KEY = 'sa-favs';
	var favs = { match: {}, competition: {}, team: {} };
	try {
		var stored = JSON.parse( localStorage.getItem( FAV_KEY ) || '{}' );
		Object.keys( favs ).forEach( function ( type ) {
			if ( stored && stored[ type ] && typeof stored[ type ] === 'object' ) {
				favs[ type ] = stored[ type ];
			}
		} );
	} catch ( e ) {}

	function isFav( type, id ) {
		return !! ( favs[ type ] && favs[ type ][ String( id ) ] );
	}

	function saveFavs() {
		try {
			localStorage.setItem( FAV_KEY, JSON.stringify( favs ) );
		} catch ( e ) {}
	}

	function syncStars() {
		document.querySelectorAll( '.sa-fav[data-sa-fav]' ).forEach( function ( btn ) {
			btn.setAttribute( 'aria-pressed', isFav( btn.getAttribute( 'data-sa-fav' ), btn.getAttribute( 'data-id' ) ) ? 'true' : 'false' );
		} );
	}

	function renderFavs() {
		var list = document.querySelector( '[data-sa-fav-list]' );
		var empty = document.querySelector( '[data-sa-fav-empty]' );
		var count = document.querySelector( '[data-sa-fav-count]' );
		var total = 0;
		var labels = { match: i18n.favMatches, competition: i18n.favCompetitions, team: i18n.favTeams };
		if ( list ) {
			list.textContent = '';
		}
		[ 'match', 'competition', 'team' ].forEach( function ( type ) {
			var ids = Object.keys( favs[ type ] );
			total += ids.length;
			if ( ! list || ! ids.length ) {
				return;
			}
			var group = document.createElement( 'div' );
			group.className = 'sa-profile__group';
			var title = document.createElement( 'h3' );
			title.textContent = labels[ type ] || '';
			group.appendChild( title );
			var ul = document.createElement( 'ul' );
			ids.forEach( function ( id ) {
				var item = favs[ type ][ id ];
				var li = document.createElement( 'li' );
				var a = document.createElement( 'a' );
				a.href = item.u || '#';
				a.textContent = item.t || '';
				var btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'sa-fav';
				btn.setAttribute( 'data-sa-fav', type );
				btn.setAttribute( 'data-id', id );
				btn.setAttribute( 'aria-pressed', 'true' );
				btn.setAttribute( 'aria-label', i18n.favRemove || '' );
				btn.title = i18n.favRemove || '';
				btn.innerHTML = '<svg class="sa-i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3.2 2.6 5.6 6.1.7-4.5 4.2 1.2 6.1L12 16.8l-5.4 3 1.2-6.1-4.5-4.2 6.1-.7z" fill="var(--sa-star-fill,none)" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>';
				li.appendChild( a );
				li.appendChild( btn );
				ul.appendChild( li );
			} );
			group.appendChild( ul );
			list.appendChild( group );
		} );
		if ( empty ) {
			empty.hidden = total > 0;
		}
		if ( count ) {
			count.textContent = String( total );
			count.hidden = ! total;
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest && e.target.closest( '.sa-fav[data-sa-fav]' );
		if ( ! btn ) {
			return;
		}
		e.preventDefault();
		var type = btn.getAttribute( 'data-sa-fav' );
		var id = String( btn.getAttribute( 'data-id' ) );
		if ( ! favs[ type ] ) {
			return;
		}
		if ( favs[ type ][ id ] ) {
			delete favs[ type ][ id ];
		} else {
			favs[ type ][ id ] = { t: btn.getAttribute( 'data-title' ) || '', u: btn.getAttribute( 'data-url' ) || '' };
		}
		saveFavs();
		syncStars();
		renderFavs();
		document.querySelectorAll( '[data-sa-mc]' ).forEach( filterMc );
	} );

	/* ---------- Match centre: status tabs + sport chips ---------- */
	var STATUS_CLASSES = {
		live: [ 'sa-row--live', 'sa-row--halftime' ],
		finished: [ 'sa-row--finished' ],
		scheduled: [ 'sa-row--scheduled' ],
	};

	function rowMatches( row, group, status ) {
		if ( ! status ) {
			return true;
		}
		if ( status === 'fav' ) {
			var teams = ( row.getAttribute( 'data-sa-teams' ) || '' ).split( ',' );
			return isFav( 'match', row.getAttribute( 'data-sa-match' ) ) ||
				isFav( 'competition', group.getAttribute( 'data-sa-comp' ) ) ||
				teams.some( function ( t ) {
					return t && t !== '0' && isFav( 'team', t );
				} );
		}
		return ( STATUS_CLASSES[ status ] || [] ).some( function ( c ) {
			return row.classList.contains( c );
		} );
	}

	function filterMc( mc ) {
		var status = mc.getAttribute( 'data-status' ) || '';
		var sport = mc.getAttribute( 'data-sport' ) || '';
		var groups = mc.querySelectorAll( '.sa-group' );
		var shown = 0;
		var live = 0;
		groups.forEach( function ( group ) {
			var sportOk = ! sport || group.getAttribute( 'data-sa-sport' ) === sport;
			var any = false;
			group.querySelectorAll( '.sa-row' ).forEach( function ( row ) {
				if ( row.classList.contains( 'sa-row--live' ) || row.classList.contains( 'sa-row--halftime' ) ) {
					live++;
				}
				var show = sportOk && rowMatches( row, group, status );
				row.hidden = ! show;
				any = any || show;
			} );
			group.hidden = ! any;
			shown += any ? 1 : 0;
		} );
		var countEl = mc.querySelector( '[data-sa-live-count]' );
		if ( countEl ) {
			if ( countEl.textContent !== String( live ) ) {
				countEl.textContent = String( live );
			}
			countEl.hidden = ! live;
		}
		var emptyEl = mc.querySelector( '[data-sa-mc-empty]' );
		var emptyFav = mc.querySelector( '[data-sa-mc-empty-fav]' );
		if ( emptyEl && emptyFav ) {
			emptyEl.hidden = ! groups.length || shown > 0 || status === 'fav';
			emptyFav.hidden = ! groups.length || shown > 0 || status !== 'fav';
		}
		// Ad slots sit between every 3 groups of the full list: hide them while
		// filtering, so an ad never ends up first or next to the tabs.
		mc.querySelectorAll( '.sa-mc__list > .sa-ad' ).forEach( function ( ad ) {
			ad.hidden = !! status || !! sport || ! shown;
		} );
	}

	document.querySelectorAll( '[data-sa-mc]' ).forEach( function ( mc ) {
		mc.querySelectorAll( '[data-sa-status-filter]' ).forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				mc.querySelectorAll( '[data-sa-status-filter]' ).forEach( function ( t ) {
					t.setAttribute( 'aria-selected', t === tab ? 'true' : 'false' );
				} );
				mc.setAttribute( 'data-status', tab.getAttribute( 'data-sa-status-filter' ) );
				filterMc( mc );
			} );
		} );
		mc.querySelectorAll( '[data-sa-filter]' ).forEach( function ( chip ) {
			chip.addEventListener( 'click', function () {
				mc.querySelectorAll( '[data-sa-filter]' ).forEach( function ( c ) {
					c.setAttribute( 'aria-selected', c === chip ? 'true' : 'false' );
				} );
				mc.setAttribute( 'data-sport', chip.getAttribute( 'data-sa-filter' ) );
				filterMc( mc );
			} );
		} );
	} );

	syncStars();
	renderFavs();

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

	/* ---------- Header pop-overs: one open at a time, close outside / Escape ---------- */
	var pops = document.querySelectorAll( 'details.sa-pop, details.sa-lang' );
	pops.forEach( function ( pop ) {
		pop.addEventListener( 'toggle', function () {
			if ( ! pop.open ) {
				return;
			}
			pops.forEach( function ( other ) {
				if ( other !== pop ) {
					other.open = false;
				}
			} );
			var field = pop.querySelector( 'input[type="search"]' );
			if ( field ) {
				field.focus();
			}
		} );
		pop.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && pop.open ) {
				pop.open = false;
				pop.querySelector( 'summary' ).focus();
			}
		} );
	} );
	document.addEventListener( 'click', function ( e ) {
		pops.forEach( function ( pop ) {
			if ( pop.open && ! pop.contains( e.target ) ) {
				pop.open = false;
			}
		} );
	} );

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
			var label = match.label;
			if ( status && status.getAttribute( 'data-sa-status' ) === 'short' && match.status === 'live' && match.elapsed !== null && match.elapsed !== undefined ) {
				label = match.elapsed + "'";
			}
			if ( status && status.textContent !== label ) {
				status.textContent = label;
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
					document.querySelectorAll( '[data-sa-mc]' ).forEach( filterMc );
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
