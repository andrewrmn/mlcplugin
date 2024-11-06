<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://mlcomdev.wpenginepowered.com
 * @since             1.0.0
 * @package           Mlcommons
 *
 * @wordpress-plugin
 * Plugin Name:       MLCommons
 * Plugin URI:        https://mlcomdev.wpenginepowered.com
 * Description:       Provides additional functions to MLCommons website
 * Version:           1.0.0
 * Author:            Kiterocket
 * Author URI:        https://mlcomdev.wpenginepowered.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       mlcommons
 * Domain Path:       /languages
 */
// If this file is called directly, abort.
if (!defined('WPINC')) {
  die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('MLCOMMONS_VERSION', '1.1.0');
define('MLCOMMONS_PATH', plugin_dir_path(__FILE__));


define('MLCOMMONS_OAUTH_TOKEN', 'mloauth_access_token');
//define('MLCOMMONS_OAUTH_TOKEN_DATA', 'mloauth_access_token_data');
//define('MLCOMMONS_OAUTH_TOKEN_REFRESH', 'mloauth_token_refresh');

//define('MLCOMMONS_GCAL_CRON_PATH', 'mlcommons-gcal-cron');
//define('MLCOMMONS_GCAL_CRON_QUERY_VAR', 'mlcommons_gcal_cron');
define('MLCOMMONS_OPTION_GCAL_COLORS', 'mlcommons_gcal_colors');
define('MLCOMMONS_DB_GCAL_EVENTS', 'mlc_gcal_events');
define('MLCOMMONS_DB_GCAL_EVENTS_VERSION', '1.0');


/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-mlcommons-activator.php
 */
function activate_mlcommons() {
  require_once plugin_dir_path(__FILE__) . 'includes/class-mlcommons-activator.php';
  Mlcommons_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-mlcommons-deactivator.php
 */
function deactivate_mlcommons() {
  require_once plugin_dir_path(__FILE__) . 'includes/class-mlcommons-deactivator.php';
  Mlcommons_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_mlcommons');
register_deactivation_hook(__FILE__, 'deactivate_mlcommons');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-mlcommons.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_mlcommons() {

  $plugin = new Mlcommons();
  $plugin->run();
}

run_mlcommons();
