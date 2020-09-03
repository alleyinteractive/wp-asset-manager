<?php
/**
 * Asset Manager Base Plugin File.
 *
 * @package AssetManager
 * @version 0.0.1
 */

/*
Plugin Name: Asset Manager
Plugin URI: https://github.com/alleyinteractive/wordpress-assetmanager
Description: Add more robust functionality to enqueuing static assets
Author: Alley Interactive
Version: 0.0.1
License: GPLv2 or later
Author URI: https://www.alleyinteractive.com/
*/

/**
 * Current version of AssetManager.
 */
define( 'AM_VERSION', '0.0.1' );

/**
 * Filesystem path to AssetManager.
 */
define( 'AM_BASE_DIR', dirname( __FILE__ ) );

/**
 * Load base classes
 */
require_once AM_BASE_DIR . '/php/class-asset-manager.php';
require_once AM_BASE_DIR . '/php/class-asset-manager-scripts.php';
require_once AM_BASE_DIR . '/php/class-asset-manager-styles.php';
require_once AM_BASE_DIR . '/php/class-asset-manager-preload.php';

if ( ! function_exists( 'am_enqueue_script' ) ) :

	/**
	 * Load an external script. Options can be passed in as an array or individual parameters.
	 *
	 * @param string $handle       Handle for script.
	 * @param string $src          URI to script.
	 * @param array  $deps         This script's dependencies.
	 * @param string $condition    Corresponds to a configured loading condition that, if matches, will allow the script to load. Defaults are 'global', 'single', and 'search'.
	 * @param string $load_method  How to load this asset.
	 * @param string $version      Version of the script.
	 * @param string $load_hook    Hook on which to load this asset.
	 */
	function am_enqueue_script( $handle, $src = false, $deps = [], $condition = 'global', $load_method = 'sync', $version = '1.0.0', $load_hook = 'wp_head' ) {
		$defaults = compact( 'handle', 'src', 'deps', 'condition', 'load_method', 'version', 'load_hook' );
		$args     = is_array( $handle ) ? array_merge( $defaults, $handle ) : $defaults;
		Asset_Manager_Scripts::instance()->add_asset( $args );
	}

endif;

if ( ! function_exists( 'am_modify_load_method' ) ) :

	/**
	 * Modify the load method of an already-enqueued script
	 *
	 * @param string $handle      Handle for script.
	 * @param string $load_method How to load this asset.
	 */
	function am_modify_load_method( $handle, $load_method = 'sync' ) {
		Asset_Manager_Scripts::instance()->modify_load_method( $handle, $load_method );
	}

endif;

add_action( 'after_setup_theme', [ 'Asset_Manager_Scripts', 'instance' ], 10 );

if ( ! function_exists( 'am_enqueue_style' ) ) :

	/**
	 * Load an external stylesheet. Options can be passed in as an array or individual parameters.
	 *
	 * @param string $handle      Handle for stylesheet. This is necessary for dependency management.
	 * @param string $src         URI to stylesheet.
	 * @param array  $deps        List of dependencies.
	 * @param string $condition   Corresponds to a configured loading condition that, if matches, will allow the stylesheet to load. Defaults are 'global', 'single', and 'search'.
	 * @param string $load_method How to load this asset.
	 * @param string $version     Version of the script.
	 * @param string $load_hook   Hook on which to load this asset.
	 * @param string $media       Media query to restrict when this asset is loaded.
	 */
	function am_enqueue_style( $handle, $src = false, $deps = [], $condition = 'global', $load_method = 'sync', $version = '1.0.0', $load_hook = 'wp_head', $media = false ) {
		$defaults = compact( 'handle', 'src', 'deps', 'condition', 'load_method', 'version', 'load_hook', 'media' );
		$args     = is_array( $handle ) ? array_merge( $defaults, $handle ) : $defaults;

		/**
		 * am_enqueue_style with `load_method => preload` is no longer supported.
		 * This patches in a call to am_preload and updates the enqueued style's
		 * load_method to 'static', which replicates the deprecated behavior.
		 */
		if ( 'preload' === $args['load_method'] ) {
			Asset_Manager_Preload::instance()->add_asset( $args );
			$args['load_method'] = 'sync';
		}

		Asset_Manager_Styles::instance()->add_asset( $args );
	}

endif;

add_action( 'after_setup_theme', [ 'Asset_Manager_Styles', 'instance' ], 10 );

if ( ! function_exists( 'am_preload' ) ) :

	/**
	 * Provide an asset with a `preload` resource hint for the browser to prioritize.
	 *
	 * @param string  $handle       Handle for asset. This is necessary for dependency management.
	 * @param string  $src          URI to asset.
	 * @param string  $condition    Corresponds to a configured loading condition that, if matches,
	 *                              will allow the asset to load.
	 *                              Defaults are 'global', 'single', and 'search'.
	 * @param string  $version      Version of the asset.
	 * @param string  $media        Media query to restrict when this asset is loaded.
	 * @param string  $as           A hint to the browser about what type of asset this is.
	 *                              See $preload_as for valid options.
	 * @param boolean $crossorigin  Preload this asset cross-origin.
	 * @param string  $mime_type    The MIME type for the preloaded asset.
	 */
	function am_preload( $handle, $src = false, $condition = 'global', $version = '1.0.0', $media = 'all', $as = false, $crossorigin = false, $mime_type = false ) {
		$defaults = compact( 'handle', 'src', 'condition', 'version', 'media', 'as', 'crossorigin', 'mime_type' );
		$args     = is_array( $handle ) ? array_merge( $defaults, $handle ) : $defaults;
		Asset_Manager_Preload::instance()->add_asset( $args );
	}

endif;

add_action( 'after_setup_theme', [ 'Asset_Manager_Preload', 'instance' ], 10 );
