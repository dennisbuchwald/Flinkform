<?php
// Screenshot helper: ?ff_dark=1 renders the site with a dark Twenty Twenty-Five style variation.
add_filter( 'wp_theme_json_data_user', function ( $theme_json ) {
	$want = ! empty( $_GET['ff_var'] ) ? sanitize_text_field( wp_unslash( $_GET['ff_var'] ) ) : ( empty( $_GET['ff_dark'] ) ? '' : 'Evening' );
	if ( '' === $want ) {
		return $theme_json;
	}
	foreach ( glob( get_template_directory() . '/styles/*.json' ) as $file ) {
		$data = json_decode( file_get_contents( $file ), true );
		if ( isset( $data['title'] ) && $want === $data['title'] ) {
			unset( $data['title'] );
			$data['version'] = $data['version'] ?? 3;
			return $theme_json->update_with( $data );
		}
	}
	return $theme_json;
} );
// Hide the admin bar on the frontend for clean shots.
add_filter( 'show_admin_bar', function ( $show ) { return empty( $_GET['ff_clean'] ) ? $show : false; } );
// Playground cannot send mail: report success so the list shows "sent", like a real site.
add_filter( 'pre_wp_mail', '__return_true' );
// ?ff_reset=1 empties the submissions table; ?ff_spread=1 spreads dates and marks the two oldest as read.
add_action( 'admin_init', function () {
	global $wpdb;
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$t = $wpdb->prefix . 'flinkform_submissions';
	if ( ! empty( $_GET['ff_reset'] ) ) {
		$wpdb->query( "DELETE FROM $t" );
	}
	if ( ! empty( $_GET['ff_spread'] ) ) {
		$ids     = $wpdb->get_col( "SELECT id FROM $t ORDER BY id DESC" );
		$offsets = [ '-25 minutes', '-3 hours', '-1 day -2 hours', '-2 days -5 hours', '-4 days -1 hour', '-6 days' ];
		foreach ( $ids as $i => $id ) {
			$when = gmdate( 'Y-m-d H:i:s', strtotime( $offsets[ $i ] ?? '-7 days' ) );
			$wpdb->update( $t, [ 'created_at' => $when, 'mail_status' => 'sent', 'status' => $i >= 3 ? 'read' : 'unread' ], [ 'id' => $id ] );
		}
	}
} );

// ?ff_listvars=1 prints the style variation titles.
add_action( 'init', function () {
	if ( empty( $_GET['ff_listvars'] ) ) { return; }
	foreach ( glob( get_template_directory() . '/styles/*.json' ) as $f ) { $d = json_decode( file_get_contents( $f ), true ); echo esc_html( basename( $f ) . ' = ' . ( $d['title'] ?? '?' ) ) . "
"; }
	exit;
} );
