<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Asset_Manager_Tests
 */

\Mantle\Testing\manager()
	->maybe_rsync_plugin()
	->with_sqlite()
	->loaded( fn () => require dirname( __DIR__ ) . '/wp-asset-manager.php' )
	->install();

if ( ! function_exists( 'get_echo' ) ) :
	function get_echo( $callable, $args = [] ) {
		ob_start();
		$callable(...array_values($args));
		return ob_get_clean();
	}
endif;
