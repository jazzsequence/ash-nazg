<?php
/**
 * The Workspace row of the dashboard's site and user table.
 *
 * Rendered inline by the dashboard and, once workspaces have loaded, by the
 * AJAX handler that replaces the placeholder row.
 *
 * @package Pantheon\AshNazg
 *
 * @var array|false|null $user_organizations Org-shaped workspace memberships, or false/null if unavailable.
 * @var string           $primary_org_id     The saved primary workspace ID, if any.
 * @var string           $primary_org_name   The saved primary workspace name, if any.
 * @var array|null       $site_info          Site info, used to default to the site's workspace.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php if ( false === $user_organizations ) : ?>
<tr id="ash-nazg-workspace-row" class="ash-nazg-workspace-pending">
	<th><?php esc_html_e( 'Workspace', 'ash-nazg' ); ?></th>
	<td><small class="ash-nazg-text-muted"><?php esc_html_e( 'Loading…', 'ash-nazg' ); ?></small></td>
</tr>
<?php elseif ( $user_organizations ) : ?>
	<?php
	// Determine display org: stored preference → site owner org → first in list.
	$display_org_id = $primary_org_id;
	$display_org_name = $primary_org_name;
	if ( ! $display_org_id ) {
		$site_org_id = $site_info['organization'] ?? null;
		if ( $site_org_id ) {
			foreach ( $user_organizations as $membership ) {
				if ( ( $membership['organization']['id'] ?? '' ) === $site_org_id ) {
					$display_org_id = $site_org_id;
					$display_org_name = $membership['organization']['profile']['name'] ?? '';
					break;
				}
			}
		}
	}
	if ( ! $display_org_id && ! empty( $user_organizations[0]['organization']['id'] ) ) {
		$display_org_id = $user_organizations[0]['organization']['id'];
		$display_org_name = $user_organizations[0]['organization']['profile']['name'] ?? '';
	}
	?>
<tr>
	<th><?php esc_html_e( 'Workspace', 'ash-nazg' ); ?></th>
	<td>
		<span id="ash-nazg-org-display" class="ash-nazg-editable">
			<span><?php echo esc_html( $display_org_name ); ?></span>
			<?php if ( count( $user_organizations ) > 1 ) : ?>
				<a href="#" id="ash-nazg-org-edit" class="ash-nazg-edit-link" title="<?php esc_attr_e( 'Change workspace', 'ash-nazg' ); ?>">
					<span class="dashicons dashicons-edit" aria-hidden="true"></span>
				</a>
			<?php endif; ?>
		</span>
		<?php if ( count( $user_organizations ) > 1 ) : ?>
		<span id="ash-nazg-org-select-wrap" class="ash-nazg-hidden">
			<select id="ash-nazg-org-select" aria-label="<?php esc_attr_e( 'Select workspace', 'ash-nazg' ); ?>">
				<?php foreach ( $user_organizations as $membership ) : ?>
					<?php
					$org_id   = $membership['organization']['id'] ?? '';
					$org_name = $membership['organization']['profile']['name'] ?? '';
					?>
					<option value="<?php echo esc_attr( $org_id ); ?>" data-name="<?php echo esc_attr( $org_name ); ?>"
						<?php selected( $org_id, $display_org_id ); ?>>
						<?php echo esc_html( $org_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="button" id="ash-nazg-org-save" class="button button-primary button-small"
				data-nonce="<?php echo esc_attr( wp_create_nonce( 'ash_nazg_save_primary_org' ) ); ?>">
				<?php esc_html_e( 'Save', 'ash-nazg' ); ?>
			</button>
			<button type="button" id="ash-nazg-org-cancel" class="button button-secondary button-small">
				<?php esc_html_e( 'Cancel', 'ash-nazg' ); ?>
			</button>
		</span>
		<?php endif; ?>
	</td>
</tr>
<?php endif; // user_organizations. ?>
