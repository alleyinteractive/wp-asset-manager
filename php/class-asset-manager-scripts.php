<?php
/**
 * Class file for Asset_Manager_Scripts
 *
 * @package AssetManager
 */

class Asset_Manager_Scripts extends Asset_Manager {

	/**
	 * Holds references to the singleton instances.
	 *
	 * @var array
	 */
	private static $instance;

	/**
	 * Scripts loaded via async or defer
	 *
	 * @var array
	 */
	public $async_scripts = array();

	/**
	 * Global JS variable on which inline objects should be added as a property
	 *
	 * @var array
	 */
	public $inline_script_context = 'amScripts';

	/**
	 * Methods by which a script can be loaded into the DOM
	 *
	 * @var array
	 */
	public $load_methods = array( 'inline', 'sync', 'async', 'defer', 'async-defer' );

	/**
	 * Methods for which wp_enqueue_* should be used instead of internal printing function
	 *
	 * @var array
	 */
	public $wp_enqueue_methods = array( 'sync', 'async', 'defer', 'async-defer' );

	/**
	 * Asset type this class is responsible for loading and managing
	 *
	 * @var string
	 */
	public $asset_type = 'script';

	/**
	 * Core asset reference filter
	 *
	 * @var string
	 */
	public $core_ref_type = 'scripts';

	private function __construct() {
		// Don't do anything, needs to be initialized via instance() method.
	}

	/**
	 * Get an instance of the class.
	 *
	 * @return Alley_assets
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new static;
			self::$instance->add_hooks();
			self::$instance->manage_async();
			self::$instance->set_defaults();
		}
		return self::$instance;
	}

	/**
	 * Add filters for managing async or defer load methods
	 */
	public function manage_async() {
		add_filter( 'script_loader_tag', array( $this, 'add_attributes' ), 10, 2 );
		add_filter( 'wpcom_js_do_concat', array( $this, 'disable_concat' ), 10, 2 );
		add_filter( 'js_do_concat', array( $this, 'disable_concat' ), 10, 2 );
	}

	/**
	 * Add async and defer attributes to script tags where necessary
	 *
	 * @param string $tag    HTML <script> tag
	 * @param string $handle Handle of script
	 *
	 * @return string The updated script tag
	 */
	public function add_attributes( $tag, $handle ) {
		if ( isset( $this->assets_by_handle[ $handle ] ) ) {
			$this_script = $this->assets_by_handle[ $handle ];
			$attribute = 'async-defer' === $this_script['load_method'] ? 'async defer' : $this_script['load_method'];

			if ( in_array( $handle, $this->async_scripts, true ) && ! preg_match( "/[^-_]{$attribute}[^-_]/", $tag ) ) {
				// Insert load attribute in front of src attribute to ensure we don't add it to script tags containing inline code
				$tag = str_replace( "src=", $attribute . " src=", $tag );
			}
		}

		return $tag;
	}

	/**
	 * Disable JS concatenation for async and defer scripts
	 *
	 * @param bool $do_concat Whether or not to concatenate this script
	 * @param string $handle  Handle for enqueued script
	 *
	 * @return bool
	 */
	public function disable_concat( $do_concat, $handle ) {
		if ( in_array( $handle, $this->async_scripts, true ) ) {
			$do_concat = false;
		}

		return $do_concat;
	}

	/**
	 * Modify the load method of a script that's already been added.
	 *
	 * This is generally useful for async or defer loading core scripts. NOTE: If you call this function on the wp_enqueue_scripts action, set a low priority to ensure the script you want is available
	 *
	 * @param string $handle      Handle of script to modify
	 * @param string $load_method Target load method
	 *
	 * @return void
	 */
	public function modify_load_method( $handle, $load_method ) {
		// Add script if it's a core asset;
		$this->add_core_asset( $handle );

		// Get key of asset, now that it's added
		$key = array_search( $handle, $this->asset_handles );

		// Only modfiy scripts that have been added, and only use load methods that are valid
		if ( false !== $key && in_array( $load_method, $this->load_methods, true ) ) {
			$this->assets_by_handle[ $handle ]['load_method'] = $load_method;
			$this->assets[ $key ]['load_method'] = $load_method;
			$this->add_to_async( $this->assets_by_handle[ $handle ] );
		}
	}

	/**
	 * Print a single script
	 *
	 * @param array $script Script to insert into DOM
	 *
	 * @return void
	 */
	public function print_asset( $script ) {
		$classes = $this->default_classes;
		$classes[] = $script['handle'];

		if ( ! empty( $script['src'] ) && ! in_array( $script['load_method'], $this->wp_enqueue_methods, true ) ) {
			if ( 'inline' === $script['load_method'] ) {
				if ( is_array( $script['src'] ) ) {
					// If src is an array, add it as a property containing a JSON object on a global variable
					printf( '<script class="%1$s" type="text/javascript">window.%2$s = window.%2$s || {}; window.%2$s["%3$s"] = %4$s</script>',
						esc_attr( implode( ' ', $classes ) ),
						esc_js( $this->inline_script_context ),
						esc_js( $script['handle'] ),
						wp_json_encode( $script['src'] )
					);
				} elseif ( 0 === validate_file( $script['src'] ) && file_exists( $script['src'] ) ) {
					printf( '<script class="%1$s" type="text/javascript">%2$s</script>', esc_attr( implode( ' ', $classes ) ), file_get_contents( $script['src'] ) );
				} else {
					$this->generate_asset_error( 'unsafe_inline', $script );
				}
			}
		}
	}

	/**
	 * Perform final mutations before adding script to array
	 *
	 * @param array $script Script to mutate
	 * @return array
	 */
	public function pre_add_asset( $script ) {
		return $script;
	}

	/**
	 * Add script to async/defer script list
	 *
	 * @param array $script Script to add
	 * @return array
	 */
	public function post_validate_asset( $script ) {
		$unsafe_dependents = array();

		if ( ! empty( $script['dependents'] ) ) {
			if ( 'defer' === $script['load_method'] ) {
				// Dependent is unsafe if it's not also 'defer'
				foreach ( $script['dependents'] as $dependent ) {
					$dependent_info = $this->assets_by_handle[ $dependent ];
					if ( 'defer' !== $dependent_info['load_method'] ) {
						$unsafe_dependents[] = $dependent;
					}
				}
			} elseif ( 'async' === $script['load_method'] || 'async-defer' === $script['load_method'] ) {
				// All dependents are unsafe
				$unsafe_dependents = $script['dependents'];
			}

			if ( ! empty( $unsafe_dependents ) && is_array( $unsafe_dependents ) ) {
				$example_dependent = $this->assets_by_handle[ $unsafe_dependents[0] ];
				$this->generate_asset_error( 'unsafe_load_method', $script, $example_dependent );
			}
		}

		$this->add_to_async( $script );

		return $script;
	}

	/**
	 * Add a script handle to the list of async or defer scripts
	 *
	 * @param array $script Script to add
	 *
	 * @return void
	 */
	public function add_to_async( $script ) {
		if (
			( 'defer' === $script['load_method'] || 'async' === $script['load_method'] || 'async-defer' === $script['load_method'] ) &&
			! in_array( $script['handle'], $this->async_scripts, true )
		) {
			$this->async_scripts[] = $script['handle'];
		}
	}
}
