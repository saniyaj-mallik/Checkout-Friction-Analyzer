<?php
/**
 * Plugin Name: Checkout Friction Analyzer
 * Plugin URI: https://github.com/yourusername/checkout-friction-analyzer
 * Description: Analyzes and identifies friction points in your WooCommerce checkout process to improve conversion rates.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: checkout-friction-analyzer
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Checkout_Friction_Analyzer
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'CFA_VERSION', '1.0.0' );
define( 'CFA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CFA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CFA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Add HPOS compatibility
add_action('before_woocommerce_init', function() {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});

// Autoloader.
spl_autoload_register( function ( $class ) {
	$prefix = 'CheckoutFrictionAnalyzer\\';
	$base_dir = CFA_PLUGIN_DIR . 'includes/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

// Initialize the plugin.
function cfa_init() {
	// Check if WooCommerce is active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				?>
                <div class="error">
                    <p><?php _e( 'Checkout Friction Analyzer requires WooCommerce to be installed and active.', 'checkout-friction-analyzer' ); ?></p>
                </div>
                <?php
			}
		);
		return;
	}

	// Initialize plugin components.
	require_once CFA_PLUGIN_DIR . 'includes/class-cfa-core.php';
	new CheckoutFrictionAnalyzer\Core();
}
add_action(
	'plugins_loaded',
	'cfa_init'
);

// Activation hook.
register_activation_hook(
	__FILE__,
	function () {
		// Create necessary database tables.
		require_once CFA_PLUGIN_DIR . 'includes/class-cfa-activator.php';
		CheckoutFrictionAnalyzer\Activator::activate();
	}
);

// Deactivation hook.
register_deactivation_hook(
	__FILE__,
	function () {
		require_once CFA_PLUGIN_DIR . 'includes/class-cfa-deactivator.php';
		CheckoutFrictionAnalyzer\Deactivator::deactivate();
	}
);