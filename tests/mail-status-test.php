#!/usr/bin/env php
<?php
/**
 * Mail status per submission, MailHealth verdicts, and the trash filter
 * (1.15.0).
 *
 * The failure these guard against is silent: a host whose mail setup is
 * broken loses every notification, the submissions pile up unread, and
 * nobody knows. Now each row says whether its mail went out, Site Health
 * says so for the site, and the trash never leaks into lists or the Pro
 * CSV export, which read through the same Repository filters.
 *
 * Run:  php tests/mail-status-test.php
 *
 * @package Flinkform
 */

declare( strict_types = 1 );

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/../' );
	}
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'MINUTE_IN_SECONDS', 60 );

	$GLOBALS['options'] = [ 'flinkform_db_version' => '3' ];
	$GLOBALS['mail_ok'] = true;
	$GLOBALS['hooks']   = [];

	function get_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
	function update_option( $name, $value, $autoload = null ) { $GLOBALS['options'][ $name ] = $value; return true; }
	function delete_transient( $k ) { return true; }
	function add_action( $hook, $cb, $p = 10, $a = 1 ) { $GLOBALS['hooks'][ $hook ][] = $cb; }
	function remove_action( $hook, $cb, $p = 10 ) { $GLOBALS['hooks'][ $hook ] = array_filter( $GLOBALS['hooks'][ $hook ] ?? [], static fn ( $c ) => $c !== $cb ); }
	function add_filter( ...$a ) { add_action( ...$a ); }
	function remove_filter( ...$a ) { remove_action( ...$a ); }
	function do_action( $hook, ...$args ) { foreach ( $GLOBALS['hooks'][ $hook ] ?? [] as $cb ) { $cb( ...$args ); } }
	function apply_filters( $hook, $value, ...$rest ) { return $value; }
	function __( $t, $d = '' ) { return $t; }

	class Fake_Error { public function __construct( private string $m ) {} public function get_error_message() { return $this->m; } }
	function wp_mail( $to, $subject, $body, $headers = [], $attachments = [] ) {
		if ( ! $GLOBALS['mail_ok'] ) {
			do_action( 'wp_mail_failed', new Fake_Error( 'Could not instantiate mail function.' ) );
			return false;
		}
		return true;
	}

	/** Records UPDATE calls and the WHERE clauses queries are built with. */
	class Fake_Wpdb {
		public string $prefix = 'wp_';
		public array $updates = [];
		public array $queries = [];
		public function update( $table, $data, $where, $f = null, $wf = null ) { $this->updates[] = [ $data, $where ]; return 1; }
		public function prepare( $sql, ...$args ) { $args = is_array( $args[0] ?? null ) ? $args[0] : $args; return vsprintf( str_replace( [ '%s', '%d' ], [ "'%s'", '%d' ], $sql ), $args ); }
		public function get_var( $sql ) { $this->queries[] = $sql; return 0; }
		public function query( $sql ) { $this->queries[] = $sql; return 2; }
		public function get_col( $sql ) { $this->queries[] = $sql; return []; }
		public function esc_like( $t ) { return $t; }
	}
	$GLOBALS['wpdb'] = new Fake_Wpdb();

	require_once __DIR__ . '/../includes/Database/Schema.php';
	require_once __DIR__ . '/../includes/Submissions/Repository.php';
	require_once __DIR__ . '/../includes/Notifications/MailHealth.php';

	$passed = 0;
	$failed = 0;
	function check( string $label, bool $ok, string $detail = '' ): void {
		global $passed, $failed;
		if ( $ok ) { ++$passed; return; }
		++$failed;
		echo "FAIL: $label" . ( '' !== $detail ? " — $detail" : '' ) . "\n";
	}

	use Flinkform\Notifications\MailHealth;
	use Flinkform\Submissions\Repository;

	// --- MailHealth verdicts ------------------------------------------------
	$v = static fn ( array $recent ) => MailHealth::verdict( [ 'recent' => $recent, 'last_ok' => 0, 'last_failed' => 0, 'last_error' => '' ] );
	check( 'nothing sent yet = none', 'none' === $v( [] ) );
	check( 'all good = good', 'good' === $v( [ 1, 1, 1 ] ) );
	check( 'last three failed = all_failed', 'all_failed' === $v( [ 1, 1, 0, 0, 0 ] ) );
	check( 'recovered since = good', 'good' === $v( [ 0, 0, 0, 1 ] ) );
	check( 'latest failed, others fine = some_failed', 'some_failed' === $v( [ 1, 1, 0 ] ) );

	// --- record(): rolling window, error kept, no addresses -----------------
	for ( $i = 0; $i < 25; $i++ ) {
		MailHealth::record( true );
	}
	MailHealth::record( false, 'SMTP connect() failed.' );
	$state = MailHealth::get();
	check( 'window capped at 20', 20 === count( $state['recent'] ) );
	check( 'last error kept', 'SMTP connect() failed.' === $state['last_error'] );
	MailHealth::record( false, 'SMTP Error: The following recipients failed: visitor@example.org' );
	check( 'addresses in error texts are masked', ! str_contains( MailHealth::get()['last_error'], 'visitor@' ) && str_contains( MailHealth::get()['last_error'], 'recipients failed' ) );
	check( 'stored state holds no mail addresses', ! str_contains( serialize( $GLOBALS['options'][ MailHealth::OPTION ] ), '@' ) );

	// --- Repository: mail status ------------------------------------------
	$repo = new Repository();
	check( 'set_mail_status accepts sent', $repo->set_mail_status( 5, 'sent' ) );
	check( 'set_mail_status writes the column', [ [ 'mail_status' => 'sent' ], [ 'id' => 5 ] ] === $GLOBALS['wpdb']->updates[0] );
	check( 'set_mail_status rejects unknown values', ! $repo->set_mail_status( 5, 'bogus' ) );
	$GLOBALS['options']['flinkform_db_version'] = '2';
	check( 'no write before the schema upgrade', ! $repo->set_mail_status( 5, 'sent' ) );
	$GLOBALS['options']['flinkform_db_version'] = '3';

	// --- Repository: trash filter -------------------------------------------
	$GLOBALS['wpdb']->queries = [];
	$repo->count( [] );
	check( 'default lists leave the trash out', str_contains( end( $GLOBALS['wpdb']->queries ), 'trashed_at IS NULL' ) );
	$repo->count( [ 'status' => 'unread' ] );
	check( 'unread count leaves the trash out', str_contains( end( $GLOBALS['wpdb']->queries ), 'trashed_at IS NULL' ) );
	$repo->count( [ 'trashed' => 'only' ] );
	check( 'trash view lists only the trash', str_contains( end( $GLOBALS['wpdb']->queries ), 'trashed_at IS NOT NULL' ) );
	$repo->count( [ 'trashed' => 'any' ] );
	check( 'any = no trash clause', ! str_contains( end( $GLOBALS['wpdb']->queries ), 'trashed_at' ) );
	$repo->count( [ 'mail_status' => 'failed' ] );
	check( 'mail failed filter', str_contains( end( $GLOBALS['wpdb']->queries ), "mail_status = 'failed'" ) );
	$GLOBALS['options']['flinkform_db_version'] = '2';
	$repo->count( [] );
	check( 'before the upgrade: no new columns in queries', ! str_contains( end( $GLOBALS['wpdb']->queries ), 'trashed_at' ) );
	check( 'before the upgrade: trash does nothing', 0 === $repo->trash_many( [ 1, 2 ] ) );
	$GLOBALS['options']['flinkform_db_version'] = '3';

	check( 'trash_many moves rows', 2 === $repo->trash_many( [ 1, 2 ] ) );
	check( 'trash keeps read/unread (sets only trashed_at)', str_contains( end( $GLOBALS['wpdb']->queries ), 'SET trashed_at =' ) && ! str_contains( end( $GLOBALS['wpdb']->queries ), 'status' ) );
	check( 'restore clears trashed_at', 2 === $repo->restore_many( [ 1, 2 ] ) && str_contains( end( $GLOBALS['wpdb']->queries ), 'trashed_at = NULL' ) );

	echo "\n";
	if ( $failed > 0 ) {
		echo "$failed FAILED, $passed passed.\n";
		exit( 1 );
	}
	echo "All $passed tests passed.\n";
}
