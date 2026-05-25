<?php
/**
 * Submission → webhook delivery bridge.
 *
 * Subscribes to the existing `perform_after_submission` hook (added
 * in Phase 3a alongside the Mailer) and enqueues one delivery row
 * per active webhook for the form. Dispatch is asynchronous: the
 * submission handler returns immediately, the user lands on the
 * success page, and the dispatcher picks the queue up either on the
 * next every-minute tick or via the single-event "right now" hook
 * we schedule alongside the enqueue.
 *
 * Conditional delivery + field mapping (Phase 6d) plug in here too —
 * for now this just gates on `is_active` and enqueues unconditionally.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Webhooks;

defined( 'ABSPATH' ) || exit;

/**
 * Hook bridge between submission save and webhook queue.
 */
final class SubmissionListener {

	private Repository $webhooks;
	private DeliveryRepository $deliveries;
	private ConditionEvaluator $conditions;

	public function __construct( Repository $webhooks, DeliveryRepository $deliveries, ?ConditionEvaluator $conditions = null ) {
		$this->webhooks   = $webhooks;
		$this->deliveries = $deliveries;
		$this->conditions = $conditions ?? new ConditionEvaluator();
	}

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'perform_after_submission', [ $this, 'on_submission' ], 20, 4 );
	}

	/**
	 * Enqueue one delivery row per active webhook attached to the
	 * submitted form, then schedule a single cron event for "right
	 * now" so dispatch happens within seconds (rather than waiting
	 * for the every-minute tick).
	 *
	 * @param int                  $submission_id Newly inserted submission id.
	 * @param string               $form_id       Form UUID.
	 * @param array<string, mixed> $clean         Sanitised values (unused here, payload is rebuilt at dispatch time).
	 * @param array<string, mixed> $definition    Form definition (unused here).
	 * @return void
	 */
	public function on_submission( int $submission_id, string $form_id, array $clean, array $definition ): void {
		unset( $definition ); // Reserved for future use (field-type aware condition checks).

		$active_webhooks = $this->webhooks->find_active_for_form( $form_id );
		if ( empty( $active_webhooks ) ) {
			return;
		}

		$queued_anything = false;
		foreach ( $active_webhooks as $webhook ) {
			// Per-webhook trigger condition (Phase 6d). An unconfigured
			// or empty condition reads as "always fire"; a misconfigured
			// one (unknown operator, missing field name) fails closed
			// from inside the evaluator.
			if ( ! $this->conditions->should_fire( $webhook, $clean ) ) {
				continue;
			}

			$delivery_id = $this->deliveries->enqueue( (int) $webhook['id'], $submission_id );
			if ( null !== $delivery_id ) {
				$queued_anything = true;
			}
		}

		// Trigger a single-event cron run a few seconds out so the
		// dispatcher fires within seconds instead of waiting for the
		// every-minute recurring tick. We don't guard on
		// `wp_next_scheduled` because the every-minute schedule is
		// always queued for the dispatcher hook — the guard would
		// always trip and the single-event would never fire. WP-Cron
		// dedupes identical (hook + timestamp + args) pairs by
		// itself; two submissions in the same second collapse to one
		// event, and submissions in different seconds queue separate
		// events that the atomic claim in DeliveryRepository handles
		// without double-sending.
		if ( $queued_anything ) {
			wp_schedule_single_event( time() + 5, Dispatcher::CRON_HOOK );
		}
	}
}
