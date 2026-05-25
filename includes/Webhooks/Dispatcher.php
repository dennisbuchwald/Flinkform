<?php
/**
 * Webhook dispatcher — drains the delivery queue.
 *
 * Runs on the `perform_dispatch_webhooks` cron hook. Pulls due
 * deliveries from `DeliveryRepository`, claims each row atomically
 * (so duplicate cron ticks can't double-send), invokes the
 * `Deliverer` for the actual HTTP request, and persists the result.
 *
 * Two cron triggers feed the hook:
 *
 *   1. Every-minute recurring schedule (registered in Plugin::init).
 *      Catches retry rows whose `next_retry_at` has passed.
 *   2. Single-event "right after this submission" scheduled by
 *      `SubmissionListener` so new deliveries dispatch within
 *      seconds, not minutes.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Webhooks;

defined( 'ABSPATH' ) || exit;

/**
 * Cron-driven webhook delivery orchestrator.
 */
final class Dispatcher {

	public const CRON_HOOK     = 'perform_dispatch_webhooks';
	public const CRON_SCHEDULE = 'perform_every_minute';

	/**
	 * Maximum deliveries processed per cron tick. Caps how much HTTP
	 * traffic a single PHP process can generate before yielding,
	 * keeps PHP-FPM workers responsive on busy sites.
	 */
	private const BATCH_SIZE = 25;

	private Repository $webhooks;
	private DeliveryRepository $deliveries;
	private Deliverer $deliverer;

	public function __construct( Repository $webhooks, DeliveryRepository $deliveries, Deliverer $deliverer ) {
		$this->webhooks   = $webhooks;
		$this->deliveries = $deliveries;
		$this->deliverer  = $deliverer;
	}

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'cron_schedules', [ $this, 'register_schedule' ] );
		add_action( self::CRON_HOOK, [ $this, 'dispatch_due_deliveries' ] );
	}

	/**
	 * Register a minute-resolution schedule. WordPress core doesn't
	 * ship one (the shortest built-in is `every_five_minutes`), but
	 * webhook responsiveness benefits from a tight tick — most
	 * receivers expect deliveries within seconds, not minutes.
	 *
	 * @param array<string, array<string, mixed>> $schedules Existing schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_schedule( array $schedules ): array {
		if ( ! isset( $schedules[ self::CRON_SCHEDULE ] ) ) {
			$schedules[ self::CRON_SCHEDULE ] = [
				'interval' => 60,
				'display'  => __( 'Every Minute (PerForm)', 'perform-forms' ),
			];
		}
		return $schedules;
	}

	/**
	 * Drain the delivery queue for this tick.
	 *
	 * For each due delivery: atomically claim → look up webhook →
	 * call deliverer → persist result with the right terminal status
	 * (success / failed / retrying).
	 *
	 * @return void
	 */
	public function dispatch_due_deliveries(): void {
		$due = $this->deliveries->find_due( self::BATCH_SIZE );

		foreach ( $due as $delivery ) {
			$id = (int) $delivery['id'];

			if ( ! $this->deliveries->claim( $id ) ) {
				continue; // Another worker grabbed it.
			}

			$webhook = $this->webhooks->find( (int) $delivery['webhook_id'] );
			if ( null === $webhook ) {
				// Webhook was deleted between enqueue and dispatch —
				// fail the row terminally so it doesn't keep getting
				// picked up. attempt counter is whatever it already
				// was; status flips to failed.
				$this->deliveries->mark_failure(
					$id,
					4, // force terminal (any attempt >= 4 in mark_failure transitions to 'failed')
					null,
					'Webhook configuration not found.'
				);
				continue;
			}

			if ( empty( $webhook['is_active'] ) ) {
				// Skip inactive webhooks but don't fail them — author
				// might be temporarily disabling them. Mark as failed
				// terminally so the row stops being picked up.
				$this->deliveries->mark_failure(
					$id,
					4,
					null,
					'Webhook is currently inactive.'
				);
				continue;
			}

			$result = $this->deliverer->deliver( $webhook, isset( $delivery['submission_id'] ) ? (int) $delivery['submission_id'] : null );
			$code   = $result['code'];
			$body   = $result['body'];

			if ( null !== $code && $code >= 200 && $code < 300 ) {
				$this->deliveries->mark_success( $id, $code, $body );
			} else {
				$this->deliveries->mark_failure( $id, (int) $delivery['attempt'], $code, $body );
			}
		}
	}
}
