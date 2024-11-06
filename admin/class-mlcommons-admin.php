<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://mlcomdev.wpenginepowered.com
 * @since      1.0.0
 *
 * @package    Mlcommons
 * @subpackage Mlcommons/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Mlcommons
 * @subpackage Mlcommons/admin
 * @author     Kiterocket <programmers@kiterocket.com>
 */
class Mlcommons_Admin {

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
   * @param      string    $plugin_name       The name of this plugin.
   * @param      string    $version    The version of this plugin.
   */
  public function __construct($plugin_name, $version) {

    $this->plugin_name = $plugin_name;
    $this->version = $version;
  }

  /**
   * Register the stylesheets for the admin area.
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
    $v = md5_file(MLCOMMONS_PATH . '/admin/css/mlcommons-admin.css');
    wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/mlcommons-admin.css', array(), $v, 'all');
  }

  /**
   * Register the JavaScript for the admin area.
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
    $v = md5_file(MLCOMMONS_PATH . '/admin/js/mlcommons-admin.js');

    $options = [
      'siteurl' => get_home_url(),
      'ajaxurl' => admin_url('admin-ajax.php'),
    ];

    wp_register_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/mlcommons-admin.js', ['jquery'], $v, ['in_footer' => true]);

    wp_localize_script($this->plugin_name, 'mlcommons_admin_options', $options);

    wp_enqueue_script($this->plugin_name);
  }

  function ajax_gcal_sync_event() {
    if (!current_user_can('manage_options')):
      die();
    endif;
    $cal_id = sanitize_key(filter_input(INPUT_POST, 'cal_id'));
    if (!$cal_id):
      die();
    endif;
    $gcal = new Mlcommons_GCal();
    $params = [
      'cal_id' => $cal_id
    ];
    $gcal->ajax_sync_event($params);

    die();
  }

  function ajax_apikey_hint() {
    if (!current_user_can('manage_options')):
      die();
    endif;
    $key = sanitize_key(filter_input(INPUT_POST, 'field'));
    if (!$key):
      die();
    endif;
    $value = MLCommons_Settings::get_setting($key, true);
    $hint = $value ? obscure_text($value, 'middle', 10) : '[EMPTY]';

    echo json_encode(['hint' => $hint]);

    die();
  }

  function add_pattern_menu() {
    add_menu_page('Patterns', 'Patterns', 'edit_posts', 'edit.php?post_type=wp_block', '', 'dashicons-editor-table', 22);
  }

  function action_wplogin_headerurl() {
    return home_url();
  }

  function action_wplogin_screen() {
    $logo = MLCommons_Settings::get_setting('mlp_login_logo', true) ? wp_get_attachment_image_src(MLCommons_Settings::get_setting('mlp_login_logo', true), 'medium')[0] : '';
    ?>
    <style type="text/css">
      #login h1 a, .login h1 a {
        background-image: url(<?= $logo ?>);
        height:100px;
        width:300px;
        background-size: contain;
        background-repeat: no-repeat;
        padding-bottom: 10px;
      }
      body.login p.submit .button.button-primary{
        background-color:black;
        border:0px none;
      }
      body{
        background:none !important;
        background-color:#EEF2F9 !important;
        background-size:cover !important;
      }
    </style>
    <?php
  }

  function action_wplogin_headertext() {
    return 'MLCommons Website';
  }

}
