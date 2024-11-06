<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://mlcomdev.wpenginepowered.com
 * @since      1.0.0
 *
 * @package    Mlcommons
 * @subpackage Mlcommons/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Mlcommons
 * @subpackage Mlcommons/includes
 * @author     Kiterocket <programmers@kiterocket.com>
 */
class Mlcommons {

  /**
   * The loader that's responsible for maintaining and registering all hooks that power
   * the plugin.
   *
   * @since    1.0.0
   * @access   protected
   * @var      Mlcommons_Loader    $loader    Maintains and registers all hooks for the plugin.
   */
  protected $loader;

  /**
   * The unique identifier of this plugin.
   *
   * @since    1.0.0
   * @access   protected
   * @var      string    $plugin_name    The string used to uniquely identify this plugin.
   */
  protected $plugin_name;

  /**
   * The current version of the plugin.
   *
   * @since    1.0.0
   * @access   protected
   * @var      string    $version    The current version of the plugin.
   */
  protected $version;

  /**
   * Define the core functionality of the plugin.
   *
   * Set the plugin name and the plugin version that can be used throughout the plugin.
   * Load the dependencies, define the locale, and set the hooks for the admin area and
   * the public-facing side of the site.
   *
   * @since    1.0.0
   */
  public function __construct() {
    if (defined('MLCOMMONS_VERSION')) {
      $this->version = MLCOMMONS_VERSION;
    } else {
      $this->version = '1.0.0';
    }
    $this->plugin_name = 'mlcommons';

    $this->load_dependencies();
    $this->set_locale();
    $this->define_admin_hooks();
    $this->define_public_hooks();
    $this->define_metabox_hooks();
  }

  /**
   * Load the required dependencies for this plugin.
   *
   * Include the following files that make up the plugin:
   *
   * - Mlcommons_Loader. Orchestrates the hooks of the plugin.
   * - Mlcommons_i18n. Defines internationalization functionality.
   * - Mlcommons_Admin. Defines all hooks for the admin area.
   * - Mlcommons_Public. Defines all hooks for the public side of the site.
   *
   * Create an instance of the loader which will be used to register the hooks
   * with WordPress.
   *
   * @since    1.0.0
   * @access   private
   */
  private function load_dependencies() {

    /**
     * Lib
     */
    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/lib.php';

    /**
     * The helper for get settings
     */
    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-mlcommons-settings.php';

    /**
     * Cron Jobs
     */
    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-mlcommons-cron.php';

    /**
     * The class responsible for defining all actions for GitHub connector
     */
    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-mlcommons-github.php';

    /**
     * Mailchimp Connector
     */
    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-mlcommons-mailchimp.php';

    /**
     * Google API Connector
     */
    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-mlcommons-googleapi.php';

    /**
     * Google Calendar Connector
     */
    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-mlcommons-gcal.php';

    /**
     * The class responsible for defining all custom fields for website
     */
    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-mlcommons-metabox-customfields.php';
    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-mlcommons-metabox.php';

    /**
     * The class responsible for orchestrating the actions and filters of the
     * core plugin.
     */
    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-mlcommons-loader.php';

    /**
     * The class responsible for defining internationalization functionality
     * of the plugin.
     */
    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-mlcommons-i18n.php';

    /**
     * The class responsible for defining all actions that occur in the admin area.
     */
    require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-mlcommons-admin.php';

    /**
     * The class responsible for defining all actions that occur in the public-facing
     * side of the site.
     */
    require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-mlcommons-public.php';

    $this->loader = new Mlcommons_Loader();
  }

  /**
   * Define the locale for this plugin for internationalization.
   *
   * Uses the Mlcommons_i18n class in order to set the domain and to register the hook
   * with WordPress.
   *
   * @since    1.0.0
   * @access   private
   */
  private function set_locale() {

    $plugin_i18n = new Mlcommons_i18n();

    $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
  }

  /**
   * Register all of the hooks related to the admin area functionality
   * of the plugin.
   *
   * @since    1.0.0
   * @access   private
   */
  private function define_admin_hooks() {

    $plugin_admin = new Mlcommons_Admin($this->get_plugin_name(), $this->get_version());

    $this->loader->add_action('admin_menu', $plugin_admin, 'add_pattern_menu');
    $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
    $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
    $this->loader->add_action('wp_ajax_mlp_apikey_hint', $plugin_admin, 'ajax_apikey_hint');
    $this->loader->add_action('wp_ajax_mlp_gcal_sync_event', $plugin_admin, 'ajax_gcal_sync_event');

    $this->loader->add_action('login_enqueue_scripts', $plugin_admin, 'action_wplogin_screen');
    $this->loader->add_action('login_headerurl', $plugin_admin, 'action_wplogin_headerurl');
    $this->loader->add_action('login_headertext', $plugin_admin, 'action_wplogin_headertext');

    $plugin_mb = new Mlcommons_Metabox();

    $this->loader->add_filter('mb_settings_pages', $plugin_mb, 'metaboxes_settings_pages');
    $this->loader->add_filter('rwmb_meta_boxes', $plugin_mb, 'metaboxes_settings_fields');
    $this->loader->add_filter('rwmb_normalize_type_field', $plugin_mb, 'block_field_options');
    $this->loader->add_filter('rwmb_meta_boxes', $plugin_mb, 'metaboxes_posttype');
    $this->loader->add_filter('rwmb_meta_boxes', $plugin_mb, 'metaboxes_block');

    if (filter_input(INPUT_GET, 'calendar_test')):
      $plugin_gcal = new Mlcommons_GCal();
      $this->loader->add_action('wp_loaded', $plugin_gcal, 'calendar_test');
    endif;

    if (filter_input(INPUT_GET, 'refresh_oauth_token')):
      $plugin_gapi = new Mlcommons_GoogleApi();
      $this->loader->add_action('wp_loaded', $plugin_gapi, 'test_refresh_token');
    endif;

    $plugin_mc = new Mlcommons_Mailchimp();
    if (filter_input(INPUT_GET, 'mlmcupdate')):
      $this->loader->add_action('wp_loaded', $plugin_mc, 'mailchimp_sync_mapping', 10);
    endif;
    if (filter_input(INPUT_GET, 'email_profile_test')):
      $this->loader->add_action('wp_loaded', $plugin_mc, 'send_test_email');
    endif;
    if (filter_input(INPUT_GET, 'mctest')):
      $this->loader->add_action('wp_loaded', $plugin_mc, 'test');
    endif;
  }

  /**
   * Register all of the hooks related to the public-facing functionality
   * of the plugin.
   *
   * @since    1.0.0
   * @access   private
   */
  private function define_public_hooks() {

    $plugin_public = new Mlcommons_Public($this->get_plugin_name(), $this->get_version());
    $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
    $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');

    //$plugin_gh = new Mlcommons_GitHub();
    //$this->loader->add_action('init', $plugin_gh, 'test');
    $plugin_gapi = new Mlcommons_GoogleApi();

    $this->loader->add_action('rest_api_init', $plugin_gapi, 'register_restapi_routes');

    $plugin_mc = new Mlcommons_Mailchimp();

    $this->loader->add_action('plugins_loaded', $plugin_mc, 'check_table_mc_hashes');
    $this->loader->add_filter('gform_pre_render', $plugin_mc, 'gform_pre_render', 10, 1);
    $this->loader->add_filter('gform_field_content', $plugin_mc, 'gform_field_content', 10, 2);
    $this->loader->add_filter('gform_confirmation', $plugin_mc, 'gform_confirmation', 10, 4);
    $this->loader->add_action('gform_after_submission', $plugin_mc, 'gform_after_submission', 30, 2);

    $plugin_cron = new Mlcommons_Cron();
    $this->loader->add_action('init', $plugin_cron, 'mlc_register_crons');
    $this->loader->add_action('mlcron_hourly', $plugin_cron, 'do_hourly_cron');
    $this->loader->add_action('mlcron_daily', $plugin_cron, 'do_daily_cron');

    $plugin_gcal = new Mlcommons_GCal();
    $this->loader->add_action('wp_ajax_nopriv_gcal_get_events', $plugin_gcal, 'ajax_get_events');
    $this->loader->add_action('wp_ajax_gcal_get_events', $plugin_gcal, 'ajax_get_events');

    $this->loader->add_action('wp_ajax_nopriv_gcal_get_event', $plugin_gcal, 'ajax_get_event');
    $this->loader->add_action('wp_ajax_gcal_get_event', $plugin_gcal, 'ajax_get_event');
  }

  /**
   * Register all metaboxes
   *
   * @since    1.0.0
   * @access   private
   */
  function define_metabox_hooks() {
    
  }

  /**
   * Run the loader to execute all of the hooks with WordPress.
   *
   * @since    1.0.0
   */
  public function run() {
    $this->loader->run();
  }

  /**
   * The name of the plugin used to uniquely identify it within the context of
   * WordPress and to define internationalization functionality.
   *
   * @since     1.0.0
   * @return    string    The name of the plugin.
   */
  public function get_plugin_name() {
    return $this->plugin_name;
  }

  /**
   * The reference to the class that orchestrates the hooks with the plugin.
   *
   * @since     1.0.0
   * @return    Mlcommons_Loader    Orchestrates the hooks of the plugin.
   */
  public function get_loader() {
    return $this->loader;
  }

  /**
   * Retrieve the version number of the plugin.
   *
   * @since     1.0.0
   * @return    string    The version number of the plugin.
   */
  public function get_version() {
    return $this->version;
  }

}
