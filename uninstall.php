<?php
/**
 * Open Builder uninstall routine. Runs only when the plugin is deleted from the
 * WordPress admin. Removes plugin options, post meta and the form-entries table.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Remove options.
delete_option( 'openb_global_styles' );

// Remove our post meta from every post.
$meta_keys = [ '_openb_tree', '_openb_enabled', '_openb_compiled_css' ];
foreach ( $meta_keys as $key ) {
	$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => $key ] );
}

// Drop the form-entries table.
$table = $wpdb->prefix . 'openb_form_entries';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

// Remove builder-owned posts (templates, popups).
$cpts = [ 'openb_template', 'openb_popup' ];
$ids  = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type IN (%s, %s)",
		$cpts[0],
		$cpts[1]
	)
);
foreach ( (array) $ids as $id ) {
	wp_delete_post( (int) $id, true );
}
