<?php
/**
 * Class file for Asset_Manager_SVG_Sprite
 *
 * @package AssetManager
 */

/**
 * Asset_Manager_SVG_Sprite class.
 *
 * @todo add_action: modify_svg_asset.
 */
class Asset_Manager_SVG_Sprite {
	use Conditions;

	/**
	 * Holds references to the singleton instances.
	 *
	 * @var array
	 */
	private static $instance;

	/**
	 * Directory from which relative paths will be completed.
	 *
	 * @var string
	 */
	public $svg_directory;

	/**
	 * The sprite document.
	 *
	 * @var DOMDocument
	 */
	public $sprite_document;

	/**
	 * The allowed HTML elements and attributes for use in `wp_kses`.
	 *
	 * @var array
	 */
	public $symbol_allowed_html = [
		'svg' => [
			'height' => true,
			'width'  => true,
			'class'  => true,
		],
		'use' => [
			'href' => true,
		],
	];

	/**
	 * Reference array of asset handles.
	 *
	 * @var array
	 */
	public $asset_handles = [];

	/**
	 * Mapping of definitions for symbols added to the sprite.
	 *
	 * @var array
	 */
	public $sprite_map = [];

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Don't do anything, needs to be initialized via instance() method.
	}

	/**
	 * Get an instance of the class.
	 *
	 * @return Asset_Manager_SVG_Sprite
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new static();
			self::$instance->set_defaults();
			self::$instance->create_sprite_sheet();
		}

		return self::$instance;
	}

	/**
	 * Initial setup.
	 */
	public function set_defaults() {
		/**
		 * Filter function for updating the directory upon which a symbol's relative
		 * path will be based.
		 *
		 * @since 0.1.2
		 *
		 * @param string $path The absolute root for relative SVG paths.
		 */
		$this->svg_directory = apply_filters( 'am_modify_svg_directory', get_stylesheet_directory() );

		/**
		 * Filter function for configuring attributes to be added to all SVG symbols.
		 *
		 * @since 0.1.2
		 *
		 * @param array $attributes {
		 *     A list of attributes to be added to all SVG symbols.
		 *
		 *     @type array $attribute Attribute name-value pairs.
		 * }
		 */
		$this->global_attributes = apply_filters( 'am_svg_attributes', [] );

		// Add global attributes to $symbol_allowed_html.
		if ( ! empty( $this->global_attributes ) ) {
			$this->update_allowed_html( $this->global_attributes );
		}
	}

	/**
	 * Creates the sprite sheet.
	 */
	public function create_sprite_sheet() {
		$this->sprite_document = new DOMDocument();

		$this->svg_root = $this->sprite_document->createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
		$svg_style = $this->sprite_document->createAttribute('style');
		$svg_style->value = 'display:none;';

		$this->svg_root->appendChild( $svg_style );
		$this->sprite_document->appendChild( $this->svg_root );

		add_action( 'wp_body_open', [ $this, 'print_sprite_sheet' ], 10 );
	}

	/**
	 * Prints the sprite sheet to the page at `wp_body_open`.
	 */
	public function print_sprite_sheet() {
		echo $this->sprite_document->C14N();
	}

	/**
	 * Convenience function that returns the symbol id based on the asset handle.
	 *
	 * @param  string $handle The asset handle.
	 * @return string         The asset handle formatted for use as the symbol id.
	 */
	public function format_handle_as_symbol_id( $handle ) {
		return "am-symbol-{$handle}";
	}


	/**
	 * Evaluates and returns the filepath for a given file.
	 *
	 * @param  string $path The relative or absolute path to the SVG file.
	 * @return string       The absolute filepath.
	 */
	public function get_the_normalized_filepath( $path ) {
		if ( empty( $path ) ) {
			return '';
		}

		// Build the file path, validating absolute or relative path.
		return ( $path[0] === DIRECTORY_SEPARATOR )
			? $path
			: rtrim( $this->svg_directory, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . $path;
	}

	/**
	 * Update allowed HTML.
	 *
	 * @param array $attributes Asset attributes.
	 */
	public function update_allowed_html( $attributes ) {
		foreach ( array_keys( $attributes ) as $attr ) {
			$this->symbol_allowed_html['svg'][ $attr ] = true;
		}
	}

	/**
	 * Returns a formatted integer value.
	 *
	 * @param  int|float $value     The value to format.
	 * @param  int       $precision The formatting precision.
	 * @return int|float            The value with the proper precision.
	 */
	public function format_precision( $value, $precision = 2 ) {
		$pow = pow( 10, $precision );
		return ( intval( $value * $pow ) / $pow );
	}

	/**
	 * Returns the contents of an SVG file.
	 *
	 * @param string $path The SVG file path.
	 * @return DOMDocument The SVG file contents.
	 */
	public function get_svg( $path ) {
		if ( empty( $path ) ) {
			return '';
		}

		if ( file_exists( $path ) && 0 === validate_file( $path ) ) {
			$file_contents = file_get_contents( $path );

			if ( ! empty( $file_contents ) ) {
				$svg = new DOMDocument();
				$svg->loadXML( $file_contents );

				return $svg;
			}
		}

		return false;
	}

	/**
	 * Determine an asset's default dimensions.
	 *
	 * @param  DOMDocument $svg   The SVG contents.
	 * @param  array       $asset The asset definition.
	 * @return array              The height and width to use for the asset.
	 */
	public function get_default_dimensions( $svg, $asset ) {
		$attributes = $asset['attributes'] ?? [];

		// Default to the height and width attributes from the asset definition.
		if ( ! empty( $attributes['height'] ) && ! empty( $attributes['width'] ) ) {
			return [
				'width'  => (int) $attributes['width'],
				'height' => (int) $attributes['height'],
			];
		}

		// Fall back to <svg> attribute values if we have both.
		$width_attr  = (int) $svg->getAttribute( 'width' ) ?? 0;
		$height_attr = (int) $svg->getAttribute( 'height' ) ?? 0;

		if ( ! empty( $width_attr ) && ! empty( $height_attr ) ) {
			return [
				'width'  => $width_attr,
				'height' => $height_attr,
			];
		}

		// Use the viewBox attribute values if neither of the above are present.
		$viewbox = $svg->getAttribute( 'viewBox' ) ?? '';

		if ( ! empty( $viewbox ) ) {
			// [0]: min-x, [1]: min-y, [2]: width, [3]: height.
			$viewbox_attr = explode( ' ', $viewbox );

			return [
				'width'  => (int) $viewbox_attr[2],
				'height' => (int) $viewbox_attr[3],
			];
		}

		// We tried...
		return [
			'width'  => 0,
			'height' => 0,
		];
	}

	/**
	 * Perform final mutations before adding an asset to sprite.
	 *
	 * @param array $asset Asset to mutate.
	 * @return array The modified asset definition.
	 */
	public function pre_add_asset( $asset ) {
		// Collect attributes.
		$this->update_allowed_html( $asset['attributes'] ?? [] );

		$src = $this->get_the_normalized_filepath( $asset['src'] );

		return ( empty( $src ) )
			? $asset
			: array_merge( $asset, [ 'src' => $src ] );
	}

	/**
	 * Adds an asset to the sprite sheet.
	 *
	 * @param array $asset An asset definition.
	 * @return void
	 */
	public function add_asset( $asset ): void {
		if ( ! $this->asset_should_add( $asset ) ) {
			return;
		}

		$asset = $this->pre_add_asset( $asset );

		// Get the SVG file contents.
		$svg = $this->get_svg( $asset['src'] ?? '' );

		if ( ! ( $svg instanceof DOMElement ) ) {
			return;
		}

		/*
		 * Try to determine a default size for the SVG.
		 * These dimensions are used to create a ratio for setting the symbol
		 * size when only one dimension is passed via `am_use_symbol()`
		 */
		[
			'width'  => $width,
			'height' => $height,
		] = $this->get_default_dimensions( $svg, $asset );

		if ( ! empty( $width ) && ! empty( $height ) ) {
			$asset['attributes'] = array_merge( $asset['attributes'] ?? [], [ $width, $height ] );
		}

		// Create the <symbol> element.
		$symbol = $this->sprite_document->createElement( 'symbol' );

		// Add the id attribute.
		$symbol_id = $this->sprite_document->createAttribute('id');
		$symbol_id->value = $this->format_handle_as_symbol_id( $asset['handle'] );
		$symbol->appendChild( $symbol_id );

		// Use the viewBox attribute from the SVG asset.
		$viewbox = $svg->getAttribute( 'viewBox' ) ?? '';

		if ( ! empty( $viewbox ) ) {
			$symbol_viewbox = $this->sprite_document->createAttribute('viewBox');
			$symbol_viewbox->value = $viewbox;

			$symbol->appendChild( $symbol_viewbox );
		}

		// Add the SVG's childNodes to the symbol.
		foreach ( iterator_to_array( $svg->childNodes ) as $childNode ) {
			$symbol->appendChild( $childNode );
		}

		// Append the symbol to the SVG sprite.
		$this->svg_root->appendChild( $symbol );

		$this->asset_handles[] = $asset['handle'];
		$this->sprite_map[ $asset['handle'] ] = $asset;
	}

	/**
	 * Returns the SVG markup for displaying a symbol.
	 *
	 * @param  string $handle The symbol handle.
	 * @param  array  $attrs  Additional attributes to add to the <svg> element.
	 * @return string         The <svg> and <use> elements for displaying a symbol.
	 */
	public function get_symbol( $handle, $attrs = [] ) {
		if ( empty( $handle ) || ! in_array( $handle, array_keys( $sprite_map ), true ) ) {
			return '';
		}

		$asset = $sprite_map[ $handle ];

		if ( empty( $asset ) ) {
			return '';
		}

		/*
		 * Use the dimensions from `get_default_dimensions()` to calculate the
		 * expected size when only one dimension is provided in $attrs.
		 */
		if ( ! empty( $asset['attributes']['width'] ) && ! empty( $asset['attributes']['height'] ) ) {
			$use_ratio_for_width  = ( empty( $attrs['width'] ) && ! empty( $attrs['height'] ) );
			$use_ratio_for_height = ( empty( $attrs['height'] ) && ! empty( $attrs['width'] ) );

			$ratio = ( $asset['attributes']['width'] / $asset['attributes']['height'] );

			if ( $use_ratio_for_width ) {
				// width from height: ratio * height.
				$attrs['width'] = $this->format_precision( $ratio * $attrs['height'] );
			} elseif ( $use_ratio_for_height ) {
				// height from width: width / ratio.
				$attrs['height'] = $this->format_precision( $attrs['width'] / $ratio );
			}
		}

		// Merge attributes.
		$local_attrs = array_merge( $this->global_attributes, $asset['attributes'] ?? [], $attrs );
		$local_attrs = array_map( 'esc_attr', $local_attrs );

		// Build a string of all attributes.
		$attrs = '';
		foreach ( $local_attrs as $name => $value ) {
			$attrs .= " $name=" . '"' . $value . '"';
		}

		return sprintf(
			'<svg %1$s><use href="#%2$s"></use></svg>',
			trim( $attrs ),
			esc_attr( $this->format_handle_as_symbol_id( $handle ) )
		);
	}

	/**
	 * Print a symbol's SVG markup.
	 *
	 * @param  string $handle The asset handle.
	 * @param  array  $attrs  Additional HTML attributes to add to the SVG markup.
	 */
	public function use_symbol( $handle, $attrs = [] ) {
		$symbol_markup = $this->get_symbol( $handle, $attrs);

		if ( ! empty( $symbol_markup ) ) {
			echo wp_kses( $symbol_markup, $this->symbol_allowed_html );
		}
	}
}
