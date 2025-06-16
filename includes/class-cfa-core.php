<?php
/**
 * Core plugin class
 *
 * @package Checkout_Friction_Analyzer
 */

namespace CheckoutFrictionAnalyzer;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;

/**
 * Core plugin class.
 *
 * @since 1.0.0
 */
class Core {

	/**
	 * Initialize the plugin.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function init_hooks() {
		// Admin hooks.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Frontend hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Cart tracking.
		add_action( 'woocommerce_add_to_cart', array( $this, 'track_add_to_cart' ), 10, 6 );
		add_action( 'woocommerce_remove_cart_item', array( $this, 'track_remove_from_cart' ), 10, 2 );

		// Checkout tracking.
		add_action( 'woocommerce_before_checkout_process', array( $this, 'track_checkout_start' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'track_checkout_process' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'track_order_processed' ), 10, 3 );
		add_action( 'woocommerce_checkout_order_created', array( $this, 'track_order_created' ), 10, 1 );

		// AJAX handlers.
		add_action( 'wp_ajax_cfa_track_friction', array( $this, 'handle_track_friction' ) );
		add_action( 'wp_ajax_nopriv_cfa_track_friction', array( $this, 'handle_track_friction' ) );
		add_action( 'wp_ajax_cfa_refresh_dashboard', array( $this, 'handle_refresh_dashboard' ) );
	}

	/**
	 * Add admin menu items.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Checkout Friction Analyzer', 'checkout-friction-analyzer' ),
			__( 'Checkout Analysis', 'checkout-friction-analyzer' ),
			'manage_options',
			'checkout-friction-analyzer',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since  1.0.0
	 * @param  string $hook Current admin page.
	 * @return void
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

		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js',
			array(),
			'3.7.0',
			true
		);

		$chart_data = $this->get_admin_chart_data();
		wp_localize_script(
			'cfa-admin',
			'cfaData',
			$chart_data
		);
	}

	/**
	 * Get admin chart data.
	 *
	 * @since  1.0.0
	 * @return array Chart data for admin dashboard.
	 */
	private function get_admin_chart_data() {
		global $wpdb;
		
		$cache_key  = 'cfa_chart_data';
		$chart_data = wp_cache_get( $cache_key );

		if ( false === $chart_data ) {
			// Get date labels for last 7 days.
			$labels = array();
			for ( $i = 6; $i >= 0; $i-- ) {
				$labels[] = gmdate( 'M j', strtotime( "-$i days" ) );
			}

			// Get abandonment data for last 7 days.
			$abandonment_data = array();

			for ( $i = 6; $i >= 0; $i-- ) {
				$date = gmdate( 'Y-m-d', strtotime( "-$i days" ) );

				$total_sessions = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(DISTINCT session_id) 
						FROM {$wpdb->prefix}cfa_friction_points 
						WHERE DATE(created_at) = %s 
						AND type IN ('checkout_start', 'order_completed')",
						$date
					)
				);

				$completed_orders = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(DISTINCT session_id) 
						FROM {$wpdb->prefix}cfa_friction_points 
						WHERE DATE(created_at) = %s 
						AND type = 'order_completed'",
						$date
					)
				);

				$abandonment_rate = $total_sessions > 0 ?
					round( ( ( $total_sessions - $completed_orders ) / $total_sessions ) * 100, 1 ) :
					0;

				$abandonment_data[] = $abandonment_rate;
			}

			// Get friction points data.
			$friction_data = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT 
						type as label,
						COUNT(*) as count
					FROM {$wpdb->prefix}cfa_friction_points
					WHERE type IN (%s, %s, %s)
					GROUP BY type
					ORDER BY count DESC
					LIMIT 4",
					'validation_error',
					'field_error',
					'form_abandonment'
				)
			);

			$friction_labels = array_column( $friction_data, 'label' );
			$friction_counts = array_column( $friction_data, 'count' );

			// Calculate current checkout abandonment rate.
			$checkout_data = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT 
						SUM(CASE WHEN type = %s THEN 1 ELSE 0 END) as started,
						SUM(CASE WHEN type = %s THEN 1 ELSE 0 END) as completed
					FROM {$wpdb->prefix}cfa_friction_points
					WHERE type IN (%s, %s)",
					'checkout_start',
					'order_created',
					'checkout_start',
					'order_created'
				)
			);

			$current_abandonment_rate = 0;
			if ( $checkout_data && $checkout_data->started > 0 ) {
				$current_abandonment_rate = round( ( ( $checkout_data->started - $checkout_data->completed ) / $checkout_data->started ) * 100, 1 );
			}

			// Prepare chart data.
			$chart_data = array(
				'chartLabels'     => $labels,
				'abandonmentData' => $abandonment_data,
				'frictionLabels'  => $friction_labels,
				'frictionData'    => $friction_counts,
				'nonce'          => wp_create_nonce( 'cfa-nonce' ),
			);

			// Cache the data for 5 minutes.
			wp_cache_set( $cache_key, $chart_data, '', 300 );
		}

		return $chart_data;
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @since  1.0.0
	 * @return void
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
	 * Track add to cart action.
	 *
	 * @since  1.0.0
	 * @param  string $cart_item_key  Cart item key.
	 * @param  int    $product_id     Product ID.
	 * @param  int    $quantity       Quantity.
	 * @param  int    $variation_id   Variation ID.
	 * @param  array  $variation      Variation data.
	 * @param  array  $cart_item_data Cart item data.
	 * @return void
	 */
	public function track_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		// Get or generate session ID.
		$session_id = WC()->session ? WC()->session->get( 'cfa_session_id' ) : null;
		if ( ! $session_id ) {
			$session_id = wp_generate_password( 32, false );
			if ( WC()->session ) {
				WC()->session->set( 'cfa_session_id', $session_id );
			}
		}

		$this->log_friction_point(
			'add_to_cart',
			array(
				'session_id'    => $session_id,
				'product_id'    => $product_id,
				'variation_id'  => $variation_id,
				'quantity'      => $quantity,
				'cart_item_key' => $cart_item_key,
				'timestamp'     => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Track remove from cart action.
	 *
	 * @since  1.0.0
	 * @param  string $cart_item_key Cart item key.
	 * @param  object $cart          Cart object.
	 * @return void
	 */
	public function track_remove_from_cart( $cart_item_key, $cart ) {
		// Get session ID.
		$session_id = WC()->session ? WC()->session->get( 'cfa_session_id' ) : null;
		if ( ! $session_id ) {
			return;
		}

		// Try to get product_id from removed_cart_contents or cart_contents.
		$product_id = null;
		if ( isset( $cart->removed_cart_contents[ $cart_item_key ]['product_id'] ) ) {
			$product_id = $cart->removed_cart_contents[ $cart_item_key ]['product_id'];
		} elseif ( isset( $cart->cart_contents[ $cart_item_key ]['product_id'] ) ) {
			$product_id = $cart->cart_contents[ $cart_item_key ]['product_id'];
		}

		$this->log_friction_point(
			'remove_from_cart',
			array(
				'session_id'    => $session_id,
				'cart_item_key' => $cart_item_key,
				'product_id'    => $product_id,
				'timestamp'     => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Track checkout start.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function track_checkout_start() {
		$this->log_friction_point(
			'checkout_start',
			array(
				'timestamp' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Track order created.
	 *
	 * @since  1.0.0
	 * @param  WC_Order $order Order object.
	 * @return void
	 */
	public function track_order_created( $order ) {
		// Get order ID using HPOS-compatible method.
		$order_id = $order->get_id();

		// Get order data using HPOS-compatible methods.
		$order_data = array(
			'order_id'   => $order_id,
			'order_total' => $order->get_total(),
			'items'      => count( $order->get_items() ),
			'session_id' => WC()->session ? WC()->session->get( 'cfa_session_id' ) : null,
			'timestamp'  => current_time( 'mysql' ),
		);

		$this->log_friction_point( 'order_created', $order_data );
	}

	/**
	 * Track checkout process.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function track_checkout_process() {
		// Track form validation errors.
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
	 * Track order processed.
	 *
	 * @since  1.0.0
	 * @param  int $order_id Order ID.
	 * @return void
	 */
	public function track_order_processed( $order_id ) {
		// Get order using HPOS-compatible method.
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$order_data = array(
			'order_id'   => $order_id,
			'session_id' => WC()->session ? WC()->session->get( 'cfa_session_id' ) : null,
			'timestamp'  => current_time( 'mysql' ),
		);

		$this->log_friction_point( 'order_completed', $order_data );
	}

	/**
	 * Log friction point.
	 *
	 * @since  1.0.0
	 * @param  string $type Friction point type.
	 * @param  array  $data Friction point data.
	 * @return bool
	 */
	private function log_friction_point( $type, $data ) {
		global $wpdb;

		try {
			// Ensure we have a session ID.
			if ( ! isset( $data['session_id'] ) || empty( $data['session_id'] ) ) {
				return false;
			}

			// Prepare the data for insertion.
			$insert_data = array(
				'session_id' => $data['session_id'],
				'type'      => $type,
				'data'      => wp_json_encode( $data ),
				'created_at' => current_time( 'mysql' ),
			);

			$result = $wpdb->insert(
				$wpdb->prefix . 'cfa_friction_points',
				$insert_data,
				array( '%s', '%s', '%s', '%s' )
			);

			if ( false === $result ) {
				return false;
			}

			return true;

		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Render admin page.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function render_admin_page() {
		require_once CFA_PLUGIN_DIR . 'admin/views/admin-page.php';
	}

	/**
	 * Calculate the abandonment rate.
	 *
	 * @since  1.0.0
	 * @return float Abandonment rate as a percentage.
	 */
	public function get_abandonment_rate() {
		global $wpdb;

		$total_sessions = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT session_id) 
				FROM {$wpdb->prefix}cfa_friction_points 
				WHERE type IN (%s, %s)",
				'session_start',
				'order_completed'
			)
		);

		$completed_orders = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT session_id) 
				FROM {$wpdb->prefix}cfa_friction_points 
				WHERE type = %s",
				'order_completed'
			)
		);

		if ( $total_sessions > 0 ) {
			return round( ( ( $total_sessions - $completed_orders ) / $total_sessions ) * 100, 2 );
		}

		return 0;
	}

	/**
	 * Get top friction points.
	 *
	 * @since  1.0.0
	 * @return array Array of friction points with their counts.
	 */
	public function get_top_friction_points() {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					type,
					COUNT(*) as count,
					MAX(data) as data
				FROM {$wpdb->prefix}cfa_friction_points
				WHERE type != %s
				GROUP BY type
				ORDER BY count DESC
				LIMIT 10",
				'order_completed'
			)
		);
	}

	/**
	 * Handle AJAX track friction request.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function handle_track_friction() {
		try {
			check_ajax_referer( 'cfa-nonce', 'nonce' );

			// Get and sanitize data.
			$type       = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
			$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
			$raw_data   = isset( $_POST['data'] ) ? $_POST['data'] : '';

			// Synchronize session ID with WooCommerce session.
			if ( class_exists( 'WC_Session' ) && function_exists( 'WC' ) && WC()->session ) {
				if ( $session_id ) {
					WC()->session->set( 'cfa_session_id', $session_id );
				}
			}

			// Handle both string and array data.
			if ( is_string( $raw_data ) ) {
				$data = json_decode( stripslashes( $raw_data ), true );
			} else {
				$data = $raw_data;
			}

			// Add session_id to data if not present.
			if ( ! isset( $data['session_id'] ) && $session_id ) {
				$data['session_id'] = $session_id;
			}

			// Ignore scroll events.
			if ( 'scroll' === $type ) {
				wp_send_json_success(
					array(
						'message' => 'Scroll event ignored',
						'type'    => $type,
					)
				);
				return;
			}

			if ( ! empty( $type ) ) {
				$result = $this->log_friction_point( $type, $data );
				if ( false === $result ) {
					wp_send_json_error( array( 'message' => 'Failed to save friction point' ) );
					return;
				}
			} else {
				wp_send_json_error( array( 'message' => 'Empty friction point type' ) );
				return;
			}

			wp_send_json_success(
				array(
					'message' => 'Friction point logged successfully',
					'type'    => $type,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => 'Internal server error',
					'error'   => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Handle AJAX refresh dashboard request.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function handle_refresh_dashboard() {
		try {
			check_ajax_referer( 'cfa-nonce', 'nonce' );

			$chart_data = $this->get_admin_chart_data();
			wp_send_json_success( $chart_data );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}
}
