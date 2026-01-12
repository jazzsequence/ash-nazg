<?php
/**
 * Test script to check git-related API endpoints.
 */

// Bootstrap WordPress from the pantheon-local-copies site.
require_once '/Users/chris.reynolds/pantheon-local-copies/cxr-ash-nazg/wp-load.php';

use Pantheon\AshNazg\API;

$site_id = '79f9c59d-a7b5-4961-ae8a-b1291065518e';
$env = 'dev';

echo "Testing Git-related API endpoints for cxr-ash-nazg...\n\n";

// Get API token.
$token = API\get_api_token();
if ( is_wp_error( $token ) ) {
	echo "ERROR: Failed to get API token: " . $token->get_error_message() . "\n";
	exit( 1 );
}

echo "✓ Got API token\n\n";

// Test 1: Code tips (branches and commits).
echo "1. Testing /v0/sites/{site_id}/code-tips\n";
$response = wp_remote_get(
	"https://api.pantheon.io/v0/sites/{$site_id}/code-tips",
	[
		'headers' => [
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
		],
		'timeout' => 15,
	]
);

if ( is_wp_error( $response ) ) {
	echo "   ERROR: " . $response->get_error_message() . "\n\n";
} else {
	$code = wp_remote_retrieve_response_code( $response );
	echo "   Status: {$code}\n";
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $body ) {
		echo "   Response structure: " . json_encode( array_keys( $body ), JSON_PRETTY_PRINT ) . "\n";
		if ( isset( $body[0] ) ) {
			echo "   First item keys: " . json_encode( array_keys( $body[0] ), JSON_PRETTY_PRINT ) . "\n";
			echo "   First item: " . json_encode( $body[0], JSON_PRETTY_PRINT ) . "\n";
		}
	}
	echo "\n";
}

// Test 2: Upstream updates.
echo "2. Testing /v0/sites/{site_id}/code-upstream-updates\n";
$response = wp_remote_get(
	"https://api.pantheon.io/v0/sites/{$site_id}/code-upstream-updates",
	[
		'headers' => [
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
		],
		'timeout' => 15,
	]
);

if ( is_wp_error( $response ) ) {
	echo "   ERROR: " . $response->get_error_message() . "\n\n";
} else {
	$code = wp_remote_retrieve_response_code( $response );
	echo "   Status: {$code}\n";
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $body ) {
		echo "   Response: " . json_encode( $body, JSON_PRETTY_PRINT ) . "\n";
	}
	echo "\n";
}

// Test 3: Environment commits.
echo "3. Testing /v0/sites/{site_id}/environments/{$env}/commits\n";
$response = wp_remote_get(
	"https://api.pantheon.io/v0/sites/{$site_id}/environments/{$env}/commits",
	[
		'headers' => [
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
		],
		'timeout' => 15,
	]
);

if ( is_wp_error( $response ) ) {
	echo "   ERROR: " . $response->get_error_message() . "\n\n";
} else {
	$code = wp_remote_retrieve_response_code( $response );
	echo "   Status: {$code}\n";
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $body ) {
		echo "   Total commits: " . count( $body ) . "\n";
		if ( isset( $body[0] ) ) {
			echo "   First commit keys: " . json_encode( array_keys( $body[0] ), JSON_PRETTY_PRINT ) . "\n";
			echo "   First commit: " . json_encode( $body[0], JSON_PRETTY_PRINT ) . "\n";
			echo "   Last commit: " . json_encode( end( $body ), JSON_PRETTY_PRINT ) . "\n";
		}
	}
	echo "\n";
}

echo "Done!\n";
