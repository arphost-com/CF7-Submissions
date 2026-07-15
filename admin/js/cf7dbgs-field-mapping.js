/**
 * Settings page: "Auto-map" button + click-to-insert detected field names
 * into the field-mapping textarea.
 *
 * Enqueued only on the plugin's Settings page — see
 * CF7DBGS_Admin::enqueue_assets() in class-cf7dbgs-admin.php.
 */
( function () {
	var ta = function () { return document.getElementById( 'cf7dbgs_field_map' ); };

	// Suggest a payload key from a CF7 field name + type.
	function suggest( name, type ) {
		if ( type === 'email' ) { return 'email'; }
		if ( type === 'tel' ) { return 'phone'; }
		var n = name.toLowerCase()
			.replace( /^your[-_]/, '' )   // your-message -> message
			.replace( /[-_]\d+$/, '' );   // checkbox-123 -> checkbox
		return n.split( /[-_\s]+/ ).map( function ( p, i ) {
			return i ? p.charAt( 0 ).toUpperCase() + p.slice( 1 ) : p;
		} ).join( '' );
	}

	function normalize( k ) { return k.toLowerCase().trim().replace( /[\s_]+/g, '-' ); }

	function existingKeys() {
		var keys = {};
		ta().value.split( /\n/ ).forEach( function ( line ) {
			line = line.trim();
			if ( ! line || line.indexOf( '#' ) === 0 || line.indexOf( '=' ) === -1 ) { return; }
			keys[ normalize( line.split( '=' )[ 0 ] ) ] = true;
		} );
		return keys;
	}

	document.addEventListener( 'click', function ( e ) {
		var t = e.target;
		if ( t.classList && t.classList.contains( 'cf7dbgs-automap' ) ) {
			var fields = JSON.parse( t.getAttribute( 'data-fields' ) );
			var have = existingKeys();
			var lines = [];
			fields.forEach( function ( f ) {
				if ( have[ normalize( f.name ) ] ) { return; }       // already mapped
				var key = suggest( f.name, f.type );
				if ( key === f.name ) { return; }                    // passthrough, no line needed
				lines.push( f.name + '=' + key );
			} );
			if ( lines.length ) {
				var header = '# ' + t.getAttribute( 'data-form' );
				ta().value = ( ta().value.replace( /\s+$/, '' ) + '\n' + header + '\n' + lines.join( '\n' ) ).replace( /^\n/, '' );
			}
			ta().focus();
			return;
		}
		if ( t.classList && t.classList.contains( 'cf7dbgs-field' ) ) {
			ta().value = ( ta().value.replace( /\s+$/, '' ) + '\n' + t.getAttribute( 'data-field' ) + '=' ).replace( /^\n/, '' );
			ta().focus();
			ta().setSelectionRange( ta().value.length, ta().value.length );
		}
	} );
}() );
