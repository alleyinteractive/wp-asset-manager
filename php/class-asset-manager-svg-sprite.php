<?php
/**
 * Class file for Asset_Manager_SVG_Sprite
 *
 * @package AssetManager
 */

/**
 * Asset_Manager_SVG_Sprite class.
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
	public static $_svg_directory; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Array for attributes to add to each symbol.
	 *
	 * @var array
	 */
	public static $_global_attributes; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * The sprite document.
	 *
	 * @var DOMDocument
	 */
	public $sprite_document;

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
	 * Allowed tags and attributes for echoing <svg> and <use> elements.
	 *
	 * @var array
	 */
	public $kses_svg_allowed_tags = [
		'svg' => [],
		'use' => [
			'href' => true,
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
	 * @return Asset_Manager_SVG_Sprite
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new static();
			self::$instance->setup();
			self::$instance->create_sprite_sheet();
		}

		return self::$instance;
	}

	/**
	 * Get the SVG directory.
	 */
	public function get_svg_directory() {
		if ( ! isset( static::$_svg_directory ) ) {
			/**
			 * Filter function for updating the directory upon which a symbol's relative
			 * path will be based.
			 *
			 * @since 1.1.0
			 *
			 * @param string $path The absolute root for relative SVG paths.
			 */
			static::$_svg_directory = apply_filters( 'am_modify_svg_directory', get_stylesheet_directory() );
		}

		return static::$_svg_directory;
	}

	/**
	 * Get the SVG directory.
	 */
	public function get_global_attributes() {
		if ( ! isset( static::$_global_attributes ) ) {
			/**
			 * Filter function for configuring attributes to be added to all SVG symbols.
			 *
			 * @since 1.1.0
			 *
			 * @param array $attributes {
			 *     A list of attributes to be added to all SVG symbols.
			 *
			 *     @type array<string, string> The key represents an HTML attribute.
			 *                                 The value represents attribute's value.
			 * }
			 */
			static::$_global_attributes = apply_filters( 'am_global_svg_attributes', [] );
		}

		return static::$_global_attributes;
	}

	/**
	 * Perform setup tasks.
	 */
	public function setup() {
		/**
		 * Ensures the sprite's `style` attribute isn't escaped.
		 *
		 * @param  string[] $styles Array of allowed CSS properties.
		 * @return string[]         Modified safe inline style properties.
		 */
		add_filter(
			'safe_style_css',
			function( $styles ) {
				$styles[] = 'left';
				$styles[] = 'overflow';
				$styles[] = 'position';
				$styles[] = 'visibility';
				return $styles;
			}
		);

		add_filter( 'wp_kses_allowed_html', [ $this, 'extend_kses_post_with_use_svg' ] );
	}

	/**
	 * Creates the sprite sheet.
	 */
	public function create_sprite_sheet() {
		$this->sprite_document = new DOMDocument();

		$this->svg_root = $this->sprite_document->createElementNS( 'http://www.w3.org/2000/svg', 'svg' );

		$this->svg_root->setAttribute( 'style', 'left:-9999px;overflow:hidden;position:absolute;visibility:hidden' );

		$this->svg_root->setAttribute( 'focusable', 'false' );
		$this->svg_root->setAttribute( 'height', '0' );
		$this->svg_root->setAttribute( 'role', 'none' );
		$this->svg_root->setAttribute( 'viewBox', '0 0 0 0' );
		$this->svg_root->setAttribute( 'width', '0' );

		$this->sprite_document->appendChild( $this->svg_root );

		add_action( 'wp_body_open', [ $this, 'print_sprite_sheet' ], 10 );
	}

	/**
	 * Prints the sprite sheet to the page at `wp_body_open`.
	 */
	public function print_sprite_sheet() {
		// Allowed tags and attributes for SVG.
		include __DIR__ . '/kses-svg.php';

		/**
		 * Filter function for patching in missing attributes and elements for escaping with `wp_kses`.
		 *
		 * @since 1.1.0
		 *
		 * @param array $am_svg_allowed_tags wp_kses allowed SVG for the sprite sheet.
		 */
		$kses_sprite_allowed_tags = apply_filters( 'am_sprite_allowed_tags', $am_kses_svg ?? [] );

		echo wp_kses(
			$this->sprite_document->C14N(),
			$kses_sprite_allowed_tags
		);
	}

	/**
	 * Convenience function that returns the symbol id based on the asset handle.
	 *
	 * @param  string $handle The asset handle.
	 * @return string         The asset handle formatted for use as the symbol id.
	 */
	public function format_handle_as_symbol_id( $handle ) {
		return empty( $handle )
			? ''
			: "am-symbol-{$handle}";
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
		return ( DIRECTORY_SEPARATOR === $path[0] )
			? $path
			: rtrim( $this->get_svg_directory(), DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . $path;
	}

	/**
	 * Update allowed SVG.
	 *
	 * @param array $attributes Asset attributes.
	 */
	public function update_svg_allowed_tags( $attributes ) {
		foreach ( array_keys( $attributes ) as $attr ) {
			$this->kses_svg_allowed_tags['svg'][ $attr ] = true;
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
			$file_contents = file_get_contents( $path ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown

			if ( ! empty( $file_contents ) ) {
				$doc = new DOMDocument();
				$doc->loadXML( $file_contents );
				$svg = $doc->getElementsByTagName( 'svg' );

				if ( ! empty( $svg->item( 0 ) ) ) {
					return $svg->item( 0 );
				}
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
			// 0. min-x, 1. min-y, 2. width, 3. height.
			$viewbox_attr = explode( ' ', $viewbox );

			if ( ! empty( $viewbox_attr[2] ) && ! empty( $viewbox_attr[3] ) ) {
				return [
					'width'  => (int) $viewbox_attr[2],
					'height' => (int) $viewbox_attr[3],
				];
			}
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
	 * @param  array $asset Asset to mutate.
	 * @return array        The modified asset definition.
	 */
	public function pre_add_asset( $asset ) {
		$src = $this->get_the_normalized_filepath( $asset['src'] );

		return ( empty( $src ) )
			? $asset
			: array_merge( $asset, [ 'src' => $src ] );
	}

	/**
	 * Create the <symbol> element based on a given asset.
	 *
	 * @todo Simplify this so we don't have to pass the asset around.
	 *
	 * @param  array $asset The asset definition.
	 * @return array        The modified asset and the symbol element.
	 */
	public function create_symbol( $asset ) {
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
		$default_dimensions = $this->get_default_dimensions( $svg, $asset );

		if ( ! empty( $default_dimensions['width'] ) && ! empty( $default_dimensions['height'] ) ) {
			$asset['attributes'] = array_merge( $asset['attributes'] ?? [], $default_dimensions );
		}

		// Create the <symbol> element.
		$symbol = $this->sprite_document->createElement( 'symbol' );

		// Add the id attribute.
		$symbol->setAttribute( 'id', $this->format_handle_as_symbol_id( $asset['handle'] ) );

		// DOMDocument::getElementById will only work if we set this attribute as the ID.
		$symbol->setIdAttribute( 'id', true );

		// Use the viewBox attribute from the SVG asset.
		$viewbox = $svg->getAttribute( 'viewBox' ) ?? '';

		if ( ! empty( $viewbox ) ) {
			$symbol->setAttribute( 'viewBox', $viewbox );
		}

		// Add the SVG's childNodes to the symbol.
		foreach ( iterator_to_array( $svg->childNodes ) as $child_node ) {
			// Exclude text nodes.
			if ( ! ( $child_node instanceof DOMText ) ) {
				$symbol->appendChild( $this->sprite_document->importNode( $child_node, true ) );
			}
		}

		// Return the asset too, as it may have been modified.
		return [ $asset, $symbol ];
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

		list( $asset, $symbol ) = $this->create_symbol( $asset );

		if ( ! ( $symbol instanceof DOMElement ) ) {
			return;
		}

		// Append the symbol to the SVG sprite.
		$this->svg_root->appendChild( $symbol );

		$this->asset_handles[]                = $asset['handle'];
		$this->sprite_map[ $asset['handle'] ] = $asset;
	}

	/**
	 * Remove a registered symbol.
	 *
	 * @param  array $handle The symbol handle.
	 * @return bool Whether the symbol was removed, or wasn't registered.
	 */
	public function remove_symbol( $handle ): bool {
		if ( ! in_array( $handle, $this->asset_handles ) ) {
			// Success: Handle not previously registered.
			return true;
		}

		// Remove the registered asset handle.
		$idx = array_search( $handle, $this->asset_handles, true );
		unset( $this->asset_handles[ $idx ] );

		// Remove the entry in the sprite_map.
		unset( $this->sprite_map[ $handle ] );

		// Get the registered symbol from the sprite sheet.
		$existing_symbol = $this->sprite_document->getElementById(
			$this->format_handle_as_symbol_id( $handle )
		);

		if ( ! ( $existing_symbol instanceof DOMElement ) ) {
			// Success: There's nothing to remove.
			return true;
		}

		// Remove the symbol.
		$symbol_was_removed = $existing_symbol->parentNode->removeChild( $existing_symbol );

		// `removeChild` returns the old child on success.
		return ! empty( $symbol_was_removed );
	}

	/**
	 * Filter allowed HTML to allow svg & use tags and attributes.
	 *
	 * @param array $allowed Allowed tags, attributes, and/or entities.
	 * @return array filtered tags.
	 */
	public function extend_kses_post_with_use_svg( $allowed ) {
		$use_svg_tags = $this->kses_svg_allowed_tags;
		return array_merge_recursive( $allowed, $use_svg_tags );
	}

	/**
	 * Returns the SVG markup for displaying a symbol.
	 *
	 * @param  string $handle The symbol handle.
	 * @param  array  $attrs  Additional attributes to add to the <svg> element.
	 * @return string         The <svg> and <use> elements for displaying a symbol.
	 */
	public function get_symbol( $handle, $attrs = [] ) {
		if ( empty( $handle ) || ! in_array( $handle, array_keys( $this->sprite_map ), true ) ) {
			return '';
		}

		$asset = $this->sprite_map[ $handle ];

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
		$local_attrs = array_merge(
			$this->get_global_attributes(),
			$asset['attributes'] ?? [],
			$attrs
		);
		$local_attrs = array_map( 'esc_attr', $local_attrs );

		// Ensure attributes are in allowed_html.
		$this->update_svg_allowed_tags( $local_attrs );

		// Build a string of all attributes.
		$attrs = '';
		foreach ( $local_attrs as $name => $value ) {
			if ( empty( $value ) ) {
				continue;
			}
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
		$symbol_markup = $this->get_symbol( $handle, $attrs );

		if ( ! empty( $symbol_markup ) ) {
			echo wp_kses_post( $symbol_markup );
		}
	}
}
