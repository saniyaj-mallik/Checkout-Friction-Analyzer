<?php
/**
 * Admin page view.
 *
 * @package Checkout_Friction_Analyzer
 *
 * Note: This file is intended to be loaded within the WordPress admin context.
 * All WordPress template functions (esc_html, esc_html_e, esc_attr, esc_js, __, get_admin_page_title, wc_get_product)
 * are available when loaded properly via WordPress hooks.
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Get friction data.
global $wpdb;
$table_name = esc_sql( $wpdb->prefix . 'cfa_friction_points' );

// Get cart analytics data.
$cart_add = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'cfa_friction_points WHERE type = %s', 'add_to_cart' ) );
$cart_remove = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'cfa_friction_points WHERE type = %s', 'remove_from_cart' ) );
$total_cart_actions = $cart_add + $cart_remove;
$cart_stats = array(
	'total_cart_actions' => $total_cart_actions,
	'add_to_cart'        => $cart_add,
	'remove_from_cart'   => $cart_remove,
);

error_log( 'CFA: Cart Analytics Data: ' . print_r( $cart_stats, true ) );

// Get checkout completion data.
$checkout_data = $wpdb->get_row(
	"SELECT 
		COUNT(*) as total,
		SUM(CASE WHEN type = 'checkout_start' THEN 1 ELSE 0 END) as started,
		SUM(CASE WHEN type = 'order_created' THEN 1 ELSE 0 END) as completed
	FROM {$table_name}
	WHERE type IN ('checkout_start', 'order_created')"
);

// Calculate checkout abandonment rate using started and completed.
$checkout_abandonment_rate = 0;
if ( $checkout_data && $checkout_data->started > 0 ) {
	$checkout_abandonment_rate = round( ( ( $checkout_data->started - $checkout_data->completed ) / $checkout_data->started ) * 100, 1 );
}

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

// Format validation errors.
$formatted_validation_errors = array();
foreach ( $validation_errors as $row_validation_error ) {
	$error_data = json_decode( $row_validation_error->data, true );
	if ( isset( $error_data['errors'] ) && is_array( $error_data['errors'] ) ) {
		$formatted_errors = array_map(
			function ( $err ) {
				// Clean up the error message.
				return trim( str_replace( array( "\n", "\t" ), '', $err ) );
			},
			$error_data['errors']
		);
		$formatted_validation_errors[] = (object) array(
			'errors' => $formatted_errors,
			'count'  => $row_validation_error->count,
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

// Get errors before abandonment.
$abandonment_errors = $wpdb->get_results(
	"SELECT JSON_EXTRACT(data, '$.last_errors') as errors_json
	FROM {$table_name}
	WHERE type = 'form_abandonment' AND JSON_LENGTH(JSON_EXTRACT(data, '$.last_errors')) > 0"
);

$error_counts = array();
foreach ( $abandonment_errors as $row_abandonment ) {
	$row_last_errors = json_decode( $row_abandonment->errors_json, true );
	if ( is_array( $row_last_errors ) ) {
		foreach ( $row_last_errors as $item_error ) {
			$key = trim( $item_error );
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

// Get top removed products.
$top_removed_products = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT JSON_EXTRACT(data, '$.product_id') as product_id, COUNT(*) as count
		FROM {$table_name}
		WHERE type = %s AND JSON_EXTRACT(data, '$.product_id') IS NOT NULL
		GROUP BY product_id
		ORDER BY count DESC
		LIMIT 5",
		'remove_from_cart'
	)
);

// Fetch product names for display.
$removed_products_display = array();
foreach ( $top_removed_products as $row ) {
	$product_id = intval( $row->product_id );
	$count      = intval( $row->count );
	$product    = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
	$product_name = $product ? $product->get_name() : __( 'Unknown Product', 'checkout-friction-analyzer' );
	$removed_products_display[] = array(
		'name'  => $product_name,
		'count' => $count,
	);
}

// Placeholder data for dashboard sections if no real data is present.
if ( empty( $validation_errors ) ) {
	$validation_errors = array(
		(object) array(
			'errors' => 'Invalid email address',
			'count'  => 7,
		),
		(object) array(
			'errors' => 'Billing PIN Code is not a valid postcode / ZIP',
			'count'  => 5,
		),
		(object) array(
			'errors' => 'Phone number is required',
			'count'  => 3,
		),
	);
}
if ( empty( $field_counts ) ) {
	$field_counts = array(
		'billing_postcode' => 6,
		'billing_email'    => 4,
		'billing_phone'    => 2,
	);
}
if ( empty( $error_counts ) ) {
	$error_counts = array(
		'Billing PIN Code is not a valid postcode / ZIP' => 5,
		'Invalid email address'                          => 3,
		'Phone number is required'                       => 2,
	);
}

/**
 * Helper function for badge class.
 *
 * @param int $count The count value.
 * @return string Badge class.
 */
function cfa_get_badge_class( $count ) {
	if ( $count >= 6 ) {
		return 'cfa-badge cfa-badge-high';
	} elseif ( $count >= 3 ) {
		return 'cfa-badge cfa-badge-medium';
	}
	return 'cfa-badge cfa-badge-low';
}
?>

<style>
.cfa-slide-toggle {
	max-height: 0;
	overflow: hidden;
	transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.cfa-slide-toggle.open {
	max-height: 500px; /* Large enough for the list. */
	transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
</style>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="cfa-dashboard">
		<!-- Cart Analytics -->
		<div class="cfa-card">
			<h2><?php esc_html_e( 'Cart Analytics', 'checkout-friction-analyzer' ); ?></h2>
			<div class="cfa-stats">
				<div class="cfa-stat">
					<span class="cfa-stat-value"><?php echo esc_html( $cart_stats['total_cart_actions'] ); ?></span>
					<span class="cfa-stat-label"><?php esc_html_e( 'Total Cart Actions', 'checkout-friction-analyzer' ); ?></span>
				</div>
				<div class="cfa-stat">
					<span class="cfa-stat-value"><?php echo esc_html( $cart_stats['add_to_cart'] ); ?></span>
					<span class="cfa-stat-label"><?php esc_html_e( 'Add to Cart', 'checkout-friction-analyzer' ); ?></span>
				</div>
				<div class="cfa-stat">
					<span class="cfa-stat-value"><?php echo esc_html( $cart_stats['remove_from_cart'] ); ?></span>
					<span class="cfa-stat-label"><?php esc_html_e( 'Remove from Cart', 'checkout-friction-analyzer' ); ?></span>
				</div>
			</div>
			<?php if ( ! empty( $removed_products_display ) ) : ?>
				<div class="cfa-top-removed-products">
					<div style="display: flex; align-items: center; justify-content: space-between;">
						<h3 style="margin: 0;"><?php esc_html_e( 'Top Removed Products', 'checkout-friction-analyzer' ); ?></h3>
						<button id="cfa-toggle-removed-products" type="button" style="margin-left: 10px; padding: 4px 12px; border-radius: 4px; border: 1px solid #ccc; background: #f8f9fa; cursor: pointer; font-size: 14px;">
							<span id="cfa-toggle-removed-products-label"><?php esc_html_e( 'Show', 'checkout-friction-analyzer' ); ?></span> ▼
						</button>
					</div>
					<ul class="cfa-error-list cfa-slide-toggle" id="cfa-removed-products-list" style="margin-top: 10px;">
						<?php foreach ( $removed_products_display as $product ) : ?>
							<li>
								<span class="cfa-badge cfa-badge-medium"><?php echo esc_html( $product['count'] ); ?></span>
								<span class="cfa-error-message"><?php echo esc_html( $product['name'] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<script>
					document.addEventListener('DOMContentLoaded', function() {
						var btn = document.getElementById('cfa-toggle-removed-products');
						var list = document.getElementById('cfa-removed-products-list');
						var label = document.getElementById('cfa-toggle-removed-products-label');
						var open = false;
						if (btn && list && label) {
							btn.addEventListener('click', function() {
								open = !open;
								if (open) {
									list.classList.add('open');
									label.textContent = '<?php echo esc_js( __( 'Hide', 'checkout-friction-analyzer' ) ); ?>';
								} else {
									list.classList.remove('open');
									label.textContent = '<?php echo esc_js( __( 'Show', 'checkout-friction-analyzer' ) ); ?>';
								}
							});
							// Ensure closed on load.
							list.classList.remove('open');
						}
					});
				</script>
			<?php endif; ?>
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
					<span class="cfa-stat-value"><?php echo esc_html( $checkout_abandonment_rate ); ?>%</span>
					<span class="cfa-stat-label"><?php esc_html_e( 'Checkout Abandonment Rate', 'checkout-friction-analyzer' ); ?></span>
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
			<?php
			// Fetch validation errors with proper aggregation.
			$validation_errors_query = $wpdb->prepare(
				"SELECT 
					JSON_EXTRACT(data, '$.errors') as errors_json,
					COUNT(*) as count
				FROM {$table_name}
				WHERE type = %s
				AND JSON_LENGTH(JSON_EXTRACT(data, '$.errors')) > 0
				GROUP BY JSON_EXTRACT(data, '$.errors')
				ORDER BY count DESC
				LIMIT 10",
				'validation_error'
			);

			$validation_errors_data = $wpdb->get_results( $validation_errors_query );

			// Process and aggregate error data.
			$error_counts = array();
			foreach ( $validation_errors_data as $error_data ) {
				$errors = json_decode( $error_data->errors_json, true );
				if ( is_array( $errors ) ) {
					foreach ( $errors as $error ) {
						// Clean up the error message.
						$error = trim( str_replace( array( "\n", "\t" ), '', $error ) );
						if ( ! empty( $error ) ) {
							// Aggregate counts.
							if ( ! isset( $error_counts[ $error ] ) ) {
								$error_counts[ $error ] = 0;
							}
							$error_counts[ $error ] += $error_data->count;
						}
					}
				}
			}

			// Sort by count in descending order.
			arsort( $error_counts );

			// Take top 5 errors.
			$error_counts = array_slice( $error_counts, 0, 5, true );
			?>

			<?php if ( ! empty( $error_counts ) ) : ?>
				<ul class="cfa-error-list">
					<?php foreach ( $error_counts as $error => $count ) : ?>
						<li>
							<span class="<?php echo esc_attr( cfa_get_badge_class( $count ) ); ?>">
								<?php echo esc_html( $count ); ?>
							</span>
							<span class="cfa-error-message">
								<?php echo esc_html( $error ); ?>
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
			<?php
			// Fetch and format abandoned fields data with proper aggregation.
			$abandoned_fields_query = $wpdb->prepare(
				"SELECT 
					JSON_EXTRACT(data, '$.abandoned_fields') as fields_json,
					COUNT(*) as count
				FROM {$table_name}
				WHERE type = %s
				AND JSON_LENGTH(JSON_EXTRACT(data, '$.abandoned_fields')) > 0
				GROUP BY JSON_EXTRACT(data, '$.abandoned_fields')
				ORDER BY count DESC
				LIMIT 10",
				'form_abandonment'
			);

			$abandoned_fields_data = $wpdb->get_results( $abandoned_fields_query );

			// Process and aggregate field data.
			$field_counts = array();
			foreach ( $abandoned_fields_data as $field_data ) {
				$fields = json_decode( $field_data->fields_json, true );
				if ( is_array( $fields ) ) {
					foreach ( $fields as $field ) {
						$field_name = isset( $field['name'] ) ? $field['name'] : ( isset( $field['id'] ) ? $field['id'] : '' );
						if ( ! empty( $field_name ) ) {
							// Convert field name to readable format.
							$readable_name = ucwords( str_replace( array( '_', '-' ), ' ', $field_name ) );

							// Aggregate counts.
							if ( ! isset( $field_counts[ $readable_name ] ) ) {
								$field_counts[ $readable_name ] = 0;
							}
							$field_counts[ $readable_name ] += $field_data->count;
						}
					}
				}
			}

			// Sort by count in descending order.
			arsort( $field_counts );

			// Take top 5 fields.
			$field_counts = array_slice( $field_counts, 0, 5, true );
			?>

			<?php if ( ! empty( $field_counts ) ) : ?>
				<ul class="cfa-error-list">
					<?php foreach ( $field_counts as $field_name => $count ) : ?>
						<li>
							<span class="<?php echo esc_attr( cfa_get_badge_class( $count ) ); ?>">
								<?php echo esc_html( $count ); ?>
							</span>
							<span class="cfa-error-message">
								<?php echo esc_html( $field_name ); ?>
							</span>
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
	</div>
</div>

