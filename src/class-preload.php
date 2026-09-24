<?php
/**
 * Class file for Asset_Manager_Preload
 *
 * @package Asset_Manager
 */

namespace Alley\WP\Asset_Manager;

/**
 * Asset_Manager_Preload class.
 */
class Preload extends Asset_Manager {
	/**
	 * Types of files that can be preloaded; corresponds to allowed `as` attribute values.
	 *
	 * @var string[]
	 */
	public array $preload_as = [
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
	 * Allowed values for the `fetchpriority` attribute.
	 *
	 * @var string[]
	 */
	public array $fetchpriority_values = [ 'auto', 'high', 'low' ];

	/**
	 * Asset type this class is responsible for loading and managing.
	 *
	 * @var string|null
	 */
	public ?string $asset_type = 'preload';

	/**
	 * Methods by which an asset can be loaded into the DOM.
	 *
	 * @var string[]
	 */
	public array $load_methods = [ 'preload' ];

	/**
	 * Map of asset 'as' and 'type` attributes based on file extension, used to
	 * patch in attributes for commonly-preloaded assets.
	 *
	 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Common_types
	 *
	 * @var array<string, array<string, string>>
	 */
	public array $asset_types = [
		'css'   => [
			'as'        => 'style',
			'mime_type' => 'text/css',
		],
		'js'    => [
			'as'        => 'script',
			'mime_type' => 'text/javascript',
		],
		'woff2' => [
			'as'        => 'font',
			'mime_type' => 'font/woff2',
		],
	];

	/**
	 * Print a single asset
	 *
	 * @param array<string, mixed> $asset Asset to insert into DOM.
	 */
	public function print_asset( $asset ): void {
		$as           = $this->asset_string( $asset, 'as' );
		$src          = $this->asset_string( $asset, 'src' );
		$classes      = $this->default_classes;
		$classes[]    = $this->asset_string( $asset, 'handle' );
		$print_string = '';

		if ( '' === $as || ! in_array( $as, $this->preload_as, true ) ) {
			// We weren't able to patch in the 'as' attribute in `post_validate_asset`.
			$this->generate_asset_error( 'invalid_preload_as_attribute', $asset );
		} elseif ( '' !== $src ) {
			$print_string = '<link rel="preload" href="%1$s" class="%2$s" as="%3$s" media="%4$s" %5$s %6$s %7$s %8$s %9$s />';

			if ( in_array( $as, [ 'style', 'script' ], true ) ) {
				// Make sure we include the asset version for styles and scripts..
				$src = add_query_arg(
					'ver',
					$this->asset_string( $asset, 'version' ),
					$src
				);
			}

			echo wp_kses(
				sprintf(
					$print_string,
					esc_url( $src ),
					esc_attr( implode( ' ', $classes ) ),
					esc_attr( $as ),
					esc_attr( $this->asset_string( $asset, 'media' ) ),
					empty( $asset['mime_type'] ) ? '' : sprintf( 'type="%s" ', esc_attr( $this->asset_string( $asset, 'mime_type' ) ) ),
					! empty( $asset['crossorigin'] ) ? 'crossorigin' : '',
					empty( $asset['imagesrcset'] ) ? '' : sprintf( 'imagesrcset="%s"', esc_attr( $this->asset_string( $asset, 'imagesrcset' ) ) ),
					empty( $asset['imagesizes'] ) ? '' : sprintf( 'imagesizes="%s"', esc_attr( $this->asset_string( $asset, 'imagesizes' ) ) ),
					empty( $asset['fetchpriority'] ) ? '' : sprintf( 'fetchpriority="%s"', esc_attr( $this->asset_string( $asset, 'fetchpriority' ) ) )
				),
				[
					'link' => [
						'rel'           => [],
						'href'          => [],
						'class'         => [],
						'as'            => [],
						'media'         => [],
						'type'          => [],
						'crossorigin'   => [],
						'imagesrcset'   => [],
						'imagesizes'    => [],
						'fetchpriority' => [
							'values' => $this->fetchpriority_values,
						],
					],
				]
			);
		}
	}

	/**
	 * Perform final mutations before adding asset to array.
	 *
	 * @param array<string, mixed> $asset Asset to mutate.
	 * @return array<string, mixed>
	 */
	public function pre_add_asset( $asset ) {
		// This is the only valid option, so we're patching it here.
		$asset['load_method'] = 'preload';
		// Preloads will always be in <head>, so we force the `wp_head` load hook.
		$asset['load_hook'] = 'wp_head';

		// Flatten the array form of `imagesrcset` so everything downstream sees a string.
		if ( ! empty( $asset['imagesrcset'] ) && is_array( $asset['imagesrcset'] ) ) {
			$asset['imagesrcset'] = $this->build_imagesrcset( $asset['imagesrcset'], $this->asset_string( $asset, 'handle' ) );
		}

		return $asset;
	}

	/**
	 * Build an `imagesrcset` attribute value from a map of descriptors to image URLs.
	 *
	 * The array is keyed by descriptor so each candidate is declared once. An integer key
	 * becomes a width descriptor — `400 => 'hero-400.jpg'` gives `hero-400.jpg 400w` — and a
	 * string key is used as-is, which is how pixel density candidates such as `2x` are declared.
	 *
	 * @param array<int|string, mixed> $candidates Map of descriptor to image URL.
	 * @param string                   $handle     Handle for the asset, used in error messages.
	 * @return string
	 */
	public function build_imagesrcset( array $candidates, string $handle = '' ): string {
		/*
		 * A list has sequential integer keys, which would be read as widths and produce
		 * nonsense like `hero.jpg 0w`.
		 */
		if ( array_is_list( $candidates ) ) {
			_doing_it_wrong(
				'am_preload',
				sprintf(
					/* translators: %s: the asset handle */
					esc_html__( 'The array form of "imagesrcset" for "%s" must be keyed by descriptor, e.g. [ 480 => \'hero-480.jpg\' ]. The attribute will not be printed.', 'wp-asset-manager' ),
					esc_html( $handle )
				),
				'2.0.0'
			);

			return '';
		}

		$srcset = [];

		foreach ( $candidates as $descriptor => $url ) {
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}

			$srcset[] = $url . ' ' . ( is_int( $descriptor ) ? $descriptor . 'w' : $descriptor );
		}

		return implode( ', ', $srcset );
	}

	/**
	 * Perform mutations to asset after validation.
	 *
	 * @param array<string, mixed> $asset Asset to mutate.
	 * @return array<string, mixed>
	 */
	public function post_validate_asset( $asset ) {
		// Attempt to patch the `as` and `mime_type` values if either is missing.
		if ( empty( $asset['as'] ) || empty( $asset['mime_type'] ) ) {
			$asset = $this->set_asset_types( $asset );
		}

		// `imagesrcset` is only meaningful on an image, so infer `as` when it wasn't supplied.
		if ( ! empty( $asset['imagesrcset'] ) && empty( $asset['as'] ) ) {
			$asset['as'] = 'image';
		}

		if ( ! empty( $asset['imagesizes'] ) && empty( $asset['imagesrcset'] ) ) {
			_doing_it_wrong(
				'am_preload',
				sprintf(
					/* translators: %s: the asset handle */
					esc_html__( '"imagesizes" has no effect for "%s" without "imagesrcset" and will not be printed.', 'wp-asset-manager' ),
					esc_html( $this->asset_string( $asset, 'handle' ) )
				),
				'2.0.0'
			);

			unset( $asset['imagesizes'] );
		}

		if (
			! empty( $asset['fetchpriority'] )
			&& ! in_array( $asset['fetchpriority'], $this->fetchpriority_values, true )
		) {
			_doing_it_wrong(
				'am_preload',
				sprintf(
					/* translators: 1: the unsupported fetchpriority value, 2: the asset handle, 3: comma-separated list of supported values */
					esc_html__( 'Unsupported "fetchpriority" value "%1$s" for "%2$s". The attribute will not be printed. Supported values: %3$s.', 'wp-asset-manager' ),
					esc_html( $this->asset_string( $asset, 'fetchpriority' ) ),
					esc_html( $this->asset_string( $asset, 'handle' ) ),
					esc_html( implode( ', ', $this->fetchpriority_values ) )
				),
				'2.0.0'
			);

			unset( $asset['fetchpriority'] );
		}

		if ( ! empty( $asset['as'] ) && 'font' === $asset['as'] ) {
			/**
			 * Preloading fonts requires the `crossorigin` attribute.
			 *
			 * @see https://drafts.csswg.org/css-fonts/#font-fetching-requirements
			 */
			$asset['crossorigin'] = true;
		}

		return $asset;
	}

	/**
	 * Get the type and/or MIME type for an asset based on its file extension.
	 * A MIME type isn't required, but will prevent the browser downloading an
	 * asset it doesn't support.
	 *
	 * @param array<string, mixed> $asset The asset for which the types are needed.
	 * @return array<string, mixed>
	 */
	public function set_asset_types( $asset ) {
		$src = $this->asset_string( $asset, 'src' );

		if ( empty( $asset ) || '' === $src ) {
			return $asset;
		}

		$path_parts = pathinfo( $src );

		if ( empty( $path_parts['extension'] ) ) {
			return $asset;
		}

		$asset_types = $this->asset_types[ $path_parts['extension'] ] ?? [];

		// Force these values through.
		if ( ! empty( $asset_types ) ) {
			return array_replace( $asset, $asset_types );
		}

		return $asset;
	}
}
