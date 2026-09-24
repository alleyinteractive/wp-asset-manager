<?php
/**
 * Trait file for reading values out of an asset definition.
 *
 * @package Asset_Manager
 */

namespace Alley\WP\Asset_Manager\Concerns;

/**
 * Trait for reading values out of an asset definition.
 *
 * Asset definitions are assembled from caller input, so any key may be absent or hold something
 * other than what the documentation describes. These helpers narrow a value once, at the point
 * it's read, rather than trusting the shape of the array.
 */
trait Asset_Values {
	/**
	 * Read a string value out of an asset.
	 *
	 * @param array<string, mixed> $asset Asset to read from.
	 * @param string               $key   Key to read.
	 * @return string
	 */
	protected function asset_string( array $asset, string $key ): string {
		$value = $asset[ $key ] ?? '';

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Read the dependency handles out of an asset.
	 *
	 * @param array<string, mixed> $asset Asset to read from.
	 * @return list<string>
	 */
	protected function asset_deps( array $asset ): array {
		if ( empty( $asset['deps'] ) || ! is_array( $asset['deps'] ) ) {
			return [];
		}

		return array_values( array_filter( $asset['deps'], 'is_string' ) );
	}
}
