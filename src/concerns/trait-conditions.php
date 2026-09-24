<?php
/**
 * Trait file for asset conditions.
 *
 * @package Asset_Manager
 */

namespace Alley\WP\Asset_Manager\Concerns;

/**
 * Trait for getting and evalutaing asset conditions.
 */
trait Conditions {

	/**
	 * Storage of the asset conditions.
	 *
	 * @var array<string, bool>|null
	 */
	protected static $_conditions = null; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Get the available conditions for loading assets.
	 *
	 * @return array<string, bool>
	 */
	public static function get_conditions() {
		if ( ! isset( static::$_conditions ) || ( defined( 'WP_IRVING_TEST' ) && WP_IRVING_TEST ) ) {
			/**
			 * Filter function for getting available conditions to check for whether or not a given asset should load
			 *
			 * @since  0.0.1
			 *
			 * @param array $conditions {
			 *     List of available conditions
			 *
			 *     @type bool $condition Condition to check. Accepts any value that can be coerced to a boolean.
			 * }
			 */
			$filtered = apply_filters(
				'am_asset_conditions',
				[
					'global' => true,
					'single' => is_single(),
					'search' => is_search(),
				]
			);

			// A filter can return anything, so coerce it back into a map of condition names.
			$conditions = [];

			foreach ( $filtered as $name => $value ) {
				if ( is_string( $name ) ) {
					$conditions[ $name ] = (bool) $value;
				}
			}

			static::$_conditions = $conditions;
		}

		return static::$_conditions;
	}

	/**
	 * Determine if an asset should be added (enqueued) or not.
	 *
	 * @param array<string, mixed> $asset Asset to check.
	 * @return bool
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

		// Already-added assets should not be added again.
		if ( empty( $asset['handle'] ) || in_array( $asset['handle'], $this->asset_handles, true ) ) {
			return false;
		}

		// If there's no condition, asset should load.
		if ( empty( $asset['condition'] ) ) {
			return true;
		}

		$condition  = $asset['condition'];
		$conditions = static::get_conditions();

		/*
		 * A condition is either a bare condition name, a list of them, or a map of
		 * `include` / `include_any` / `exclude` lists. Only the map form has keys to read.
		 */
		$map = is_array( $condition ) ? $condition : [];

		$condition_include     = [];
		$condition_include_any = [];
		$condition_exclude     = $this->condition_list( $map['exclude'] ?? [] );

		if ( ! empty( $map['include'] ) ) {
			$condition_include = $this->condition_list( $map['include'] );
		} elseif ( ! empty( $map['include_any'] ) ) {
			$condition_include_any = $this->condition_list( $map['include_any'] );
		} elseif ( ! $condition_exclude ) {
			// No keys at all, so the whole value is the list of conditions to include.
			$condition_include = $this->condition_list( $condition );
		}

		$condition_result = true;

		// Check 'include' conditions (all must be true for asset to load).
		foreach ( $condition_include as $condition_true ) {
			if ( empty( $conditions[ $condition_true ] ) ) {
				$condition_result = false;
				break;
			}
		}

		// Check for 'include_any' to allow for matching of _any_ condition instead of all conditions.
		if ( $condition_include_any ) {
			$condition_result = false;

			foreach ( $condition_include_any as $condition_true ) {
				if ( ! empty( $conditions[ $condition_true ] ) ) {
					$condition_result = true;
					break;
				}
			}
		}

		// Check 'exclude' conditions (all must be false for asset to load).
		// Verify $condition_result is true. If it's already false, we don't need to check excludes.
		if ( $condition_exclude && $condition_result ) {
			foreach ( $condition_exclude as $condition_false ) {
				if ( ! empty( $conditions[ $condition_false ] ) ) {
					$condition_result = false;
					break;
				}
			}
		}

		return $condition_result;
	}

	/**
	 * Normalize a condition value into a list of condition names.
	 *
	 * Conditions are supplied by the caller, so a single name and a list of names are both
	 * accepted, and anything that isn't a usable name is dropped rather than compared.
	 *
	 * @param mixed $condition One condition name, or a list of them.
	 * @return list<string>
	 */
	private function condition_list( $condition ): array {
		$names = [];

		foreach ( is_array( $condition ) ? $condition : [ $condition ] as $name ) {
			if ( is_string( $name ) && '' !== $name ) {
				$names[] = $name;
			}
		}

		return $names;
	}
}
