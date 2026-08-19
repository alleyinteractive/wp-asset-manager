<?php
/**
 * Class file for Asset_Manager_Styles
 *
 * @package Asset_Manager
 */

namespace Alley\WP\Asset_Manager;

/**
 * Asset_Manager_Styles class.
 */
class Styles extends Asset_Manager {
	/**
	 * Whether or not loadCSS has been loaded.
	 *
	 * @var bool
	 */
	public $loadcss_added = false;

	/**
	 * Methods by which a stylesheet can be loaded into the DOM
	 *
	 * @var string[]
	 */
	public array $load_methods = [ 'sync', 'async', 'defer', 'inline' ];

	/**
	 * Asset type this class is responsible for loading and managing
	 *
	 * @var string|null
	 */
	public ?string $asset_type = 'style';

	/**
	 * Core asset reference filter
	 *
	 * @var string
	 */
	public ?string $core_ref_type = 'styles';

	/**
	 * Print a single stylesheet
	 *
	 * @param array<string, mixed> $stylesheet Stylesheet to insert into DOM.
	 */
	public function print_asset( $stylesheet ): void {
		$load_method  = $this->asset_string( $stylesheet, 'load_method' );
		$src          = $this->asset_string( $stylesheet, 'src' );
		$classes      = $this->default_classes;
		$classes[]    = $this->asset_string( $stylesheet, 'handle' );
		$print_string = '';

		if ( '' !== $src && ! in_array( $load_method, $this->wp_enqueue_methods, true ) ) {
			if ( 'inline' === $load_method ) {
				// Validate inline styles.
				if ( am_validate_path( $src ) ) {
					$contents = file_get_contents( $src ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown

					printf(
						'<style class="%1$s" type="text/css">%2$s</style>',
						esc_attr( implode( ' ', $classes ) ),
						/**
						 * Filter the inline stylesheet.
						 *
						 * @param string               $contents   Contents to filter.
						 * @param array<string, mixed> $stylesheet Stylesheet being rendered.
						 */
						apply_filters( 'am_inline_stylesheet', (string) $contents, $stylesheet ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					);
				} else {
					$this->generate_asset_error( 'unsafe_inline', $stylesheet );
				}
			} elseif ( 'async' === $load_method || 'defer' === $load_method ) {
				$media   = $this->asset_string( $stylesheet, 'media' );
				$version = $this->asset_string( $stylesheet, 'version' );

				if ( '' !== $version ) {
					$src = add_query_arg( 'ver', $version, $src );
				}

				if ( 'async' === $load_method ) {
					$onload_media = '' !== $media ? $media : 'all';
					$print_string = '<link rel="stylesheet" class="%2$s" href="%1$s" media="print" onload="this.onload=null;this.media=\'' . $onload_media . '\'" /><noscript><link rel="stylesheet" href="%1$s" %3$s class="%2$s" /></noscript>';
				} elseif ( 'defer' === $load_method ) {
					$print_string = '<script class="%2$s" type="text/javascript">document.addEventListener("DOMContentLoaded",function(){loadCSS("%1$s");});</script><noscript><link rel="stylesheet" href="%1$s" class="%2$s" %3$s/></noscript>';
				}

				echo wp_kses(
					sprintf(
						$print_string,
						esc_url( $src ),
						esc_attr( implode( ' ', $classes ) ),
						'' !== $media ? sprintf( 'media="%s" ', esc_attr( $media ) ) : ''
					),
					[
						'link'     => [
							'rel'    => [],
							'href'   => [],
							'class'  => [],
							'media'  => [],
							'as'     => [],
							'onload' => [],
						],
						'script'   => [
							'class' => [],
							'type'  => [],
						],
						'noscript' => [],
					]
				);
			}
		}
	}

	/**
	 * Add loadCSS if necessary.
	 *
	 * @param array<string, mixed> $stylesheet Stylesheet to check.
	 * @return array<string, mixed>
	 */
	public function pre_add_asset( $stylesheet ) {
		// Add loadCSS for defer method.
		if ( 'defer' === $this->asset_string( $stylesheet, 'load_method' ) && ! $this->loadcss_added ) {
			am_enqueue_script(
				[
					'handle'      => 'loadCSS',
					'src'         => AM_BASE_DIR . '/js/loadCSS.min.js',
					'load_method' => 'inline',
					'load_hook'   => 'am_critical',
				]
			);
			$this->loadcss_added = true;
		}

		return $stylesheet;
	}

	/**
	 * Perform mutations to stylesheet after validation.
	 *
	 * @param array<string, mixed> $stylesheet Stylesheet to mutate.
	 * @return array<string, mixed>
	 */
	public function post_validate_asset( $stylesheet ) {
		$dependents  = is_array( $stylesheet['dependents'] ?? null ) ? $stylesheet['dependents'] : [];
		$load_method = $this->asset_string( $stylesheet, 'load_method' );

		if ( $dependents && in_array( $load_method, [ 'async', 'defer' ], true ) ) {
			$dependent = $this->asset_string( [ 'handle' => reset( $dependents ) ], 'handle' );

			$this->generate_asset_error( 'unsafe_load_method', $stylesheet, $this->assets_by_handle[ $dependent ] ?? [] );
		}

		return $stylesheet;
	}
}
