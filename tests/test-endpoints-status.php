<?php
/**
 * Endpoints status page tests.
 *
 * @package Pantheon\AshNazg
 */

use PHPUnit\Framework\TestCase;
use Pantheon\AshNazg\API;

// This suite runs outside WordPress; stub the one i18n function the probe code needs.
if ( ! function_exists( '__' ) ) {
	/**
	 * Test stub for WordPress's __().
	 *
	 * @param string $text Text to translate.
	 * @return string The text, untranslated.
	 */
	function __( $text ) {
		return $text;
	}
}

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
		$this->load_registry_dependencies();

		$v0_reads = [];
		foreach ( API\get_endpoint_registry() as $entry ) {
			if ( 'v0' === $entry[0] && 'GET' === $entry[1] ) {
				$v0_reads[] = $entry[2];
			}
		}

		foreach ( [
			'/sites/{site_id}/available-plans',
			'/sites/{site_id}/code-tips',
			'/sites/{site_id}/environments/{env}/domains',
			'/sites/{site_id}/environments/{env}/domains/dns',
			'/sites/{site_id}/environments/{env}/metrics',
			'/users/{user_id}/memberships/sites',
		] as $path ) {
			$this->assertContains( $path, $v0_reads, "The registry should list v0 read {$path}, which is probed via the v0 client" );
		}
	}

	/**
	 * v1 endpoints must be labeled /v1, not left with an empty version prefix.
	 */
	public function test_v1_endpoints_show_version_prefix() {
		$file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/endpoints-status.php' );

		$this->assertStringContainsString(
			"( \$use_v0 ? '/v0' : '/v1' )",
			$file_contents,
			'Non-v0 endpoints should be labeled /v1, not given an empty prefix'
		);
	}

	/**
	 * User Permissions should test the real v1 membership endpoint, not the
	 * removed v0 authorizations endpoint.
	 */
	public function test_user_permissions_uses_v1_memberships() {
		$this->load_registry_dependencies();

		$v1_reads = [];
		foreach ( API\get_endpoint_registry() as $entry ) {
			if ( 'v1' === $entry[0] && 'GET' === $entry[1] ) {
				$v1_reads[] = $entry[2];
			}
		}

		$this->assertContains(
			'/sites/{site_id}/users/{user_id}/memberships?first=10',
			$v1_reads,
			'The registry should list the v1 memberships endpoint'
		);
		$this->assertNotContains( '/sites/{site_id}/authorizations', $v1_reads, 'authorizations is a v0-only endpoint' );
	}

	/**
	 * Upstream Updates should test the real status lookup, not the stale
	 * pre-migration v0 endpoint.
	 */
	public function test_upstream_updates_uses_real_status_check() {
		$file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/endpoints-status.php' );

		$this->assertStringContainsString(
			'test_upstream_status_endpoint(',
			$file_contents,
			'Upstream Updates should use the real workspace-based status check'
		);
		$this->assertStringNotContainsString(
			"'/sites/%s/upstream-updates'",
			$file_contents,
			'Upstream Updates should no longer test the stale pre-migration v0 endpoint'
		);
	}

	/**
	 * Test that endpoints can be probed one group at a time.
	 *
	 * The dashboard loads groups separately so the live requests are spread
	 * across several page requests instead of blocking one.
	 */
	public function test_groups_can_be_probed_individually() {
		$this->load_registry_dependencies();

		// Addons has no reads, so probing it sends no request: every endpoint is listed, none probed.
		$addons = API\probe_endpoint_group( 'Addons', null, 'dev', 'user-id' );
		$expected = array_filter(
			API\get_endpoint_registry(),
			function ( $entry ) {
				return 'Addons' === $entry[4];
			}
		);

		$this->assertCount( count( $expected ), $addons );
		$this->assertNotEmpty( $addons );
		$this->assertSame( [ 'skipped' ], array_values( array_unique( array_column( $addons, 'status' ) ) ) );

		$this->assertSame( [], API\probe_endpoint_group( 'Nonexistent', null, 'dev', 'user-id' ) );
	}

	/**
	 * Load the registry's ABSPATH-guarded file safely outside WordPress.
	 */
	private function load_registry_dependencies() {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', __DIR__ . '/' );
		}

		if ( ! defined( 'ASH_NAZG_PLUGIN_DIR' ) ) {
			define( 'ASH_NAZG_PLUGIN_DIR', dirname( ASH_NAZG_PLUGIN_FILE ) . '/' );
		}

		// A WordPress constant, absent when the suite runs outside WordPress.
		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}

		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/endpoints-status.php';
	}

	/**
	 * Test the registry's groups are unique and ordered.
	 */
	public function test_registry_groups_are_unique() {
		$this->load_registry_dependencies();

		$groups = API\get_endpoint_groups();

		$this->assertSame( $groups, array_values( array_unique( $groups ) ) );
		$this->assertContains( 'Backups', $groups );
		$this->assertContains( 'Environments', $groups );
	}

	/**
	 * Test placeholders are filled, URL-encoded, and unknown ones are reported.
	 */
	public function test_resolve_endpoint_path_fills_known_placeholders() {
		$this->load_registry_dependencies();

		$resolved = API\resolve_endpoint_path(
			'/sites/{site_id}/environments/{env}/backups/{backup_id}',
			[
				'site_id' => 'abc-123',
				'env' => 'multi dev',
			]
		);

		$this->assertSame( '/sites/abc-123/environments/multi%20dev/backups/{backup_id}', $resolved['path'] );
		$this->assertSame( [ '{backup_id}' ], $resolved['missing'] );
	}

	/**
	 * Test writes are listed but never sent.
	 *
	 * Probing a POST, PUT or DELETE would change data on the site.
	 */
	public function test_writes_are_listed_not_probed() {
		$this->load_registry_dependencies();

		foreach ( [ 'POST', 'PUT', 'PATCH', 'DELETE' ] as $method ) {
			$result = API\probe_registry_entry(
				[ 'v1', $method, '/sites/{site_id}/wipe', 'Do something', 'Sites' ],
				[ 'site_id' => 'abc-123' ]
			);

			$this->assertSame( 'skipped', $result['status'], $method );
			$this->assertSame( 'Not probed: changes data', $result['error'], $method );
			$this->assertSame( $method, $result['method'] );
			$this->assertSame( '/v1/sites/abc-123/wipe', $result['path'] );
		}
	}

	/**
	 * Test a read needing an ID we can't supply is listed with the reason, and v0 paths are prefixed.
	 */
	public function test_unresolvable_reads_are_listed_with_reason() {
		$this->load_registry_dependencies();

		$result = API\probe_registry_entry(
			[ 'v0', 'GET', '/sites/{site_id}/builds/{build_id}', 'Get a build', 'Builds & Logs' ],
			[ 'site_id' => 'abc-123' ]
		);

		$this->assertSame( 'skipped', $result['status'] );
		$this->assertSame( 'Not probed: needs {build_id}', $result['error'] );
		$this->assertSame( '/v0/sites/abc-123/builds/{build_id}', $result['path'] );
		$this->assertSame( 'v0', $result['version'] );
	}
}
