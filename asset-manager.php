<?php
/**
 * Asset Manager Base Plugin File.
 *
 * @package AssetManager
 */

/*
Plugin Name: Asset Manager
Plugin URI: https://github.com/alleyinteractive/wp-asset-manager
Description: Add more robust functionality to enqueuing static assets
Author: Alley Interactive
Version: 1.1.0
License: GPLv2 or later
Author URI: https://www.alleyinteractive.com/
*/

/**
 * Filesystem path to AssetManager.
 */
defined( 'AM_BASE_DIR' ) || define( 'AM_BASE_DIR', dirname( __FILE__ ) );

if ( ! class_exists( 'Asset_Manager' ) ) :
	/**
	 * Load traits.
	 */
	require_once AM_BASE_DIR . '/php/traits/trait-conditions.php';
	require_once AM_BASE_DIR . '/php/traits/trait-asset-error.php';

	/**
	 * Load base classes
	 */
	require_once AM_BASE_DIR . '/php/class-asset-manager.php';
	require_once AM_BASE_DIR . '/php/class-asset-manager-scripts.php';
	require_once AM_BASE_DIR . '/php/class-asset-manager-styles.php';
	require_once AM_BASE_DIR . '/php/class-asset-manager-preload.php';
	require_once AM_BASE_DIR . '/php/class-asset-manager-svg-sprite.php';
endif;

if ( ! function_exists( 'am_enqueue_script' ) ) :

	/**
	 * Load an external script. Options can be passed in as an array or individual parameters.
	 *
	 * @param string $handle       Handle for script.
	 * @param string $src          URI to script.
	 * @param array  $deps         This script's dependencies.
	 * @param string $condition    Corresponds to a configured loading condition that, if matches,
	 *                             will allow the script to load.
	 *                             'global' is assumed if no condition is declared.
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
	 * @param string $condition   Corresponds to a configured loading condition that, if matches,
	 *                            will allow the stylesheet to load.
	 *                            'global' is assumed if no condition is declared.
	 * @param string $load_method How to load this asset.
	 * @param string $version     Version of the script.
	 * @param string $load_hook   Hook on which to load this asset.
	 * @param string $media       Media query to restrict when this asset is loaded.
	 */
	function am_enqueue_style( $handle, $src = false, $deps = [], $condition = 'global', $load_method = 'sync', $version = '1.0.0', $load_hook = 'wp_head', $media = false ) {
		$defaults = compact( 'handle', 'src', 'deps', 'condition', 'load_method', 'version', 'load_hook', 'media' );
		$args     = is_array( $handle ) ? array_merge( $defaults, $handle ) : $defaults;

		/**
		 * Using am_enqueue_style with `load_method => preload` is no longer supported.
		 * This patches in a call to am_preload and updates the enqueued style's
		 * load_method to 'sync', which replicates the deprecated behavior.
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
	 *                              'global' is assumed if no condition is declared.
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

if ( ! function_exists( 'am_register_symbol' ) ) :

	/**
	 * Define a symbol to be added to the SVG sprite.
	 *
	 * @param string $handle     Handle for asset, used to refer to the symbol in `am_use_symbol`.
	 * @param string $src        Absolute path from the current theme root, or a relative path
	 *                           based on the current theme root. Use the `am_modify_svg_directory`
	 *                           filter to update the directory from which relative paths will be
	 *                           completed.
	 * @param string $condition  Corresponds to a configured loading condition that, if matches,
	 *                           will allow the asset to be added to the sprite sheet.
	 *                           'global' is assumed if no condition is declared.
	 * @param array  $attributes An array of attribute names and values to add to the resulting <svg>
	 *                           everywhere it is printed.
	 */
	function am_register_symbol( $handle, $src = false, $condition = 'global', $attributes = [] ) {
		$defaults = compact( 'handle', 'src', 'condition', 'attributes' );
		$args     = is_array( $handle ) ? array_merge( $defaults, $handle ) : $defaults;
		Asset_Manager_SVG_Sprite::instance()->add_asset( $args );
	}

endif;

if ( ! function_exists( 'am_deregister_symbol' ) ) :

	/**
	 * Remove a previously-registered symbol.
	 *
	 * @param string $handle Handle for the asset to be removed.
	 */
	function am_deregister_symbol( $handle = '' ) {
		return Asset_Manager_SVG_Sprite::instance()->remove_symbol( $handle );
	}

endif;

if ( ! function_exists( 'am_get_symbol' ) ) :

	/**
	 * Returns the SVG with `<use>` element referencing the symbol.
	 *
	 * @param string $handle The symbol name.
	 * @param array  $attrs  The attributes to add to the SVG element.
	 */
	function am_get_symbol( $handle, $attrs = [] ) {
		return Asset_Manager_SVG_Sprite::instance()->get_symbol( $handle, $attrs );
	}

endif;

if ( ! function_exists( 'am_use_symbol' ) ) :

	/**
	 * Prints the SVG with `<use>` element referencing the symbol.
	 *
	 * @param string $handle The symbol name.
	 * @param array  $attrs  The attributes to add to the SVG element.
	 */
	function am_use_symbol( $handle, $attrs = [] ) {
		Asset_Manager_SVG_Sprite::instance()->use_symbol( $handle, $attrs );
	}

endif;

add_action( 'after_setup_theme', [ 'Asset_Manager_SVG_Sprite', 'instance' ], 10 );
