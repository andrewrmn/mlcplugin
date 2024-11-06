<?php

use RRule\RRule;

class Mlcommons_Metabox {

  function block_field_options($field) {
    $field['options'] = [
      'default' => __('Default', 'mlcommons'),
      'calendar' => __('Calendar', 'mlcommons'),
    ];
    return $field;
  }

  function cb_block_mtblock($attributes, $is_preview = false, $post_id = null) {

// Unique HTML ID if available.
    $id = 'mt-' . ( $attributes['id'] ?? '' );
    if (!empty($attributes['anchor'])) :
      $id = $attributes['anchor'];
    endif;

// Custom CSS class name.
    $class = 'mt-block ' . ( $attributes['className'] ?? '' );
    if (!empty($attributes['align'])) :
      $class .= " align{$attributes['align']}";
    endif;
    $image = isset($attributes['data']) && isset($attributes['data']['image']) ? $attributes['data']['image'] : false;
    $alignment = isset($attributes['data']) && isset($attributes['data']['alignment']) ? $attributes['data']['alignment'] : 'left';
    $class .= " align-" . $alignment;
    ?>
    <div id="<?= $id ?>" class="<?= $class ?>">
      <div class="image">
        <?php
        if ($image):
          echo wp_get_attachment_image($image, 'full');
        endif;
        ?>
      </div>
      <div class="content">
        <InnerBlocks />
      </div>
      <hr class="clear"/>
    </div>
    <?php
  }

  function cb_block_ghblock($attributes, $is_preview = false, $post_id = null) {

// Unique HTML ID if available.
    $id = 'gh-' . ( $attributes['id'] ?? '' );
    if (!empty($attributes['anchor'])) :
      $id = $attributes['anchor'];
    endif;

// Custom CSS class name.
    $class = 'gh-block ' . ( $attributes['className'] ?? '' );
    if (!empty($attributes['align'])) :
      $class .= " align{$attributes['align']}";
    endif;
    if (isset($attributes['data']) && isset($attributes['data']['type'])):
      $class .= " type-{$attributes['data']['type']}";
    endif;

    $markdown = get_post_meta($post_id, 'mlp_github_content_markdown', true);
    $path = get_post_meta($post_id, 'mlp_github_path', true);
    $parsed = Mlcommons_GitHub::parse($markdown);
    $section = isset($attributes['data']) && isset($attributes['data']['section']) && $attributes['data']['section'] ? $attributes['data']['section'] : false;
    $title = $content = false;
    $enable_block = true;
    if ($section):
      $title = isset($parsed ['sections'][$section]) && isset($parsed ['sections'][$section]['title']) ? $parsed ['sections'][$section]['title'] : false;
      $content = isset($parsed ['sections'][$section]) && isset($parsed ['sections'][$section]['content']) ? $parsed ['sections'][$section]['content'] : false;
      $enable_block = false;
    endif;
    ?>
    <div id="<?= $id ?>" class="<?= $class ?>">
      <?php
      if ($enable_block):
        echo '<!-- no github content set -->';
        echo '<InnerBlocks />';
      else:
        if ($is_preview):
          echo '<strong>GitHub Section</strong> ' . $section;
        else:
          if ($section):
            if ($title && $content):
              echo '<h2 id="' . $section . '">' . $title . '</h2>';
              echo $content;
            endif;
          endif;
        endif;
      endif;
      ?>
    </div>
    <?php
  }

  function cb_block_wgdetails($attributes, $is_preview = false, $post_id = null) {

    // Unique HTML ID if available.
    $id = 'wd-' . ($attributes['id'] ?? '');
    if (!empty($attributes['anchor'])) {
      $id = $attributes['anchor'];
    }

    // Custom CSS class name.
    $class = 'mt-wgdetails ' . ($attributes['className'] ?? '');
    if (!empty($attributes['align'])) {
      $class .= " align{$attributes['align']}";
    }

    // Get alignment data attribute if available.
    $alignment = $attributes['data']['alignment'] ?? 'left';
    $class .= " align-" . $alignment;

    // Initialize Google Calendar class.
    $gcal = new Mlcommons_GCal();

    // Retrieve meta data.
    $deliverables = get_post_meta($post_id, 'wg_deliverables', true);
    $schedule = get_post_meta($post_id, 'wg_schedule', true);
    $submission_deadline_event = get_post_meta($post_id, 'wg_submission_deadline_event', true);
    $results_publication_event = get_post_meta($post_id, 'wg_results_publication_event', true);
    $link_discord = get_post_meta($post_id, 'wg_link_discord', true);
    $link_join = get_post_meta($post_id, 'wg_link_join', true);

    // Retrieve submission deadline event details.
    if ($submission_deadline_event) {
      $event_submission = $gcal->get_event($submission_deadline_event);
      if ($event_submission) {
        $submission_date_timestamp = strtotime($event_submission['cal_date_start']);
        $submission_formatted_date = date("F j, Y", $submission_date_timestamp);
        $submission_full_day_name = date("l", $submission_date_timestamp);
      }
    }

    // Retrieve results publication event details.
    if ($results_publication_event) {
      $event_results = $gcal->get_event($results_publication_event);
      if ($event_results) {
        $results_date_timestamp = strtotime($event_results['cal_date_start']);
        $results_formatted_date = date("F j, Y", $results_date_timestamp);
        $results_full_day_name = date("l", $results_date_timestamp);
      }
    }

    // Retrieve schedule event details.
    if ($schedule) {
      $schedule_event = $gcal->get_next_schedule($schedule);
      $frequency = '';
      if (!empty($schedule_event['cal_recurring_info'])) {
        $recurring_info = json_decode($schedule_event['cal_recurring_info'], true);
        if (!empty($recurring_info[0])) {
          $parsed_recurrence = $gcal->parseRecurrenceRule($recurring_info[0]);
          $frequency = ucfirst(strtolower($parsed_recurrence['FREQ']));
        }
      }
      if ($schedule_event) {
        $schedule_start_date_timestamp = $gcal->date_conv($schedule_event['cal_date_start'], 'time')->getTimestamp();
        $schedule_start_formatted_date = date("F j, Y", $schedule_start_date_timestamp);
        $schedule_start_full_day_name = date("l", $schedule_start_date_timestamp);
        $schedule_start_formatted_time = date("H:i", strtotime($gcal->date_conv($schedule_event['cal_date_start'])));
        $schedule_end_formatted_time = date("H:i", strtotime($gcal->date_conv($schedule_event['cal_date_end'])));
      }
    }

    // Determine number of rows based on available data.
    $rows = 1;
    if ($schedule)
      $rows++;
    if ($submission_deadline_event || $results_publication_event)
      $rows++;
    if ($link_join || $link_discord)
      $rows++;

    // Generate HTML output.
    ?>
    <div id="<?= $id ?>" class="<?= $class ?> working-groups__single__deliverables-section_flexible col-qty-<?= $rows; ?>">

      <div class="deliverables">
        <h2 class="wp-block-heading">Deliverables</h2>
        <?= $deliverables ?>
      </div>
      <!-- /deliverables -->

      <div class="links-dates">
        <div class="row">
          <section>
            <h2 class="wp-block-heading">Join</h2>
            <div class="wp-block-buttons">
              <?php if ($link_join): ?>
                <div class="wp-block-button is-style-quaternary">
                  <a class="wp-block-button__link wp-element-button" href="<?= esc_url($link_join) ?>" rel="noreferrer noopener">Join the Working Group</a>
                </div>
              <?php endif; ?>
              <?php if ($link_discord): ?>
                <div class="wp-block-button is-style-tertiary">
                  <a class="wp-block-button__link wp-element-button" href="<?= esc_url($link_discord) ?>" rel="noreferrer noopener">Join Discord</a>
                </div>
              <?php endif; ?>
            </div>
          </section>
        </div>
        <div class="row">
          <?php if ($schedule): ?>
            <section>
              <h2 class="wp-block-heading">Meeting Schedule</h2>
              <div class="box">
                <h3 class="title"><?= $schedule_start_full_day_name ?> <?= $schedule_start_formatted_date ?></h3>
                <p><?= $frequency . ' - ' . $schedule_start_formatted_time . ' - ' . $schedule_end_formatted_time ?> Pacific Time</p>
              </div>
            </section>
          <?php endif; ?>
          <?php
          $nearest_event = $gcal->get_nearest_events([$submission_deadline_event, $results_publication_event], true);
          if ($nearest_event):
            if ($nearest_event === $results_publication_event):
              ?>
              <section>
                <h2 class="wp-block-heading">Results Publication</h2>
                <div class="box orange">
                  <h3 class="title"><?= $results_formatted_date ?></h3>
                  <p><?= $results_full_day_name ?></p>
                </div>
              </section>
            <?php elseif ($nearest_event === $submission_deadline_event): ?>
              <section>
                <h2 class="wp-block-heading">Submission Date</h2>
                <div class="box orange">
                  <h3 class="title"><?= $submission_formatted_date ?></h3>
                  <p><?= $submission_full_day_name ?></p>
                </div>
              </section>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        <!-- /row -->
      </div>
      <!-- /links dates -->
    </div>
    <!-- /wg details -->
    <?php
  }

  function cb_block_submissiondate($attributes, $is_preview = false, $post_id = null) {

// Unique HTML ID if available.
    $id = 'wgsd-' . ( $attributes['id'] ?? '' );
    if (!empty($attributes['anchor'])) :
      $id = $attributes['anchor'];
    endif;

// Custom CSS class name.
    $class = 'mt-submissiondate ' . ( $attributes['className'] ?? '' );
    if (!empty($attributes['align'])) :
      $class .= " align{$attributes['align']}";
    endif;
    $alignment = isset($attributes['data']) && isset($attributes['data']['alignment']) ? $attributes['data']['alignment'] : 'left';
    $class .= " align-" . $alignment;

    global $wpdb;
    $table_name = $wpdb->prefix . 'gcal_events';
    $q = "SELECT DISTINCT * FROM  " . $table_name . " where cal_id='{$attributes['event_id']}' limit 1";
    $event = $wpdb->get_row($q, ARRAY_A);
    if ($event):
      $date_timestamp = strtotime($event['cal_date_end']);

      // Format the date as "Month Day, Year"
      $formatted_date = date("F j, Y", $date_timestamp);
      $full_day_name = date("l", $date_timestamp);
    endif;
    ?>
    <div id="<?= $id ?>" class="<?= $class ?>">
      <p>
        <?php
        echo $formatted_date;
        ?><br/>
        <?= $full_day_name ?>
      </p>
    </div>
    <?php
  }

  function metaboxes_block($meta_boxes) {

    $meta_boxes[] = [
      'title' => __('MLCommons GitHub Block', 'mlcommons'),
      'id' => 'mb-ghblock',
      'type' => 'block',
      'supports' => [
      ],
      'render_callback' => [$this, 'cb_block_ghblock'],
      'fields' => [
        [
          'type' => 'text',
          'id' => 'section',
          'name' => __('Section', 'mlcommons'),
        ],
        [
          'type' => 'radio',
          'id' => 'type',
          'name' => __('Block Type', 'mlcommons'),
          'std' => 'default',
        ],
      ],
    ];

    $gcal = new Mlcommons_GCal();
    $wg_events = $gcal->get_workinggroup_events();
    $sub_events = $gcal->get_submission_events();

    $submissions = [];
    $wgevents = [];

    $submissions[''] = 'Select one event';
    foreach ($sub_events as $e):
      $submissions [$e['cal_id']] = $e['cal_summary'];
    endforeach;

    $wgevents[''] = 'Select one event';
    foreach ($wg_events as $e):
      $wgevents [$e['cal_parent_event']] = $e['cal_summary'];
    endforeach;

    $meta_boxes[] = [
      'title' => __('MLCommons Submission Dates', 'mlcommons'),
      'id' => 'mb-submissiondate',
      'type' => 'block',
      'context' => 'side',
      'supports' => [
      ],
      'render_callback' => [$this, 'cb_block_submissiondate'],
      'fields' => [
        [
          'type' => 'select',
          'id' => 'event_id',
          'name' => __('Event', 'mlcommons'),
          'options' => $submissions
        ],
      ],
    ];

    $meta_boxes[] = [
      'title' => __('MLCommons Working Group Details', 'mlcommons'),
      'id' => 'mb-wgdetails',
      'type' => 'block',
      'storage_type' => 'post_meta',
      'supports' => [
        'multiple' => false,
      ],
      'render_callback' => [$this, 'cb_block_wgdetails'],
      'fields' => [
        [
          'type' => 'wysiwyg',
          'id' => 'wg_deliverables',
          'name' => __('Deliverables', 'mlcommons'),
        ],
        [
          'type' => 'select',
          'id' => 'wg_schedule',
          'name' => __('Schedule', 'mlcommons'),
          'options' => $wgevents
        ],
        [
          'type' => 'select',
          'id' => 'wg_submission_deadline_event',
          'name' => __('Submission Deadline', 'mlcommons'),
          'options' => $submissions
        ],
        [
          'type' => 'select',
          'id' => 'wg_results_publication_event',
          'name' => __('Results Publications', 'mlcommons'),
          'options' => $submissions
        ],
        [
          'type' => 'url',
          'id' => 'wg_link_join',
          'name' => __('Join Link', 'mlcommons'),
        ],
        [
          'type' => 'url',
          'id' => 'wg_link_discord',
          'name' => __('Discord Link', 'mlcommons'),
        ],
      ],
    ];

    $meta_boxes[] = [
      'title' => __('MLCommons Media + Text', 'mlcommons'),
      'id' => 'mb-mtblock',
      'type' => 'block',
      'context' => 'side',
      'supports' => [
      ],
      'render_callback' => [$this, 'cb_block_mtblock'],
      'fields' => [
        [
          'type' => 'single_image',
          'id' => 'image',
          'name' => __('Image', 'mlcommons'),
        ],
        [
          'type' => 'radio',
          'id' => 'alignment',
          'name' => __('Alignment', 'mlcommons'),
          'std' => 'left',
          'options' => [
            'left' => 'Left',
            'right' => 'Right',
          ]
        ],
      ],
    ];

    return $meta_boxes;
  }

  function metaboxes_posttype($meta_boxes) {

    $prefix = 'mlp_';

    $meta_boxes[] = [
      'id' => 'mb-github',
      'post_types' => ['page'],
      'title' => __('GitHub Connection', 'mlcommons'),
      'fields' => [
        [
          'name' => __('Repository Path', 'mlcommons'),
          'id' => $prefix . 'github_path',
          'type' => 'text',
        ],
        [
          'name' => __('Available Sections', 'mlcommons'),
          'type' => 'custom_html',
          'callback' => function () {
            $ret = '<p><em>No sections found yet.</em><br/>Please provide a valid repository path</p>';
            global $post;
            $sections = get_post_meta(get_the_ID(), 'mlp_github_content_sections', true);
            if (is_array($sections) && count($sections)):
              $ret = '[<a href="#" class="copy-ml-section">' . implode('</a>], [<a href="#" class="copy-ml-section">', $sections) . '</a>]';
            endif;
            return $ret;
          }
        ],
        [
          'name' => __('Last Update', 'mlcommons'),
          'id' => $prefix . 'github_last_fetch',
          'type' => 'datetime',
        ],
      ],
    ];
    return $meta_boxes;
  }

  function metaboxes_settings_pages($settings_pages) {
    $settings_pages[] = [
      'menu_title' => __('MLCommons', 'mlcommons'),
      'id' => 'mlcommons_settings',
      'option_name' => 'mlcommons-settings',
      'position' => 25,
      'parent' => 'options-general.php',
      'style' => 'no-boxes',
      'columns' => 1,
      'tabs' => [
        'general' => [
          'label' => 'General',
          'icon' => 'dashicons-admin-generic'
        ],
        'github' => [
          'label' => 'GitHub',
          'icon' => 'dashicons-admin-plugins'
        ],
        'google' => [
          'label' => 'Google',
          'icon' => 'dashicons-admin-plugins'
        ],
        'mailchimp' => [
          'label' => 'Mailchimp',
          'icon' => 'dashicons-admin-plugins'
        ],
        'tools' => [
          'label' => 'Tools',
          'icon' => 'dashicons-admin-tools'
        ],
      ],
      'tab_style' => 'left',
      'icon_url' => 'dashicons-admin-generic',
    ];

    return $settings_pages;
  }

  function get_mc_info() {
    $option = get_option(MLCOMMONS_OPTION_MC_SYNC);

    $ret = '';

    _bfOn();
    if ($option):
      $ret = json_decode($option, true);
      foreach ($ret as $category):
        echo '<p>';
        echo '<strong>';
        echo $category['title'];
        echo '</strong>';

        echo '<ul>';
        foreach ($category['groups'] as $ig => $group):
          echo '<li>[' . $ig . '] ' . $group . '</li>';
        endforeach;
        echo '</ul>';

        echo '</p>';
      endforeach;
    else:
      echo '<p>Sync Outdated</p>';
    endif;
    _d($ret, '', 'textarea');

    $url_update = add_query_arg('mlmcupdate', 1, get_admin_url(null, '/options-general.php?page=mlcommons_settings#tab-mailchimp'));
    //$mainlist = MLCommons_Settings::get_setting('mlp_mc_list_main'); // 'db1322ee57';
    $ret = '<p><a href="' . esc_url($url_update) . '">Sync Mailchimp</a></p>' . _bfGet(false);

    return $ret;
  }

  function get_mc_fields($plain = false) {

    $option = get_option(MLCOMMONS_OPTION_MC_SYNC);
    if ($option):
      $tree = json_decode($option, true);
      if ($plain):
        $ret = [];
        foreach ($tree as $cat => $items):
          foreach ($items['groups'] as $gid => $gn):
            $ret[$gid] = $gn . ' - ' . $gid;
          endforeach;
        endforeach;
      else:
        $ret = $tree;
      endif;
      $ret['GITHUB'] = 'GitHub Profile';
      $ret['DISCORD'] = 'Discord Profile';
      $ret['MMERGE7'] = 'Company';

    else:
      $ret = ['No information'];
    endif;
    return $ret;
  }

  function get_gf_mc_profile_fields() {
    $form = MLCommons_Settings::get_setting('mlp_mc_gf_form_join', true);
    $result = GFAPI::get_form($form);
    $fields = [];
    //$exclude_types = ['html', 'section', 'consent', 'hidden'];
    $exclude_types = ['html', 'section', 'hidden'];

    foreach ($result['fields'] as $f):
      if (!in_array($f->inputType, $exclude_types)):

        if ($f->id == 7):
        //_dd($f);
        endif;

        if (is_array($f->inputs)):

          foreach ($f->inputs as $in):
            $fields[$in['id']] = $in['label'] . ' - ' . $in['id'];
          endforeach;
        else:
          $fields[$f->id] = $f->label . ' - ' . $f->id;
        endif;
      endif;
    endforeach;
    return $fields;
  }

  function metaboxes_settings_fields($meta_boxes) {

    $gravity_forms = [
      '' => 'Select one Form'
    ];
    $events = [
      '' => 'Select one Recurrent Event'
    ];

    $gcal = new Mlcommons_GCal();
    global $wpdb;
    $table_name = $wpdb->prefix . 'gcal_events';
    $query = "SELECT DISTINCT cal_recurring_event_id, cal_summary from `$table_name` order by cal_summary ASC";
    $recurrent_events = $wpdb->get_results($query);
    foreach ($recurrent_events as $e):
      if ($e->cal_recurring_event_id):
        $events[$e->cal_recurring_event_id] = $e->cal_summary . ' - ' . $e->cal_recurring_event_id;
      endif;
    endforeach;

    $table_name = $wpdb->prefix . 'gf_form';
    $query = "SELECT id,title from `$table_name` where is_trash <> 1 order by is_active desc, title asc";
    $forms = $wpdb->get_results($query);
    foreach ($forms as $f):
      $gravity_forms[$f->id] = $f->title . ' - #' . $f->id;
    endforeach;
    $gf_edit_fields = $this->get_gf_mc_profile_fields();
    $mc_edit_fields = $this->get_mc_fields(true);

    $prefix = 'mlp_';
    $meta_boxes[] = [
      'id' => 'ml-general',
      'settings_pages' => ['mlcommons_settings'],
      'tab' => 'general',
      'fields' => [
        [
          'name' => __('Login Logo', 'mlcommons'),
          'id' => $prefix . 'login_logo',
          'type' => 'single_image',
        ],
        [
          'name' => __('Featured Category', 'mlcommons'),
          'id' => $prefix . 'featured_category',
          'type' => 'taxonomy_advanced',
          'taxonomy' => 'category'
        ],
      ],
    ];

    $meta_boxes[] = [
      'id' => 'ml-google',
      'settings_pages' => ['mlcommons_settings'],
      'tab' => 'google',
      'tabs' => [
        'credentials' => 'Credentials',
        'configuration' => 'Local Configuration',
      ],
      'fields' => [
        [
          'name' => __('REST API Client Token', 'mlcommons'),
          'id' => $prefix . 'gcal_restapi_client_token',
          'type' => 'apikey',
          'tab' => 'credentials',
        ],
        [
          'name' => __('Calendar ID', 'mlcommons'),
          'id' => $prefix . 'gcal_calendar_id',
          'type' => 'text',
          'tab' => 'credentials',
        ],
        [
          'name' => __('Service Account JSON', 'mlcommons'),
          'id' => $prefix . 'gcal_service_account_json',
          'type' => 'apikey',
          'tab' => 'credentials',
        ],
        [
          'name' => __('Service Account Email', 'mlcommons'),
          'id' => $prefix . 'gcal_service_account_email',
          'type' => 'text',
          'tab' => 'credentials',
        ],
        [
          'name' => __('Event Color Mapping', 'mlcommons'),
          'id' => $prefix . 'gcal_event_colors',
          'tab' => 'configuration',
          'type' => 'group',
          'clone' => true,
          'sort_clone' => true,
          'fields' => [
            [
              'name' => __('Title', 'mlcommons'),
              'id' => $prefix . 'gcal_gcolor_title',
              'type' => 'text',
            ],
            [
              'name' => __('Color', 'mlcommons'),
              'id' => $prefix . 'gcal_gcolor',
              'type' => 'select',
              'options' => $gcal->get_gcolors('event')
            ],
            [
              'name' => __('Color Local', 'mlcommons'),
              'id' => $prefix . 'gcal_gcolor_local',
              'type' => 'color',
            ],
          ]
        ],
        [
          'name' => __('Calendar Grouping', 'mlcommons'),
          'id' => $prefix . 'gcal_groups',
          'tab' => 'configuration',
          'collapsible' => true,
          'type' => 'group',
          'clone' => true,
          'group_title' => '{' . $prefix . 'gcal_slug}',
          'sort_clone' => true,
          'fields' => [
            [
              'name' => __('Group Slug', 'mlcommons'),
              'id' => $prefix . 'gcal_slug',
              'type' => 'text',
            ],
            [
              'name' => __('Color', 'mlcommons'),
              'id' => $prefix . 'gcal_color',
              'type' => 'color',
            ],
            [
              'name' => __('Event', 'mlcommons'),
              'id' => $prefix . 'gcal_event',
              'type' => 'select',
              'options' => $events,
              'clone' => true,
              'sort_clone' => true,
            ]
          ]
        ],
      ],
    ];

    $meta_boxes[] = [
      'id' => 'ml-github',
      'settings_pages' => ['mlcommons_settings'],
      'tab' => 'github',
      'fields' => [
        [
          'name' => __('GitHub Repository Owner', 'mlcommons'),
          'id' => $prefix . 'github_repository_owner',
          'type' => 'text',
//          'required' => true,
        ],
        [
          'name' => __('GitHub Repository', 'mlcommons'),
          'id' => $prefix . 'github_repository',
          'type' => 'text',
//          'required' => true,
        ],
        [
          'name' => __('GitHub Access Token', 'mlcommons'),
          'id' => $prefix . 'github_access_token',
          'type' => 'apikey',
          'placeholder' => __('github_pat_.............', 'mlcommons'),
//          'required' => true,
        ],
      ],
    ];

    $meta_boxes[] = [
      'id' => 'ml-mailchimp',
      'settings_pages' => ['mlcommons_settings'],
      'tab' => 'mailchimp',
      'fields' => [
        [
          'name' => __('Hash TTL Creation (minutes)', 'mlcommons'),
          'id' => $prefix . 'mc_hash_ttl',
          'type' => 'number',
          'std' => 60,
//          'required' => true,
        ],
        [
          'name' => __('Hash TTL Opened (minutes)', 'mlcommons'),
          'id' => $prefix . 'mc_hash_ttl_opened',
          'type' => 'number',
          'std' => 2,
//          'required' => true,
        ],
        [
          'name' => __('Server Prefix', 'mlcommons'),
          'id' => $prefix . 'mc_server_prefix',
          'type' => 'text',
          'std' => 'us21',
//          'required' => true,
        ],
        [
          'name' => __('Marketing API Key', 'mlcommons'),
          'id' => $prefix . 'mc_api_key',
          'type' => 'apikey',
//          'required' => true,
        ],
        [
          'name' => __('Transactional Email API Key', 'mlcommons'),
          'id' => $prefix . 'mc_api_key_email',
          'type' => 'apikey',
//          'required' => true,
        ],
        [
          'name' => __('Main List', 'mlcommons'),
          'id' => $prefix . 'mc_list_main',
          'type' => 'text',
//          'required' => true,
        ],
        [
          'name' => __('Email Template', 'mlcommons'),
          'id' => $prefix . 'mc_email_template',
          'type' => 'post',
          'post_type' => ['wp_block'],
//          'required' => true,
        ],
        [
          'name' => __('Webhook User Updated', 'mlcommons'),
          'id' => $prefix . 'mc_webhook_user_updated',
          'type' => 'text',
        ],
        [
          'name' => __('Join Page', 'mlcommons'),
          'id' => $prefix . 'mc_page_join',
          'type' => 'post',
          'post_type' => ['page'],
//          'required' => true,
        ],
        [
          'name' => __('Join Gravity Form', 'mlcommons'),
          'id' => $prefix . 'mc_gf_form_join',
          'type' => 'select',
          'options' => $gravity_forms
        ],
        [
          'name' => __('Profile Page', 'mlcommons'),
          'id' => $prefix . 'mc_page_profile',
          'type' => 'post',
          'post_type' => ['page'],
//          'required' => true,
        ],
        [
          'name' => __('Profile Gravity Form', 'mlcommons'),
          'id' => $prefix . 'mc_gf_form_update',
          'type' => 'select',
          'options' => $gravity_forms
        ],
        [
          'name' => __('Profile Mapping', 'mlcommons'),
          'id' => $prefix . 'mc_gf_form_update_mapping',
          'type' => 'group',
          'clone' => true,
          'sort_clone' => true,
          'fields' => [
            [
              'name' => __('Mailchimp Field', 'mlcommons'),
              'id' => $prefix . 'mc_field',
              'type' => 'text',
            ],
            [
              'name' => __('GF Field', 'mlcommons'),
              'id' => $prefix . 'gf_field',
              'type' => 'select',
              'options' => $gf_edit_fields
            ]
          ]
        ],
        [
          'name' => __('Mailchimp Fields Information', 'mlcommons'),
          'type' => 'custom_html',
          'callback' => [$this, 'get_mc_info']
        ],
      ],
    ];
    $meta_boxes[] = [
      'id' => 'ml-tools',
      'settings_pages' => ['mlcommons_settings'],
      'tab' => 'tools',
      'fields' => [
        [
          'name' => __('Info', 'mlcommons'),
          'type' => 'custom_html',
          'std' => '<p>a</p>'
        ],
      ],
    ];

    return $meta_boxes;
  }

}
