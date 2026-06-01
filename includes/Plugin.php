<?php
/**
 * Plugin bootstrap class.
 *
 * Single entry point for runtime initialisation. Phase 0 keeps init() empty
 * on purpose — the surface area lives here so subsequent phases can wire
 * block registration, REST routes, admin pages, etc. without touching the
 * main plugin file again.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin runtime singleton.
 */
final class Plugin {

	private static ?self $instance = null;

	/**
	 * Get (or create) the shared instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boot subsystems.
	 *
	 * Called once on `plugins_loaded`. Wires the block registry, the
	 * submission handler and the admin menu — every other PerForm
	 * capability builds on top of these three.
	 *
	 * @return void
	 */
	public function init(): void {
		// Auto-migrate the DB schema when the installed version is
		// older than the bundled one. Covers the file-update path
		// (FTP upload) where register_activation_hook never fires.
		// dbDelta inside Schema::create() is idempotent, so the
		// upgrade is safe to call on every page load — but the
		// option-version check keeps the actual SQL off the hot
		// path once the install is current.
		$installed_version = (string) get_option( Database\Schema::OPTION_DB_VERSION, '0' );
		if ( $installed_version !== Database\Schema::DB_VERSION ) {
			Database\Schema::create();
		}

		( new Blocks\Registry() )->register();

		// Forms indexer registers its cache-invalidation hooks on both
		// front and back ends — a save_post during a REST request needs
		// to invalidate too.
		( new Forms\Indexer() )->register();

		(
			new Submissions\Handler(
				new Forms\Locator(),
				new Submissions\Repository()
			)
		)->register();

		// Notifications subscribe to perform_after_submission. Registered
		// unconditionally — even REST-context saves should mail the admin.
		( new Notifications\Mailer() )->register();

		// Webhook subsystem (Phase 6). Three pieces:
		//
		//   1. REST controller — editor-facing CRUD via /perform/v1/webhooks
		//   2. Dispatcher — drains the delivery queue on the cron hook
		//      and registers the every-minute schedule. Must run on both
		//      front and back ends because the cron hook can fire from
		//      either context.
		//   3. SubmissionListener — bridges perform_after_submission
		//      into the delivery queue + schedules a single-event cron
		//      tick for fast first dispatch.
		//
		// Wired up as a unit so the wp_clear_scheduled_hook in
		// Deactivator stays the only place that touches cron state
		// outside of normal flow.
		$webhook_repo     = new Webhooks\Repository();
		$delivery_repo    = new Webhooks\DeliveryRepository();
		$webhook_deliverer = new Webhooks\Deliverer( new Submissions\Repository() );

		( new Webhooks\RestController( $webhook_repo, $webhook_deliverer ) )->register();
		( new Webhooks\Dispatcher( $webhook_repo, $delivery_repo, $webhook_deliverer ) )->register();
		( new Webhooks\SubmissionListener( $webhook_repo, $delivery_repo ) )->register();

		// Self-heal the dispatcher schedule when the plugin was
		// already active before the Phase-6 update landed. Activator
		// runs once on the activation click; an FTP-update of files
		// for an already-active plugin would otherwise leave the
		// every-minute schedule unregistered. Idempotent — the if
		// guard means the hot path costs one db get_option lookup
		// (wp_next_scheduled hits the cron transient).
		if ( ! wp_next_scheduled( Webhooks\Dispatcher::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, Webhooks\Dispatcher::CRON_SCHEDULE, Webhooks\Dispatcher::CRON_HOOK );
		}

		// SMTP transport (Phase A-b). The Transport itself defers
		// its actual hook registrations to the `init` action, so
		// it's safe to instantiate here on plugins_loaded — see
		// the class docblock for the i18n-timing rationale.
		( new Smtp\Transport() )->register();

		// GDPR / DSGVO privacy integration — privacy-policy content,
		// personal data exporter + eraser for WP's built-in privacy
		// tools (Tools > Export/Erase Personal Data).
		Privacy::register();

		if ( is_admin() ) {
			( new Admin\Menu() )->register();
		}
	}

	/**
	 * Singletons are not cloneable.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Singletons are not constructible from outside.
	 */
	private function __construct() {}
}
