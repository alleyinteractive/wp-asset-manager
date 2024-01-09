<?php
/**
 * Class file for Asset_Manager
 *
 * @package AssetManager
 */

namespace Alley\WP\Asset_Manager;

/**
 * Asset_Manager
 *
 * Asset manager class.
 */
abstract class Asset_Manager {
	use Concerns\Asset_Error;
	use Concerns\Singleton;
	use Concerns\Conditions;

	/**
	 * Array of assets to insert
	 *
	 * @var array
	 */
	public $assets = [];

	/**
	 * Reference array of asset handles
	 *
	 * @var array
	 */
	public $asset_handles = [];

	/**
	 * Reference array of assets with their handle as the array key
	 *
	 * @var array
	 */
	public $assets_by_handle = [];

	/**
	 * Boolean to define whether or not to ignore errors when enqueueing assets
	 *
	 * @var bool
	 */
	public $be_quiet = false;

	/**
	 * Reference to default assets in WP core
	 *
	 * @var array
	 */
	public $core_assets_ref = [];

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
	public $assets_by_dependency = [];

	/**
	 * Array of manually-loaded assets (no auto-dependency management)
	 *
	 * @var array
	 */
	public $assets_manual = [];

	/**
	 * Array of conditions with which to determine when a assets loads
	 *
	 * @var array
	 */
	public $conditions = [];

	/**
	 * Array of default classes to add to printed assets handles
	 *
	 * @var array
	 */
	public $default_classes = [];

	/**
	 * Methods by which a asset can be loaded into the DOM
	 *
	 * @var array
	 */
	public $load_methods = [ 'sync', 'async' ];

	/**
	 * Methods for which wp_enqueue_* should be used instead of internal printing function
	 *
	 * @var array
	 */
	public $wp_enqueue_methods = [ 'sync' ];

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
	 * Actions on which to load assets.
	 *
	 * IMPORTANT NOTE: Order matters! Configure these in order of first to last,
	 * according to roughly at what point in DOM construction the hook is called.
	 * For example, wp_head should always be configured before wp_footer!
	 *
	 * Troubleshooting:
	 *    - These hooks correspond to the $load_hook argument when adding an asset,
	 *      and should be used to determine where an asset is inserted into the DOM.
	 *    - For any custom actions, you will have to add a call to do_action yourself in the appropriate location
	 *    - wp_enqueue_* will only allow you to print assets on the wp_print_* actions,
	 *      so if you are using a load method that allows enqueuing via `wp_enqueue_*` then you may need to override
	 *      it with a custom print condition.
	 *    - Custom actions will likely require that you enqueue the assets on the same action, but with an earlier priority.
	 *
	 * @var array $load_hooks {
	 *      List of available hooks on which an asset can be loaded. These can be any valid hook.
	 *
	 *      @type array $hook {
	 *          Hook name. Any valid hook in core or otherwise.
	 *
	 *          @type string $method Class method to call on the provided hook. Accepts an integer corresponding to the priority                                 at which to call the method
	 *      }
	 * }
	 */
	public $load_hooks = [
		'am_critical' => [
			'validate_assets' => 15,
			'load_assets'     => 20,
		],
		'wp_head'     => [
			'validate_assets' => 1,
			'load_assets'     => 5,
		],
		'wp_footer'   => [
			'validate_assets' => 12,
			'load_assets'     => 15,
		],
	];

	/**
	 * Default print function throws error (and prints nothing)
	 *
	 * @param array $asset Asset to print.
	 */
	abstract public function print_asset( $asset );

	/**
	 * Perform final mutations before adding asset to array
	 *
	 * @param array $asset Asset to mutate.
	 * @return $array
	 */
	abstract public function pre_add_asset( $asset );

	/**
	 * Perform mutations to asset after validation
	 *
	 * @param array $asset Asset to mutate.
	 *
	 * @return array
	 */
	abstract public function post_validate_asset( $asset );

	/**
	 * Constructor
	 */
	protected function __construct() {
		$this->add_hooks();
		$this->set_defaults();
	}

	/**
	 * Set default properties
	 *
	 * NOTE: $handle provided when enqueueing the asset will always be added as a class
	 *
	 * @return void
	 */
	public function set_defaults() {
		/**
		 * Filter function used to get the default classes to add to the resulting asset markup
		 *
		 * @since 0.0.1
		 *
		 * @param array $classes List of classes to apply to `class` attribute of resulting asset markup
		 */
		$this->default_classes = apply_filters( 'am_asset_classes', [ 'wp-asset-manager' ] );

		/**
		 * Filter function used to ignore errors when enqueueing assets
		 *
		 * @since 0.0.1
		 *
		 * @param bool $ignore_errors Whether or not to ignore errors
		 */
		$this->be_quiet = (bool) apply_filters( 'am_ignore_asset_errors', false );

		if ( ! empty( $this->asset_type ) ) {
			$this->wp_enqueue_function = 'wp_enqueue_' . $this->asset_type;
		}

		if ( ! empty( $this->core_ref_type ) ) {
			$this->core_assets_global = 'wp_' . $this->core_ref_type;
		}

		$this->set_asset_type_defaults();
	}

	/**
	 * Set or filter properties for a specific type of asset
	 */
	public function set_asset_type_defaults() {}

	/**
	 * Add hooks for outputting assets
	 *
	 * @return void
	 */
	public function add_hooks() {
		foreach ( $this->load_hooks as $hook => $functions ) {
			foreach ( $functions as $function => $priority ) {
				add_action( $hook, [ $this, $function ], $priority );
			}
		}
	}

	/**
	 * Set reference to core assets
	 *
	 * @param mixed $assets Assets object.
	 */
	public function set_core_assets_ref( $assets ) {
		$this->core_assets_ref = $assets->registered;
	}

	/**
	 * Add a asset to the manifest of assets to load
	 *
	 * @param array $args {
	 *  Arguments for loading asset. May differ based on asset type, but most contain the following.
	 *
	 *      @type string $handle      Handle for asset. Currently not used, but could be used to dequeue assets in the future.
	 *      @type string $src         URI for src attribute of printed asset handle
	 *      @type string $condition   Corresponds to a configured condition under which the asset should be loaded
	 *      @type string $load_hook   Hook on which to load the asset
	 *      @type string $load_method Style with which to load this asset. Defaults to 'sync'.
	 *                                Accepts 'sync', 'async', 'defer', with additional values for specific asset types.
	 * }
	 *
	 * @return void
	 */
	public function add_asset( $args ) {
		$wp_enqueue_function = $this->wp_enqueue_function;
		$args['type']        = $this->asset_type;

		if ( $this->asset_should_add( $args ) ) {
			// Validate load style.
			if ( empty( $args['load_method'] ) || ! in_array( $args['load_method'], $this->load_methods, true ) ) {
				$args['load_method'] = 'sync';
			}

			// Set in footer value based on asset_type.
			if ( ! in_array( $args['type'], [ 'style', 'preload' ], true ) ) {
				if ( empty( $args['in_footer'] ) ) {
					$args['in_footer'] = ! empty( $args['load_hook'] ) && 'wp_footer' === $args['load_hook'] ? true : false;
				}
			} elseif ( empty( $args['media'] ) ) {
				$args['media'] = 'all';
			}

			// Set load_hook value based on in_footer.
			if ( empty( $args['load_hook'] ) ) {
				$args['load_hook'] = ! empty( $args['in_footer'] ) ? 'wp_footer' : 'wp_head';
			}

			$args = $this->pre_add_asset( $args );

			// Enqueue asset if applicable.
			if ( in_array( $args['load_method'], $this->wp_enqueue_methods, true ) && empty( $args['loaded'] ) ) {
				if ( function_exists( $wp_enqueue_function ) ) {
					$wp_enqueue_function(
						$args['handle'],
						$args['src'],
						$args['deps'],
						$args['version'],
						'style' === $args['type'] ? $args['media'] : $args['in_footer']
					);
					$args['loaded'] = true;
				} else {
					echo wp_kses_post( $this->format_error( $this->generate_asset_error( 'invalid_enqueue_function', false, $wp_enqueue_function ) ) );
				}
			}

			// Add to asset arrays.
			// phpcs:disable Generic.Formatting.MultipleStatementAlignment
			$this->assets[] = $args;
			$this->assets_by_handle[ $args['handle'] ] = $args;
			$this->asset_handles[] = $args['handle'];
			//phpcs:enable
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
	 * @param array $asset - asset to sort in the dependency array.
	 *
	 * @return array
	 */
	public function find_dependents( $asset ) {
		$dependents = [];

		// Loop through each asset and check if this one is in its dependency array.
		foreach ( $this->assets as $current_asset ) {
			if ( ! empty( $current_asset['deps'] ) && in_array( $asset['handle'], $current_asset['deps'], true ) ) {
				$dependents[] = $current_asset['handle'];
			}
		}

		return $dependents;
	}

	/**
	 * Make sure the assets and their dependencies are valid
	 */
	public function validate_assets() {
		foreach ( $this->assets as $idx => $asset ) {
			// Collect dependents.
			$asset['dependents'] = $this->find_dependents( $asset );

			// Enqueue dependencies either part of core or enqueued directly through wp_enqueue_*.
			$this->add_core_dependencies( $asset );

			// Validate asset load_hook.
			$available_hooks     = array_keys( $this->load_hooks );
			$asset_load_hook_key = array_search( $asset['load_hook'], $available_hooks, true );

			if ( empty( $this->load_hooks[ $asset['load_hook'] ] ) ) {
				$this->generate_asset_error( 'invalid_load_hook', $asset );
				continue;
			}

			// Check for missing dependencies or mismatched load_hook.
			if ( ! empty( $asset['deps'] ) ) {
				foreach ( $asset['deps'] as $dependency ) {
					$this_dep = [];

					// Check if dependency exists.
					if ( empty( $this->assets_by_handle[ $dependency ] ) ) {
						$this->generate_asset_error( 'missing', $asset, $dependency );
						// Skip to the next dependency if this one is missing, as none of the other errors will be relevant.
						continue;
					} else {
						$this_dep = $this->assets_by_handle[ $dependency ];
					}

					$dep_load_hook_key = array_search( $this_dep['load_hook'], $available_hooks, true );

					// Ensure dependency is loading in an appropriate load_hook.
					if ( $dep_load_hook_key > $asset_load_hook_key ) {
						$this->generate_asset_error( 'unsafe_load_hook', $this_dep, $asset );
					}

					// Ensure dependencies don't require each other.
					if ( ! empty( $this_dep['deps'] )
						&& in_array( $asset['handle'], $this_dep['deps'], true )
						&& in_array( $this_dep['handle'], $asset['deps'], true )
					) {
						$this->generate_asset_error( 'circular_dependency', $asset, $this_dep['handle'] );
					}
				}
			}

			// Perform any type-specific validation checks or array mutation after validation.
			$asset = $this->post_validate_asset( $asset );

			// Reset asset in arrays.
			$this->assets[ $idx ]                       = $asset;
			$this->assets_by_handle[ $asset['handle'] ] = $asset;
			$this->asset_handles[ $idx ]                = $asset['handle'];
		} // End foreach.
	}

	/**
	 * Check if a asset has any dependencies that exist in WP Core and, if so, enqueue them
	 *
	 * @param array $asset Asset to check for core dependencies.
	 *
	 * @return void
	 */
	public function add_core_dependencies( $asset ) {
		$load_method = ! empty( $asset['load_method'] ) ? $asset['load_method'] : 'sync';

		if ( ! empty( $asset['deps'] ) ) {
			foreach ( $asset['deps'] as $dependency ) {
				$this->add_core_asset( $dependency, $load_method );
			}
		}
	}

	/**
	 * Add a core asset to the custom asset array, so we can track it as a dependency and make load method modifications
	 *
	 * @param string $handle      Handle of core asset to add.
	 * @param string $load_method Customize load method of core asset, otherwise leave it as 'sync'.
	 *
	 * @return void
	 */
	public function add_core_asset( $handle, $load_method = 'sync' ) {
		if ( ! is_string( $this->core_assets_global ) || empty( $this->core_assets_global ) ) {
			return;
		}

		$core_assets        = $GLOBALS[ $this->core_assets_global ] ?? [];
		$core_assets_ref    = $core_assets->registered ?? [];
		$in_footer          = $core_assets->in_footer ?? [];
		$core_asset_handles = array_keys( $core_assets_ref );

		/*
		 * Add assets that are wp_enqueued_* for custom enqueues,
		 * but only if they're not also custom enqueued
		 * Otherwise, we run the risk of enqueuing an asset twice
		 */
		if ( ! in_array( $handle, $this->asset_handles, true ) && in_array( $handle, $core_asset_handles, true ) ) {
			$core_asset   = $core_assets_ref[ $handle ];
			$is_in_footer = in_array( $core_asset->handle, $in_footer, true );
			$is_enqueued  = wp_style_is( $handle, 'enqueued' ) || wp_script_is( $handle, 'enqueued' );

			$this->add_asset(
				[
					'handle'      => $handle,
					'src'         => $core_asset->src,
					'condition'   => 'global',
					'deps'        => $core_asset->deps ? $core_asset->deps : [],
					'in_footer'   => $is_in_footer,
					'load_hook'   => $is_in_footer ? 'wp_footer' : 'wp_head',
					'loaded'      => ! $is_enqueued ? false : true,
					'load_method' => ! $is_enqueued ? $load_method : 'sync',
					'type'        => $this->asset_type,
					'version'     => $core_asset->ver,
				]
			);
		}
	}

	/**
	 * Verify an asset should load in the current load cycle
	 *
	 * This function primarily checks to see if the $load_hook matches the currently active WordPress hook.
	 * If the provided $load_hook has already happened (determined by the order in which $this->load_hooks are defined),
	 * the asset will be printed at the next available opportunity.
	 *
	 * @param array $asset Asset to check whether or not it should load.
	 *
	 * @return bool
	 */
	public function asset_should_load( $asset ) {
		$this_action           = current_filter();
		$available_hooks       = array_keys( $this->load_hooks );
		$target_hook_position  = array_search( $asset['load_hook'], $available_hooks, true );
		$current_hook_position = array_search( $this_action, $available_hooks, true );

		// Load assets that are configured to load on this hook or on a previous hook but were enqueued too late.
		$dom_position_matches = ! empty( $asset['load_hook'] )
			&& ( $target_hook_position <= $current_hook_position || false === $target_hook_position );
		$has_src              = ! empty( $asset['src'] );
		// Load assets that have not yet been loaded.
		$asset_loaded = ! empty( $asset['loaded'] ) ? $asset['loaded'] : false;

		return $dom_position_matches && $has_src && ! $asset_loaded;
	}
}
