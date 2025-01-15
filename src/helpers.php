<?php
/**
 * Template tags for the wp-asset-manager plugin.
 *
 * Intentionally not namespaced.
 *
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
 *
 * @package AssetManager
 */

use Alley\WP\Asset_Manager\Preload;
use Alley\WP\Asset_Manager\Scripts;
use Alley\WP\Asset_Manager\Styles;
use Alley\WP\Asset_Manager\SVG_Sprite;

if ( ! function_exists( 'am_validate_path' ) ) {
	/**
	 * Helper function to validate a path before doing something with it.
	 *
	 * @param string $path The path to validate.
	 *
	 * @return bool True if the path is valid, false otherwise.
	 */
	function am_validate_path( string $path ): bool {
		return in_array( validate_file( $path ), [ 0, 2 ], true ) && file_exists( $path );
	}
}

if ( ! function_exists( 'am_enqueue_script' ) ) :

	/**
	 * Load an external script. Options can be passed in as an array or individual parameters.
	 *
	 * @param string|array         $handle Handle for script.
	 * @param string|null          $src URI to script.
	 * @param array<string>        $deps This script's dependencies.
	 * @param array<string>|string $condition Corresponds to a configured loading condition that, if matches,
	 *                                        will allow the script to load.
	 *                                        'global' is assumed if no condition is declared.
	 * @param string               $load_method  How to load this asset.
	 * @param string|null          $version      Version of the script.
	 * @param string               $load_hook    Hook on which to load this asset.
	 *
	 * @phpstan-param string|array{
	 *   handle: string,
	 *   src?: string,
	 *   condition?: string,
	 *   deps?: array<string>,
	 *   load_hook?: string,
	 *   load_method?: string,
	 *   version?: string
	 * } $handle
	 */
	function am_enqueue_script( array|string $handle, ?string $src = null, array $deps = [], array|string $condition = 'global', string $load_method = 'sync', ?string $version = '1.0.0', string $load_hook = 'wp_head' ): void {
		$defaults = compact( 'handle', 'src', 'deps', 'condition', 'load_method', 'version', 'load_hook' );
		$args     = is_array( $handle ) ? array_merge( $defaults, $handle ) : $defaults;
		Scripts::instance()->add_asset( $args );
	}

endif;

if ( ! function_exists( 'am_modify_load_method' ) ) :

	/**
	 * Modify the load method of an already-enqueued script
	 *
	 * @param string $handle      Handle for script.
	 * @param string $load_method How to load this asset.
	 */
	function am_modify_load_method( string $handle, string $load_method = 'sync' ): void {
		Scripts::instance()->modify_load_method( $handle, $load_method );
	}

endif;

if ( ! function_exists( 'am_enqueue_style' ) ) :

	/**
	 * Load an external stylesheet. Options can be passed in as an array or individual parameters.
	 *
	 * @param string|array $handle      Handle for stylesheet. This is necessary for dependency management.
	 * @param string       $src         URI to stylesheet.
	 * @param array        $deps        List of dependencies.
	 * @param array|string $condition   Corresponds to a configured loading condition that, if matches,
	 *                                  will allow the stylesheet to load.
	 *                                  'global' is assumed if no condition is declared.
	 * @param string       $load_method How to load this asset.
	 * @param string|null  $version     Version of the script.
	 * @param string       $load_hook   Hook on which to load this asset.
	 * @param string       $media       Media query to restrict when this asset is loaded.
	 *
	 * @phpstan-param string|array{
	 *   handle: string,
	 *   src?: string,
	 *   deps?: array<string>,
	 *   condition?: array<string>|string,
	 *   load_method?: string,
	 *   version?: string,
	 *   load_hook?: string,
	 *   media?: string
	 * } $handle
	 */
	function am_enqueue_style( array|string $handle, ?string $src = null, array $deps = [], array|string $condition = 'global', string $load_method = 'sync', ?string $version = '1.0.0', string $load_hook = 'wp_head', ?string $media = null ): void {
		$defaults = compact( 'handle', 'src', 'deps', 'condition', 'load_method', 'version', 'load_hook', 'media' );
		$args     = is_array( $handle ) ? array_merge( $defaults, $handle ) : $defaults;

		/**
		 * Using am_enqueue_style with `load_method => preload` is no longer supported.
		 * This patches in a call to am_preload and updates the enqueued style's
		 * load_method to 'sync', which replicates the deprecated behavior.
		 */
		if ( 'preload' === $args['load_method'] ) {
			Preload::instance()->add_asset( $args );
			$args['load_method'] = 'sync';
		}

		Styles::instance()->add_asset( $args );
	}

endif;

if ( ! function_exists( 'am_preload' ) ) :

	/**
	 * Provide an asset with a `preload` resource hint for the browser to prioritize.
	 *
	 * @param array|string $handle       Handle for asset. This is necessary for dependency management.
	 * @param string       $src          URI to asset.
	 * @param array|string $condition    Corresponds to a configured loading condition that, if matches,
	 *                                   will allow the asset to load.
	 *                                   'global' is assumed if no condition is declared.
	 * @param string|null  $version      Version of the asset.
	 * @param string       $media        Media query to restrict when this asset is loaded.
	 * @param string       $as           A hint to the browser about what type of asset this is.
	 *                                   See $preload_as for valid options.
	 * @param boolean      $crossorigin  Preload this asset cross-origin.
	 * @param string       $mime_type    The MIME type for the preloaded asset.
	 *
	 * @phpstan-param string|array{
	 *   handle: string,
	 *   src?: string,
	 *   condition?: array<string>|string,
	 *   version?: string,
	 *   media?: string,
	 *   as?: string,
	 *   crossorigin?: bool,
	 *   mime_type?: string
	 * } $handle Handle for asset. This is necessary for dependency management.
	 */
	function am_preload( array|string $handle, ?string $src = null, array|string $condition = 'global', ?string $version = '1.0.0', string $media = 'all', ?string $as = null, bool $crossorigin = false, ?string $mime_type = null ): void {
		$defaults = compact( 'handle', 'src', 'condition', 'version', 'media', 'as', 'crossorigin', 'mime_type' );
		$args     = is_array( $handle ) ? array_merge( $defaults, $handle ) : $defaults;
		Preload::instance()->add_asset( $args );
	}

endif;

if ( ! function_exists( 'am_register_symbol' ) ) :

	/**
	 * Define a symbol to be added to the SVG sprite.
	 *
	 * @param string|array $handle     Handle for asset, used to refer to the symbol in `am_use_symbol`.
	 * @param string       $src        Absolute path from the current theme root, or a relative path
	 *                                 based on the current theme root. Use the `am_modify_svg_directory`
	 *                                 filter to update the directory from which relative paths will be
	 *                                 completed.
	 * @param array|string $condition  Corresponds to a configured loading condition that, if matches,
	 *                                 will allow the asset to be added to the sprite sheet.
	 *                                 'global' is assumed if no condition is declared.
	 * @param array        $attributes An array of attribute names and values to add to the resulting <svg>
	 *                                 everywhere it is printed.
	 *
	 * @phpstan-param string|array{
	 *   handle: string,
	 *   src?: string,
	 *   condition?: array<string>|string,
	 *   attributes?: array<string, string>
	 * } $handle
	 */
	function am_register_symbol( array|string $handle, ?string $src = null, array|string $condition = 'global', array $attributes = [] ): void {
		$defaults = compact( 'handle', 'src', 'condition', 'attributes' );
		$args     = is_array( $handle ) ? array_merge( $defaults, $handle ) : $defaults;
		SVG_Sprite::instance()->add_asset( $args );
	}

endif;

if ( ! function_exists( 'am_deregister_symbol' ) ) :

	/**
	 * Remove a previously-registered symbol.
	 *
	 * @param string $handle Handle for the asset to be removed.
	 */
	function am_deregister_symbol( string $handle ): bool {
		return SVG_Sprite::instance()->remove_symbol( $handle );
	}

endif;

if ( ! function_exists( 'am_get_symbol' ) ) :

	/**
	 * Returns the SVG with `<use>` element referencing the symbol.
	 *
	 * @param string                $handle The symbol name.
	 * @param array<string, string> $attrs  The attributes to add to the SVG element.
	 */
	function am_get_symbol( string $handle, array $attrs = [] ): string {
		return SVG_Sprite::instance()->get_symbol( $handle, $attrs );
	}

endif;

if ( ! function_exists( 'am_use_symbol' ) ) :

	/**
	 * Prints the SVG with `<use>` element referencing the symbol.
	 *
	 * @param string                $handle The symbol name.
	 * @param array<string, string> $attrs  The attributes to add to the SVG element.
	 */
	function am_use_symbol( string $handle, array $attrs = [] ): void {
		SVG_Sprite::instance()->use_symbol( $handle, $attrs );
	}

endif;

if ( ! function_exists( 'am_symbol_is_registered' ) ) :

	/**
	 * Returns true if a symbol is registered.
	 *
	 * @param  string $handle The registered SVG asset handle.
	 * @return bool           Whether the symbol is registered.
	 */
	function am_symbol_is_registered( string $handle ): bool {
		if ( empty( $handle ) ) {
			return false;
		}

		return in_array( $handle, SVG_Sprite::instance()->asset_handles, true );
	}

endif;
