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

// Get friction data.
global $wpdb;
$table_name = esc_sql( $wpdb->prefix . 'cfa_friction_points' );

// Get cart abandonment data.
$cart_abandonment = $wpdb->get_row(
	"SELECT 
		COUNT(*) as total,
		SUM(CASE WHEN type = 'add_to_cart' THEN 1 ELSE 0 END) as added,
		SUM(CASE WHEN type = 'remove_from_cart' THEN 1 ELSE 0 END) as removed
	FROM {$table_name}
	WHERE type IN ('add_to_cart', 'remove_from_cart')"
);

// Get checkout completion data.
$checkout_data = $wpdb->get_row(
	"SELECT 
		COUNT(*) as total,
		SUM(CASE WHEN type = 'checkout_start' THEN 1 ELSE 0 END) as started,
		SUM(CASE WHEN type = 'order_created' THEN 1 ELSE 0 END) as completed
	FROM {$table_name}
	WHERE type IN ('checkout_start', 'order_created')"
);

// Get form abandonment data.
$form_abandonment = $wpdb->get_row(
	"SELECT 
		AVG(JSON_EXTRACT(data, '$.time_spent')) as avg_time,
		AVG(JSON_EXTRACT(data, '$.fields_filled')) as avg_fields
	FROM {$table_name}
	WHERE type = 'form_abandonment'"
);

// Get validation errors.
$validation_errors = $wpdb->get_results(
	"SELECT 
		data,
		COUNT(*) as count
	FROM {$table_name}
	WHERE type = 'validation_error'
	GROUP BY data
	ORDER BY count DESC
	LIMIT 5"
);

// Format validation errors
$formatted_validation_errors = array();
foreach ($validation_errors as $error) {
    $error_data = json_decode($error->data, true);
    if (isset($error_data['errors']) && is_array($error_data['errors'])) {
        $formatted_errors = array_map(function($err) {
            // Clean up the error message
            return trim(str_replace(array("\n", "\t"), '', $err));
        }, $error_data['errors']);
        $formatted_validation_errors[] = (object) array(
            'errors' => $formatted_errors,
            'count' => $error->count
        );
    }
}

// Get top abandoned fields.
$abandoned_fields = $wpdb->get_results(
	"SELECT JSON_EXTRACT(data, '$.abandoned_fields') as fields_json
	FROM {$table_name}
	WHERE type = 'form_abandonment' AND JSON_LENGTH(JSON_EXTRACT(data, '$.abandoned_fields')) > 0"
);

$field_counts = array();
foreach ( $abandoned_fields as $row ) {
	$fields = json_decode( $row->fields_json, true );
	if ( is_array( $fields ) ) {
		foreach ( $fields as $field ) {
			$key = isset( $field['name'] ) ? $field['name'] : ( isset( $field['id'] ) ? $field['id'] : '' );
			if ( $key ) {
				if ( ! isset( $field_counts[ $key ] ) ) {
					$field_counts[ $key ] = 0;
				}
				$field_counts[ $key ]++;
			}
		}
	}
}
arsort( $field_counts );
$field_counts = array_slice( $field_counts, 0, 5, true );

// Get errors before abandonment
$abandonment_errors = $wpdb->get_results(
	"SELECT JSON_EXTRACT(data, '$.last_errors') as errors_json
	FROM {$table_name}
	WHERE type = 'form_abandonment' AND JSON_LENGTH(JSON_EXTRACT(data, '$.last_errors')) > 0"
);

$error_counts = array();
foreach ( $abandonment_errors as $row ) {
	$errors = json_decode( $row->errors_json, true );
	if ( is_array( $errors ) ) {
		foreach ( $errors as $error ) {
			$key = trim( $error );
			if ( $key ) {
				if ( ! isset( $error_counts[ $key ] ) ) {
					$error_counts[ $key ] = 0;
				}
				$error_counts[ $key ]++;
			}
		}
	}
}
arsort( $error_counts );
$error_counts = array_slice( $error_counts, 0, 5, true );

// Placeholder data for dashboard sections if no real data is present
if ( empty( $validation_errors ) ) {
	$validation_errors = array(
		(object) array( 'errors' => 'Invalid email address', 'count' => 7 ),
		(object) array( 'errors' => 'Billing PIN Code is not a valid postcode / ZIP', 'count' => 5 ),
		(object) array( 'errors' => 'Phone number is required', 'count' => 3 ),
	);
}
if ( empty( $field_counts ) ) {
	$field_counts = array(
		'billing_postcode' => 6,
		'billing_email' => 4,
		'billing_phone' => 2,
	);
}
if ( empty( $error_counts ) ) {
	$error_counts = array(
		'Billing PIN Code is not a valid postcode / ZIP' => 5,
		'Invalid email address' => 3,
		'Phone number is required' => 2,
	);
}

// Helper function for badge class
function cfa_get_badge_class( $count ) {
	if ( $count >= 6 ) {
		return 'cfa-badge cfa-badge-high';
	} elseif ( $count >= 3 ) {
		return 'cfa-badge cfa-badge-medium';
	}
	return 'cfa-badge cfa-badge-low';
}
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
			<?php if ( ! empty( $formatted_validation_errors ) ) : ?>
				<ul class="cfa-error-list">
					<?php foreach ( $formatted_validation_errors as $error ) : ?>
						<li>
							<span class="<?php echo esc_attr( cfa_get_badge_class( $error->count ) ); ?>"><?php echo esc_html( $error->count ); ?></span>
							<span class="cfa-error-message">
								<?php 
								if (is_array($error->errors)) {
									foreach ($error->errors as $err_msg) {
										echo '<div>' . esc_html($err_msg) . '</div>';
									}
								} else {
									echo esc_html($error->errors);
								}
								?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p><?php esc_html_e( 'No validation errors recorded.', 'checkout-friction-analyzer' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Top Abandoned Fields -->
		<div class="cfa-card">
			<h2><?php esc_html_e( 'Top Abandoned Fields', 'checkout-friction-analyzer' ); ?></h2>
			<?php if ( ! empty( $field_counts ) ) : ?>
				<ul class="cfa-error-list">
					<?php foreach ( $field_counts as $field => $count ) : ?>
						<li>
							<span class="<?php echo esc_attr( cfa_get_badge_class( $count ) ); ?>"><?php echo esc_html( $count ); ?></span>
							<span class="cfa-error-message"><?php echo esc_html( $field ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p><?php esc_html_e( 'No abandoned fields recorded.', 'checkout-friction-analyzer' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Errors Before Abandonment -->
		<div class="cfa-card">
			<h2><?php esc_html_e( 'Errors Before Abandonment', 'checkout-friction-analyzer' ); ?></h2>
			<?php if ( ! empty( $error_counts ) ) : ?>
				<ul class="cfa-error-list">
					<?php foreach ( $error_counts as $error => $count ) : ?>
						<li>
							<span class="<?php echo esc_attr( cfa_get_badge_class( $count ) ); ?>"><?php echo esc_html( $count ); ?></span>
							<span class="cfa-error-message"><?php echo esc_html( $error ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p><?php esc_html_e( 'No errors recorded before abandonment.', 'checkout-friction-analyzer' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Abandonment Rate Chart -->
		<div class="cfa-card">
			<h2><?php esc_html_e( 'Abandonment Rate', 'checkout-friction-analyzer' ); ?></h2>
			<div class="cfa-chart-container">
				<canvas id="abandonmentChart" width="400" height="120"></canvas>
			</div>
		</div>

		<!-- Friction Points Chart -->
		<div class="cfa-card">
			<h2><?php esc_html_e( 'Friction Points', 'checkout-friction-analyzer' ); ?></h2>
			<div class="cfa-chart-container">
				<canvas id="frictionPointsChart" width="400" height="120"></canvas>
			</div>
		</div>

		<!-- Checkout Time Chart -->
		<div class="cfa-card">
			<h2><?php esc_html_e( 'Checkout Time', 'checkout-friction-analyzer' ); ?></h2>
			<div class="cfa-chart-container">
				<canvas id="checkoutTimeChart" width="400" height="120"></canvas>
			</div>
		</div>
	</div>
</div> 