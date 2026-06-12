<?php
/**
 * Flinkform admin menu.
 *
 * Owns the top-level "Flinkform" menu and dispatches every Flinkform admin
 * page registration. Each page is constructed lazily inside its menu
 * callback so unused pages don't pay any initialisation cost.
 *
 * @package Flinkform
 * @since 0.1.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the Flinkform admin into wp-admin.
 */
final class Menu {

	/**
	 * Slug of the top-level menu page (and the default submenu —
	 * the Submissions list, since that's what site operators look at
	 * most often).
	 */
	public const PARENT_SLUG = 'perffo-submissions';

	/**
	 * Capability required to access any Flinkform admin page. `manage_options`
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
		$submissions_hook = add_menu_page(
			__( 'Flinkform', 'flinkform' ),
			__( 'Flinkform', 'flinkform' ),
			self::CAPABILITY,
			self::PARENT_SLUG,
			[ $this, 'render_submissions_page' ],
			'dashicons-feedback',
			26
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Submissions', 'flinkform' ),
			__( 'Submissions', 'flinkform' ),
			self::CAPABILITY,
			self::PARENT_SLUG,
			[ $this, 'render_submissions_page' ]
		);

		$forms_hook = add_submenu_page(
			self::PARENT_SLUG,
			__( 'Forms', 'flinkform' ),
			__( 'Forms', 'flinkform' ),
			self::CAPABILITY,
			FormsPage::SLUG,
			[ $this, 'render_forms_page' ]
		);

		// The Webhook Log and SMTP pages are owned by Flinkform Pro, which
		// attaches its own submenus here via add_submenu_page( Menu::PARENT_SLUG, … ).

		// Enqueue page-specific admin styles via wp_add_inline_style() so no
		// raw <style> tags appear in the page output.
		if ( $submissions_hook ) {
			add_action( "admin_print_styles-{$submissions_hook}", [ $this, 'enqueue_submissions_styles' ] );
		}
		if ( $forms_hook ) {
			add_action( "admin_print_styles-{$forms_hook}", [ $this, 'enqueue_forms_styles' ] );
		}
	}

	/**
	 * Enqueue inline CSS for the Submissions admin page.
	 *
	 * @return void
	 */
	public function enqueue_submissions_styles(): void {
		wp_register_style( 'perffo-admin-submissions', false, [], FLINKFORM_VERSION );
		wp_enqueue_style( 'perffo-admin-submissions' );
		wp_add_inline_style( 'perffo-admin-submissions', SubmissionsPage::inline_css() );
	}

	/**
	 * Enqueue inline CSS for the Forms admin page.
	 *
	 * @return void
	 */
	public function enqueue_forms_styles(): void {
		wp_register_style( 'perffo-admin-forms', false, [], FLINKFORM_VERSION );
		wp_enqueue_style( 'perffo-admin-forms' );
		wp_add_inline_style( 'perffo-admin-forms', FormsPage::inline_css() );
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
	 * Handle GET/POST actions for Flinkform pages BEFORE wp-admin renders
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
		}
	}
}
