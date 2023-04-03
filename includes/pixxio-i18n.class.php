<?php
namespace Pixxio;

class i18n extends Singleton {

	/**
	 * load plugin translations
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
	 */
	public function getLocale() {
		if( ! function_exists( 'wp_get_current_user' ) ) {
			return get_locale();
		}

		$user        = wp_get_current_user();
		$user_locale = get_user_meta( $user->ID, 'locale', true ) ?: get_locale();
		
		return $user_locale;
	}
}
