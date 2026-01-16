<?php
/**
 * Workflows management page template.
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
	<?php render_pantheon_header( get_admin_page_title() ); ?>

	<p class="description">
		<?php esc_html_e( 'Trigger workflows to perform automated tasks such as installing plugins and configuring services.', 'ash-nazg' ); ?>
	</p>

	<?php if ( $message ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
			<?php if ( $workflow_status && ! is_wp_error( $workflow_status ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Workflow ID:', 'ash-nazg' ); ?></strong>
					<?php echo esc_html( $workflow_id ); ?>
				</p>
				<?php if ( isset( $workflow_status['description'] ) ) : ?>
					<p>
						<strong><?php esc_html_e( 'Description:', 'ash-nazg' ); ?></strong>
						<?php echo esc_html( $workflow_status['description'] ); ?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $error ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $error ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! $site_id ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Unable to detect Pantheon site ID. This feature only works on Pantheon-hosted sites.', 'ash-nazg' ); ?></p>
		</div>
	<?php elseif ( empty( $workflows ) ) : ?>
		<div class="notice notice-info">
			<p><?php esc_html_e( 'No workflows available.', 'ash-nazg' ); ?></p>
		</div>
	<?php else : ?>
		<div class="workflows-container">
			<h2><?php esc_html_e( 'Available Workflows', 'ash-nazg' ); ?></h2>

			<p class="description">
				<strong><?php esc_html_e( 'Current Environment:', 'ash-nazg' ); ?></strong>
				<?php echo esc_html( $environment ); ?>
			</p>

			<?php foreach ( $workflows as $workflow ) : ?>
				<?php
				$workflow_id = isset( $workflow['id'] ) ? $workflow['id'] : '';
				$workflow_name = isset( $workflow['name'] ) ? $workflow['name'] : $workflow_id;
				$workflow_description = isset( $workflow['description'] ) ? $workflow['description'] : '';
				$allowed_envs = isset( $workflow['allowed_envs'] ) ? $workflow['allowed_envs'] : [ 'dev' ];
				$is_env_allowed = in_array( $environment, $allowed_envs, true );
				?>
				<div class="workflow-card <?php echo ! $is_env_allowed ? 'workflow-disabled' : ''; ?>">
					<h3><?php echo esc_html( $workflow_name ); ?></h3>

					<?php if ( $workflow_description ) : ?>
						<p class="description">
							<?php echo esc_html( $workflow_description ); ?>
						</p>
					<?php endif; ?>

					<?php if ( ! $is_env_allowed ) : ?>
						<p class="description">
							<strong><?php esc_html_e( 'Note:', 'ash-nazg' ); ?></strong>
							<?php
							printf(
								/* translators: %s: comma-separated list of environment names */
								esc_html__( 'This workflow can only be triggered on: %s', 'ash-nazg' ),
								esc_html( implode( ', ', $allowed_envs ) )
							);
							?>
						</p>
					<?php endif; ?>

					<form method="post" action="">
						<?php wp_nonce_field( 'ash_nazg_trigger_workflow', 'ash_nazg_workflows_nonce' ); ?>
						<input type="hidden" name="workflow_id" value="<?php echo esc_attr( $workflow_id ); ?>">

						<p class="submit">
							<input
								type="submit"
								name="submit"
								class="button button-primary"
								value="<?php esc_attr_e( 'Trigger Workflow', 'ash-nazg' ); ?>"
								<?php disabled( ! $is_env_allowed ); ?>
							>
						</p>
					</form>
				</div>
			<?php endforeach; ?>
		</div>

		<p class="submit">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ash-nazg' ) ); ?>" class="button">
				<?php esc_html_e( 'Back to Dashboard', 'ash-nazg' ); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
