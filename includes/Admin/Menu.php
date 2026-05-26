<?php
/**
 * PerForm admin menu.
 *
 * Owns the top-level "PerForm" menu and dispatches every PerForm admin
 * page registration. Each page is constructed lazily inside its menu
 * callback so unused pages don't pay any initialisation cost.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the PerForm admin into wp-admin.
 */
final class Menu {

	/**
	 * Slug of the top-level menu page (and the default submenu —
	 * the Submissions list, since that's what site operators look at
	 * most often).
	 */
	public const PARENT_SLUG = 'perform-submissions';

	/**
	 * Capability required to access any PerForm admin page. `manage_options`
	 * keeps the bar at "site admin"; later phases (settings, webhooks) may
	 * introduce finer-grained capabilities.
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'register_pages' ] );
		add_action( 'admin_init', [ $this, 'dispatch_actions' ] );
	}

	/**
	 * Add the menu entries.
	 *
	 * @return void
	 */
	public function register_pages(): void {
		add_menu_page(
			__( 'PerForm', 'perform-forms' ),
			__( 'PerForm', 'perform-forms' ),
			self::CAPABILITY,
			self::PARENT_SLUG,
			[ $this, 'render_submissions_page' ],
			'dashicons-feedback',
			26
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Submissions', 'perform-forms' ),
			__( 'Submissions', 'perform-forms' ),
			self::CAPABILITY,
			self::PARENT_SLUG,
			[ $this, 'render_submissions_page' ]
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Forms', 'perform-forms' ),
			__( 'Forms', 'perform-forms' ),
			self::CAPABILITY,
			FormsPage::SLUG,
			[ $this, 'render_forms_page' ]
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Webhook Log', 'perform-forms' ),
			__( 'Webhook Log', 'perform-forms' ),
			self::CAPABILITY,
			WebhookLogPage::SLUG,
			[ $this, 'render_webhook_log_page' ]
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'SMTP', 'perform-forms' ),
			__( 'SMTP', 'perform-forms' ),
			self::CAPABILITY,
			SmtpPage::SLUG,
			[ $this, 'render_smtp_page' ]
		);
	}

	/**
	 * Render the Submissions page (list or detail depending on the URL).
	 *
	 * @return void
	 */
	public function render_submissions_page(): void {
		( new SubmissionsPage() )->render();
	}

	/**
	 * Render the Forms overview page.
	 *
	 * @return void
	 */
	public function render_forms_page(): void {
		( new FormsPage() )->render();
	}

	/**
	 * Render the Webhook Log page.
	 *
	 * @return void
	 */
	public function render_webhook_log_page(): void {
		( new WebhookLogPage() )->render();
	}

	/**
	 * Render the SMTP settings page.
	 *
	 * @return void
	 */
	public function render_smtp_page(): void {
		( new SmtpPage() )->render();
	}

	/**
	 * Handle GET/POST actions for PerForm pages BEFORE wp-admin renders
	 * its header (so we can redirect cleanly after bulk actions etc.).
	 *
	 * @return void
	 */
	public function dispatch_actions(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page identifier, not security boundary.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		switch ( $page ) {
			case self::PARENT_SLUG:
				( new SubmissionsPage() )->dispatch();
				break;
			case FormsPage::SLUG:
				( new FormsPage() )->dispatch();
				break;
			case SmtpPage::SLUG:
				( new SmtpPage() )->dispatch();
				break;
		}
	}
}
