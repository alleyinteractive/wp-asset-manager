<?php
/**
 * Class file for Asset_Manager_Styles
 *
 * @package AssetManager
 */

class Asset_Manager_Styles extends Asset_Manager {

	/**
	 * Holds references to the singleton instances.
	 *
	 * @var array
	 */
	private static $instance;

	/**
	 * Whether or loadCSS and preload polyfill have been loaded
	 *
	 * @var bool
	 */
	public $preload_engaged = false;

	/**
	 * Methods by which a stylesheet can be loaded into the DOM
	 *
	 * @var array
	 */
	public $load_methods = array( 'sync', 'preload', 'async', 'defer', 'inline' );

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

	private function __construct() {
		// Don't do anything, needs to be initialized via instance() method.
	}

	/**
	 * Get an instance of the class.
	 *
	 * @return class
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new static;
			self::$instance->add_hooks();
			self::$instance->set_defaults();
		}
		return self::$instance;
	}

	/**
	 * Print a single stylesheet
	 *
	 * @param array $stylesheet Stylesheet to insert into DOM
	 *
	 * @return void
	 */
	public function print_asset( $stylesheet ) {
		$classes = $this->default_classes;
		$classes[] = $stylesheet['handle'];
		$print_string = '';

		if ( ! empty( $stylesheet['src'] ) && ! in_array( $stylesheet['load_method'], $this->wp_enqueue_methods, true ) ) {
			if ( 'inline' === $stylesheet['load_method'] ) {
				// Validate inline styles
				if ( 0 === validate_file( $stylesheet['src'] ) && file_exists( $stylesheet['src'] ) ) {
					printf( '<style class="%1$s" type="text/css">%2$s</style>', esc_attr( implode( ' ', $classes ) ), file_get_contents( $stylesheet['src'] ) );
				} else {
					$this->generate_asset_error( 'unsafe_inline', $stylesheet );
				}
			} elseif ( 'preload' === $stylesheet['load_method'] || 'async' === $stylesheet['load_method'] || 'defer' === $stylesheet['load_method'] ) {
				$media = false;
				$src = $stylesheet['src'];

				if ( ! empty( $stylesheet['version'] ) ) {
					$src = add_query_arg( 'ver', $stylesheet['version'], $stylesheet['src'] );
				}

				if ( ! empty( $stylesheet['media'] ) ) {
					$media = $stylesheet['media'];
				}

				if ( 'preload' === $stylesheet['load_method'] ) {
					$print_string = '<link rel="preload" href="%1$s" class="%2$s" %3$s as="style" onload="this.rel=\'stylesheet\'"></link><noscript><link rel="stylesheet" href="%1$s" class="%2$s" media="%3$s" /></noscript>';
				} elseif ( 'async' === $stylesheet['load_method'] ) {
					$print_string = '<script class="%2$s" type="text/javascript">loadCSS("%1$s");</script><noscript><link rel="stylesheet" href="%1$s" class="%2$s" %3$s /></noscript>';
				} elseif ( 'defer' === $stylesheet['load_method'] ) {
					$print_string = '<script class="%2$s" type="text/javascript">document.addEventListener("DOMContentLoaded",function(){loadCSS("%1$s");});</script><noscript><link rel="stylesheet" href="%1$s" class="%2$s" %3$s /></noscript>';
				}

				echo wp_kses(
					sprintf( $print_string,
						esc_url( $src ),
						esc_attr( implode( ' ', $classes ) ),
						! empty( $media ) ? sprintf( 'media="%s"', esc_attr( $media ) ) : ''
					),
					array(
						'link' => array(
							'rel' => array(),
							'href' => array(),
							'class' => array(),
							'media' => array(),
							'as' => array(),
							'onload' => array(),
						),
						'script' => array(
							'class' => array(),
							'type' => array(),
						),
						'noscript' => array(),
					)
				);
			}
		}
	}

	/**
	 * Add loadCSS and preload polyfill if necessary
	 *
	 * @param array $stylesheet Stylesheet to check
	 * @return array
	 */
	public function pre_add_asset( $stylesheet ) {
		// Add preload script
		if ( ( 'preload' === $stylesheet['load_method'] || 'async' === $stylesheet['load_method'] || 'defer' === $stylesheet['load_method'] ) && ! $this->preload_engaged ) {
			am_enqueue_script( array(
				'handle' => 'loadCSS',
				'src' => AM_BASE_DIR . '/js/loadCSS.min.js',
				'load_method' => 'inline',
				'load_hook' => 'am_critical',
			) );
			$this->preload_engaged = true;
		}

		return $stylesheet;
	}

	/**
	 * Perform mutations to stylesheet after validation
	 *
	 * @param array $stylesheet Stylesheet to mutate
	 * @return array
	 */
	public function post_validate_asset( $stylesheet ) {
		if (
			! empty( $stylesheet['dependents'] ) &&
			( 'preload' === $stylesheet['load_method'] || 'async' === $stylesheet['load_method'] || 'defer' === $stylesheet['load_method'] )
		) {
			$this->generate_asset_error( 'unsafe_load_method', $stylesheet, $this->assets_by_handle[ $stylesheet['dependents'][0] ] );
		}

		return $stylesheet;
	}
}
