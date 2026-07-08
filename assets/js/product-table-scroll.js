( function() {
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

	function enhanceTables( root ) {
		var scope = root || document;
		var tables = scope.querySelectorAll(
			'.elementor-widget-woocommerce-product-content table,' +
			'.woocommerce div.product .woocommerce-tabs .panel table,' +
			'.woocommerce div.product .woocommerce-Tabs-panel table,' +
			'.woocommerce-Tabs-panel--description table'
		);

		tables.forEach( wrapTable );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function() {
			enhanceTables( document );
		} );
	} else {
		enhanceTables( document );
	}
}() );
