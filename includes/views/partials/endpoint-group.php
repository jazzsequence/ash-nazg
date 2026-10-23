<?php
/**
 * One API endpoint group: heading and results table.
 *
 * Each endpoint row comes from the shared endpoint-row.php partial.
 *
 * @package Pantheon\AshNazg
 *
 * @var string $group     Group name.
 * @var array  $endpoints List of endpoint results, one per endpoint in the group.
 * @var int    $cached_at Fallback Unix timestamp for rows without their own.
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
			<?php foreach ( $endpoints as $endpoint ) : // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Consumed by the included row partial. ?>
				<?php require ASH_NAZG_PLUGIN_DIR . 'includes/views/partials/endpoint-row.php'; ?>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
