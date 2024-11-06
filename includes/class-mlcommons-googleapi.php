<?php

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use Google\Service\Calendar;

class Mlcommons_GoogleApi {

  private $client = false;

  function get_serviceaccount_email() {
    $email = MLCommons_Settings::get_setting('mlp_gcal_service_account_email', true);
    if (is_email($email)):
      return $email;
    endif;
    return false;
  }

  function get_serviceaccount_json() {
    $json = MLCommons_Settings::get_setting('mlp_gcal_service_account_json', true);
    if ($json):
      return json_decode($json, true);
    endif;
    return false;
  }

  function refresh_token_serviceaccount() {
    require_once MLCOMMONS_PATH . '/3rdparty/google-api/vendor/autoload.php';
    $SCOPES = ['https://www.googleapis.com/auth/calendar.readonly'];
    $credentials = new ServiceAccountCredentials(
        $SCOPES,
        $this->get_serviceaccount_json(),
        $this->get_serviceaccount_email()
    );
    $client = new Client();
    $credentials->updateMetadata(['httpHandler' => $client]);
    $result = 'fail';
    try {
      $token = $credentials->fetchAuthToken();
      //print_r($credentials->getLastReceivedToken());
      if (isset($token['access_token'])):
        update_option(MLCOMMONS_OAUTH_TOKEN, $token['access_token']);
      endif;
      $result = 'sucess';
    } catch (Exception $ex) {
      delete_option(MLCOMMONS_OAUTH_TOKEN);
      return new WP_Error('error_fetching_token', 'Error Fetching Token', array('status' => 501, 'msg' => $ex->getMessage()));
    }
    return $result;
  }

  function register_restapi_routes() {
    register_rest_route('mlcp/v1', 'gcal/', array(
      'methods' => 'POST',
      'callback' => [$this, 'rest_handle_gcal'],
      'permission_callback' => '__return_true'
    ));
    register_rest_route('mlcp/v1', 'mlcal/', array(
      'methods' => 'GET',
      'callback' => [$this, 'rest_handle_mlcal'],
      'permission_callback' => '__return_true'
    ));
    register_rest_route('mlcp/v1', 'mlcal/(?P<eid>[^\/]+)', array(
      'methods' => 'GET',
      'callback' => [$this, 'rest_handle_mlcal'],
      'permission_callback' => '__return_true'
    ));
  }

  function is_valid_client_token($client_token) {
    return $client_token === MLCommons_Settings::get_setting('mlp_gcal_restapi_client_token', true);
  }

  function sync_colors(WP_REST_Request $request, $params) {
    $gcal = new Mlcommons_GCal();
    return $gcal->sync_colors($params);
  }

  function sync_calendar(WP_REST_Request $request, $params) {
    $gcal = new Mlcommons_GCal();
    return $gcal->sync_calendar($params);
  }

  function get_client(): Google\Client {
    if ($this->client):
      return $this->client;
    endif;

    require_once MLCOMMONS_PATH . '/3rdparty/google-api/vendor/autoload.php';
    $sa = $this->get_serviceaccount_json();
    if (!$sa || !is_array($sa)):
      wp_die('Error on service creation');
    endif;

    $client = new Google\Client();
    $client->setApplicationName('MLCommons Website');
    $client->setScopes('https://www.googleapis.com/auth/calendar.readonly');
    $client->setAuthConfig($this->get_serviceaccount_json());
    $client->setSubject($this->get_serviceaccount_email());
    $this->client = $client;
    return $client;
  }

  function get_service_calendar($scope, $client = false): Google\Service\Calendar {
    require_once MLCOMMONS_PATH . '/3rdparty/google-api/vendor/autoload.php';
    return new Google\Service\Calendar($client ?: $this->get_client());
  }

  function sync_recurrences($params) {
    require_once MLCOMMONS_PATH . '/3rdparty/google-api/vendor/autoload.php';
    $sa = $this->get_serviceaccount_json();
    if (!$sa || !is_array($sa)):
      wp_die('Error on service creation');
    endif;
    

    global $wpdb;
    $metas = ['wg_schedule', 'wg_submission_deadline_event', 'wg_results_publication_event'];
    $sql = "SELECT distinct pm.meta_value 
      FROM wp_postmeta pm
      JOIN wp_posts p ON pm.post_id = p.ID
      WHERE p.post_type = 'page' and pm.meta_key in ('" . implode("','", $metas) . "')";
    $result = $wpdb->get_results($sql, ARRAY_A);
    if (!count($result)):
      $ret = [
        'result' => 'ok',
        'ids' => []
      ];
      return $ret;
    endif;
    $eventIds = wp_list_pluck($result, 'meta_value');
    
    $client = $this->get_client();
    $client->setUseBatch(true);
    $service = new Google_Service_Calendar($client);
    $batch = $service->createBatch($client);

    $cal_id = MLCommons_Settings::get_setting('mlp_gcal_calendar_id');

    foreach ($eventIds as $eventId) {
      $request = $service->events->get($cal_id, $eventId, ['alt' => 'json']);
      try {
        $batch->add($request, $eventId);
      } catch (Exception $exc) {
        echo $exc->getTraceAsString();
        die();
      }
    }

    try {
      $results = $batch->execute();
    } catch (Exception $exc) {
      echo $exc->getTraceAsString();
      die();
    }

    $ret = [
      'result' => 'ok',
      'ids' => []
    ];

    foreach ($results as $eventId => $google_event) {

      if ($google_event instanceof Google_Service_Calendar_Event) {

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

        $event_id_db = str_replace('response-', '', $eventId);

        $where = [
          'cal_parent_event' => $event_id_db,
        ];
        $query = $wpdb->update(
            $table_name,
            $data,
            $where
        );
        $ret ['ids'][$event_id_db] = $query;
      } else {
        return new WP_Error('error_fetch_event', 'Sync Single Event Error', array('status' => 405, 'msg' => 'Error fetching event: ' . $eventId . ' / ' . $event_id_db));
      }
    }
    return $ret;
  }

  private function validate_restapi_date($date) {
    $dateObj = DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $date);

    // Verificar si la fecha es válida y el formato es exacto
    if ($dateObj && $dateObj->format('Y-m-d\TH:i:s.v\Z') === $date) {
      return true;
    } else {
      return false;
    }
  }

  public function rest_handle_mlcal(WP_REST_Request $request) {

    $eid = $request->get_param('eid');
    $gcal = new Mlcommons_GCal();
    if (!$eid):

      $views = ['day', 'week', 'month'];

      $defaults = [
        'start' => date("Y-m-01", time()) . "T00:00:00Z",
        'end' => date("Y-m-d", strtotime('+1 month', time())) . "T23:59:59Z",
        'view' => 'week'
      ];

      $args = [
        'view' => in_array(filter_input(INPUT_GET, 'view'), $views) ? filter_input(INPUT_GET, 'view') : $defaults['view'],
        'dates' => [
          'start' => filter_input(INPUT_GET, 'start') && $this->validate_restapi_date(filter_input(INPUT_GET, 'start')) ? filter_input(INPUT_GET, 'start') : $defaults['start'],
          'end' => filter_input(INPUT_GET, 'end') && $this->validate_restapi_date(filter_input(INPUT_GET, 'end')) ? filter_input(INPUT_GET, 'end') : $defaults['end'],
        ]
      ];

      $response = $gcal->get_events($args);

    else:
      $event = $gcal->get_event($eid);
      if (is_array($event)):
        $response = [
          'eid' => $eid,
          'html' => $gcal->get_html_popup($event)
        ];

      else:
        $response = [
          'eid' => 'invalid'
        ];
      endif;

    endif;

    echo json_encode($response);
  }

  public function rest_handle_gcal(WP_REST_Request $request) {
    $params = $request->get_json_params();
    $client_token = isset($params['client_token']) && $params['client_token'] ? $params['client_token'] : false;
    if (!$client_token || !$this->is_valid_client_token($client_token)):
      return new WP_Error('error_client_token', 'Token Error', array('status' => 405, 'msg' => 'Invalid Client Token'));
    endif;
    $do = isset($params['do']) && $params['do'] ? $params['do'] : false;
    $response_code = '400';
    $extra = false;
    switch ($do):
      case 'token-refresh':
      case 'token-refresh-sa':
        $ret = $this->refresh_token_serviceaccount();
        if ('sucess' === $ret):
          $response_code = '200';
        endif;
        break;
      case 'color-sync':
        $ret = $this->sync_colors($request, $params);
        if (true === $ret):
          $response_code = '200';
        endif;
        break;
      case 'calendar-sync':
        $sync_status = $this->sync_calendar($request, $params);
        if (is_array($sync_status)):
          $response_code = '200';
        endif;
        $extra = $sync_status;
        $ret = 'sucess';
        break;
      default:
        $ret = 'invalid-action';
        break;
    endswitch;
    return new WP_REST_Response(['result' => $ret, 'extra' => $extra], $response_code, []);
  }

}
