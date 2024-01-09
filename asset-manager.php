<?php
/**
 * Asset Manager Base Plugin File.
 *
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
 *
 * @package AssetManager
 */

/**
 * Filesystem path to AssetManager.
 */
defined( 'AM_BASE_DIR' ) || define( 'AM_BASE_DIR', __DIR__ );

// Load the Composer autoloader.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	add_action(
		'admin_notices',
		function () {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					esc_html_e(
						'Asset Manager is not installed. Please switch to a tagged release or track the `production-built` branch.',
						'am'
					);
					?>
				</p>
			</div>
			<?php
		}
	);

	return;
}

// Setup the aliases to the legacy Asset Manager classes (pre-1.4.0).
class_alias( \Alley\WP\Asset_Manager\Scripts::class, 'Asset_Manager_Scripts' );
class_alias( \Alley\WP\Asset_Manager\Styles::class, 'Asset_Manager_Styles' );
class_alias( \Alley\WP\Asset_Manager\Preload::class, 'Asset_Manager_Preload' );
class_alias( \Alley\WP\Asset_Manager\SVG_Sprite::class, 'Asset_Manager_SVG_Sprite' );

// Require the helpers that are used to interact with the plugin.
require_once __DIR__ . '/src/helpers.php';

/**
 * Map plugin meta capabilities.
 *
 * @param string[] $caps Primitive capabilities required of the user.
 * @param string   $cap  Capability being checked.
 * @return string[] Updated primitive capabilities.
 */
function am_map_meta_caps( $caps, $cap ) {
	// By default, require the 'manage_options' capability to view asset errors.
	if ( 'am_view_asset_error' === $cap ) {
		$caps = [ 'manage_options' ];
	}

	return $caps;
}
add_filter( 'map_meta_cap', 'am_map_meta_caps', 10, 2 );

// Setup the plugin's main classes after the theme has been setup.
add_action( 'after_setup_theme', [ \Alley\WP\Asset_Manager\Preload::class, 'instance' ], 10 );
add_action( 'after_setup_theme', [ \Alley\WP\Asset_Manager\Scripts::class, 'instance' ], 10 );
add_action( 'after_setup_theme', [ \Alley\WP\Asset_Manager\Styles::class, 'instance' ], 10 );
add_action( 'after_setup_theme', [ \Alley\WP\Asset_Manager\SVG_Sprite::class, 'instance' ], 10 );
