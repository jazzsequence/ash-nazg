<?php
/**
 * Endpoints status page tests.
 *
 * @package Pantheon\AshNazg
 */

use PHPUnit\Framework\TestCase;

/**
 * Test the Dashboard's API Endpoints Status diagnostics.
 *
 * Note: These are structural/pattern tests, consistent with this project's
 * existing test style for API endpoint coverage.
 */
class Test_Endpoints_Status extends TestCase {

	/**
	 * Nothing on the endpoints status page should be hardcoded as unavailable.
	 * Every row must be a real, live test - routed through v0 if v1 has no
	 * equivalent, never a static assumption about availability.
	 */
	public function test_no_hardcoded_unavailable_endpoints() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/endpoints-status.php';

		$this->assertFalse(
			function_exists( 'Pantheon\AshNazg\API\mark_endpoint_unavailable' ),
			'mark_endpoint_unavailable() should not exist - every endpoint row must be live-tested'
		);

		$file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/endpoints-status.php' );

		$this->assertStringNotContainsString(
			'mark_endpoint_unavailable',
			$file_contents,
			'No call sites should reference mark_endpoint_unavailable()'
		);
	}

	/**
	 * test_endpoint() must support routing through the v0 client for
	 * endpoints with no v1 equivalent, instead of faking their status.
	 */
	public function test_endpoint_supports_v0_routing() {
		$file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/endpoints-status.php' );

		$func_pos = strpos( $file_contents, 'function test_endpoint(' );
		$this->assertNotFalse( $func_pos, 'Should find function test_endpoint' );

		$func_content = substr( $file_contents, $func_pos, 1500 );

		$this->assertStringContainsString( '$use_v0', $func_content, 'test_endpoint should accept a $use_v0 parameter' );
		$this->assertStringContainsString(
			"api_request_v0( \$endpoint['path'] )",
			$func_content,
			"test_endpoint must call api_request_v0() with the already-/v0-prefixed \$endpoint['path'], not the raw \$path - api_request_v0()'s contract requires the endpoint string to already include the /v0 prefix (see its call sites in includes/api.php), and passing the unprefixed \$path silently sends requests to the wrong URL"
		);
	}

	/**
	 * Endpoints that have a working v0 fallback (per includes/api.php) must be
	 * live-tested via v0 on this page, not marked as a gap.
	 */
	public function test_v0_only_endpoints_are_live_tested() {
		$file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/endpoints-status.php' );

		$v0_tested_paths = [
			'/sites/%s/plan',
			'/sites/%s/available-plans',
			'/sites/%s/authorizations',
			'/sites/%s/code-tips',
			'/sites/%s/upstream-updates',
			'/sites/%s/environments/%s/backups/schedule',
			'/sites/%s/environments/%s/domains',
			'/sites/%s/environments/%s/domains/dns',
			'/sites/%s/environments/%s/metrics',
			'/users/%s/machine-tokens',
			'/users/%s/memberships/sites',
		];

		foreach ( $v0_tested_paths as $path ) {
			$this->assertStringContainsString(
				$path,
				$file_contents,
				"Should test {$path} via test_endpoint()"
			);
		}
	}
}
