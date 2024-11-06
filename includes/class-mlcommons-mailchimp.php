<?php

class Mlcommons_Mailchimp {

  var $client;

  function __construct() {
    require_once MLCOMMONS_PATH . '/3rdparty/mailchimp/vendor/autoload.php';
    $this->client = new MailchimpMarketing\ApiClient();
    $config = [
      'apiKey' => MLCommons_Settings::get_setting('mlp_mc_api_key', true),
      'server' => MLCommons_Settings::get_setting('mlp_mc_server_prefix', true),
    ];
    $this->client->setConfig($config);
  }

  function check_table_mc_hashes() {

    $stored_version = get_option(MLCOMMONS_DB_MAILCHIMP_HASHES);

    if ($stored_version !== MLCOMMONS_DB_MAILCHIMP_HASHES_VERSION) {

      $this->update_table_mc_hashes(true);
    }
  }

  function update_table_mc_hashes($update_ver) {
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $table_name = $wpdb->prefix . 'mc_hashes';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        mc_id bigint(20) NOT NULL AUTO_INCREMENT,
        mc_hash char(32) NOT NULL,
        mc_email char(200) NOT NULL,
        mc_date_created timestamp NULL,
        mc_date_sent timestamp NULL,
        mc_date_opened timestamp NULL,
        mc_sent_times int NOT NULL DEFAULT 0 ,
        mc_status int NOT NULL DEFAULT 0 ,
        mc_notes text NOT NULL,
        PRIMARY KEY (mc_id),
        UNIQUE KEY mc_hash (mc_hash),
        KEY hash_opened (mc_hash, mc_date_opened),
        KEY mc_email (mc_email)
    ) $charset_collate;";

    dbDelta($sql);

    if ($update_ver):
      update_option(MLCOMMONS_DB_MAILCHIMP_HASHES, MLCOMMONS_DB_MAILCHIMP_HASHES_VERSION);
    endif;
  }

  function test() {
    if (!is_admin() || !filter_input(INPUT_GET, 'mctest')):
      return;
    endif;
    $mc = new Mlcommons_Mailchimp();
//$result = $mc->email_exists('amoreno@kiterocket.com');
    //$result = $mc->test_email('amoreno@kiterocket.com');
//$result = $mc->get_profile('ale@impresslabs.com');
    $result = $mc->get_profile('amoreno@kiterocket.com');
    _dd($result, false, 'print', false);
    if (true === $result):
      echo 'exists';
    else:
      echo 'not exists';
    endif;
    die('test mc');

    require_once MLCOMMONS_PATH . '/3rdparty/mailchimp/vendor/autoload.php';
    try {
      $mailchimp = new MailchimpTransactional\ApiClient();
      $mailchimp->setApiKey(MLCommons_Settings::get_setting('mlp_mc_api_key_email', true));
      $response = $mailchimp->users->ping();
      print_r($response);
    } catch (Error $e) {
      echo 'Error: ', $e->getMessage(), "\n";
    }
  }

  function mailchimp_sync_mapping() {
    if (is_admin() && current_user_can('manage_options') && filter_input(INPUT_GET, 'mlmcupdate')):

      $mainlist = MLCommons_Settings::get_setting('mlp_mc_list_main', true);
      $this->get_interest_categories($mainlist, true);
      add_action('admin_notices', function () {
        echo '<div class="notice notice-success is-dismissible"><p>Mailchimp sync!</p></div>';
      }, 10);

//_dd($member);
//_dd($this->client->searchMembers->search($email));
//_dd($this->client->lists->getListMembersInfo($mainlist));
//print_r($this->client->lists->getAllLists());
//_dd($this->get_interest_categories($mainlist));
//_dd($this->client->lists->getListInterestCategories($mainlist));
//print_r($this->client->searchMembers->search($email));

    endif;
  }

  function get_interest_group($list_id, $category_id) {
    $response = $this->client->lists->listInterestCategoryInterests(
        $list_id,
        $category_id,
        null,
        null,
        100
    );
    $ret = [];
    foreach ($response->interests as $group):
      $ret[$group->id] = $group->name;
    endforeach;
    return $ret;
  }

  function get_interest_categories($list_id, $force_sync = false) {

    if (!$force_sync):
      $option = get_option(MLCOMMONS_OPTION_MC_SYNC);
      if ($option):
        return json_decode($option, true);
      endif;

    endif;

    $allowable_categories = [
      '091ecf5bfe' => 'MLCommons Newsletter',
      '7e73a9cdcb' => 'Public Working Groups',
      '9dee7df730' => 'Members-only Working Groups'
    ];

    try {
      $lists = $this->client->lists->getListInterestCategories($list_id);
    } catch (Exception $exc) {
      _d($exc->getTraceAsString());
      die();
    }
    $categories = [];
    if ($lists):
      $keys = array_keys($allowable_categories);
      foreach ($lists->categories as $category):
        if (in_array($category->id, $keys)):

          $category_info = [
            'title' => $category->title,
            'groups' => $this->get_interest_group($list_id, $category->id)
          ];
          $categories[$category->id] = $category_info;

        endif;
      endforeach;
    endif;

    update_option(MLCOMMONS_OPTION_MC_SYNC, json_encode($categories));
    return $categories;
  }

  function get_lists() {
    return $this->client->getAllLists();
  }

  function get_profile($email = false) {
    if (!$email || !is_email($email)):
      return false;
    endif;

    $member = $this->client->searchMembers->search($email);
    if (count($member->exact_matches->members) < 1):
      return false;
    endif;

    return json_decode(json_encode($member->exact_matches->members[0]), true);

//$mainlist = MLCommons_Settings::get_setting('mlp_mc_list_main'); // 'db1322ee57';
//_dd($member);
//_dd($this->client->searchMembers->search($email));
//_dd($this->client->lists->getListMembersInfo($mainlist));
//print_r($this->client->lists->getAllLists());
//_dd($this->get_interest_categories($mainlist));
//_dd($this->client->lists->getListInterestCategories($mainlist));
//print_r($this->client->searchMembers->search($email));
  }

  function email_exists($email = false) {

    if (!$email || !is_email($email)):
      return -1;
    endif;

    $return = false;

    try {
      $response = $this->client->searchMembers->search($email);
      if ($response->exact_matches->total_items) {
        $return = true;
      }
    } catch (Exception $exc) {
      $msg = '<h2>Mailchimp error on search</h2>';
      wp_die($msg);
    }
    return $return;
  }

  function has_active_hash($email) {
    if (!is_email($email)):
      return -1;
    endif;

    global $wpdb;
    $table_name = $wpdb->prefix . 'mc_hashes';
    $query = $wpdb->prepare("SELECT mc_id from `$table_name` where `mc_email` = %s and mc_date_opened is null order by mc_id desc limit 1", $email);
    return $wpdb->get_row($query);
  }

  function mark_hash_opened($hash) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mc_hashes';
    $query = $wpdb->prepare("SELECT *  from `$table_name` WHERE mc_hash = %s and mc_date_opened IS NULL", $hash);
    $row = $wpdb->get_row($query, ARRAY_A);
    if ($row):
      $query = $wpdb->prepare("UPDATE `$table_name` set `mc_date_opened` = CURRENT_TIMESTAMP() WHERE mc_hash = %s ", $hash);
      return $wpdb->query($query);
    endif;
  }

  function cleanup_hash_table() {
    global $wpdb;

    $creation_ttl = $join_form = MLCommons_Settings::get_setting('mlp_mc_hash_ttl', true);
    $table_name = $wpdb->prefix . 'mc_hashes';
    $where_time = '(mc_date_created  < CURRENT_TIMESTAMP() - INTERVAL ' . intval($creation_ttl) . ' MINUTE )';

    $query = $wpdb->prepare("DELETE from `$table_name` where " . $where_time);
    $wpdb->query($query);
  }

  function is_valid_hash($hash, $email = false) {

    global $wpdb;

    $creation_ttl = $join_form = MLCommons_Settings::get_setting('mlp_mc_hash_ttl', true);
    $opened_ttl = $join_form = MLCommons_Settings::get_setting('mlp_mc_hash_ttl_opened', true);
    $table_name = $wpdb->prefix . 'mc_hashes';
    $where_time = '( mc_date_created  > CURRENT_TIMESTAMP() - INTERVAL ' . intval($creation_ttl) . ' MINUTE ';
    $where_time .= ' AND ';
    $where_time .= ' (mc_date_opened is null or mc_date_opened > CURRENT_TIMESTAMP() - INTERVAL ' . intval($opened_ttl) . ' MINUTE )  )';

    if ($email && is_email($email)):
      $query = $wpdb->prepare("SELECT mc_id from `$table_name` where `mc_email` = %s and  `mc_hash` = %s and " . $where_time, $email, $hash);
      $row = $wpdb->get_row($query, ARRAY_A);
      if (isset($row['mc_id']) && $row['mc_id']):
        return true;
      else:
        return -1;
      endif;
    elseif ($hash):
      $query = $wpdb->prepare("SELECT * from `$table_name` where `mc_hash` = %s and " . $where_time, $hash);
      $row = $wpdb->get_row($query, ARRAY_A);
      return $row ?: -1;
    else:
      return false;
    endif;
  }

  function update_hash_status($hash) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mc_hashes';
    $query = $wpdb->prepare("UPDATE `$table_name` set `mc_date_sent` = CURRENT_TIMESTAMP(), mc_sent_times = mc_sent_times+1 WHERE mc_hash = %s ", $hash);
    return $wpdb->query($query);
  }

  function gen_temp_hash($email, $type = 'join') {
    if (!$email || !is_email($email)):
      return -1;
    endif;

    if ($this->has_active_hash($email)):
      return -2;
    endif;

    $hash = md5(uniqid($email, true));

    global $wpdb;
    $table_name = $wpdb->prefix . 'mc_hashes';
    $query = $wpdb->prepare("INSERT INTO `$table_name` set `mc_email` = %s,`mc_hash` = %s, 	mc_date_created = current_timestamp() ", $email, $hash);
    $wpdb->query($query);
    return $wpdb->insert_id ? $hash : -3;
  }

  function get_mailchimp_fields_mapping($mode) {
    $update_mapping = MLCommons_Settings::get_setting('mlp_mc_gf_form_update_mapping', true);
    $profile = [];
    foreach ($update_mapping as $i => $map):
      $profile[$map['mlp_mc_field']] = $map['mlp_gf_field'];
    endforeach;

    $mailchimp_gravity_mapping = [
      'profile' => $profile,
      'join' => []
    ];
    return $mailchimp_gravity_mapping[$mode];
  }

  /*
    Add a Read-only attribute to each fiel that is "email-readonly" class
   */

  function gform_field_content($field_content, $field) {
    if (defined('REST_REQUEST') && REST_REQUEST):
      return $field_content;
    endif;

    if (!str_contains($field['cssClass'], 'email-readonly')) {
      return $field_content;
    }
    $ret = str_replace('<input ', '<input readonly ', $field_content);
    return $ret;
  }

  /*
    This method performs the validation for rendering a form that have a mailchimp workflow
    only if the form_id match with join / update form id defined on the settings
   */

  function gform_pre_render($form) {

    if (defined('REST_REQUEST') && REST_REQUEST):
      return $form;
    endif;

    $join_form = MLCommons_Settings::get_setting('mlp_mc_gf_form_join', true);
    $edit_form = MLCommons_Settings::get_setting('mlp_mc_gf_form_update', true);

    if ($join_form <> $form['id'] && $edit_form <> $form['id']):
      return $form;
    endif;
    /*    _dd($form);
      $mapping_plugin = gf_mailchimp();
      _dd($mapping_plugin->get_form_settings($form));
      _dd($mapping_plugin->merge_vars_field_map($form)); */
    //check the validity of hash (for edit/join)
    if (!filter_input(INPUT_GET, MLCOMMONS_HASH_MC_UPDATE_QUERYVAR)):
      add_filter('gform_form_not_found_message', function ($msg, $id) {
        return '<div class="gform-mc-error" data-error="missing"><h2>Error</h2><p>You don\'t have permissions to access this form</p></div>';
      }, '', $form['id']);
      return false;
    endif;

    switch ($form['id']):
      case $join_form:
        //detect hash, fill email, make readonly field
        $hash = filter_input(INPUT_GET, MLCOMMONS_HASH_MC_UPDATE_QUERYVAR);
        $ret = $this->is_valid_hash($hash);
        if (!$ret || -1 == $ret):
          add_filter('gform_form_not_found_message', function ($msg, $id) {
            return '<div class="gform-mc-error" data-error="wrong" data-type="join"><h2>Error</h2><p>You don\'t have permissions to access this form</p></div>';
          }, '', $form['id']);
          return false;
        endif;
        foreach ($form['fields'] as &$field):

          if (str_contains($field->cssClass, 'email-readonly')):
            $field->defaultValue = $ret['mc_email'];
          endif;
        endforeach;
        $this->mark_hash_opened($hash);
        break;
      case $edit_form:



        $queried_hash = filter_input(INPUT_GET, MLCOMMONS_HASH_MC_UPDATE_QUERYVAR);
        $hash = $this->is_valid_hash($queried_hash);

        if (!$hash || -1 == $hash):

          add_filter('gform_form_not_found_message', function ($msg, $id) {
            return '<div class="gform-mc-error" data-error="wrong" data-type="edit"><h2>Error</h2><p>You don\'t have permissions to access this form</p></div>';
          }, '', $form['id']);

          return false;

        endif;
        foreach ($form['fields'] as &$field):
          if (str_contains($field->cssClass, 'email-readonly')):
            $field->defaultValue = $hash['mc_email'];
          endif;
        endforeach;

        $profile = $this->get_profile($hash['mc_email']);

        $mapping = $this->get_mailchimp_fields_mapping('profile');

        //_dd($mapping);

        $available_merge_fields = array_keys($profile['merge_fields']);

        foreach ($form['fields'] as &$field):
          if (str_contains($field->cssClass, 'log-profile')):
            $field->defaultValue = json_encode($profile);
          elseif (str_contains($field->cssClass, 'email-readonly')):
            $field->defaultValue = $profile['email_address'];
          elseif (is_array($field->inputs)):
            foreach ($field->inputs as $ixc => &$input):
              if (isset($field->choices[$ixc])):

                $input['label'] = $input['label'] . '-' . $input['id'];

                $field_mc = array_search($input['id'], $mapping);
                if ($field->id == 7):
// IS CONSENT FIELD, should be changed to normal field
//                  _d($field->choices[$ixc]['isSelected']);
//                  _d($profile);
//                  _dd($field);

                endif;
                if (false !== $field_mc):

                  $field->choices[$ixc]['isSelected'] = isset($profile['interests'][$field_mc]) && $profile['interests'][$field_mc] ? 1 : 0;
                endif;
              endif;
            endforeach;
          elseif (in_array($field->id, $mapping)):
            $pos = array_search($field->id, $mapping);
            $field->defaultValue = $profile['merge_fields'][$pos];
          endif;
        endforeach;
        // _dd($profile);

        $this->mark_hash_opened($hash['mc_hash']);
        break;
    endswitch;

    return $form;
  }

  /*
    Defines which automatic actions is triggered after the any gravityform confirmation.
    the main condition is a field "workflow" that defines each scenario
   */

  function gform_confirmation($confirmation, $form, $entry, $is_ajax) {

    $ids = wp_list_pluck($form['fields'], 'id');
    $labels = wp_list_pluck($form['fields'], 'label');
    $types = wp_list_pluck($form['fields'], 'type');

    //if is undefined a "workflow" field or form doesnt contain an email field type?
    if (!in_array('workflow', $labels) || !in_array('email', $types)):
      return $confirmation;
    endif;

    //find email key
    $email_ix_value = array_search('email', $types);
    if (!isset($entry[$email_ix_value]) || !is_email($entry[$email_ix_value])):
      return $confirmation;
    endif;
    $email = $entry[$email_ix_value];

    //find workflow key
    $workflow_ix_value = $ids[array_search('workflow', $labels)];
    if (!isset($entry[$workflow_ix_value]) || !$entry[$workflow_ix_value]):
      return $confirmation;
    endif;

    $workflow = $entry[$workflow_ix_value];

    switch ($workflow):

      //workflow for check email existence on mailchimp, 
      //if true: 
      // - create a hash
      // - update the hash on user profile on mc 
      // - send a transactional email that contains the hash link for EDIT PROFILE
      //if not: 
      // - create a hash
      // - redirect the user to the JOIN url 

      case 'mailchimp_email_check':

        $email_exists = $this->email_exists($email);

        if (true === $email_exists):

          $hash = $this->gen_temp_hash($email, 'profile');

          switch ($hash):
            case false:
              $error = 'Uncaught Error';
              $confimation_msg = 'ERROR: ' . $error;
              return false;
              break;
            case -1:
              $error = 'Invalid Email format';
              $confimation_msg = 'ERROR: ' . $error;

              break;
            case -2:
              $error = 'Active Hash';
              $confimation_msg = 'ERROR: ' . $error;
              break;
            case -3:
              $error = 'Fail on hash generation';
              $confimation_msg = 'ERROR: ' . $error;
              break;
            default:
              //double check :)
              if (true === $this->is_valid_hash($hash, $email)):
                $profile = $this->get_profile($email);
                //$this->update_mc_hash($profile['id'], $hash);
                $this->update_hash_status($hash);
                $confimation_msg = 'Please check your email, you should receive one email with a temporary link to edit your profile';
                $this->send_email_profile($profile, $hash);

              endif;
              break;
          endswitch;

        else:
          $hash = $this->gen_temp_hash($email, 'join');
          $hash_url = $this->get_hashed_url($hash, 'join');
          //$confimation_msg = '<a href="' . esc_url($hash_url) . '">click here</a> to register';

          $confimation_msg = '<h2>Thank you</h2><p>You will be redirected in 5 seconds to complete the sign-up process.</p>';
          $confirmation .= GFCommon::get_inline_script_tag("setTimeout(function(){window.open('$hash_url', '_top')}, 5000);");
        endif;
        $confirmation = str_replace('[msg]', $confimation_msg, $confirmation);
        break;

      case 'mailchimp_profile_update':
        //workflow for after updating the profile trigger a mailchimp webhook requested by nathan
        $args = ['email' => $email];
        $this->trigger_mc_webhook('profile-update', $args);
        break;
    endswitch;
    return $confirmation;
  }

  function gform_after_submission($entry, $form) {
    $gform_id = $entry['form_id'];
    $gform_id_join_form = MLCommons_Settings::get_setting('mlp_mc_gf_form_join', true);
    $gform_id_update_form = MLCommons_Settings::get_setting('mlp_mc_gf_form_update', true);
    if (!$gform_id_join_form || !$gform_id_update_form):
      return;
    endif;
    switch ($gform_id):
      case $gform_id_join_form:
        //nothing
        break;
      case $gform_id_update_form:
        //verify if there's a email change
        //mark the hash used (status 1)
        break;
    endswitch;
    return;
    _d($entry);
    _dd($form);
  }

  function get_hashed_url($hash, $mode = 'join') {
    if ('join' === $mode):
      $base_page = MLCommons_Settings::get_setting('mlp_mc_page_join', true);
    elseif ('profile' === $mode):
      $base_page = MLCommons_Settings::get_setting('mlp_mc_page_profile', true);
    else:
      return -1;
    endif;
    $hash_url = add_query_arg(MLCOMMONS_HASH_MC_UPDATE_QUERYVAR, $hash, get_permalink($base_page));
    return $hash_url;
  }

  function update_mc_hash($subscriber_hash, $hash) {

    $hash_url = $this->get_hashed_url($hash, 'profile');
    $mainlist = MLCommons_Settings::get_setting('mlp_mc_list_main', true); // 'db1322ee57';
    $response = $this->client->lists->setListMember($mainlist, $subscriber_hash, [
      "merge_fields" => [
        'MERGE10' => $hash_url
      ]
    ]);
    return $response;
  }

  function trigger_mc_webhook($type, $args) {
    switch ($type) {
      case 'profile-update':

        $json_data = json_encode(array(
          'email_address' => $args['email'],
        ));

        //$webhook_url = 'https://hook.us1.make.com/ehioeddnhf4ih4bwocpbl8ojoz2o7lkc';
        $webhook_url = MLCommons_Settings::get_setting('mlp_mc_webhook_user_updated', true);
        if (!$webhook_url):
          return false;
        endif;
        $headers = array(
          'Content-Type' => 'application/json',
        );

        $args = array(
          'body' => $json_data,
          'headers' => $headers,
          'timeout' => 30,
        );

        $response = wp_remote_post($webhook_url, $args);

        if (is_wp_error($response)) {
          //$error_message = $response->get_error_message();
          //echo "Something went wrong: $error_message";
          return false;
        } else {
          /* $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            echo "Response code: $response_code\n";
            echo "Response body: $response_body\n"; */
          return true;
        }
        break;
    }
    return false;
  }

  function send_email_profile($profile, $hash) {
    require_once MLCOMMONS_PATH . '/3rdparty/mailchimp/vendor/autoload.php';
    $merge_fields = isset($profile['merge_fields']) ? $profile['merge_fields'] : [];

    $email_to = $profile['email_address'];
    $block_id = MLCommons_Settings::get_setting('mlp_mc_email_template', true);

    $edit_profile_url = get_permalink(MLCommons_Settings::get_setting('mlp_mc_page_profile', true));
    $fields = [
      'FNAME' => $merge_fields['FNAME'],
      'LNAME' => $merge_fields['LNAME'],
      'MERGE10' => isset($merge_fields['MERGE10']) ? $merge_fields['MERGE10'] : '',
      'hash_profile' => add_query_arg(MLCOMMONS_HASH_MC_UPDATE_QUERYVAR, $hash, $edit_profile_url)
    ];
    $email_block = get_post($block_id);
    $email_body_html = do_blocks($email_block->post_content);
    $email_body_plain = $email_block->post_content;
    foreach ($fields as $key => $value) :
      if ($value):
        $placeholder = '**' . $key . '**';
        $email_body_html = str_replace($placeholder, $value, $email_body_html);
        $email_body_plain = str_replace($placeholder, $value, $email_body_plain);
      endif;
    endforeach;

    try {
      $mailchimp = new MailchimpTransactional\ApiClient();
      $mailchimp->setApiKey(MLCommons_Settings::get_setting('mlp_mc_api_key_email', true));
      $subaccount = MLCommons_Settings::get_setting('mlp_mc_api_----', true);
      $message = [
        'subject' => 'MLCommons Update Profile',
        'from_email' => 'wordpress@mlcommons.org',
        'from_name' => 'MLCommons',
        'subaccount' => 'wordpress-subscription-form', ///
        /* 'header'=>[
          'X-MC-Subaccount'=>'wordpress-subscription-form',
          ], */
        "to" => [
          [
            "email" => $email_to,
            "type" => "to"
          ]
        ],
        'html' => $email_body_html,
        'text' => strip_tags($email_body_plain),
      ];
      $response = $mailchimp->messages->send(["message" => $message]);
    } catch (Error $e) {
      echo 'Error: ', $e->getMessage(), "\n";
    }
  }

  function send_test_email() {
    if (!current_user_can('manage_options')):
      return;
    endif;
    $profile = $this->get_profile('amoreno@kiterocket.com');
    $this->send_email_profile($profile);

    require_once MLCOMMONS_PATH . '/3rdparty/mailchimp/vendor/autoload.php';

    $hash = isset($fields['hash']) ? $fields['hash'] : 'hash13213213212';
    $email_to = $email ?: 'amoreno@kiterocket.com';

    $block_id = MLCommons_Settings::get_setting('mlp_mc_email_template', true);

    $email_block = get_post($block_id);

    _dd($email_block, $block_id);

    try {
      $mailchimp = new MailchimpTransactional\ApiClient();
      $mailchimp->setApiKey(MLCommons_Settings::get_setting('mlp_mc_api_key_email', true));
      $message = [
        'subject' => 'Test Email',
        'from_email' => 'wordpress@mlcommons.org',
        'from_name' => 'MLCommons',
        "to" => [
          [
            "email" => $email_to,
            "type" => "to"
          ]
        ],
        'html' => '<p>Hash is ' . $hash . '</p>',
        'text' => 'Hash is ' . $hash,
      ];
      $response = $mailchimp->messages->send(["message" => $message]);
      _dd($response);
    } catch (Error $e) {
      echo 'Error: ', $e->getMessage(), "\n";
    }
  }

}
