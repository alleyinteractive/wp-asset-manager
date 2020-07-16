<?php
/**
 * PHPUnit bootstrap file
 *
 * @package WP_Irving
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

// Constant to determine when tests are running.
define( 'WP_IRVING_TEST', true );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	// PHPCS is complaining about this line not being translated, but it not really relevant for this file.
	// phpcs:disable
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL;
	exit( 1 );
	// phpcs:enable
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( __DIR__ ) . '/asset-manager.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

/**
 * Is the current version of WordPress at least ... ?
 *
 * @param  float $min_version Minimum version required, e.g. 3.9.
 * @return bool True if it is, false if it isn't.
 */
function _am_phpunit_is_wp_at_least( $min_version ) {
	global $wp_version;
	return floatval( $wp_version ) >= $min_version;
}

// Load custom `UnitTestCase` classes
require_once( __DIR__ . '/class-asset-manager-test.php' );
