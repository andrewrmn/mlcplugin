<?php

class MLCommons_Settings {

  static public function get_setting($setting, $earlier_use = false) {
    
    if ($earlier_use):
      $s = get_option('mlcommons-settings');
      $o = isset($s[$setting]) ? $s[$setting] : false;
    else:
      $o = rwmb_meta($setting, ['object_type' => 'setting'], 'mlcommons-settings');
    endif;
    return $o;
  }

}
