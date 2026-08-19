<?php
/**
 * Class file for Asset_Manager_Scripts
 *
 * @package Asset_Manager
 */

namespace Alley\WP\Asset_Manager;

/**
 * Asset_Manager_Scripts
 */
class Scripts extends Asset_Manager {
	/**
	 * Global JS variable on which inline objects should be added as a property
	 *
	 * @var string
	 */
	public string $inline_script_context = 'amScripts';

	/**
	 * Methods by which a script can be loaded into the DOM
	 *
	 * @var string[]
	 */
	public array $load_methods = [ 'inline', 'sync', 'async', 'defer' ];

	/**
	 * Methods for which wp_enqueue_* should be used instead of internal printing function
	 *
	 * @var string[]
	 */
	public array $wp_enqueue_methods = [ 'sync', 'async', 'defer' ];

	/**
	 * Asset type this class is responsible for loading and managing
	 *
	 * @var string|null
	 */
	public ?string $asset_type = 'script';

	/**
	 * Core asset reference filter
	 *
	 * @var string
	 */
	public ?string $core_ref_type = 'scripts';

	/**
	 * Set default properties for script manager
	 */
	public function set_asset_type_defaults(): void {
		/**
		 * Filter function for setting new inline script context.
		 *
		 * @since 0.0.1
		 *
		 * @param string $inline_script_context Property of the window object under which inlined values will be nested.
		 */
		$this->inline_script_context = apply_filters( 'am_inline_script_context', 'amScripts' );
	}

	/**
	 * Modify the load method of a script that's already been added.
	 *
	 * This is generally useful for async or defer loading core scripts.
	 * NOTE: If you call this function on the wp_enqueue_scripts action,
	 * set a low priority to ensure the script you want is available.
	 *
	 * @param string $handle      Handle of script to modify.
	 * @param string $load_method Target load method.
	 */
	public function modify_load_method( $handle, $load_method ): void {
		// Add script if it's a core asset.
		$this->add_core_asset( $handle );

		// Get key of asset, now that it's added.
		$key = array_search( $handle, $this->asset_handles, true );

		// Only modify scripts that have been added, and only use load methods that are valid.
		if ( false !== $key && in_array( $load_method, $this->load_methods, true ) ) {
			$this->assets_by_handle[ $handle ]['load_method'] = $load_method;
			$this->assets[ $key ]['load_method']              = $load_method;

			/*
			 * The script is already registered with WordPress at this point, so the
			 * `strategy` passed to `wp_enqueue_script()` can no longer be changed by
			 * re-enqueueing. `wp_script_add_data()` writes to the same storage that
			 * `WP_Scripts::do_item()` reads when it renders the tag.
			 */
			if ( in_array( $load_method, [ 'async', 'defer' ], true ) ) {
				wp_script_add_data( $handle, 'strategy', $load_method );
			}
		}
	}

	/**
	 * Print a single script.
	 *
	 * @param array<string, mixed> $script Script to insert into DOM.
	 */
	public function print_asset( $script ): void {
		$handle      = $this->asset_string( $script, 'handle' );
		$load_method = $this->asset_string( $script, 'load_method' );
		$src         = $script['src'] ?? '';
		$classes     = $this->default_classes;
		$classes[]   = $handle;

		if ( ! empty( $src ) && ! in_array( $load_method, $this->wp_enqueue_methods, true ) ) {
			if ( 'inline' === $load_method ) {
				if ( is_array( $src ) ) {
					// If src is an array, add it as a property containing a JSON object on a global variable.
					printf(
						'<script class="%1$s" type="text/javascript">window.%2$s = window.%2$s || {}; window.%2$s["%3$s"] = %4$s</script>',
						esc_attr( implode( ' ', $classes ) ),
						esc_js( $this->inline_script_context ),
						esc_js( $handle ),
						wp_json_encode( $src )
					);
				} elseif ( is_string( $src ) && am_validate_path( $src ) ) {
					$file_contents = file_get_contents( $src ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown

					printf(
						'<script class="%1$s" type="text/javascript">%2$s</script>',
						esc_attr( implode( ' ', $classes ) ),
						$file_contents // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					);
				} else {
					$this->generate_asset_error( 'unsafe_inline', $script );
				}
			}
		}
	}

	/**
	 * Perform final mutations before adding script to array.
	 *
	 * @param array<string, mixed> $script Script to mutate.
	 * @return array<string, mixed>
	 */
	public function pre_add_asset( $script ) {
		return $script;
	}

	/**
	 * Add script to async/defer script list.
	 *
	 * @param array<string, mixed> $script Script to add.
	 * @return array<string, mixed>
	 */
	public function post_validate_asset( $script ) {
		$unsafe_dependents = [];
		$load_method       = $this->asset_string( $script, 'load_method' );
		$dependents        = $this->asset_deps( [ 'deps' => $script['dependents'] ?? [] ] );

		if ( $dependents ) {
			if ( 'defer' === $load_method ) {
				// Dependent is unsafe if it's not also 'defer'.
				foreach ( $dependents as $dependent ) {
					$dependent_info = $this->assets_by_handle[ $dependent ] ?? [];

					if ( 'defer' !== $this->asset_string( $dependent_info, 'load_method' ) ) {
						$unsafe_dependents[] = $dependent;
					}
				}
			} elseif ( 'async' === $load_method ) {
				// All dependents are unsafe.
				$unsafe_dependents = $dependents;
			}

			if ( $unsafe_dependents ) {
				$example_dependent = $this->assets_by_handle[ $unsafe_dependents[0] ] ?? [];

				$this->generate_asset_error( 'unsafe_load_method', $script, $example_dependent );
			}
		}

		return $script;
	}
}
