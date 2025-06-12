<?php
/**
 * Admin page view
 *
 * @package Checkout_Friction_Analyzer
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Get friction data
global $wpdb;
$table_name = $wpdb->prefix . 'cfa_friction_points';

// Get cart abandonment data
$cart_abandonment = $wpdb->get_row(
	"SELECT 
		COUNT(*) as total,
		SUM(CASE WHEN type = 'add_to_cart' THEN 1 ELSE 0 END) as added,
		SUM(CASE WHEN type = 'remove_from_cart' THEN 1 ELSE 0 END) as removed
	FROM $table_name 
	WHERE type IN ('add_to_cart', 'remove_from_cart')"
);

// Get checkout completion data
$checkout_data = $wpdb->get_row(
	"SELECT 
		COUNT(*) as total,
		SUM(CASE WHEN type = 'checkout_start' THEN 1 ELSE 0 END) as started,
		SUM(CASE WHEN type = 'order_created' THEN 1 ELSE 0 END) as completed
	FROM $table_name 
	WHERE type IN ('checkout_start', 'order_created')"
);

// Get form abandonment data
$form_abandonment = $wpdb->get_row(
	"SELECT 
		AVG(JSON_EXTRACT(data, '$.time_spent')) as avg_time,
		AVG(JSON_EXTRACT(data, '$.fields_filled')) as avg_fields
	FROM $table_name 
	WHERE type = 'form_abandonment'"
);

// Get validation errors
$validation_errors = $wpdb->get_results(
	"SELECT 
		JSON_EXTRACT(data, '$.errors') as errors,
		COUNT(*) as count
	FROM $table_name 
	WHERE type = 'validation_error'
	GROUP BY JSON_EXTRACT(data, '$.errors')
	ORDER BY count DESC
	LIMIT 5"
);
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="cfa-dashboard">
		<!-- Cart Analytics -->
		<div class="cfa-card">
			<h2><?php esc_html_e( 'Cart Analytics', 'checkout-friction-analyzer' ); ?></h2>
			<div class="cfa-stats">
				<div class="cfa-stat">
					<span class="cfa-stat-value"><?php echo esc_html( $cart_abandonment->total ); ?></span>
					<span class="cfa-stat-label"><?php esc_html_e( 'Total Cart Actions', 'checkout-friction-analyzer' ); ?></span>
				</div>
				<div class="cfa-stat">
					<span class="cfa-stat-value"><?php echo esc_html( $cart_abandonment->added ); ?></span>
					<span class="cfa-stat-label"><?php esc_html_e( 'Items Added', 'checkout-friction-analyzer' ); ?></span>
				</div>
				<div class="cfa-stat">
					<span class="cfa-stat-value"><?php echo esc_html( $cart_abandonment->removed ); ?></span>
					<span class="cfa-stat-label"><?php esc_html_e( 'Items Removed', 'checkout-friction-analyzer' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Checkout Analytics -->
		<div class="cfa-card">
			<h2><?php esc_html_e( 'Checkout Analytics', 'checkout-friction-analyzer' ); ?></h2>
			<div class="cfa-stats">
				<div class="cfa-stat">
					<span class="cfa-stat-value"><?php echo esc_html( $checkout_data->started ); ?></span>
					<span class="cfa-stat-label"><?php esc_html_e( 'Checkouts Started', 'checkout-friction-analyzer' ); ?></span>
				</div>
				<div class="cfa-stat">
					<span class="cfa-stat-value"><?php echo esc_html( $checkout_data->completed ); ?></span>
					<span class="cfa-stat-label"><?php esc_html_e( 'Orders Completed', 'checkout-friction-analyzer' ); ?></span>
				</div>
				<div class="cfa-stat">
					<span class="cfa-stat-value">
						<?php 
						echo $checkout_data->started > 0 
							? esc_html( round( ( $checkout_data->completed / $checkout_data->started ) * 100, 1 ) ) 
							: '0';
						?>%
					</span>
					<span class="cfa-stat-label"><?php esc_html_e( 'Conversion Rate', 'checkout-friction-analyzer' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Form Abandonment -->
		<div class="cfa-card">
			<h2><?php esc_html_e( 'Form Abandonment', 'checkout-friction-analyzer' ); ?></h2>
			<div class="cfa-stats">
				<div class="cfa-stat">
					<span class="cfa-stat-value"><?php echo esc_html( round( $form_abandonment->avg_time / 60, 1 ) ); ?>m</span>
					<span class="cfa-stat-label"><?php esc_html_e( 'Avg. Time Spent', 'checkout-friction-analyzer' ); ?></span>
				</div>
				<div class="cfa-stat">
					<span class="cfa-stat-value"><?php echo esc_html( round( $form_abandonment->avg_fields, 1 ) ); ?></span>
					<span class="cfa-stat-label"><?php esc_html_e( 'Avg. Fields Filled', 'checkout-friction-analyzer' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Top Validation Errors -->
		<div class="cfa-card">
			<h2><?php esc_html_e( 'Top Validation Errors', 'checkout-friction-analyzer' ); ?></h2>
			<?php if ( ! empty( $validation_errors ) ) : ?>
				<ul class="cfa-error-list">
					<?php foreach ( $validation_errors as $error ) : ?>
						<li>
							<span class="cfa-error-count"><?php echo esc_html( $error->count ); ?></span>
							<span class="cfa-error-message"><?php echo esc_html( $error->errors ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p><?php esc_html_e( 'No validation errors recorded.', 'checkout-friction-analyzer' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div> 