<?php
/**
 * Site Health check: do Flinkform's notification mails go out? (1.15.0)
 *
 * Reads what Notifications\MailHealth recorded from real submissions. It
 * sends nothing itself: a test mail proves the setup at one moment, the
 * record shows what happened to the inquiries people actually sent.
 *
 * Honest in the wording: "accepted" is all WordPress can know. Whether a
 * mail reached the inbox (spam folder, SPF/DKIM) is outside its reach, so
 * the good result still says to check the inbox once.
 *
 * @package Flinkform
 * @since 1.15.0
 */

declare( strict_types = 1 );

namespace Flinkform\Admin;

defined( 'ABSPATH' ) || exit;

use Flinkform\Notifications\MailHealth;

/**
 * Registers and runs the "notification mails" Site Health test.
 */
final class MailHealthCheck {

	private const TEST_ID = 'flinkform_mail';

	/**
	 * @return void
	 */
	public function register(): void {
		add_filter( 'site_status_tests', [ $this, 'add_test' ] );
	}

	/**
	 * @param array<string, mixed> $tests
	 * @return array<string, mixed>
	 */
	public function add_test( array $tests ): array {
		$tests['direct'][ self::TEST_ID ] = [
			'label' => __( 'Flinkform notification mails', 'flinkform' ),
			'test'  => [ $this, 'run' ],
		];
		return $tests;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function run(): array {
		$state   = MailHealth::get();
		$verdict = MailHealth::verdict( $state );
		$check   = '<p>' . esc_html__( 'WordPress can only tell whether a mail was handed over for sending, not whether it arrived. Send one test submission and look in the inbox (and the spam folder) once.', 'flinkform' ) . '</p>';

		$result = [
			'label'       => __( 'Flinkform notification mails are being sent', 'flinkform' ),
			'status'      => 'good',
			'badge'       => [
				'label' => __( 'Flinkform', 'flinkform' ),
				'color' => 'blue',
			],
			'description' => $check,
			'actions'     => '',
			'test'        => self::TEST_ID,
		];

		if ( 'none' === $verdict ) {
			$result['label']       = __( 'Flinkform has not sent a notification mail yet', 'flinkform' );
			$result['description'] = '<p>' . esc_html__( 'Nothing to judge until the first submission comes in.', 'flinkform' ) . '</p>' . $check;
			return $result;
		}

		if ( 'good' === $verdict ) {
			return $result;
		}

		$error = '' !== $state['last_error']
			? '<p>' . sprintf(
				/* translators: %s: error message reported by WordPress when sending failed. */
				esc_html__( 'Last error: %s', 'flinkform' ),
				'<code>' . esc_html( $state['last_error'] ) . '</code>'
			) . '</p>'
			: '';
		$hint = '<p>' . esc_html__( 'Submissions are still saved and listed under Flinkform → Submissions, so nothing is lost. The usual cause is a host without a working mail setup; an SMTP plugin (or Flinkform Pro\'s SMTP setting) sending through a real mailbox fixes it.', 'flinkform' ) . '</p>';

		if ( 'all_failed' === $verdict ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'Flinkform notification mails are failing', 'flinkform' );
			$result['badge']['color'] = 'red';
		} else {
			$result['status']      = 'recommended';
			$result['label']       = __( 'Some Flinkform notification mails failed recently', 'flinkform' );
			$result['badge']['color'] = 'orange';
		}
		$result['description'] = $error . $hint;
		$result['actions']     = sprintf(
			'<p><a href="%s">%s</a></p>',
			esc_url( add_query_arg( [ 'page' => Menu::PARENT_SLUG, 'mail_status' => 'failed' ], admin_url( 'admin.php' ) ) ),
			esc_html__( 'Show submissions whose mail failed', 'flinkform' )
		);
		return $result;
	}
}
