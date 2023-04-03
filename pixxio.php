<?php
/*
 * Plugin Name: pixx.io
 * Version: 0.1.0
 * Description: The official WordPress plugin for pixx.io. Bring Digital Asset Management to your WordPress sites by importing assets into your media library.
 * Plugin URI: https://www.pixx.io/
 * Author: 48DESIGN GmbH
 * Author URI: https://48design.com
 * Text Domain: pixxio
 * Requires PHP: 7.4
 *
 */

namespace Pixxio;

require( 'includes/pixxio-singleton.class.php' );

 /**
  * Pixxio Plugin Main Class
  *
  * @package Pixxio
  * @since 1.0.0
  * @method static i18n i18n()
  */
class Pixxio extends Singleton {
	private static $init = false;

	public static $i18n;
	/**
	 * Pixxio plugin directory
	 */
	public const DIR = __DIR__;
	/**
	 * Pixxio plugin main file
	 */
	public const ENTRY = __FILE__;

	private static function autoload( $className ) {
		if ( substr( $className, 0, 7 ) !== 'Pixxio\\' ) {
			return;
		}

		$base_dir  = plugin_dir_path( __FILE__ ) . 'includes/';
		$file      = str_replace( '\\', '-', strtolower( $className ) ) . '.class.php';
		$file_path = $base_dir . $file;

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}

	public static function __callStatic( $name, $arguments ) {
		$className = 'Pixxio\\' . $name;
		if ( class_exists( $className ) ) {
			$instance = $className::get_instance();
			$instanceClass = get_class($instance);
			if ( get_class($instance) === $className ) {
				return $instance;
			} else {
				throw new \Exception( 'Class name case mismatch: Did you mean to use Pixxio::' . substr( $instanceClass, 7 ) . '?' );
			}
		}
	}

	public static function init() {
		if ( static::$init ) {
			return;
		}

		spl_autoload_register( array( static::class, 'autoload' ) );

		i18n::init();

		static::$init = true;
	}
}
Pixxio::init();
