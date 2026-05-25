<?php
/**
 * Webhook log admin page controller.
 *
 * Mounts the WebhookLogListTable inside the standard PerForm admin
 * area. Mirrors the FormsPage / SubmissionsPage pattern so the
 * three pages feel native to each other.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Admin;

use PerForm\Webhooks\DeliveryRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Webhook Log admin page.
 */
final class WebhookLogPage {

	public const SLUG = 'perform-webhook-log';

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render(): void {
		$table = new WebhookLogListTable( new DeliveryRepository() );
		$table->prepare_items();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'Webhook Log', 'perform-forms' ); ?>
			</h1>
			<p class="description" style="margin-top: 4px;">
				<?php esc_html_e( 'Every outbound webhook delivery dispatched by PerForm — one row per attempt. Use it to debug receivers, audit retries and verify the queue is draining.', 'perform-forms' ); ?>
			</p>

			<hr class="wp-header-end">

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
				<?php
				$table->search_box( __( 'Search webhooks', 'perform-forms' ), 'perform-webhook-search' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}
}
