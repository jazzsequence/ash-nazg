<?php
/**
 * One API endpoint group: heading and results table.
 *
 * Rendered both inline for cached groups and by the AJAX handler for groups
 * probed on demand, so the markup stays identical.
 *
 * @package Pantheon\AshNazg
 *
 * @var string $group     Group name.
 * @var array  $endpoints List of endpoint results from probe_endpoint_group().
 * @var int    $cached_at Unix timestamp the group was probed.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ash-nazg-endpoint-group" data-group="<?php echo esc_attr( $group ); ?>">
	<h3 class="ash-nazg-section-header">
		<?php echo esc_html( $group ); ?>
		<span class="ash-nazg-label-text">
			(<?php echo count( $endpoints ); ?> <?php echo esc_html( _n( 'endpoint', 'endpoints', count( $endpoints ), 'ash-nazg' ) ); ?>)
		</span>
	</h3>
	<table class="widefat striped ash-nazg-table-mb">
		<thead>
			<tr>
				<th class="ash-nazg-th-icon"></th>
				<th class="ash-nazg-th-20"><?php esc_html_e( 'Endpoint', 'ash-nazg' ); ?></th>
				<th class="ash-nazg-th-45"><?php esc_html_e( 'Path', 'ash-nazg' ); ?></th>
				<th class="ash-nazg-th-10"><?php esc_html_e( 'Last Checked', 'ash-nazg' ); ?></th>
				<th><?php esc_html_e( 'Status / Data', 'ash-nazg' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $endpoints as $endpoint ) : ?>
				<tr class="ash-nazg-endpoint-row" data-status="<?php echo esc_attr( $endpoint['status'] ); ?>">
					<td class="ash-nazg-th-icon">
						<?php if ( 'success' === $endpoint['status'] ) : ?>
							<span class="dashicons dashicons-yes-alt ash-nazg-icon-success" title="<?php esc_attr_e( 'Available', 'ash-nazg' ); ?>"></span>
						<?php elseif ( 'unavailable' === $endpoint['status'] ) : ?>
							<span class="dashicons dashicons-minus ash-nazg-icon-warning" title="<?php echo esc_attr( $endpoint['error'] ?? __( 'Unavailable', 'ash-nazg' ) ); ?>"></span>
						<?php else : ?>
							<span class="dashicons dashicons-dismiss ash-nazg-icon-error" title="<?php echo esc_attr( $endpoint['error'] ?? __( 'Error', 'ash-nazg' ) ); ?>"></span>
						<?php endif; ?>
					</td>
					<td>
						<strong><?php echo esc_html( $endpoint['name'] ); ?></strong>
						<?php if ( ! empty( $endpoint['description'] ) ) : ?>
							<br><small class="ash-nazg-text-muted"><?php echo esc_html( $endpoint['description'] ); ?></small>
						<?php endif; ?>
					</td>
					<td><code class="ash-nazg-tiny-text"><?php echo esc_html( $endpoint['path'] ); ?></code></td>
					<td>
						<?php if ( $cached_at ) : ?>
							<small class="ash-nazg-text-muted"><?php echo esc_html( human_time_diff( $cached_at ) ); ?> <?php esc_html_e( 'ago', 'ash-nazg' ); ?></small>
						<?php else : ?>
							<small class="ash-nazg-text-light">—</small>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( 'success' === $endpoint['status'] && ! empty( $endpoint['data'] ) ) : ?>
							<?php foreach ( $endpoint['data'] as $data_key => $data_value ) : ?>
								<?php if ( is_scalar( $data_value ) ) : ?>
									<div class="ash-nazg-small-text">
										<strong><?php echo esc_html( ucfirst( $data_key ) ); ?>:</strong>
										<?php echo esc_html( $data_value ); ?>
									</div>
								<?php endif; ?>
							<?php endforeach; ?>
						<?php elseif ( 'success' !== $endpoint['status'] && ! empty( $endpoint['error'] ) ) : ?>
							<small class="ash-nazg-text-error"><?php echo esc_html( $endpoint['error'] ); ?></small>
						<?php else : ?>
							<small class="ash-nazg-text-light">—</small>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
