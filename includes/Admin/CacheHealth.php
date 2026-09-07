<?php
/**
 * Site Health check: is a page with a form actually being cached?
 *
 * Until 1.14.0 every page holding a Flinkform form excluded itself from
 * the full-page cache, which cost roughly 0.7 s of server time per view
 * on precisely the pages a site wants to convert on. That was invisible
 * from the WordPress admin — you had to fetch the page yourself and read
 * the cache plugin's HTML comment at the bottom.
 *
 * This test makes it visible, in both directions: it says so when a form
 * page is excluded again, and it says so when there is no page cache to
 * begin with (in which case nothing here is broken, there is just nothing
 * to measure).
 *
 * @package Flinkform
 * @since 1.14.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Admin;

defined( 'ABSPATH' ) || exit;

use Flinkform\Forms\Indexer;

/**
 * Registers and runs the "form pages are cacheable" Site Health test.
 */
final class CacheHealth {

	/**
	 * Site Health test id.
	 *
	 * @var string
	 */
	private const TEST_ID = 'flinkform_page_cache';

	/**
	 * Response headers that mean "a cache handled this request".
	 *
	 * Values are matched case-insensitively as substrings of the header
	 * value, so `x-cache: HIT from …` counts.
	 *
	 * @var array<string, array<int, string>>
	 */
	private const HIT_HEADERS = [
		'x-litespeed-cache'      => [ 'hit' ],
		'x-cache'                => [ 'hit' ],
		'x-cache-status'         => [ 'hit' ],
		'cf-cache-status'        => [ 'hit' ],
		'x-proxy-cache'          => [ 'hit' ],
		'x-nananana'             => [ 'batcache' ],
		'x-cache-enabled'        => [ 'true' ],
		'x-wp-cf-super-cache'    => [ 'cache' ],
	];

	/**
	 * Hook the test in.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'site_status_tests', [ $this, 'add_test' ] );
	}

	/**
	 * Register the async test.
	 *
	 * @param array<string, mixed> $tests Site Health test registry.
	 * @return array<string, mixed>
	 */
	public function add_test( array $tests ): array {
		$tests['async'][ self::TEST_ID ] = [
			'label'     => __( 'Flinkform form pages can be cached', 'flinkform' ),
			'test'      => [ $this, 'run' ],
			'has_rest'  => false,
			'async_direct_test' => [ $this, 'run' ],
		];

		return $tests;
	}

	/**
	 * Run the test.
	 *
	 * @return array<string, mixed> Site Health result array.
	 */
	public function run(): array {
		$result = [
			'label'       => __( 'Flinkform form pages can be cached', 'flinkform' ),
			'status'      => 'good',
			'badge'       => [
				'label' => __( 'Performance', 'flinkform' ),
				'color' => 'blue',
			],
			'description' => '',
			'actions'     => '',
			'test'        => self::TEST_ID,
		];

		$url = $this->sample_form_url();
		if ( '' === $url ) {
			$result['status']      = 'good';
			$result['label']       = __( 'No published form pages to check', 'flinkform' );
			$result['description'] = '<p>' . esc_html__( 'Flinkform did not find a published page containing a form, so there is nothing to measure yet.', 'flinkform' ) . '</p>';
			return $result;
		}

		$probe = $this->probe( $url );

		if ( 'error' === $probe['verdict'] ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'Could not check whether form pages are cached', 'flinkform' );
			$result['description'] = '<p>' . sprintf(
				/* translators: %s: error message from the loopback request. */
				esc_html__( 'Flinkform could not fetch one of its own form pages: %s. This says nothing about your caching — only that this check could not run.', 'flinkform' ),
				'<code>' . esc_html( $probe['detail'] ) . '</code>'
			) . '</p>';
			return $result;
		}

		if ( 'excluded' === $probe['verdict'] ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'Form pages are excluded from the page cache', 'flinkform' );
			$result['description'] = '<p>' . sprintf(
				/* translators: %s: URL of the checked page. */
				esc_html__( 'The page %s tells caching plugins not to cache it. Every visit to it therefore runs the full WordPress stack, which typically costs a few hundred milliseconds of extra server time.', 'flinkform' ),
				'<code>' . esc_html( $url ) . '</code>'
			) . '</p><p>' . esc_html__( 'Since version 1.14.0 Flinkform itself does not do this any more. Something else on the page does: another plugin, a snippet, or the flinkform_render_challenge_inline filter set to true.', 'flinkform' ) . '</p>';
			return $result;
		}

		if ( 'cached' === $probe['verdict'] ) {
			$result['label']       = __( 'Form pages are being cached', 'flinkform' );
			$result['description'] = '<p>' . sprintf(
				/* translators: 1: URL of the checked page, 2: name of the response header that proved it. */
				esc_html__( 'The page %1$s was served from a page cache (%2$s). Flinkform loads its spam challenge after the page, so caching and spam protection both work.', 'flinkform' ),
				'<code>' . esc_html( $url ) . '</code>',
				'<code>' . esc_html( $probe['detail'] ) . '</code>'
			) . '</p>';
			return $result;
		}

		// Nothing said "cached", nothing said "excluded". Most likely there
		// is no page cache at all, but a loopback request is not proof: many
		// caches deliberately bypass requests coming from the server itself.
		$result['label']       = __( 'No page cache detected on form pages', 'flinkform' );
		$result['description'] = '<p>' . sprintf(
			/* translators: %s: URL of the checked page. */
			esc_html__( 'Flinkform found nothing blocking the cache on %s, and no sign that a cache answered either. Either no page caching is active, or your cache skips requests that come from the server itself.', 'flinkform' ),
			'<code>' . esc_html( $url ) . '</code>'
		) . '</p><p>' . esc_html__( 'To check for certain, open a form page in a private browser window twice and look at the end of the page source for your caching plugin\'s comment.', 'flinkform' ) . '</p>';

		return $result;
	}

	/**
	 * Fetch the page twice and classify the answer.
	 *
	 * Twice, because the first request may be the one that fills the cache —
	 * asking only once would report a miss on a perfectly healthy setup.
	 *
	 * @param string $url Page to fetch.
	 * @return array{verdict: string, detail: string}
	 */
	private function probe( string $url ): array {
		$response = null;

		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			$response = wp_remote_get(
				$url,
				[
					'timeout'     => 10,
					'sslverify'   => false,
					'redirection' => 2,
					// No cookies: a logged-in request is never cached, and
					// this check has to see what an anonymous visitor sees.
					'cookies'     => [],
					'headers'     => [ 'Cache-Control' => 'no-transform' ],
				]
			);

			if ( is_wp_error( $response ) ) {
				return [
					'verdict' => 'error',
					'detail'  => $response->get_error_message(),
				];
			}
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 400 ) {
			return [
				'verdict' => 'error',
				'detail'  => sprintf( 'HTTP %d', $code ),
			];
		}

		$body = (string) wp_remote_retrieve_body( $response );

		// The exclusion marker beats everything: some caching plugins print
		// their "cannot cache" comment AND a cache-status header.
		if ( false !== stripos( $body, 'DONOTCACHEPAGE' ) ) {
			return [
				'verdict' => 'excluded',
				'detail'  => 'DONOTCACHEPAGE',
			];
		}

		foreach ( self::HIT_HEADERS as $header => $needles ) {
			$value = wp_remote_retrieve_header( $response, $header );
			if ( ! is_string( $value ) || '' === $value ) {
				continue;
			}
			foreach ( $needles as $needle ) {
				if ( false !== stripos( $value, $needle ) ) {
					return [
						'verdict' => 'cached',
						'detail'  => $header . ': ' . $value,
					];
				}
			}
		}

		// Several popular caches leave no header at all and only announce
		// themselves in an HTML comment at the very end of the document.
		if ( preg_match( '/(cached@\d+|Performance optimized by|Cached page generated by|Page (?:supported )?[Cc]ached by|Page Caching using)/', $body, $match ) ) {
			return [
				'verdict' => 'cached',
				'detail'  => trim( $match[0] ),
			];
		}

		return [
			'verdict' => 'unknown',
			'detail'  => '',
		];
	}

	/**
	 * Permalink of one published page that contains a form.
	 *
	 * @return string Empty string when there is none.
	 */
	private function sample_form_url(): string {
		foreach ( ( new Indexer() )->all() as $form ) {
			foreach ( (array) ( $form['sources'] ?? [] ) as $source ) {
				if ( 'publish' !== ( $source['post_status'] ?? '' ) ) {
					continue;
				}
				$permalink = get_permalink( (int) ( $source['post_id'] ?? 0 ) );
				if ( is_string( $permalink ) && '' !== $permalink ) {
					return $permalink;
				}
			}
		}

		return '';
	}
}
