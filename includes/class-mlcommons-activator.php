<?php

/**
 * Fired during plugin activation
 *
 * @link       https://mlcomdev.wpenginepowered.com
 * @since      1.0.0
 *
 * @package    Mlcommons
 * @subpackage Mlcommons/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Mlcommons
 * @subpackage Mlcommons/includes
 * @author     Kiterocket <programmers@kiterocket.com>
 */
class Mlcommons_Activator {

  /**
   * Short Description. (use period)
   *
   * Long Description.
   *
   * @since    1.0.0
   */
  public static function activate() {
    $plugin_gcal = new Mlcommons_GCal();
    $plugin_gcal->check_table_gcal_events();
  }

}
