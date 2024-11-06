<?php

class Mlcommons_GCal {

  var $definitions;
  var $timezone = '';

  function __construct() {
    $this->timezone = get_option('timezone_string');

    $this->definitions = $this->load_group_definitions();
  }

  function get_gcolors($scope = 'event') {
    foreach ($this->definitions['gcal_colors'][$scope] as $color_id => $colors):
      $ret[$color_id] = $colors['bg'] . ' - Color ID ' . $color_id;
    endforeach;
    return $ret;
  }

  function get_nearest_events($events, $just_one = false) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gcal_events';
    $today = date('Y-m-d');

    $placeholders = implode( ', ', array_fill( 0, count( $events ), '%s' ) );

    $q = $wpdb->prepare(
        "SELECT cal_id, cal_parent_event 
     FROM `$table_name` 
     WHERE cal_parent_event IN ($placeholders) 
     ORDER BY ABS(TIMESTAMPDIFF(SECOND, cal_date_start, '$today'))",
        $events
    );
    
    $nearest_events = $wpdb->get_results($q, ARRAY_A);

    if ($just_one):
      return !empty($nearest_events) ? $nearest_events[0]['cal_id'] : false;
    else:
      return !empty($nearest_events) ? $nearest_events : [];
    endif;
  }

  function date_conv($dateStr, $ret = 'string') {

    /* $dateTime = new DateTime($dateStr, new DateTimeZone('UTC'));
      $dateTime->setTimezone(new DateTimeZone($this->timezone)); */
    $dateTime = new DateTime($dateStr, new DateTimeZone('UTC'));
    $dateTime->setTimezone(new DateTimeZone($this->timezone));

    if ('string' === $ret):
      return $dateTime->format('Y-m-d H:i:s');
    else:
      return $dateTime;
    endif;
  }

  function get_local_color($color_id = 1) {

    $ret = false;
    for ($i = 0; $i < count($this->definitions['groups_colors']); $i++):
      if (intval($this->definitions['groups_colors'][$i]['mlp_gcal_gcolor']) === intval($color_id)):
        $ret = [
          'id' => $color_id,
          'color' => $this->definitions['groups_colors'][$i]['mlp_gcal_gcolor_local'],
          'title' => $this->definitions['groups_colors'][$i]['mlp_gcal_gcolor_title'],
        ];
      endif;
    endfor;
    return $ret;
  }

  function load_group_definitions() {


    $ret = [
      'gcal_colors' => get_option(MLCOMMONS_OPTION_GCAL_COLORS),
      'groups_colors' => MLCommons_Settings::get_setting('mlp_gcal_event_colors', true),
      'groups_calendars' => [],
      'calendar_groups' => [],
      'calendar_colors' => []
    ];
    return $ret;
  }

  function check_table_gcal_events() {
    $stored_version = get_option(MLCOMMONS_DB_GCAL_EVENTS);

    if ($stored_version !== MLCOMMONS_DB_GCAL_EVENTS_VERSION) {

      $this->update_table_gcal_events(true);
    }
  }

  function update_table_gcal_events($update_ver) {
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $table_name = $wpdb->prefix . 'gcal_events';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        cal_id char(64) NOT NULL,
        cal_ical_uid char(64) NOT NULL,
        cal_color_id char(4) NULL,
        cal_recurring_event_id char(64) NULL,
        cal_recurring_info text NULL,
        cal_all_day int(1) DEFAULT 0 NOT NULL,
        cal_extended_prop TEXT NULL,
        cal_summary char(200) NOT NULL,
        cal_description text NULL,
        cal_date_start datetime NOT NULL,
        cal_date_end datetime NOT NULL,
        cal_tz char(32) NULL,
        cal_status char(32) NULL,
        cal_parent_event char(64) NULL,
        PRIMARY KEY (cal_id),
        KEY kdates (cal_date_start, cal_date_end),
        KEY kstatus (cal_status),
        KEY ksearch (cal_date_start, cal_date_end, cal_summary, cal_recurring_event_id),
        KEY krecurring (cal_recurring_event_id),
        KEY kparent (cal_parent_event, cal_date_start),
        KEY kcolor (cal_color_id)
    ) $charset_collate;";

    dbDelta($sql);
    if ($update_ver):
      update_option(MLCOMMONS_DB_GCAL_EVENTS, MLCOMMONS_DB_GCAL_EVENTS_VERSION);
    endif;
  }

  function get_workinggroup_events() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gcal_events';
    $q = "SELECT DISTINCT cal_parent_event, cal_summary FROM  " . $table_name . " where cal_color_id=1 order by cal_summary ";
    return $wpdb->get_results($q, ARRAY_A);
  }

  function get_submission_events() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gcal_events';
    $q = "SELECT DISTINCT * FROM  " . $table_name . " where cal_color_id=6 order by cal_summary ";
    return $wpdb->get_results($q, ARRAY_A);
  }

  function get_events($args = []) {
    $views = ['day', 'week', 'month'];

    global $wpdb;
    $defaults = [
      'shortcode' => false,
      'view' => isset($args['view']) && in_array($args['view'], $views) ? $args['view'] : 'week',
      'dates' => [
        'start' => date("Y-m-01", time()) . "T00:00:00Z",
        'end' => date("Y-m-d", strtotime('+1 month', time())) . "T23:59:59Z",
      ],
      'limit' => 999,
    ];

    $params = wp_parse_args($args, $defaults);

    $dates = $params['dates'];

    $table_name = $wpdb->prefix . 'gcal_events';

    $orderby = 'start ASC, title ASC';

    if ('day' === $params['view']):
      $q = $wpdb->prepare(
          "SELECT DISTINCT cal_id as id, "
          . "cal_summary as title, "
          . "cal_all_day as isAllDay, "
          . "cal_color_id as color_id, "
          . "cal_date_start as start, "
          . "cal_date_end as end, "
          . "cal_recurring_event_id "
          . "FROM  " . $table_name . " "
          . "WHERE cal_color_id IS NOT NULL AND (cal_date_start >= %s AND cal_date_end <= %s) "
          . "ORDER BY " . $orderby . " "
          . "LIMIT " . intval($params['limit']),
          date("Y-m-d", strtotime($dates['start'])) . "T00:00:00Z",
          date("Y-m-d", strtotime($dates['start'])) . "T23:59:59Z",
      );
    else:
      $q = $wpdb->prepare(
          "SELECT DISTINCT cal_id as id, "
          . "cal_summary as title, "
          . "cal_all_day as isAllDay, "
          . "cal_color_id as color_id, "
          . "cal_date_start as start, "
          . "cal_date_end as end, "
          . "cal_recurring_event_id "
          . "FROM  " . $table_name . " "
          . "WHERE cal_color_id IS NOT NULL AND ((cal_date_start >= %s AND cal_date_end <= %s) OR (cal_date_start <= %s AND cal_date_end >= %s)) "
          . "ORDER BY " . $orderby . " "
          . "LIMIT " . intval($params['limit']),
          $dates['start'],
          $dates['end'],
          $dates['start'],
          $dates['end'],
      );
    endif;

    $results = $wpdb->get_results($q, ARRAY_A);
    unset($params['limit']);
    $ret = [
      'params' => $params,
      'events' => [],
      'calendars' => [],
      'calendar_ids' => [],
      'hours' => [8, 17],
      'calendars_in_view' => [],
      'allday_view' => false
    ];

    if ($results):
      $calendars = [];
      foreach ($results as $e):
        $color = $this->get_local_color($e['color_id']);

        $cal_id = $e['color_id'] ? $e['color_id'] : 1;
        $e['title'] = $this->cleanup_event_title($e['title']);
        $e['calendarId'] = 'calendar-' . $cal_id;
        $e['category'] = $e['isAllDay'] ? 'allday' : 'time';
        $e['backgroundColor'] = $color ? $color['color'] : 'red';
        $e['start'] = $e['isAllDay'] ? $e['start'] : $this->date_conv($e['start']);
        $e['end'] = $e['isAllDay'] ? $e['end'] : $this->date_conv($e['end']);

        $ret['events'][] = $e;

        if (!$e['isAllDay']):
          $hs = intval(date('H', strtotime($e['start'])));
          $he = intval(date('H', strtotime($e['end'])));
          $ret['hours'] = array_unique(array_merge([$hs, $he], $ret['hours']));
        else:
          $ret['allday_view'] = true;
        endif;
        $ret['calendars_in_view'] = array_unique(array_merge([$cal_id], $ret['calendars_in_view']));

      endforeach;
      $ret['hours'] = [
        min($ret['hours']) - 1, max($ret['hours']) + 1
      ];

    endif;

    $available_cals = [1, 2, 6];
    foreach ($available_cals as $acid):

      $calendars[] = 'calendar-' . $acid;

      $color = $this->get_local_color($acid);

      $ret['calendars'][] = [
        'id' => 'calendar-' . $acid,
        'name' => $color ? $color['title'] : 'Cal ' . $acid,
        'bgColor' => $color ? $color['color'] : 'red',
      ];
      $ret['calendar_ids'][] = 'calendar-' . $acid;

    endforeach;

    if ($params['shortcode']):
      $ret['events'] = [];
    endif;

    //_d($ret);
    return $ret;
  }

  function cleanup_event_title($title) {
    $pattern = '/^(mlc|mlcommons)\s/i';

    $result = preg_replace($pattern, '', $title);

    return trim($result);
  }

  function sync_colors($params) {
    $params = is_array($params) ? $params : array();
    $args = wp_parse_args($params, array());

    $verbose = isset($args['verbose']) && 'yes' === $args['verbose'] ? true : false;

    $gapi = new Mlcommons_GoogleApi();
    $service = $gapi->get_service_calendar('sync_colors');
    if (!$service):
      $this->error('500', 'error-service-creation');
    endif;

    $result = 'fail';
    try {
      $service_response = $service->colors->get();

      $result = 'sucess';
      $colors = [
        'calendar' => [],
        'event' => []
      ];

      foreach ($service_response->getCalendar() as $key => $color):
        $colors['calendar'][$key] = [
          'bg' => $color->getBackground(),
          'fg' => $color->getForeground(),
        ];
      endforeach;
      foreach ($service_response->getEvent() as $key => $color):
        $colors['event'][$key] = [
          'bg' => $color->getBackground(),
          'fg' => $color->getForeground(),
        ];
      endforeach;
      update_option(MLCOMMONS_OPTION_GCAL_COLORS, $colors);
      if ($verbose):
        $ret = $colors;
      endif;
    } catch (Exception $ex) {
      $this->error('500', 'service-error');
      $ret = $ex->getTraceAsString();
    }
    return ['result' => $result, 'extra' => $ret];
  }

  function sync_event_recurrence($params) {
    $params = is_array($params) ? $params : array();
    $args = wp_parse_args($params, array());

    $event_id = isset($args['parent_event']) ? $args['parent_event'] : false;
    if (!$event_id):
      echo 'missing-parent-event-id';
      die();
    endif;
    //$event_id = str_replace('@google.com','',$event_id);

    $gapi = new Mlcommons_GoogleApi();
    $service = $gapi->get_service_calendar('sync_event_recurrence');
    if (!$service):
      $this->error('500', 'error-service-creation');
    endif;
    $cal_id = MLCommons_Settings::get_setting('mlp_gcal_calendar_id');

    try {

      $google_event = $service->events->get($cal_id, $event_id);
      $recurrence_rules = [];
      $extended_properties = [];

      if ($google_event->getRecurrence()) {
        foreach ($google_event->getRecurrence() as $rule) {
          $recurrence_rules[] = $rule;
        }
      }
      if ($google_event->getExtendedProperties()) {
        foreach ($google_event->getExtendedProperties() as $prop) {
          $extended_properties[] = $prop;
        }
      }

      global $wpdb;
      $table_name = $wpdb->prefix . 'gcal_events';
      $data = [
        'cal_recurring_info' => json_encode($recurrence_rules),
        'cal_extended_prop' => json_encode($extended_properties)
      ];
      $where = [
        'cal_parent_event' => $event_id,
      ];
      $query = $wpdb->update(
          $table_name,
          $data,
          $where
      );
    } catch (Exception $ex) {
      
    }
  }

  function is_all_day($google_event) {
    if (!$google_event->getStart()->dateTime && !$google_event->getEnd()->dateTime) {
      return true;
    } else {
      return false;
    }
    return $google_event->getTransparency() && 'transparent' !== $google_event->getTransparency() ? 1 : 0;
  }

  function ajax_sync_event($params) {

    $params = is_array($params) ? $params : array();
    $args = wp_parse_args($params, array());

    $event_id = isset($args['cal_id']) ? $args['cal_id'] : false;
    if (!$event_id):
      echo 'missing-event-id';
      die();
    endif;
    //$event_id = str_replace('@google.com','',$event_id);

    $gapi = new Mlcommons_GoogleApi();
    $service = $gapi->get_service_calendar('sync_event');
    if (!$service):
      $this->error('500', 'error-service-creation');
    endif;
    $row = false;
    $cal_id = MLCommons_Settings::get_setting('mlp_gcal_calendar_id');

    try {
      $event = $this->get_event($event_id);

      //$google_event = $service->events->get($cal_id, 'r9ppomomkv4s2nn5dsrce30l20');
      //_dd($google_event);
      $google_event = $service->events->get($cal_id, $event['cal_id']);

      $sum = $google_event->getSummary();
      if ($sum):

        $parent_event = explode('_', $google_event->getId())[0];
        $row = [
          'id' => $google_event->getId(),
          'parent_event' => $parent_event,
          'status' => $google_event->getStatus(),
          'date_start' => $google_event->getStart()->dateTime ?: $google_event->getStart()->date,
          'date_end' => $google_event->getEnd()->dateTime ?: $google_event->getEnd()->date,
          'all_day' => $this->is_all_day($google_event),
          'tz' => $google_event->getStart()->timeZone,
          'ical_uid' => $google_event->getICalUID(),
          'color_id' => $google_event->getColorId(),
          'recurring_event_id' => $google_event->getRecurringEventId(),
          'summary' => $google_event->getSummary(),
          'description' => $google_event->getDescription(),
          'log' => false
        ];
        try {
          $this->persist_event($row);
        } catch (Exception $ex_db) {

          $this->error('500', 'db-sync-event');
        }
        if (current_user_can('manage_options')):
          _d($row);
          _d($google_event);
        endif;

      endif;

      return $row;
    } catch (Exception $ex) {
      $this->error('403', 'connection-error-get-event' . $ex->getMessage());
    }
  }

  function sync_calendar($params) {
    $start = microtime(true);
    $params = is_array($params) ? $params : array();
    $args = wp_parse_args($params, array());

    $verbose = isset($args['verbose']) && 'yes' === $args['verbose'] ? true : false;
    $extra_verbose = isset($args['extra_verbose']) && 'yes' === $args['extra_verbose'] ? true : false;
    $max_rounds = isset($args['max_rounds']) ? intval($args['max_rounds']) : -1;
    $start_date = isset($args['start_date']) ? date('Y-m-d', strtotime($args['start_date'])) : date('Y-m-d', strtotime('-12 month'));
    $end_date = isset($args['end_date']) ? date('Y-m-d', strtotime($args['end_date'])) : date('Y-m-d', strtotime('+18 month'));

    $base_optParams = array(
      //'timeZone' => 'UTC',
      'maxResults' => isset($args['max_results']) && intval($args['max_results']) <= 2500 ? intval($args['max_results']) : 2500,
      'singleEvents' => true,
      'showDeleted' => true,
      'orderBy' => 'startTime'
        //'orderBy' => 'updated'
    );

    $gapi = new Mlcommons_GoogleApi();
    $service = $gapi->get_service_calendar('sync_calendar');
    if (!$service):
      $this->error('500', 'error-service-creation');
    endif;
    try {
      $cal_id = MLCommons_Settings::get_setting('mlp_gcal_calendar_id');

      $pageToken = NULL;
      $rows = array();
      $ix = 0;
      $rounds = 0;
      do {
        $optParams = array_merge($base_optParams, array(
          'timeMin' => $start_date . 'T00:00:00Z',
          'timeMax' => $end_date . 'T23:59:59Z',
          'pageToken' => $pageToken,
        ));

        $service_response = $service->events->listEvents($cal_id, $optParams);

        foreach ($service_response->items as $google_event) {
          //$e = new Google\Service\Calendar\Event($google_event);
          //$e->getExtendedProperties();
          if ($google_event->getSummary()):
            $recurrence_rules = [];
            $extended_properties = [];
            if ($google_event->getRecurrence()) {
              foreach ($google_event->getRecurrence() as $rule) {
                $recurrence_rules[] = $rule;
              }
            }
            if ($google_event->getExtendedProperties()) {
              foreach ($google_event->getExtendedProperties() as $prop) {
                $extended_properties[] = $prop;
              }
            }
            $parent_event = explode('_', $google_event->getId())[0];
            $row = [
              'id' => $google_event->getId(),
              'parent_event' => $parent_event,
              'date_start' => $google_event->getStart()->dateTime ?: $google_event->getStart()->date,
              'date_end' => $google_event->getEnd()->dateTime ?: $google_event->getEnd()->date,
              'tz' => $google_event->getStart()->timeZone,
              'all_day' => $this->is_all_day($google_event),
              'ical_uid' => $google_event->getICalUID(),
              'color_id' => $google_event->getColorId(),
              'recurring_event_id' => $google_event->getRecurringEventId(),
              'status' => $google_event->getStatus(),
              'recurring_info' => json_encode($recurrence_rules),
              'extended_prop' => json_encode($extended_properties),
              'summary' => $google_event->getSummary(),
              'description' => $google_event->getDescription(),
              'log' => false
            ];
            try {
              $this->persist_event($row);
            } catch (Exception $ex_db) {
              $this->error('500', 'db-sync-events');
            }


            $rows[] = array(
              'ix' => $ix,
              'summary' => $google_event->getSummary(),
              'recurring_event_id' => $google_event->getRecurringEventId(),
              'color_id' => $google_event->getColorId() ? $google_event->getColorId() : '',
              'status' => $google_event->getStatus(),
              'id' => $google_event->getId(),
              'parent' => $row['parent_event'],
              'date_start' => $google_event->getStart()->dateTime,
              'date_end' => $google_event->getEnd()->dateTime
            );
            $ix++;
          endif;
        }
        $pageToken = $service_response->getNextPageToken();
        $rounds++;
      } while ($pageToken && ($rounds <= $max_rounds || $max_rounds === -1));

      $reid = array_filter(array_unique(wp_list_pluck($rows, 'id')));
      $this->purge_events($reid, $optParams['timeMin'], $optParams['timeMax']);

      if ($verbose):
        $ret = array(
          'duration' => -1,
          'total' => count($rows),
          'rounds' => $rounds,
          'params' => $base_optParams,
          'batch' => false,
        );
        if ($extra_verbose):
          $ret['reid'] = $reid;
          $ret['rows'] = $rows;
        endif;
      else:
        $ret = array(
          'duration' => -1,
          'total' => count($rows),
          'rounds' => $rounds,
          'batch' => false,
        );
      endif;
      $batch_result = $gapi->sync_recurrences($args);
      $ret['batch'] = $batch_result;

      $end = microtime(true);
      $duration = $end - $start;
      $seconds = (int) $duration;
      $microseconds = ($duration - $seconds) * 1000000;

      $ret ['duration'] = number_format($duration) . 's' . $microseconds;

      return $ret;
    } catch (Exception $ex) {
      _d($ex);
      $this->error('403', 'connection-error-get-events');
    }
  }

  private function purge_events($eirds, $timemin, $timemax) {
    global $wpdb;
    $start = explode('T', $timemin)[0] . ' 0:0:0';
    $end = explode('T', $timemax)[0] . ' 23:59:59';
    $table_name = $wpdb->prefix . 'gcal_events';
    $eirds = array_filter($eirds);

    $ids = "'" . implode("','", $eirds) . "'";

    $q = "SELECT DISTINCT cal_id FROM $table_name where "
        . "( cal_date_start>='{$start}' AND cal_date_end<='{$end}' ) AND "
        . "cal_id in ({$ids}) ";
    $to_exclude = $wpdb->get_results($q, ARRAY_A);
    if ($to_exclude):
      $excluded = wp_list_pluck($to_exclude, 'cal_id');

      //$q = "SELECT DISTINCT cal_recurring_event_id from $table_name where cal_recurring_event_id IS NOT NULL AND cal_recurring_event_id not in ({$ids})";
      $q = "DELETE from $table_name where "
          . "cal_id NOT IN "
          . "('" . implode("','", $excluded) . "')";
      //$q = "DELETE from $table_name where cal_status='cancelled'";
      //_dd($eirds);

      $wpdb->query($q);
    ///_d($q);
    endif;
    //_dd($results);
  }

  function persist_event(&$event) {

    global $wpdb;
    $table_name = $wpdb->prefix . 'gcal_events';

    if ('cancelled' === $event['status']):
      $query = $wpdb->prepare("DELETE from `$table_name` where `cal_id` = %s", $event['id']);
      $result = $wpdb->query($query);
      $event['_sync_status'] = 'deleted';
      return;
    endif;

    $query = $wpdb->prepare("SELECT cal_id from `$table_name` where `cal_id` = %s limit 1", $event['id']);
    $result = $wpdb->get_row($query, ARRAY_A);

    if ($event['all_day']):
      $event['date_start'] = date('Y-m-d H:i:s', strtotime($event['date_start']));
      $timestamp = strtotime($event['date_end']);
      $timestamp -= 1;
      $event['date_end'] = date('Y-m-d H:i:s', $timestamp);

    endif;

    if (!$result['cal_id']):
      $data = [
        'cal_id' => $event['id'],
        'cal_ical_uid' => $event['ical_uid'],
        'cal_color_id' => $event['color_id'],
        'cal_recurring_event_id' => $event['recurring_event_id'],
        'cal_all_day' => $event['all_day'],
        'cal_status' => $event['status'],
        'cal_summary' => $event['summary'],
        'cal_description' => $event['description'],
        'cal_date_start' => $event['date_start'],
        'cal_date_end' => $event['date_end'],
        'cal_tz' => $event['tz'],
        'cal_parent_event' => $event['parent_event'],
      ];
      $query = $wpdb->insert(
          $table_name,
          $data,
      );
      $event['_sync_status'] = 'create';
    else:
      $data = [
        'cal_ical_uid' => $event['ical_uid'],
        'cal_color_id' => $event['color_id'],
        'cal_recurring_event_id' => $event['recurring_event_id'],
        'cal_all_day' => $event['all_day'],
        'cal_status' => $event['status'],
        'cal_summary' => $event['summary'],
        'cal_description' => $event['description'],
        'cal_date_start' => $event['date_start'],
        'cal_date_end' => $event['date_end'],
        'cal_tz' => $event['tz'],
        'cal_parent_event' => $event['parent_event'],
      ];
      $where = [
        'cal_id' => $event['id']
      ];
      $query = $wpdb->update(
          $table_name,
          $data,
          $where
      );
      $event['_sync_status'] = 'update';
    endif;
  }

  function error($header_code, $internal_code) {
    $header = '400 Bad Request';
    switch ($header_code):

      case '403':
        $header = 'HTTP/1.0 403 Forbidden';
        break;
      default:
        break;
    endswitch;

    header($header);
    wp_die('Error ' . $internal_code);
    die();
  }

  function get_html_popup($event) {
    $ret = '';
    if (is_array($event) && $event['cal_id']):



      if ($event['cal_all_day']):

        $dateTime1 = new DateTime($event['cal_date_start']);
        $dateTime2 = new DateTime($event['cal_date_end']);

        $formattedDate1 = $dateTime1->format('M jS, Y');
        $formattedDate2 = $dateTime2->format('M jS, Y');
        $dates = $formattedDate1;

      else:
        $dateTime1 = $this->date_conv($event['cal_date_start'], 'date');
        $dateTime2 = $this->date_conv($event['cal_date_end'], 'date');

        $formattedDate1 = $dateTime1->format('M jS, Y H:i');
        $formattedDate2 = $dateTime2->format('H:i');
        $dates = $formattedDate1 . ' to ' . $formattedDate2;
      endif;

      $has_description = isset($event['cal_description']) && trim($event['cal_description']) ? true : false;
      $description = $has_description ? $this->make_links($event['cal_description']) : '';

      $description_class = ['description'];
      if (!$has_description):
        $description_class [] = 'empty-description';
      endif;

      $ret = '<h3 class="title">' . $this->cleanup_event_title($event['cal_summary']) . '</h3>';
      $ret .= '<p class="dates">' . $dates . '</p>';
      $ret .= '<div class="' . implode(' ', $description_class) . '">' . $description . '</div>';
      if (current_user_can('manage_options') && MLCommons_Settings::get_setting('mlp_gcal_enable_debug', true)):

        $ret .= '<div class="debug">';
        $ret .= '<span class="id">#' . $event['cal_id'] . '</span>';
        _bfOn();
        _d($event, 'event', 'print', false);
        $ret .= _bfGet(false);
        $ret .= '</div>';

      endif;

    endif;
    //$ret.= '<!-- end ' . $event['id'] . '-->';
    return $ret;
  }

  function ajax_get_events() {

    $views = ['day', 'week', 'month'];

    $args = [
      'view' => in_array(filter_input(INPUT_POST, 'view'), $views) ? filter_input(INPUT_POST, 'view') : 'week',
      'dates' => [
        'start' => filter_input(INPUT_POST, 'start'),
        'end' => filter_input(INPUT_POST, 'end'),
      ]
    ];
    $e = $this->get_events($args);
    echo json_encode($e);

    die();
  }

  function ajax_get_event() {
    $eid = filter_input(INPUT_POST, 'eid');
    if (!$eid):
      die('Invalid EID');
    endif;
    $event = $this->get_event($eid);
    echo $this->get_html_popup($event);
    die();
  }

  function get_next_schedule($parent_event) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'gcal_events';
    $today = date('Y-m-d');
    $query = $wpdb->prepare("SELECT * from `$table_name` where `cal_parent_event`=%s ORDER BY ABS(TIMESTAMPDIFF(SECOND, `cal_date_start`, %s)) ", $parent_event, $today);
    $event = $wpdb->get_row($query, ARRAY_A);

    if (!$event):
      return false;
    endif;

    if (!$event['cal_recurring_info'] || '[]' === $event['cal_recurring_info']):
      $this->sync_event_recurrence(['parent_event' => $parent_event]);
      return $wpdb->get_row($query, ARRAY_A);
    else:
      return $event;
    endif;
  }

  function parseRecurrenceRule($rrule) {
    $ruleParts = explode(';', $rrule);
    $parsedRule = [];

    foreach ($ruleParts as $part) {
      $partComponents = explode('=', $part);
      $parsedRule[str_replace('RRULE:', '', $partComponents[0])] = isset($partComponents[1]) ? $partComponents[1] : null;
    }

    return $parsedRule;
  }

  function get_event($eid) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gcal_events';
    $query = $wpdb->prepare("SELECT * from `$table_name` where `cal_id`=%s LIMIT 1", $eid);

    return $wpdb->get_row($query, ARRAY_A);
  }

  function make_links($string) {
    if (!$string):
      return '';
    endif;
    $string = strip_tags($string, ['a', 'br', 'p']);
    $pattern = '/(<a\s+(?:[^>]*?\s+)?href=[\'"]([^\'"]*)[\'"][^>]*>(.*?)<\/a>)|((https?:\/\/\S+))/i';

    // replace each text on the <a> with the url 
    $tagged_text = preg_replace_callback($pattern, function ($matches) {
      $tag_length = 50;
      // check if contains <a> and if is needit to replace
      if (!empty($matches[1])) {
        // text is lenght than tag)_lenght?
        if (strlen($matches[3]) > $tag_length) {
          //truncate
          $text_truncated = substr($matches[3], 0, $tag_length) . '...';
          // rebuild link
          return '<a class="parsed-link" href="' . $matches[2] . '" title="' . $matches[2] . '" target="_blank">' . $text_truncated . '</a>';
        }
        // return adding target blank only
        return str_replace('<a ', '<a target="_blank" ', $matches[0]);
      } else {
        // if string isnt linked with <a>, tag it.
        // truncate too
        $link_tagged = strlen($matches[4]) > $tag_length ? substr($matches[4], 0, $tag_length) . '...' : $matches[4];
        return '<a target="_blank" class="parsed-link" href="' . $matches[4] . '" title="' . $matches[4] . '">' . $link_tagged . '</a>';
      }
    }, $string);

    return $tagged_text;
  }

}
