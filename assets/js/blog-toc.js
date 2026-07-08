( function() {
	var MIN_HEADINGS = 4;
	var CONTENT_SELECTOR = [
		'.elementor-widget-theme-post-content .elementor-widget-container',
		'.elementor-widget-post-content .elementor-widget-container',
		'.elementor-widget-theme-post-content .elementor-post__content',
		'.elementor-widget-post-content .elementor-post__content'
	].join( ', ' );
	var HEADING_SELECTOR = 'h2, h3';
	var ACTIVE_CLASS = 'is-active';

	function slugify( text ) {
		var normalized = ( text || '' )
			.toString()
			.trim()
			.toLowerCase()
			.normalize( 'NFKD' )
			.replace( /[\u0300-\u036f]/g, '' )
			.replace( /[^a-z0-9\s-]/g, '' )
			.replace( /\s+/g, '-' )
			.replace( /-+/g, '-' )
			.replace( /^-|-$/g, '' );

		return normalized || 'section';
	}

	function getContentRoot() {
		return document.querySelector( CONTENT_SELECTOR );
	}

	function getHeadings( root ) {
		return Array.from( root.querySelectorAll( HEADING_SELECTOR ) ).filter( function( heading ) {
			return heading.innerText.trim();
		} );
	}

	function ensureHeadingIds( headings ) {
		var seen = {};

		headings.forEach( function( heading ) {
			var baseId = heading.id ? heading.id.trim() : slugify( heading.innerText );
			var nextId = baseId;
			var suffix = 2;

			while ( seen[ nextId ] || ( nextId !== heading.id && document.getElementById( nextId ) ) ) {
				nextId = baseId + '-' + suffix;
				suffix += 1;
			}

			seen[ nextId ] = true;
			heading.id = nextId;
		} );
	}

	function buildItem( heading, hasSeenH2 ) {
		var item = document.createElement( 'li' );
		var link = document.createElement( 'a' );
		var isNestedH3 = 'H3' === heading.tagName && hasSeenH2;

		item.className = 'linsy-blog-toc__item ' + ( isNestedH3 ? 'is-level-h3' : 'is-level-h2' );
		link.className = 'linsy-blog-toc__link';
		link.href = '#' + heading.id;
		link.textContent = heading.innerText.trim();
		link.dataset.targetId = heading.id;

		item.appendChild( link );

		return item;
	}

	function buildToc( headings ) {
		var wrapper = document.createElement( 'nav' );
		var title = document.createElement( 'div' );
		var list = document.createElement( 'ol' );
		var hasSeenH2 = false;

		wrapper.className = 'linsy-blog-toc';
		wrapper.setAttribute( 'aria-label', 'Table of contents' );

		title.className = 'linsy-blog-toc__title';
		title.textContent = 'On this page';

		list.className = 'linsy-blog-toc__list';

		headings.forEach( function( heading ) {
			list.appendChild( buildItem( heading, hasSeenH2 ) );

			if ( 'H2' === heading.tagName ) {
				hasSeenH2 = true;
			}
		} );

		wrapper.appendChild( title );
		wrapper.appendChild( list );

		return wrapper;
	}

	function insertToc( root, toc, headings ) {
		var firstHeading = headings[ 0 ];

		if ( ! firstHeading || ! firstHeading.parentNode ) {
			return false;
		}

		firstHeading.parentNode.insertBefore( toc, firstHeading );

		return true;
	}

	function bindSmoothScroll( toc ) {
		toc.addEventListener( 'click', function( event ) {
			var link = event.target.closest( '.linsy-blog-toc__link' );
			var target;

			if ( ! link ) {
				return;
			}

			target = document.getElementById( link.dataset.targetId );

			if ( ! target ) {
				return;
			}

			event.preventDefault();
			target.scrollIntoView( {
				behavior: 'smooth',
				block: 'start'
			} );

			if ( window.history && window.history.replaceState ) {
				window.history.replaceState( null, '', '#' + target.id );
			}
		} );
	}

	function bindActiveState( toc, headings ) {
		var linksById = {};
		var observer;
		var links = Array.from( toc.querySelectorAll( '.linsy-blog-toc__link' ) );

		headings.forEach( function( heading ) {
			var link = links.find( function( item ) {
				return item.dataset.targetId === heading.id;
			} );

			if ( link ) {
				linksById[ heading.id ] = link;
			}
		} );

		function setActive( id ) {
			Object.keys( linksById ).forEach( function( key ) {
				linksById[ key ].classList.toggle( ACTIVE_CLASS, key === id );
			} );
		}

		observer = new IntersectionObserver( function( entries ) {
			var visible = entries
				.filter( function( entry ) {
					return entry.isIntersecting;
				} )
				.sort( function( a, b ) {
					return a.boundingClientRect.top - b.boundingClientRect.top;
				} );

			if ( visible.length ) {
				setActive( visible[ 0 ].target.id );
			}
		}, {
			rootMargin: '-20% 0px -65% 0px',
			threshold: [ 0, 1 ]
		} );

		headings.forEach( function( heading ) {
			observer.observe( heading );
		} );

		if ( headings.length ) {
			setActive( headings[ 0 ].id );
		}
	}

	function initBlogToc() {
		var root = getContentRoot();
		var headings;
		var toc;

		if ( ! root || root.querySelector( '.linsy-blog-toc' ) ) {
			return;
		}

		headings = getHeadings( root );

		if ( headings.length < MIN_HEADINGS ) {
			return;
		}

		ensureHeadingIds( headings );
		toc = buildToc( headings );

		if ( ! insertToc( root, toc, headings ) ) {
			return;
		}

		bindSmoothScroll( toc );
		bindActiveState( toc, headings );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initBlogToc );
	} else {
		initBlogToc();
	}
}() );
