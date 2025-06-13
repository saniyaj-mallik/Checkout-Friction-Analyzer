<?php
/**
 * Plugin deactivation handler
 *
 * @package Checkout_Friction_Analyzer
 */

namespace CheckoutFrictionAnalyzer;

/**
 * Plugin deactivation handler
 */
class Deactivator {
	/**
	 * Deactivate the plugin
	 */
	public static function deactivate() {
		// Clear scheduled events.
		wp_clear_scheduled_hook( 'cfa_daily_cleanup' );

		// Optionally clear data.
		if ( get_option( 'cfa_clear_data_on_deactivate', false ) ) {
			self::clear_data();
		}
	}

	/**
	 * Clear plugin data
	 */
	private static function clear_data() {
		global $wpdb;

		// Drop custom tables.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cfa_friction_points" );

		// Delete options.
		delete_option( 'cfa_version' );
		delete_option( 'cfa_settings' );
		delete_option( 'cfa_clear_data_on_deactivate' );
	}
}
