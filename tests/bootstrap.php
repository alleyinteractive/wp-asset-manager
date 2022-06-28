<?php
/**
 * PHPUnit bootstrap file
 *
 * @package WP_Irving
 */

use function Mantle\Testing\tests_add_filter;

require_once __DIR__ . '/../vendor/wordpress-autoload.php';

\Mantle\Testing\install();

tests_add_filter( 'muplugins_loaded', function() {
	require dirname( __DIR__ ) . '/asset-manager.php';
} );

if ( ! function_exists( 'get_echo' ) ) :
	function get_echo( $callable, $args = [] ) {
		ob_start();
		$callable(...array_values($args));
		return ob_get_clean();
	}
endif;
