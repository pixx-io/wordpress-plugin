<?php
/*
 * Plugin Name: pixx.io
 * Version: 2.0.1
 * Description: The official WordPress plugin for pixx.io. Bring Digital Asset Management to your WordPress sites by importing assets into your media library.
 * Author: pixx.io GmbH
 * Author URI: https://www.pixx.io/
 * Text Domain: pixxio
 * Requires PHP: 7.4
 * Requires at least: 6.0
 *
 */

namespace Pixxio;

use TypeError;

require_once 'includes/pixxio-singleton.class.php';

 /**
  * Pixxio Plugin Main Class
  *
  * @package Pixxio
  * @since 2.0.0
  * @method static i18n i18n()
  * @method static MediaHandler MediaHandler()
  * @method static Admin Admin()
  */
class Pixxio extends Singleton {
	private static $init = false;

	/**
	 * Pixxio plugin directory
	 */
	public const DIR = __DIR__;
	/**
	 * Pixxio plugin main file
	 */
	public const MAIN = __FILE__;

	/**
	 * Filled automatically from package.json in ::init()
	 *
	 * @var string
	 */
	public static $version = '0.0.0';

	/**
	 * Handles autoloading of the plugin classes
	 *
	 * @param string $className
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
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
			$instance      = $className::get_instance();
			$instanceClass = get_class( $instance );
			if ( get_class( $instance ) === $className ) {
				return $instance;
			} else {
				throw new \Exception( 'Class name case mismatch: Did you mean to use Pixxio::' . substr( $instanceClass, 7 ) . '?' );
			}
		}
	}

	/**
	 * Register autoload and initialize sub classes
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 * @throws TypeError
	 */
	public static function init() {
		if ( static::$init ) {
			return;
		}

		add_action(
			'plugins_loaded',
			function() {
				if ( ! function_exists( 'get_plugin_data' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				static::$version = \get_plugin_data( __FILE__, false, false )['Version'];
			}
		);

		spl_autoload_register( array( static::class, 'autoload' ) );

		i18n::init();
		Admin::init();
		MediaHandler::init();

		static::$init = true;
	}
}
Pixxio::init();
