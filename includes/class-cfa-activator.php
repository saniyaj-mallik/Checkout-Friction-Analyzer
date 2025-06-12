<?php
/**
 * Plugin activation handler
 *
 * @package Checkout_Friction_Analyzer
 */

namespace CheckoutFrictionAnalyzer;

/**
 * Plugin activation handler
 */
class Activator {
	/**
	 * Activate the plugin
	 */
	public static function activate() {
		self::create_tables();
		self::create_options();
	}

	/**
	 * Create database tables
	 */
	private static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cfa_friction_points (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			session_id varchar(32) NOT NULL,
			type varchar(50) NOT NULL,
			data longtext NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY session_id (session_id),
			KEY type (type),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	/**
	 * Create plugin options
	 */
	private static function create_options() {
		add_option( 'cfa_version', CFA_VERSION );
		add_option(
			'cfa_settings',
			array(
				'enable_tracking'     => true,
				'track_page_load'     => true,
				'track_form_errors'   => true,
				'track_abandonment'   => true,
				'session_recording'   => false,
				'heatmap_integration' => false,
			)
		);
	}
} 