<?php
/**
 * Class file for Asset_Manager_Styles
 *
 * @package AssetManager
 */

/**
 * Asset_Manager_Styles class.
 */
class Asset_Manager_Styles extends Asset_Manager {

	/**
	 * Holds references to the singleton instances.
	 *
	 * @var array
	 */
	private static $instance;

	/**
	 * Whether or not loadCSS has been loaded.
	 *
	 * @var bool
	 */
	public $loadcss_added = false;

	/**
	 * Methods by which a stylesheet can be loaded into the DOM
	 *
	 * @var array
	 */
	public $load_methods = [ 'sync', 'async', 'defer', 'inline' ];

	/**
	 * Asset type this class is responsible for loading and managing
	 *
	 * @var string
	 */
	public $asset_type = 'style';

	/**
	 * Core asset reference filter
	 *
	 * @var string
	 */
	public $core_ref_type = 'styles';

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Don't do anything, needs to be initialized via instance() method.
	}

	/**
	 * Get an instance of the class.
	 *
	 * @return Asset_Manager_Styles
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new static();
			self::$instance->add_hooks();
			self::$instance->set_defaults();
		}
		return self::$instance;
	}

	/**
	 * Print a single stylesheet
	 *
	 * @param array $stylesheet Stylesheet to insert into DOM.
	 *
	 * @return void
	 */
	public function print_asset( $stylesheet ) {
		$classes      = $this->default_classes;
		$classes[]    = $stylesheet['handle'];
		$print_string = '';

		if ( ! empty( $stylesheet['src'] ) && ! in_array( $stylesheet['load_method'], $this->wp_enqueue_methods, true ) ) {
			if ( 'inline' === $stylesheet['load_method'] ) {
				// Validate inline styles.
				if ( 0 === validate_file( $stylesheet['src'] ) && file_exists( $stylesheet['src'] ) ) {
					$file_contents = function_exists( 'wpcom_vip_file_get_contents' )
						? wpcom_vip_file_get_contents( $stylesheet['src'] )
						: file_get_contents( $stylesheet['src'] ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown

					printf(
						'<style class="%1$s" type="text/css">%2$s</style>',
						esc_attr( implode( ' ', $classes ) ),
						$file_contents // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					);
				} else {
					$this->generate_asset_error( 'unsafe_inline', $stylesheet );
				}
			} elseif ( 'async' === $stylesheet['load_method'] || 'defer' === $stylesheet['load_method'] ) {
				$media = false;
				$src   = $stylesheet['src'];

				if ( ! empty( $stylesheet['version'] ) ) {
					$src = add_query_arg( 'ver', $stylesheet['version'], $stylesheet['src'] );
				}

				if ( ! empty( $stylesheet['media'] ) ) {
					$media = $stylesheet['media'];
				}

				if ( 'async' === $stylesheet['load_method'] ) {
					$onload_media = empty( $media ) ? 'all' : $media;
					$print_string = '<link rel="stylesheet" class="%2$s" href="%1$s" media="print" onload="this.onload=null;this.media=\'' . $onload_media . '\'" /><noscript><link rel="stylesheet" href="%1$s" %3$s class="%2$s" /></noscript>';
				} elseif ( 'defer' === $stylesheet['load_method'] ) {
					$print_string = '<script class="%2$s" type="text/javascript">document.addEventListener("DOMContentLoaded",function(){loadCSS("%1$s");});</script><noscript><link rel="stylesheet" href="%1$s" class="%2$s" %3$s/></noscript>';
				}

				echo wp_kses(
					sprintf(
						$print_string,
						esc_url( $src ),
						esc_attr( implode( ' ', $classes ) ),
						! empty( $media ) ? sprintf( 'media="%s" ', esc_attr( $media ) ) : ''
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
	 * @param array $stylesheet Stylesheet to check.
	 * @return array
	 */
	public function pre_add_asset( $stylesheet ) {
		// Add loadCSS for defer method.
		if ( 'defer' === $stylesheet['load_method'] && ! $this->loadcss_added ) {
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
	 * @param array $stylesheet Stylesheet to mutate.
	 * @return array
	 */
	public function post_validate_asset( $stylesheet ) {
		if (
			! empty( $stylesheet['dependents'] ) &&
			( 'async' === $stylesheet['load_method'] || 'defer' === $stylesheet['load_method'] )
		) {
			$this->generate_asset_error( 'unsafe_load_method', $stylesheet, $this->assets_by_handle[ $stylesheet['dependents'][0] ] );
		}

		return $stylesheet;
	}
}
