<?php
/**
 * Plugin bootstrap class.
 *
 * Single entry point for runtime initialisation. Phase 0 keeps init() empty
 * on purpose — the surface area lives here so subsequent phases can wire
 * block registration, REST routes, admin pages, etc. without touching the
 * main plugin file again.
 *
 * @package Flinkform
 * @since 0.1.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform;

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
	 * submission handler and the admin menu — every other Flinkform
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

		// Notifications subscribe to flinkform_after_submission. Registered
		// unconditionally — even REST-context saves should mail the admin.
		( new Notifications\Mailer() )->register();

		// Webhooks are owned by Flinkform Pro (REST CRUD, cron dispatcher,
		// submission listener, the delivery tables + the Webhook Log page).
		// Pro wires the whole subsystem via the bridge's
		// flinkform_register_modules hook and owns its own DB schema + cron
		// lifecycle. The free core ships no webhook code.

		// SMTP transport is owned by Flinkform Pro — it registers the Transport
		// (phpmailer_init overrides + conflict detection) via the bridge's
		// flinkform_register_modules hook. The free core sends mail through the
		// WordPress default (wp_mail) transport.

		// GDPR / DSGVO privacy integration — privacy-policy content,
		// personal data exporter + eraser for WP's built-in privacy
		// tools (Tools > Export/Erase Personal Data).
		Privacy::register();

		// Submission retention / auto-purge (storage limitation). The cron
		// callback is registered here; the daily schedule is set on activation
		// and self-healed below for the file-only update path.
		( new Submissions\Retention() )->register();
		if ( ! wp_next_scheduled( Submissions\Retention::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', Submissions\Retention::CRON_HOOK );
		}

		if ( is_admin() ) {
			( new Admin\Menu() )->register();
		}

		/**
		 * Pro add-on foothold — the single, well-timed hook the Flinkform Pro
		 * add-on docks onto to wire its own subsystems.
		 *
		 * Fires once on `plugins_loaded`, after the free core has wired all of
		 * its own modules, so the add-on can rely on the core being ready. The
		 * add-on registers its listener from its main file (top-level), which
		 * runs before `plugins_loaded`, so it is already attached when this
		 * fires. With no add-on installed this is a no-op.
		 *
		 * This is the Pro counterpart to the hard wiring above. See
		 * includes/Bridge/README.md for the frozen bridge-layer contract.
		 *
		 * @since 0.2.0
		 */
		do_action( 'flinkform_register_modules' );
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
