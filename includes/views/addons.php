<?php
/**
 * Addons management page template.
 *
 * @package Pantheon\AshNazg
 */

namespace Pantheon\AshNazg\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Manage Pantheon site addons such as Redis, Solr, and other available services.', 'ash-nazg' ); ?>
	</p>

	<?php if ( $message ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $error ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo wp_kses_post( $error ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $api_error ) : ?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'API Error:', 'ash-nazg' ); ?></strong>
				<?php echo esc_html( $api_error->get_error_message() ); ?>
			</p>
		</div>
	<?php elseif ( empty( $addons ) ) : ?>
		<div class="notice notice-info">
			<p><?php esc_html_e( 'No addons available for this site.', 'ash-nazg' ); ?></p>
		</div>
	<?php else : ?>
		<form method="post" action="">
			<?php wp_nonce_field( 'ash_nazg_update_addons', 'ash_nazg_addons_nonce' ); ?>

			<table class="form-table" role="presentation">
				<tbody>
					<?php foreach ( $addons as $addon ) : ?>
						<?php
						$addon_id = isset( $addon['id'] ) ? $addon['id'] : '';
						$addon_name = isset( $addon['name'] ) ? $addon['name'] : $addon_id;
						$addon_description = isset( $addon['description'] ) ? $addon['description'] : '';
						$is_enabled = isset( $addon['enabled'] ) && null !== $addon['enabled'] ? (bool) $addon['enabled'] : false;
						$state_unknown = ! isset( $addon['enabled'] ) || null === $addon['enabled'];
						?>
						<tr>
							<th scope="row">
								<label for="addon_<?php echo esc_attr( $addon_id ); ?>">
									<?php echo esc_html( $addon_name ); ?>
								</label>
							</th>
							<td>
								<div class="addon-toggle-wrapper">
									<label class="addon-toggle">
										<input
											type="checkbox"
											id="addon_<?php echo esc_attr( $addon_id ); ?>"
											name="addons[<?php echo esc_attr( $addon_id ); ?>]"
											value="on"
											<?php checked( $is_enabled ); ?>
										/>
										<span class="addon-toggle-slider"></span>
									</label>
									<span class="addon-status">
										<?php
										if ( $state_unknown ) {
											esc_html_e( 'Unknown', 'ash-nazg' );
										} else {
											echo $is_enabled ? esc_html__( 'Enabled', 'ash-nazg' ) : esc_html__( 'Disabled', 'ash-nazg' );
										}
										?>
									</span>
								</div>
								<?php if ( $addon_description ) : ?>
									<p class="description">
										<?php echo esc_html( $addon_description ); ?>
									</p>
								<?php endif; ?>
								<?php if ( $state_unknown ) : ?>
									<p class="description">
										<em><?php esc_html_e( 'Current state cannot be determined. Toggle to desired state and save.', 'ash-nazg' ); ?></em>
									</p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Changes', 'ash-nazg' ); ?>">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ash-nazg' ) ); ?>" class="button">
					<?php esc_html_e( 'Back to Dashboard', 'ash-nazg' ); ?>
				</a>
			</p>
		</form>
	<?php endif; ?>
</div>
