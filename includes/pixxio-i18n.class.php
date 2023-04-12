<?php
/**
 * Language and translation related functionality
 *
 * @package Pixxio
 * @since 2.0.0
 */

namespace Pixxio;

class i18n extends Singleton {

	/**
	 * load plugin translations
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function init() {
		add_action(
			'init',
			function() {
				load_plugin_textdomain(
					'pixxio',
					false,
					plugin_basename( Pixxio::DIR ) . '/languages/'
				);
			}
		);
	}

	/**
	 * Return the current user's locale, falling back to the site locale
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function getLocale() {
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			return get_locale();
		}

		$user        = wp_get_current_user();
		$user_locale = get_user_meta( $user->ID, 'locale', true ) ?: get_locale();

		return $user_locale;
	}

	/**
	 * Helper function to use translations from the default text domain
	 * without interfering with programs like PoEdit
	 *
	 * @return void
	 */
	public function __d( $string ) {
		return call_user_func( '__', $string );
	}
}
