<?php
/**
 * Core plugin class
 *
 * @package Checkout_Friction_Analyzer
 */

namespace CheckoutFrictionAnalyzer;

/**
 * Core plugin class
 */
class Core {
	/**
	 * Initialize the plugin
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		// Admin hooks
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Frontend hooks
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'track_checkout_process' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'track_order_processed' ) );
	}

	/**
	 * Add admin menu items
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Checkout Friction Analyzer', 'checkout-friction-analyzer' ),
			__( 'Checkout Analysis', 'checkout-friction-analyzer' ),
			'manage_woocommerce',
			'checkout-friction-analyzer',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'woocommerce_page_checkout-friction-analyzer' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'cfa-admin',
			CFA_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			CFA_VERSION
		);

		wp_enqueue_script(
			'cfa-admin',
			CFA_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			CFA_VERSION,
			true
		);

		// Add Chart.js
		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js',
			array(),
			'3.7.0',
			true
		);
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		if ( ! is_checkout() ) {
			return;
		}

		wp_enqueue_script(
			'cfa-frontend',
			CFA_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			CFA_VERSION,
			true
		);

		wp_localize_script(
			'cfa-frontend',
			'cfaData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cfa-nonce' ),
			)
		);
	}

	/**
	 * Track checkout process
	 */
	public function track_checkout_process() {
		// Track form validation errors
		$errors = WC()->session->get( 'wc_notices', array() );
		if ( ! empty( $errors['error'] ) ) {
			$this->log_friction_point(
				'form_validation',
				array(
					'errors' => $errors['error'],
				)
			);
		}
	}

	/**
	 * Track order processed
	 *
	 * @param int $order_id Order ID.
	 */
	public function track_order_processed( $order_id ) {
		// Track successful order completion
		$this->log_friction_point(
			'order_completed',
			array(
				'order_id' => $order_id,
			)
		);
	}

	/**
	 * Log friction point
	 *
	 * @param string $type Friction point type.
	 * @param array  $data Friction point data.
	 */
	private function log_friction_point( $type, $data ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'cfa_friction_points',
			array(
				'session_id' => isset( $data['session_id'] ) ? $data['session_id'] : '',
				'type'       => $type,
				'data'       => json_encode( $data ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		require_once CFA_PLUGIN_DIR . 'admin/views/admin-page.php';
	}

	/**
	 * Calculate the abandonment rate
	 *
	 * @return float Abandonment rate as a percentage
	 */
	public function get_abandonment_rate() {
		global $wpdb;

		$total_sessions = $wpdb->get_var(
			"SELECT COUNT(DISTINCT session_id) 
			FROM {$wpdb->prefix}cfa_friction_points 
			WHERE type IN ('session_start', 'order_completed')"
		);

		$completed_orders = $wpdb->get_var(
			"SELECT COUNT(DISTINCT session_id) 
			FROM {$wpdb->prefix}cfa_friction_points 
			WHERE type = 'order_completed'"
		);

		if ( $total_sessions > 0 ) {
			return round( ( ( $total_sessions - $completed_orders ) / $total_sessions ) * 100, 2 );
		}

		return 0;
	}

	/**
	 * Calculate the average checkout time
	 *
	 * @return float Average checkout time in seconds
	 */
	public function get_avg_checkout_time() {
		global $wpdb;

		$avg_time = $wpdb->get_var(
			"SELECT AVG(TIMESTAMPDIFF(SECOND, start_time, end_time))
			FROM (
				SELECT 
					MIN(created_at) as start_time,
					MAX(created_at) as end_time
				FROM {$wpdb->prefix}cfa_friction_points
				WHERE type IN ('session_start', 'order_completed')
				GROUP BY session_id
			) as checkout_times"
		);

		return round( $avg_time, 2 );
	}

	/**
	 * Get top friction points
	 *
	 * @return array Array of friction points with their counts
	 */
	public function get_top_friction_points() {
		global $wpdb;

		return $wpdb->get_results(
			"SELECT 
				type,
				COUNT(*) as count,
				MAX(data) as data
			FROM {$wpdb->prefix}cfa_friction_points
			WHERE type != 'order_completed'
			GROUP BY type
			ORDER BY count DESC
			LIMIT 10"
		);
	}
} 