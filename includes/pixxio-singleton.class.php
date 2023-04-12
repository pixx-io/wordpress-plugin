<?php
/**
 * Singleton abstract class
 *
 * @package Pixxio
 * @since 2.0.0
 */

namespace Pixxio;

abstract class Singleton {
	private static $instances = array();

	private function __construct() { }

	/**
	 * returns the Singleton instance
	 *
	 * @since 2.0.0
	 *
	 * @return static
	 */
	final public static function get_instance() {
		$class = get_called_class();

		if ( ! isset( $instances[ $class ] ) ) {
			self::$instances[ $class ] = new $class();
		}

		return self::$instances[ $class ];
	}

	private function __clone() {  }

	public function __sleep() {  }

	public function __wakeup() {  }
}
