<?php
/**
 * Trait file for singleton
 *
 * @package AssetManager
 */

namespace Alley\WP\Asset_Manager\Concerns;

/**
 * Make a class into a singleton.
 */
trait Singleton {
	/**
	 * Existing instances.
	 *
	 * @var array
	 */
	protected static $instances = [];

	/**
	 * Get class instance.
	 *
	 * @return static
	 */
	public static function instance() {
		$class = get_called_class();

		if ( ! isset( static::$instances[ $class ] ) ) {
			static::$instances[ $class ] = new static();
		}

		return self::$instances[ $class ];
	}
}
