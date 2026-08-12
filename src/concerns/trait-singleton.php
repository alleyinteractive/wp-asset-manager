<?php
/**
 * Trait file for singleton
 *
 * @package Asset_Manager
 */

namespace Alley\WP\Asset_Manager\Concerns;

/**
 * Make a class into a singleton.
 *
 * Subclasses are instantiated through `new static()`, so every class using this trait has to
 * keep the same constructor signature.
 *
 * @phpstan-consistent-constructor
 */
trait Singleton {
	/**
	 * Existing instances.
	 *
	 * @var array<class-string, static>
	 */
	protected static $instances = [];

	/**
	 * Get class instance.
	 *
	 * @return static
	 */
	public static function instance() {
		$class = static::class;

		if ( ! isset( static::$instances[ $class ] ) ) {
			static::$instances[ $class ] = new static();
		}

		return static::$instances[ $class ];
	}
}
