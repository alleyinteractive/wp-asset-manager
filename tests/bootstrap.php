<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Asset_Manager_Tests
 */

require_once __DIR__ . '/../vendor/autoload.php';

\Mantle\Testing\manager()
	->loaded( fn () => require dirname( __DIR__ ) . '/asset-manager.php' )
	->install();

if ( ! function_exists( 'get_echo' ) ) :
	function get_echo( $callable, $args = [] ) {
		ob_start();
		$callable(...array_values($args));
		return ob_get_clean();
	}
endif;
