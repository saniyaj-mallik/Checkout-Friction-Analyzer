<?php
/**
 * Admin page view
 *
 * @package Checkout_Friction_Analyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="cfa-dashboard">
		<div class="cfa-overview">
			<div class="cfa-card">
				<h2><?php esc_html_e( 'Checkout Friction Overview', 'checkout-friction-analyzer' ); ?></h2>
				<div class="cfa-stats">
					<div class="cfa-stat">
						<span class="cfa-stat-value"><?php echo esc_html( $this->get_abandonment_rate() ); ?>%</span>
						<span class="cfa-stat-label"><?php esc_html_e( 'Abandonment Rate', 'checkout-friction-analyzer' ); ?></span>
					</div>
					<div class="cfa-stat">
						<span class="cfa-stat-value"><?php echo esc_html( $this->get_avg_checkout_time() ); ?>s</span>
						<span class="cfa-stat-label"><?php esc_html_e( 'Avg. Checkout Time', 'checkout-friction-analyzer' ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<div class="cfa-friction-points">
			<div class="cfa-card">
				<h2><?php esc_html_e( 'Top Friction Points', 'checkout-friction-analyzer' ); ?></h2>
				<div class="cfa-friction-list">
					<?php
					$friction_points = $this->get_top_friction_points();
					if ( ! empty( $friction_points ) ) :
						foreach ( $friction_points as $point ) :
							?>
							<div class="cfa-friction-item">
								<div class="cfa-friction-type"><?php echo esc_html( $point->type ); ?></div>
								<div class="cfa-friction-data"><?php echo esc_html( $point->data ); ?></div>
								<div class="cfa-friction-count"><?php echo esc_html( $point->count ); ?></div>
							</div>
							<?php
						endforeach;
					else :
						?>
						<p><?php esc_html_e( 'No friction points recorded yet.', 'checkout-friction-analyzer' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="cfa-settings">
			<div class="cfa-card">
				<h2><?php esc_html_e( 'Settings', 'checkout-friction-analyzer' ); ?></h2>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'cfa_settings' );
					do_settings_sections( 'cfa_settings' );
					?>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Tracking', 'checkout-friction-analyzer' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="cfa_settings[enable_tracking]" value="1" <?php checked( get_option( 'cfa_settings' )['enable_tracking'] ); ?>>
									<?php esc_html_e( 'Track checkout friction points', 'checkout-friction-analyzer' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Track Page Load', 'checkout-friction-analyzer' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="cfa_settings[track_page_load]" value="1" <?php checked( get_option( 'cfa_settings' )['track_page_load'] ); ?>>
									<?php esc_html_e( 'Track page load times', 'checkout-friction-analyzer' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Track Form Errors', 'checkout-friction-analyzer' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="cfa_settings[track_form_errors]" value="1" <?php checked( get_option( 'cfa_settings' )['track_form_errors'] ); ?>>
									<?php esc_html_e( 'Track form validation errors', 'checkout-friction-analyzer' ); ?>
								</label>
							</td>
						</tr>
					</table>
					<?php submit_button(); ?>
				</form>
			</div>
		</div>
	</div>
</div> 