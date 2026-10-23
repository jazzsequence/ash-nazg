<?php
/**
 * One API endpoint row.
 *
 * Rendered inline by the group partial and by the per-endpoint AJAX handler,
 * so a probed row replaces its placeholder with identical markup.
 *
 * @package Pantheon\AshNazg
 *
 * @var array $endpoint  Endpoint result: name, path, description, status, data, error, method, version,
 *                       and optionally id and cached_at.
 * @var int   $cached_at Fallback Unix timestamp the endpoint was probed.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$endpoint_probed_at = $endpoint['cached_at'] ?? $cached_at;
$endpoint_row_class = 'pending' === $endpoint['status'] ? 'ash-nazg-endpoint-row ash-nazg-endpoint-pending' : 'ash-nazg-endpoint-row';
?>
<tr class="<?php echo esc_attr( $endpoint_row_class ); ?>" data-status="<?php echo esc_attr( $endpoint['status'] ); ?>" data-entry-id="<?php echo esc_attr( $endpoint['id'] ?? '' ); ?>" data-version="<?php echo esc_attr( $endpoint['version'] ?? '' ); ?>">
	<td class="ash-nazg-th-icon">
		<?php if ( 'success' === $endpoint['status'] ) : ?>
			<span class="dashicons dashicons-yes-alt ash-nazg-icon-success" title="<?php esc_attr_e( 'Available', 'ash-nazg' ); ?>"></span>
		<?php elseif ( 'pending' === $endpoint['status'] ) : ?>
			<span class="dashicons dashicons-update ash-nazg-icon-locked" title="<?php esc_attr_e( 'Checking', 'ash-nazg' ); ?>"></span>
		<?php elseif ( 'unavailable' === $endpoint['status'] ) : ?>
			<span class="dashicons dashicons-minus ash-nazg-icon-warning" title="<?php echo esc_attr( $endpoint['error'] ?? __( 'Unavailable', 'ash-nazg' ) ); ?>"></span>
		<?php elseif ( 'skipped' === $endpoint['status'] ) : ?>
			<span class="dashicons dashicons-minus ash-nazg-icon-locked" title="<?php echo esc_attr( $endpoint['error'] ?? __( 'No request sent', 'ash-nazg' ) ); ?>"></span>
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
	<td><code class="ash-nazg-tiny-text"><?php echo esc_html( trim( ( $endpoint['method'] ?? '' ) . ' ' . $endpoint['path'] ) ); ?></code></td>
	<td>
		<?php if ( $endpoint_probed_at && 'pending' !== $endpoint['status'] ) : ?>
			<small class="ash-nazg-text-muted"><?php echo esc_html( human_time_diff( $endpoint_probed_at ) ); ?> <?php esc_html_e( 'ago', 'ash-nazg' ); ?></small>
		<?php else : ?>
			<small class="ash-nazg-text-light">—</small>
		<?php endif; ?>
	</td>
	<td>
		<?php if ( 'pending' === $endpoint['status'] ) : ?>
			<small class="ash-nazg-text-muted"><?php esc_html_e( 'Checking…', 'ash-nazg' ); ?></small>
		<?php elseif ( 'success' === $endpoint['status'] && ! empty( $endpoint['data'] ) ) : ?>
			<?php foreach ( $endpoint['data'] as $data_key => $data_value ) : ?>
				<?php if ( is_scalar( $data_value ) ) : ?>
					<div class="ash-nazg-small-text">
						<strong><?php echo esc_html( ucfirst( $data_key ) ); ?>:</strong>
						<?php echo esc_html( $data_value ); ?>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		<?php elseif ( 'skipped' === $endpoint['status'] ) : ?>
			<small class="ash-nazg-text-muted"><?php echo esc_html( $endpoint['error'] ?? '' ); ?></small>
		<?php elseif ( 'success' !== $endpoint['status'] && ! empty( $endpoint['error'] ) ) : ?>
			<small class="ash-nazg-text-error"><?php echo esc_html( $endpoint['error'] ); ?></small>
		<?php else : ?>
			<small class="ash-nazg-text-light">—</small>
		<?php endif; ?>
	</td>
</tr>
