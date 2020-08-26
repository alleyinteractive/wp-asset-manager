<?php
/**
 * Class file for Asset_Manager_Preload
 *
 * @package AssetManager
 */

/**
 * Asset_Manager_Preload class.
 */
class Asset_Manager_Preload extends Asset_Manager {

	/**
	 * Holds references to the singleton instances.
	 *
	 * @var array
	 */
	private static $instance;

	/**
	 * Methods by which an asset can be preloaded.
	 *
	 * @var array
	 */
	public $preload_as = [
		'audio',
		'document',
		'embed',
		'fetch',
		'font',
		'image',
		'object',
		'script',
		'style',
		'track',
		'worker',
		'video',
	];

	/**
	 * Asset type this class is responsible for loading and managing
	 *
	 * @var string
	 */
	public $asset_type = 'preload';


	/**
	 * Methods by which a asset can be loaded into the DOM
	 *
	 * @var array
	 */
	public $load_methods = [ 'preload' ];

	/**
	 * Map of asset 'as' and 'type` attributes based on file extension, used to
	 * patch in attributes for commonly-preloaded assets.
	 *
	 * @var array
	 */
	public $asset_types = [
		'css' => [
			'as'        => 'style',
			'mime_type' => 'text/css',
		],
		'js' => [
			'as'        => 'script',
			'mime_type' => 'text/javascript',
		],
		'woff' => [
			'as'        => 'font',
			'mime_type' => 'font/woff',
		],
		'woff2' => [
			'as'        => 'font',
			'mime_type' => 'font/woff2',
		],
	];

	/**
	 * Constructor.
	 */
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
			self::$instance = new static();
			self::$instance->add_hooks();
			self::$instance->set_defaults();
		}
		return self::$instance;
	}

	/**
	 * Print a single asset
	 *
	 * @param array $asset Asset to insert into DOM.
	 *
	 * @return void
	 */
	public function print_asset( $asset ) {
		$classes      = $this->default_classes;
		$classes[]    = $asset['handle'];
		$print_string = '';

		if ( empty( $asset['as'] ) || ! in_array( $asset['as'], $this->preload_as, true ) ) {
			// We weren't able to patch in the 'as' attribute in `post_validate_asset`.
			$this->generate_asset_error( 'invalid_preload_attribute', $asset, [ 'attribute' => 'as' ] );
		} else if ( ! empty( $asset['src'] ) ) {
			$print_string = '<link rel="preload" href="%1$s" class="%2$s" as="%3$s" media="%4$s" %5$s %6$s />';
			$asset_src = add_query_arg(
				'ver',
				$asset['version'],
				$asset['src']
			);

			echo wp_kses(
				sprintf(
					$print_string,
					esc_url( $asset_src ),
					esc_attr( implode( ' ', $classes ) ),
					esc_attr( $asset['as'] ),
					esc_attr( $asset['media'] ),
					empty( $asset['mime_type'] ) ? '' : sprintf( 'type="%s" ', esc_attr( $asset['mime_type'] ) ),
					$asset['crossorigin'] ? esc_attr( 'crossorigin' ) : ''
				),
				[
					'link'     => [
						'rel'         => [],
						'href'        => [],
						'class'       => [],
						'as'          => [],
						'media'       => [],
						'type'        => [],
						'crossorigin' => [],
					],
				]
			);
		}
	}

	/**
	 * Perform final mutations before adding script to array.
	 *
	 * @param array $asset Asset to mutate.
	 * @return array
	 */
	public function pre_add_asset( $asset ) {
		return $asset;
	}

	/**
	 * Perform mutations to asset after validation.
	 *
	 * @param array $asset Asset to mutate.
	 * @return array
	 */
	public function post_validate_asset( $asset ) {
		// Attempt to patch the `as` and `mime_type` values if either is missing.
		if ( empty( $asset['as'] ) || empty( $asset['mime_type'] ) ) {
			$asset = $this->set_asset_types( $asset );
		}

		if ( ! empty( $asset['as'] ) && 'font' === $asset['as'] ) {
			// Preloading fonts requires the `crossorigin` attribute.
			// https://developer.mozilla.org/en-US/docs/Web/HTML/Preloading_content#Cross-origin_fetches
			$asset['crossorigin'] = true;
		}

		return $asset;
	}

	/**
	 * Get the type and/or MIME type for an asset based on its file extension.
	 * A MIME type isn't required, but will prevent the browser downloading an
	 * asset it doesn't support.
	 *
	 * @param  array  $asset The asset for which the types are needed.
	 * @return array         The $asset.
	 */
	public function set_asset_types( $asset ) {
		$path_parts = pathinfo( $asset['src'] );
		$asset_types = $this->asset_types[ $path_parts['extension'] ] ?? [];

		if ( ! empty( $asset_types ) ) {
			// Force these values through.
			return array_replace( $asset, $asset_types );
		}

		return $asset;
	}
}

