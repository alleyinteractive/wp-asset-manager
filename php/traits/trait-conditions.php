<?php
/**
 * Trait file for asset conditions.
 *
 * @package AssetManager
 */

trait Conditions {
	/**
	 * Get the available conditions for loading assets.
	 */
	public static function get_conditions() {
		if ( ! isset( static::$_conditions ) || ( defined( 'WP_IRVING_TEST' ) && WP_IRVING_TEST ) ) {
			/**
			 * Filter function for getting available conditions to check for whether or not a given asset should load
			 *
			 * @since  0.0.1
			 *
			 * @param array $conditions {
			 * 		List of available conditions
			 *
			 * 		@type bool $condition Condition to check. Accepts any value that can be coerced to a boolean.
			 * }
			 */
			static::$_conditions = apply_filters(
				'am_asset_conditions',
				[
					'global' => true,
					'single' => is_single(),
					'search' => is_search(),
				]
			);
		}

		return static::$_conditions;
	}

	/**
	 * Determine if an asset should be added (enqueued) or not.
	 *
	 * @param string $asset Type of asset.
	 * @return bool|WP_Error
	 */
	public function asset_should_add( $asset ) {
		/**
		 * Filter function for preventing an asset from loading, regardless of conditions
		 *
		 * @since  0.0.1
		 *
		 * @param bool  $add_asset Whether or not to forcefully prevent asset from loading
		 * @param array $asset     Asset to prevent from loading
		 */
		if ( ! apply_filters( 'am_asset_should_add', true, $asset ) ) {
			return false;
		}

		// Assets already added should not be added again.
		if ( empty( $asset['handle'] ) || in_array( $asset['handle'], $this->asset_handles, true ) ) {
			return false;
		}

		// If there's no condition, asset should load.
		if ( empty( $asset['condition'] ) ) {
			return true;
		}

		$conditions       = static::get_conditions();
		$condition_result = true;

		// Default functionality of condition is 'include'.
		if ( ! empty( $asset['condition']['include'] ) ) {
			$condition_include = $asset['condition']['include'];
		} elseif ( ! empty( $asset['condition']['include_any'] ) ) {
			$condition_include_any = $asset['condition']['include_any'];
		} elseif ( empty( $asset['condition']['exclude'] ) ) {
			$condition_include = $asset['condition'];
		}

		// Check 'include' conditions (all must be true for asset to load)
		// There might only be an 'exclude' condition, so check empty() first.
		if ( ! empty( $condition_include ) ) {
			$condition_include = ! is_array( $condition_include ) ? [ $condition_include ] : $condition_include;

			foreach ( $condition_include as $condition_true ) {
				if ( $conditions[ $condition_true ] ) {
					continue;
				} else {
					$condition_result = false;
					break;
				}
			}
		}

		// Check for 'include_any' to allow for matching of _any_ condition instead of all conditions.
		if ( ! empty( $condition_include_any ) ) {
			$condition_result      = false;
			$condition_include_any = ! is_array( $condition_include_any ) ? [ $condition_include_any ] : $condition_include_any;

			foreach ( $condition_include_any as $condition_true ) {
				if ( $conditions[ $condition_true ] ) {
					$condition_result = true;
				}
			}
		}

		// Check 'exclude' conditions (all must be false for asset to load)
		// Verify $condition_result is true. If it's already false, we don't need to check excludes.
		if ( ! empty( $asset['condition']['exclude'] ) && $condition_result ) {
			$condition_exclude = ! is_array( $asset['condition']['exclude'] ) ? [ $asset['condition']['exclude'] ] : $asset['condition']['exclude'];

			foreach ( $condition_exclude as $condition_false ) {
				if ( ! $conditions[ $condition_false ] ) {
					continue;
				} else {
					$condition_result = false;
					break;
				}
			}
		}

		return $condition_result;
	}
}
