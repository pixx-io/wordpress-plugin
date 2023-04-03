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
}
