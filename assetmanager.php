<?php

if ( ! function_exists( 'am_enqueue_script' ) ) :

	/**
	 * Load an external script. Options can be passed in as an array or individual parameters.
	 *
	 * @param {string} $handle - Handle for script. This is necessary for dependency management.
	 * @param {string} $src - URI to script
	 * @param {string} $condition - Corresponds to a configured loading condition that, if matches, will allow the script to load.
	 * 		Defaults are 'global', 'single', and 'search'.
	 * @param {string} $load_method - how to load this asset.
	 * @param {string} $load_hook - hook on which to load this asset
	 */
	function am_enqueue_script( $handle, $src = false, $deps = array(), $condition = 'global', $load_method = 'sync', $version = '1.0.0', $load_hook = 'wp_head' ) {
		$defaults = compact( 'handle', 'src', 'deps', 'condition', 'load_method', 'version', 'load_hook' );
		$args = is_array( $handle ) ? array_merge( $defaults, $handle ) : $defaults;
		Assetmanager_Scripts::instance()->add_asset( $args );
	}

endif;

if ( ! function_exists( 'am_modify_load_method' ) ) :

	/**
	 * Modify method by which a script is loaded. Mostly useful for adding async or defer to already-enqueued assets
	 *
	 * @param {string} $handle - Handle for script.
	 * @param {string} $load_method - how to load this asset.
	 */
	function am_modify_load_method( $handle, $load_method = 'sync' ) {
		Assetmanager_Scripts::instance()->modify_load_method( $handle, $load_method );
	}

endif;

add_action( 'after_setup_theme', array( 'Assetmanager_Scripts', 'instance' ), 10 );

if ( ! function_exists( 'am_enqueue_style' ) ) :

	/**
	 * Load an external stylesheet. Options can be passed in as an array or individual parameters.
	 *
	 * @param {string} $handle - Handle for stylesheet. This is necessary for dependency management.
	 * @param {string} $src - URI to stylesheet.
	 * @param {string} $condition - Corresponds to a configured loading condition that, if matches, will allow the stylesheet to load.
	 * 		Defaults are 'global', 'single', and 'search'.
	 * @param {string} $load_method - how to load this asset.
	 * @param {string} $load_hook - load_hook on which to load this asset
	 * @param {bool} $pre_wp - whether or not to load this asset before WordPress enqueued assets
	 * @param {string} $media - media query to restrict when this asset is loaded
	 */
	function am_enqueue_style( $handle, $src = false, $deps = array(), $condition = 'global', $load_method = 'sync', $version = '1.0.0', $load_hook = 'wp_head', $media = false ) {
		$defaults = compact( 'handle', 'src', 'deps', 'condition', 'load_method', 'version', 'load_hook', 'media' );
		$args = is_array( $handle ) ? array_merge( $defaults, $handle ) : $defaults;
		Assetmanager_Styles::instance()->add_asset( $args );
	}

endif;

add_action( 'after_setup_theme', array( 'Assetmanager_Styles', 'instance' ), 10 );