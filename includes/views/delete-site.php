<?php
/**
 * Delete Site admin page view.
 *
 * @package Pantheon\AshNazg
 */

?>

<div class="wrap ash-nazg-delete-site-page">
	<?php \Pantheon\AshNazg\Admin\render_pantheon_header( __( 'Delete Site', 'ash-nazg' ) ); ?>

	<!-- Site Info Card -->
	<div class="ash-nazg-card ash-nazg-card-full ash-nazg-mb-20">
		<h2><?php esc_html_e( 'Site to Delete', 'ash-nazg' ); ?></h2>
		<table class="widefat striped">
			<tr>
				<th><?php esc_html_e( 'Site Name', 'ash-nazg' ); ?></th>
				<td><code><?php echo esc_html( $site_name ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Site ID', 'ash-nazg' ); ?></th>
				<td><code><?php echo esc_html( $site_id ); ?></code></td>
			</tr>
		</table>
	</div>

	<!-- Deletion Form -->
	<div class="ash-nazg-card ash-nazg-card-full ash-nazg-mb-20"
		style="border: 3px solid #dc3232; background: #fff8f8;">
		<h2 style="color: #dc3232;">
			<?php esc_html_e( 'Final Confirmation Required', 'ash-nazg' ); ?>
		</h2>

		<form id="ash-nazg-delete-site-form">
			<p>
				<strong><?php esc_html_e( 'To confirm deletion, type "DELETE" in the box below:', 'ash-nazg' ); ?></strong>
			</p>

			<p>
				<input
					type="text"
					id="delete-confirmation"
					name="delete_confirmation"
					class="regular-text"
					placeholder="<?php esc_attr_e( 'Type DELETE here', 'ash-nazg' ); ?>"
					autocomplete="off"
					required
				>
			</p>

			<p style="text-align: center; margin: 40px 0;">
				<button
					type="submit"
					id="delete-site-button"
					class="ash-nazg-big-red-button"
					disabled
				>
					<?php esc_html_e( 'DELETE SITE', 'ash-nazg' ); ?>
				</button>
			</p>
		</form>

	</div>

	<!-- Cancel message (hidden by default, shown only on cancel) -->
	<div id="cancel-message" class="ash-nazg-card ash-nazg-card-full" style="display: none; margin-top: 20px; border: 3px solid #46b450; background: #f7fcf7;">
		<p style="text-align: center; font-size: 24px; margin: 20px 0;">
			<strong><?php esc_html_e( '😅 Whew! That was a close one!', 'ash-nazg' ); ?></strong>
			<br>
			<span style="font-size: 18px;"><?php esc_html_e( 'Deletion cancelled. Your site is safe.', 'ash-nazg' ); ?></span>
		</p>
	</div>

	<!-- Escape Route Link -->
	<p style="text-align: center; margin-top: 20px;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ash-nazg' ) ); ?>" class="button button-large">
			← <?php esc_html_e( 'Back to Dashboard', 'ash-nazg' ); ?>
		</a>
	</p>
</div>
