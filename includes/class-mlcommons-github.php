<?php

class Mlcommons_GitHub {

  function test() {
    if (!is_admin() || !filter_input(INPUT_GET, 'ghtest')):
      return;
    endif;
die();
    $github_path = filter_input(INPUT_GET, 'file') ?: '/';

    $response_curl = $this->fetchGitHubFileCurl($github_path);

    //_d($response_wp, 'wp');
    if (!is_array($response_curl)):
      _d('...', 'Contents for ' . $github_path);
      $response_content = base64_decode($response_curl->content);

      $args = (array) $response_curl;

      $this->update_page($github_path, $response_content, $args);
    else:
      //dir
      _d($response_curl, 'Contents for ' . $file_path);
    endif;
    die('Test end');
  }

  function update_page($github_path, $markdown_content, $gh_response) {
    $args = [
      'post_type' => 'page',
      'posts_per_page' => 1,
      'fields' => 'ids',
      'meta_query' => [
        [
          'key' => 'mlp_github_path',
          'value' => esc_attr($github_path)
        ]
      ]
    ];

    $q = new WP_Query($args);
    if (!$q->have_posts()):
      die('File doesnt have a target to save');
    endif;

    update_post_meta($q->posts[0], 'mlp_github_sha', $gh_response['sha']);
    update_post_meta($q->posts[0], 'mlp_github_last_fetch', date('Y-m-d H:i:s'));
    update_post_meta($q->posts[0], 'mlp_github_content_markdown', $markdown_content);

    $parsed_content = $this->parse($markdown_content);
    $sections = array_keys($parsed_content['sections']);

    delete_post_meta($q->posts[0], 'mlp_github_content_sections');
    add_post_meta($q->posts[0], 'mlp_github_content_sections', $sections);
  }

  static function parse($response) {
    require_once (MLCOMMONS_PATH . '/3rdparty/parsedown/Parsedown.php');
    $Parsedown = new Parsedown();
    $content = $Parsedown->text($response);
    $pattern = '/<h2>(.*?)<\/h2>(.*?)(?=(?:<h2>|$))/s';
    preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

    $sections = [];
    foreach ($matches as $match) {
      //remove \n & compact 
      $c = preg_replace('!\s+!smi', ' ', $match[2]);
      $title = $match[1];
      $sections[sanitize_title($title)] = [
        'title' => $title,
        'content' => trim($c)
      ];
    }
    $ret = [
      'parsed' => $content,
      'sections' => $sections
    ];

    return $ret;
  }

  function fetchGitHubFileCurl($file_path) {

    $owner = MLCommons_Settings::get_setting('mlp_github_repository_owner', true); //'mlcommons'; //'kiterocketdev';
    $repo = MLCommons_Settings::get_setting('mlp_github_repository', true); //'website-pages'; // 'test-repo';
    $token = MLCommons_Settings::get_setting('mlp_github_access_token', true);

    $url = "https://api.github.com/repos/{$owner}/{$repo}/contents/{$file_path}";
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => '1.1',
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'User-Agent: MLCommons',
        'Authorization: Bearer ' . $token,
        'Accepts: application/vnd.github.html+json'
      ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    $c = json_decode($response);

    $ret = false;
    if (is_array($c) || property_exists($c, 'content')):
      $ret = $c;
    else:
      $ret = [
        'error' => true,
      ];
    endif;
    return $ret;
  }

  //tbd: Why doesnt work with wp_http class?
  function _fetchGitHubFile($owner, $repo, $file_path) {
    return false;

    $url = "https://api.github.com/repos/{$owner}/{$repo}/contents/{$file_path}";
    $token = MLCommons_Settings::get_setting('mlp_github_access_token', true);
    $args = [
      'method' => 'GET',
      'timeout' => 1,
      'redirection' => 10,
      'httpversion' => CURL_HTTP_VERSION_1_1,
      'header' => [
        'Authorization: Bearer ' . $token,
        'User-Agent: MLCommons', // Replace with your application name
      ]
    ];

    $response = wp_remote_get($url, $args);

    if (is_wp_error($response)) {
      die('Error: ' . $response->get_error_message());
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['content'])) {
      // Decode the base64-encoded content
      return base64_decode($data['content']);
    } else {
      return [
        $url,
        'File not found or access denied',
        $data];
    }
  }

}
