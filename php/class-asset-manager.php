<?php
/**
 * Class file for Asset_Manager
 *
 * @package AssetManager
 */

abstract class Asset_Manager {

	/**
	 * Array of assets to insert
	 *
	 * @var array
	 */
	public $assets = array();

	/**
	 * Reference array of asset handles
	 *
	 * @var array
	 */
	public $asset_handles = array();

	/**
	 * Reference array of assets with their handle as the array key
	 *
	 * @var array
	 */
	public $assets_by_handle = array();

	/**
	 * Reference to default assets in WP core
	 *
	 * @var array
	 */
	public $core_assets_ref = array();

	/**
	 * Variable name for global containing WP assets
	 *
	 * @var string
	 */
	public $core_assets_global = '';

	/**
	 * Variable name for core enqueue function
	 *
	 * @var string
	 */
	public $wp_enqueue_function = '';

	/**
	 * Array of assets, organized by dependencies
	 *
	 * @var array
	 */
	public $assets_by_dependency = array();

	/**
	 * Array of manually-loaded assets (no auto-dependency management)
	 *
	 * @var array
	 */
	public $assets_manual = array();

	/**
	 * Array of conditions with which to determine when a assets loads
	 *
	 * @var array
	 */
	public $conditions = array();

	/**
	 * Array of default classes to add to printed assets handles
	 *
	 * @var array
	 */
	public $default_classes = array();

	/**
	 * Methods by which a asset can be loaded into the DOM
	 *
	 * @var array
	 */
	public $load_methods = array( 'sync', 'async' );

	/**
	 * Methods for which wp_enqueue_* should be used instead of internal printing function
	 *
	 * @var array
	 */
	public $wp_enqueue_methods = array( 'sync' );

	/**
	 * Asset type this class is responsible for loading and managing
	 *
	 * @var string
	 */
	public $asset_type = null;

	/**
	 * Core asset reference filter
	 *
	 * @var string
	 */
	public $core_ref_type = null;

	/**
	 * Actions on which to load assets. Configure these in order of first to last, according to when they're called in the HTML document.
	 *
	 * Additional information:
	 * 	  - These hooks correspond to the $load_hook argument when adding an asset, and determine where an asset is inserted into the DOM
	 *	  - For any custom actions, you will have to add a call to do_action yourself in the appropriate location
	 *    - wp_enqueue_* will only allow you to print assets on the wp_print_* actions, so you may need to set up a custom print condition
	 *    - Custom actions will likely require that you enqueue the assets on the same action, but with an earlier priority.
	 *
	 * @var array: {
	 * 		@type array $hook: {
	 *			@type int $function Function to call on this hook and priority to call it with
	 * 		}
	 * }
	 */
	public $load_hooks = array(
		'am_critical' => array(
			'validate_assets' => 15,
			'load_assets' => 20,
		),
		'wp_head' => array(
			'validate_assets' => 1,
			'load_assets' => 5,
		),
		'wp_footer' => array(
			'validate_assets' => 12,
			'load_assets' => 15,
		),
	);

	/**
	 * Default print function throws error (and prints nothing)
	 *
	 * @param {array} $asset - asset to print
	 */
	abstract function print_asset( $asset );

	/**
	 * Perform final mutations before adding asset to array
	 *
	 * @param {array} $asset - asset to mutate
	 * @return $array
	 */
	abstract function pre_add_asset( $asset );

	/**
	 * Perform mutations to asset after validation
	 *
	 * @param {array} $asset - asset to mutate
	 * @return $array
	 */
	abstract function post_validate_asset( $asset );

	private function __construct() {
		// Don't do anything, needs to be initialized via instance() method.
	}

	/**
	 * Set default properties
	 * NOTE: $handle provided when enqeueing the asset will always be added as a class
	 *
	 * @return void
	 */
	public function set_defaults() {
		$this->default_classes = apply_filters( 'am_asset_classes', array( 'wp-asset-manager' ) );
		$this->be_quiet = apply_filters( 'am_ignore_asset_errors', false );

		if ( ! empty( $this->asset_type ) ) {
			$this->wp_enqueue_function = 'wp_enqueue_' . $this->asset_type;
		}

		if ( ! empty( $this->core_ref_type ) ) {
			$this->core_assets_global = 'wp_' . $this->core_ref_type;
		}
	}

	/**
	 * Add hooks for outputting assets
	 *
	 * @return void
	 */
	public function add_hooks() {
		foreach ( $this->load_hooks as $hook => $functions ) {
			foreach ( $functions as $function => $priority ) {
				add_action( $hook, array( $this, $function ), $priority );
			}
		}
	}

	/**
	 * Set reference to core assets
	 *
	 * @return void
	 */
	public function set_core_assets_ref( $assets ) {
		$this->core_assets_ref = $assets->registered;
	}

	/**
	 * Add a asset to the manifest of assets to load
	 *
	 * @param {array} $args - Arguments for loading asset. May differ based on asset type, but most contain: {
	 * 		@type string $handle Handle for asset. Currently not used, but could be used to dequeue assets in the future.
	 * 		@type string $src URI for src attribute of printed asset handle
	 * 		@type string $condition Corresponds to a configured condition under which the asset should be loaded
	 * 		@type string $load_hook note: generally loading using 'defer' for load_method is better than setting 'wp_footer' for load_hook
	 * 		@type string $load_method Style with which to load this asset. Options are 'auto', 'sync' (or false), 'async', 'defer'
	 * }
	 */
	public function add_asset( $args ) {
		$wp_enqueue_function = $this->wp_enqueue_function;
		$args['type'] = $this->asset_type;

		if ( $this->asset_should_add( $args ) ) {
			// Validate load style
			if ( empty( $args['load_method'] ) || ! in_array( $args['load_method'], $this->load_methods, true ) ) {
				$args['load_method'] = 'sync';
			}

			// Set in footer value based on load_hook
			if ( empty( $args['in_footer'] ) ) {
				$args['in_footer'] = ! empty( $args['load_hook'] ) && 'wp_footer' === $args['load_hook'] ? true : false;
			}

			// Set load_hook value based on in_footer
			if ( empty( $args['load_hook'] ) ) {
				$args['load_hook'] = ! empty( $args['in_footer'] ) ? 'wp_footer' : 'wp_head';
			}

			$args = $this->pre_add_asset( $args );

			// Enqueue asset if applicable
			if ( in_array( $args['load_method'], $this->wp_enqueue_methods, true ) && empty( $args['loaded'] ) ) {
				if ( function_exists( $wp_enqueue_function ) ) {
					$wp_enqueue_function( $args['handle'], $args['src'], $args['deps'], $args['version'], $args['in_footer'] );
					$args['loaded'] = true;
				} else {
					echo wp_kses_post( $this->format_error( $this->generate_asset_error( 'invalid_enqueue_function', false, $wp_enqueue_function ) ) );
				}
			}

			// Add to asset arrays
			$this->assets[] = $this->assets_by_handle[ $args['handle'] ] = $args;
			$this->asset_handles[] = $args['handle'];
		}
	}

	/**
	 * Loop through assets and print each on the appropriate hook, as specified
	 *
	 * @return void
	 */
	public function load_assets() {
		foreach ( $this->assets as $idx => $asset ) {
			if ( $this->asset_should_load( $asset ) ) {
				$this->print_asset( $asset );
				$this->assets[ $idx ]['loaded'] = true;
			}
		}
	}

	/**
	 * Consolidate direct dependents of this asset
	 *
	 * @param {array} $asset - asset to sort in the dependency array
	 * @return {array}
	 */
	public function find_dependents( $asset ) {
		$dependents = array();

		// Loop through each asset and check if this one is in its dependency array
		foreach ( $this->assets as $current_asset ) {
			if ( ! empty( $current_asset['deps'] ) && in_array( $asset['handle'], $current_asset['deps'], true ) ) {
				$dependents[] = $current_asset['handle'];
			}
		};

		return $dependents;
	}

	/**
	 * Make sure the assets and their dependencies are valid
	 *
	 * @return {bool|WP_Error}
	 */
	public function validate_assets() {
		foreach ( $this->assets as $idx => $asset ) {
			// Collect dependents
			$asset['dependents'] = $this->find_dependents( $asset );

			// Enqueue dependencies either part of core or enqueued directly through wp_enqueue_*
			$this->add_core_dependencies( $asset );

			// Validate asset load_hook
			$available_hooks = array_keys( $this->load_hooks );
			$asset_load_hook_key = array_search( $asset['load_hook'], $available_hooks, true );

			if ( empty( $this->load_hooks[ $asset['load_hook'] ] ) ) {
				$this->generate_asset_error( 'invalid_load_hook', $asset );
				continue;
			}

			// Check for missing dependencies or mismatched load_hook
			if ( ! empty( $asset['deps'] ) ) {
				foreach ( $asset['deps'] as $dependency ) {
					$this_dep = array();

					// Check if dependency exists
					if ( empty( $this->assets_by_handle[ $dependency ] ) ) {
						$this->generate_asset_error( 'missing', $asset, $dependency );
						// Skip to the next dependency if this one is missing, as none of the other errors will be relevant
						continue;
					} else {
						$this_dep = $this->assets_by_handle[ $dependency ];
					}

					$dep_load_hook_key = array_search( $this_dep['load_hook'], $available_hooks, true );

					// Ensure dependency is loading in an appropriate load_hook
					if ( $dep_load_hook_key > $asset_load_hook_key ) {
						$this->generate_asset_error( 'unsafe_load_hook', $this_dep, $asset );
					}

					// Ensure dependencies don't require each other
					if ( ! empty( $this_dep['deps'] )
						&& in_array( $asset['handle'], $this_dep['deps'], true )
						&& in_array( $this_dep['handle'], $asset['deps'], true )
					) {
						$this->generate_asset_error( 'circular_dependency', $asset, $this_dep['handle'] );
					}
				};
			}

			// Perform any type-specific validation checks or array mutation after validation
			$this->post_validate_asset( $asset );

			// Reset asset in arrays
			$this->assets[ $idx ] = $asset;
			$this->assets_by_handle[ $asset['handle'] ] = $asset;
			$this->asset_handles[ $idx ] = $asset['handle'];
		} // End foreach().
	}

	/**
	 * Check if a asset has any dependencies that exist in WP Core and, if so, enqueue them
	 *
	 * @param {array} $asset - asset on which we need to check for core dependencies
	 * @return void
	 */
	public function add_core_dependencies( $asset ) {
		$load_method = ! empty( $asset['load_method'] ) ? $asset['load_method'] : 'sync';

		if ( ! empty( $asset['deps'] ) ) {
			foreach ( $asset['deps'] as $idx => $dependency ) {
				$this->add_core_asset( $dependency, $load_method );
			}
		}
	}

	/**
	 * Add a core asset to the custom asset array, so we can track it as a dependency and make load method modifications
	 *
	 * @param {string} $handle - Handle of core asset to add
	 * @param {string} $load_method - Customize load method of core asset, otherwise leave it as syncronous
	 * @return void
	 */
	public function add_core_asset( $handle, $load_method = 'sync' ) {
		global ${$this->core_assets_global};
		$core_assets_ref = ${$this->core_assets_global}->registered;
		$in_footer = ${$this->core_assets_global}->in_footer;
		$core_asset_handles = array_keys( ${$this->core_assets_global}->registered );

		// Add assets that are wp_enqueued_* for custom enqueues, but only if they're not also custom enqueued
		// Otherwise, we run the risk of enqueuing an asset twice
		if ( ! in_array( $handle, $this->asset_handles, true ) && in_array( $handle, $core_asset_handles, true ) ) {
			$core_asset = $core_assets_ref[ $handle ];
			$is_in_footer = in_array( $core_asset->handle, $in_footer, true );
			$is_enqueued = wp_style_is( $handle, 'enqueued' ) || wp_script_is( $handle, 'enqueued' );

			$this->add_asset( array(
				'handle' => $handle,
				'src' => $core_asset->src,
				'condition' => 'global',
				'deps' => $core_asset->deps ? $core_asset->deps : array(),
				'in_footer' => $is_in_footer,
				'load_hook' => $is_in_footer ? 'wp_footer' : 'wp_head',
				'loaded' => ! $is_enqueued ? false : true,
				'load_method' => ! $is_enqueued ? $load_method : 'sync',
				'type' => $this->asset_type,
				'version' => $core_asset->ver,
			) );
		}
	}

	/**
	 * Determine if an asset should be added (enqueued) or not
	 *
	 * @return {bool|WP_Error}
	 */
	public function asset_should_add( $asset ) {
		if ( ! apply_filters( 'am_asset_should_add', true, $asset ) ) {
			return false;
		}

		// Already-added assets should not be added again
		if ( empty( $asset['handle'] ) || in_array( $asset['handle'], $this->asset_handles, true ) ) {
			return false;
		}

		// If there's no condition, asset should load
		if ( empty( $asset['condition'] ) ) {
			return true;
		}

		// Check conditions
		$conditions = apply_filters( 'am_asset_conditions', array(
			'global' => true,
			'single' => is_single(),
			'search' => is_search(),
		) );
		$condition_result = true;

		// Default functionality of condition is 'include'
		if ( ! empty( $asset['condition']['include'] ) ) {
			$condition_include = $asset['condition']['include'];
		} elseif ( empty( $asset['condition']['exclude'] ) ) {
			$condition_include = $asset['condition'];
		}

		// Check 'include' conditions (all must be true for asset to load)
		// There might only be an 'exclude' condition, so check empty() first
		if ( ! empty( $condition_include ) ) {
			$condition_include = ! is_array( $condition_include ) ? array( $condition_include ) : $condition_include;

			foreach( $condition_include as $condition_true ) {
				if ( $conditions[ $condition_true ] ) {
					continue;
				} else {
					$condition_result = false;
					break;
				}
			}
		}

		// Check 'exclude' conditions (all must be false for asset to load)
		// Verify $condition_result is true. If it's already false, we don't need to check excludes.
		if ( ! empty( $asset['condition']['exclude'] ) && $condition_result ) {
			$condition_exclude = ! is_array( $asset['condition']['exclude'] ) ? array( $asset['condition']['exclude'] ) : $asset['condition']['exclude'];

			foreach( $condition_exclude as $condition_false ) {
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

	/**
	 * Verify an asset should load in the current load cycle
	 *
	 * @param {array} $asset - asset to check
	 * @return {bool}
	 */
	public function asset_should_load( $asset ) {
		$this_action = current_filter();
		$available_hooks = array_keys( $this->load_hooks );
		$target_hook_position = array_search( $asset['load_hook'], $available_hooks, true );
		$current_hook_position = array_search( $this_action, $available_hooks, true );

		// Load assets that are configured to load on this hook or on a previous hook but were enqueued too late
		$dom_position_matches = ! empty( $asset['load_hook'] )
			&& ( $target_hook_position <= $current_hook_position || false === $target_hook_position );
		$has_src = ! empty( $asset['src'] );
		// Load assets that have not yet been loaded
		$asset_loaded = ! empty( $asset['loaded'] ) ? $asset['loaded'] : false;

		return $dom_position_matches && $has_src && ! $asset_loaded;
	}

	/**
	 * Generate and echo a WP_Error based on a provided error code
	 *
	 * @param {array} $code - error code
	 * @param {array} $asset - offending asset
	 * @param {array|string} $info - additional information about a dependency or dependent
	 * @return {WP_Error}
	 */
	public function generate_asset_error( $code, $asset, $info = false ) {
		switch ( $code ) {
			case 'circular_dependency':
				$message = sprintf( __( 'You have a circular dependency in your enqueues. <strong>%1$s</strong> and <strong>%2$s</strong> require each other as dependencies.', 'am' ), $asset['handle'], $info );
				break;

			case 'invalid_load_hook':
				$message = sprintf( __( 'Asset <strong>%1$s</strong> is using an invalid load_hook. The asset is configured to load on hook <strong>%2$s</strong>, but this hook does not exist.', 'am' ), $asset['handle'], $asset['load_hook'] );
				break;

			case 'unsafe_load_hook':
				$message = sprintf( __( 'Asset <strong>%1$s</strong>, configured to load on hook <strong>%2$s</strong>, is loading after an asset that depends on it: <strong>%3$s</strong>, configured to load on hook <strong>%4$s</strong>', 'am' ), $asset['handle'], $asset['load_hook'], $info['handle'], $info['load_hook'] );
				break;

			case 'missing':
				$message = sprintf( __( 'A dependency you listed for this asset is invalid. <strong>%1$s</strong> lists <strong>%2$s</strong> as a dependency, but that asset is not configured to load on this page.', 'am' ), $asset['handle'], $info );
				break;

			case 'cannot_print':
				$message = sprintf( __( 'Asset of type <strong>%1$s</strong> does not exist or does not have a print_asset() function configured.', 'am' ), $asset['type'] );
				break;

			case 'invalid_enqueue_function':
				$message = sprintf( __( 'You attempted to enqueue an asset with function %1$s, which does not exist.', 'am' ), $info );
				break;

			case 'unsafe_load_method':
				$message = sprintf( __( 'Asset <strong>%1$s</strong> uses the <strong>%2$s</strong> load method, meaning there is no guarantee it will be available for its dependent asset <strong>%3$s</strong>, using <strong>%4$s</strong> load method.', 'am' ), $asset['handle'], $asset['load_method'], $info['handle'], $info['load_method'] );
				break;

			case 'unsafe_inline':
				$message = sprintf( __( 'You attempted to load <strong>%1$s</strong> using the "inline" load method, but it is an external asset or the asset does not exist.', 'am' ), $asset['src'] );
				break;

			default:
				$message = sprintf( __( 'Something went wrong when enqueueing <strong>%s</strong>.', 'am' ), $asset['handle'] );
				break;
		}

		$this->format_error( new WP_Error( $code, $message, $asset ) );
	}

	/**
	 * Display an error to the user
	 *
	 * @param {WP_Error} $error - error to display to user
	 * @return {string}
	 */
	public function format_error( $error ) {
		if ( current_user_can( 'manage_options' ) ) {
			$code = $error->get_error_code();
			echo wp_kses( '<div class="enqueue-error"><strong>ENQUEUE ERROR</strong>: <em>' . $code . '</em> - ' . $error->get_error_message( $code ) . ' Bad asset: <br><pre>' . print_r( $error->get_error_data( $code ), true ) . '</pre></div>', array(
				'div' => array( 'class' ),
				'strong' => array(),
				'em' => array(),
				'pre' => array(),
			) );
		}
	}
}
