<?php
namespace Pixxio;

abstract class Singleton {
	private static $instances = array();

	private function __construct() { }

	/**
	 * returns the Singleton instance
	 *
	 * @return static::class
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
