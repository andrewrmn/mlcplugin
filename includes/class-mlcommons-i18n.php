<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://mlcomdev.wpenginepowered.com
 * @since      1.0.0
 *
 * @package    Mlcommons
 * @subpackage Mlcommons/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Mlcommons
 * @subpackage Mlcommons/includes
 * @author     Kiterocket <programmers@kiterocket.com>
 */
class Mlcommons_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'mlcommons',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
