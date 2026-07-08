( function() {
	var descriptionHeaderPattern = /applications?|benefits?|selection|reason|condition|usage|use cases?|notes?|remarks?|advantages?|features?|product standards?/i;
	var compactHeaderPattern = /alloy|temper|thickness|width|length|diameter|designation|element|composition|tensile|yield|elongation|industry|min|max|size|grade|standard/i;

	function wrapTable( table ) {
		if ( ! table || table.closest( '.table-responsive' ) ) {
			return;
		}

		if ( table.classList.contains( 'shop_table' ) ) {
			return;
		}

		var wrapper = document.createElement( 'div' );
		wrapper.className = 'table-responsive';

		table.parentNode.insertBefore( wrapper, table );
		wrapper.appendChild( table );
	}

	function getWordCount( text ) {
		return text.trim().split( /\s+/ ).filter( Boolean ).length;
	}

	function isCompactText( text ) {
		var normalized = text.trim();

		if ( ! normalized ) {
			return true;
		}

		return normalized.length <= 18 &&
			getWordCount( normalized ) <= 3 &&
			! /[.;:]/.test( normalized );
	}

	function classifyColumn( headerText, texts ) {
		var nonEmptyTexts = texts.filter( Boolean );
		var maxLength = nonEmptyTexts.reduce( function( max, text ) {
			return Math.max( max, text.length );
		}, 0 );
		var totalLength = nonEmptyTexts.reduce( function( sum, text ) {
			return sum + text.length;
		}, 0 );
		var compactCount = nonEmptyTexts.filter( isCompactText ).length;
		var compactRatio = nonEmptyTexts.length ? compactCount / nonEmptyTexts.length : 1;
		var averageLength = nonEmptyTexts.length ? totalLength / nonEmptyTexts.length : 0;
		var isDescription = descriptionHeaderPattern.test( headerText ) ||
			maxLength >= 36 ||
			averageLength >= 22 ||
			compactRatio < 0.6;
		var isCompact = ! isDescription && (
			compactHeaderPattern.test( headerText ) ||
			( maxLength <= 20 && compactRatio >= 0.75 )
		);

		if ( isDescription ) {
			return 'description';
		}

		if ( isCompact ) {
			return 'compact';
		}

		return 'default';
	}

	function markCell( cell, type ) {
		cell.classList.remove( 'is-description-column', 'is-compact-column', 'is-spanning-cell' );

		if ( 'description' === type ) {
			cell.classList.add( 'is-description-column' );
			return;
		}

		if ( 'compact' === type ) {
			cell.classList.add( 'is-compact-column' );
		}
	}

	function classifyTableColumns( table ) {
		var rows = Array.from( table.rows );

		if ( ! rows.length ) {
			return;
		}

		var headerCells = Array.from( rows[ 0 ].cells );
		var columnCount = headerCells.length;
		var columnTexts = Array.from( { length: columnCount }, function() {
			return [];
		} );
		var columnTypes = [];

		rows.forEach( function( row, rowIndex ) {
			var cells = Array.from( row.cells );
			var isSimpleRow = cells.length === columnCount && cells.every( function( cell ) {
				return 1 === cell.colSpan;
			} );

			cells.forEach( function( cell ) {
				if ( cell.colSpan > 1 ) {
					cell.classList.add( 'is-spanning-cell' );

					if ( cell.innerText.trim().length > 24 ) {
						cell.classList.add( 'is-description-column' );
					}
				}
			} );

			if ( ! isSimpleRow ) {
				return;
			}

			cells.forEach( function( cell, columnIndex ) {
				if ( 0 === rowIndex ) {
					return;
				}

				columnTexts[ columnIndex ].push( cell.innerText.trim() );
			} );
		} );

		headerCells.forEach( function( cell, columnIndex ) {
			columnTypes[ columnIndex ] = classifyColumn( cell.innerText.trim(), columnTexts[ columnIndex ] );
		} );

		rows.forEach( function( row ) {
			var cells = Array.from( row.cells );
			var isSimpleRow = cells.length === columnCount && cells.every( function( cell ) {
				return 1 === cell.colSpan;
			} );

			if ( ! isSimpleRow ) {
				return;
			}

			cells.forEach( function( cell, columnIndex ) {
				markCell( cell, columnTypes[ columnIndex ] );
			} );
		} );
	}

	function enhanceTables( root ) {
		var scope = root || document;
		var tables = scope.querySelectorAll(
			'.elementor-widget-woocommerce-product-content table,' +
			'.woocommerce div.product .woocommerce-tabs .panel table,' +
			'.woocommerce div.product .woocommerce-Tabs-panel table,' +
			'.woocommerce-Tabs-panel--description table'
		);

		tables.forEach( function( table ) {
			classifyTableColumns( table );
			wrapTable( table );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function() {
			enhanceTables( document );
		} );
	} else {
		enhanceTables( document );
	}
}() );
