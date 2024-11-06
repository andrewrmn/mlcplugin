<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://mlcomdev.wpenginepowered.com
 * @since      1.0.0
 *
 * @package    Mlcommons
 * @subpackage Mlcommons/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Mlcommons
 * @subpackage Mlcommons/public
 * @author     Kiterocket <programmers@kiterocket.com>
 */
class Mlcommons_Public {

  /**
   * The ID of this plugin.
   *
   * @since    1.0.0
   * @access   private
   * @var      string    $plugin_name    The ID of this plugin.
   */
  private $plugin_name;

  /**
   * The version of this plugin.
   *
   * @since    1.0.0
   * @access   private
   * @var      string    $version    The current version of this plugin.
   */
  private $version;

  /**
   * Initialize the class and set its properties.
   *
   * @since    1.0.0
   * @param      string    $plugin_name       The name of the plugin.
   * @param      string    $version    The version of this plugin.
   */
  public function __construct($plugin_name, $version) {

    $this->plugin_name = $plugin_name;
    $this->version = $version;
  }

  /**
   * Register the stylesheets for the public-facing side of the site.
   *
   * @since    1.0.0
   */
  public function enqueue_styles() {

    /**
     * This function is provided for demonstration purposes only.
     *
     * An instance of this class should be passed to the run() function
     * defined in Mlcommons_Loader as all of the hooks are defined
     * in that particular class.
     *
     * The Mlcommons_Loader will then create the relationship
     * between the defined hooks and the functions defined in this
     * class.
     */
    
    $v = md5_file(MLCOMMONS_PATH . '/public/css/mlcommons-public.css');
    wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/mlcommons-public.css', array(), $v, 'all');
  }

  /**
   * Register the JavaScript for the public-facing side of the site.
   *
   * @since    1.0.0
   */
  public function enqueue_scripts() {

    /**
     * This function is provided for demonstration purposes only.
     *
     * An instance of this class should be passed to the run() function
     * defined in Mlcommons_Loader as all of the hooks are defined
     * in that particular class.
     *
     * The Mlcommons_Loader will then create the relationship
     * between the defined hooks and the functions defined in this
     * class.
     */
    $v = md5_file(MLCOMMONS_PATH . '/public/js/mlcommons-public.js');
    wp_register_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/mlcommons-public.js', array('jquery'), $v, ['in_footer' => true]);

    $options = [
      'siteurl' => get_home_url(),
      'ajaxurl' => admin_url('admin-ajax.php'),
    ];

    if (is_user_logged_in() && current_user_can('edit_pages')):
      $options['settings_link'] = get_admin_url();

      $edit_link = get_permalink();
      if (is_search()):
      elseif (is_category() || is_tag() || is_tax()):
        $edit_link = get_term_link(get_queried_object_id());
      elseif (is_single() || is_page() || is_singular()) :
        $edit_link = get_permalink();
      endif;

      $options ['edit-link'] = $edit_link;
    endif;

    wp_localize_script($this->plugin_name, 'mlcommons_settings', $options);
    wp_enqueue_script($this->plugin_name);
  }

}
