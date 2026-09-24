<?php
/**
 * Asset Manager Tests: Bootstrap.
 *
 * @see https://mantle.alley.com/docs/testing
 *
 * @package Asset_Manager
 */

declare(strict_types=1);

\Mantle\Testing\manager()
	->maybe_rsync_plugin()
	->with_sqlite()
	->loaded( fn () => require dirname( __DIR__ ) . '/wp-asset-manager.php' )
	/**
	 * Disable on-demand object cache loading for local testing.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/enable_loading_object_cache_dropin/#description
	 * @see https://github.com/alleyinteractive/mantle-framework/releases/tag/v1.4.0
	 */
	->without_local_object_cache()
	->install();
