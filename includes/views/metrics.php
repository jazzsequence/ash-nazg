<?php
/**
 * Metrics page template.
 *
 * @package Pantheon\AshNazg
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Pantheon\AshNazg\Admin;
?>

<div class="wrap">
	<?php Admin\render_pantheon_header( get_admin_page_title() ); ?>

	<p><?php esc_html_e( 'View environment performance metrics including traffic, visitors, and cache performance.', 'ash-nazg' ); ?></p>

	<?php if ( ! $site_id ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'Not running on Pantheon. Metrics features are not available.', 'ash-nazg' ); ?></p>
		</div>
	<?php else : ?>

		<div class="ash-nazg-card ash-nazg-card-full ash-nazg-mb-20">
			<h2><?php esc_html_e( 'Metrics Filters', 'ash-nazg' ); ?></h2>

			<div class="ash-nazg-metrics-filters">
				<!-- Environment Selector -->
				<div class="ash-nazg-filter-group">
					<label for="metrics-environment"><?php esc_html_e( 'Environment:', 'ash-nazg' ); ?></label>
					<select id="metrics-environment" name="environment">
						<?php
						$env_order = [ 'dev', 'test', 'live' ];
						$multidevs = [];

						foreach ( $environments as $env_id => $env_data ) {
							if ( ! in_array( $env_id, $env_order, true ) ) {
								$multidevs[] = $env_id;
							}
						}
						sort( $multidevs );
						$all_envs = array_merge( $env_order, $multidevs );

						foreach ( $all_envs as $env_id ) :
							if ( ! isset( $environments[ $env_id ] ) ) {
								continue;
							}
							$is_selected = ( $env_id === $selected_env );
							?>
							<option value="<?php echo esc_attr( $env_id ); ?>" <?php selected( $is_selected ); ?>>
								<?php echo esc_html( strtoupper( $env_id ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<!-- Duration Selector -->
				<div class="ash-nazg-filter-group">
					<label for="metrics-duration"><?php esc_html_e( 'Time Period:', 'ash-nazg' ); ?></label>
					<select id="metrics-duration" name="duration">
						<option value="7d"><?php esc_html_e( 'Last 7 Days', 'ash-nazg' ); ?></option>
						<option value="28d" selected><?php esc_html_e( 'Last 28 Days', 'ash-nazg' ); ?></option>
						<option value="12w"><?php esc_html_e( 'Last 12 Weeks', 'ash-nazg' ); ?></option>
						<option value="12m"><?php esc_html_e( 'Last 12 Months', 'ash-nazg' ); ?></option>
					</select>
				</div>

				<button id="load-metrics" class="button button-primary"><?php esc_html_e( 'Load Metrics', 'ash-nazg' ); ?></button>
				<button id="refresh-metrics" class="button button-secondary"><?php esc_html_e( 'Refresh', 'ash-nazg' ); ?></button>
			</div>

			<div id="metrics-loading" class="ash-nazg-loading" style="display: none;">
				<p><?php esc_html_e( 'Loading metrics data...', 'ash-nazg' ); ?></p>
			</div>

			<div id="metrics-error" class="notice notice-error" style="display: none;">
				<p></p>
			</div>
		</div>

		<!-- Raw API Response (Debug) -->
		<div id="metrics-raw-data" class="ash-nazg-card ash-nazg-card-full ash-nazg-mb-20" style="display: none;">
			<details>
				<summary style="cursor: pointer; padding: 10px; background: #f6f7f7; font-weight: 600;">
					<?php esc_html_e( 'API Request Details (Debug)', 'ash-nazg' ); ?>
				</summary>
				<div style="padding: 10px 0;">
					<h3><?php esc_html_e( 'API Endpoint:', 'ash-nazg' ); ?></h3>
					<pre id="api-endpoint" class="ash-nazg-code-block" style="background: #f6f7f7; padding: 10px; overflow-x: auto; margin: 10px 0;"></pre>
				</div>
			</details>

			<details style="margin-top: 10px;">
				<summary style="cursor: pointer; padding: 10px; background: #f6f7f7; font-weight: 600;">
					<?php esc_html_e( 'API Response Data (Debug)', 'ash-nazg' ); ?>
				</summary>
				<div style="padding: 10px 0;">
					<p class="description"><?php esc_html_e( 'This shows the raw JSON data returned from the Pantheon API.', 'ash-nazg' ); ?></p>
					<pre id="api-response" class="ash-nazg-code-block" style="background: #f6f7f7; padding: 10px; overflow-x: auto; max-height: 400px; margin: 10px 0;"></pre>
				</div>
			</details>
		</div>

		<!-- Charts Section -->
		<div id="metrics-charts" style="display: none;">
			<!-- Summary Stats -->
			<div class="ash-nazg-card ash-nazg-card-full ash-nazg-mb-20">
				<h2><?php esc_html_e( 'Summary Statistics', 'ash-nazg' ); ?></h2>
				<div class="ash-nazg-metrics-summary">
					<div class="ash-nazg-metric-stat">
						<span class="ash-nazg-stat-label"><?php esc_html_e( 'Total Pages Served:', 'ash-nazg' ); ?></span>
						<span id="total-pages-served" class="ash-nazg-stat-value">-</span>
					</div>
					<div class="ash-nazg-metric-stat">
						<span class="ash-nazg-stat-label"><?php esc_html_e( 'Total Unique Visits:', 'ash-nazg' ); ?></span>
						<span id="total-unique-visits" class="ash-nazg-stat-value">-</span>
					</div>
					<div class="ash-nazg-metric-stat">
						<span class="ash-nazg-stat-label"><?php esc_html_e( 'Avg Cache Hit Ratio:', 'ash-nazg' ); ?></span>
						<span id="avg-cache-ratio" class="ash-nazg-stat-value">-</span>
					</div>
				</div>
			</div>

			<!-- Pages Served Chart -->
			<div class="ash-nazg-card ash-nazg-card-full ash-nazg-mb-20">
				<h2><?php esc_html_e( 'Pages Served', 'ash-nazg' ); ?></h2>
				<canvas id="pages-served-chart"></canvas>
				<div class="ash-nazg-chart-summary">
					<p class="description">
						<strong><?php esc_html_e( 'Total:', 'ash-nazg' ); ?></strong> <span id="chart-total-pages">-</span>
						<span class="ash-nazg-summary-separator">•</span>
						<?php esc_html_e( 'Total number of pages served (page views) across all requests for the selected time period. Includes both cached and uncached requests.', 'ash-nazg' ); ?>
					</p>
				</div>
			</div>

			<!-- Unique Visits Chart -->
			<div class="ash-nazg-card ash-nazg-card-full ash-nazg-mb-20">
				<h2><?php esc_html_e( 'Unique Visits', 'ash-nazg' ); ?></h2>
				<canvas id="unique-visits-chart"></canvas>
				<div class="ash-nazg-chart-summary">
					<p class="description">
						<strong><?php esc_html_e( 'Total:', 'ash-nazg' ); ?></strong> <span id="chart-total-visits">-</span>
						<span class="ash-nazg-summary-separator">•</span>
						<?php esc_html_e( 'Number of unique visitors to your site for the selected time period. Tracked by unique IP addresses.', 'ash-nazg' ); ?>
					</p>
				</div>
			</div>

			<!-- Cache Performance Chart -->
			<div class="ash-nazg-card ash-nazg-card-full ash-nazg-mb-20">
				<h2><?php esc_html_e( 'Cache Performance', 'ash-nazg' ); ?></h2>
				<canvas id="cache-performance-chart"></canvas>
				<div class="ash-nazg-chart-summary">
					<p class="description">
						<strong><?php esc_html_e( 'Cache Hit Ratio:', 'ash-nazg' ); ?></strong> <span id="chart-cache-ratio">-</span>
						<span class="ash-nazg-summary-separator">•</span>
						<?php esc_html_e( 'Percentage of requests served from cache vs. origin. Higher ratios indicate better performance and reduced server load. Green line shows successful cache hits, red line shows cache misses that required processing.', 'ash-nazg' ); ?>
					</p>
				</div>
			</div>
		</div>

	<?php endif; ?>
</div>
