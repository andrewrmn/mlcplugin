<?php

class RWMB_Apikey_Field extends RWMB_Field {

  public static function html($meta, $field) {
    return sprintf(
        '<textarea stype="password" name="%s" id="%s"  placeholder="%s"  class="rwmb-apikey"></textarea><a id="#hint-%s" href="#hint-%s" data-field="%s">Current Key</a>',
        $field['field_name'],
        $field['id'],
        'Enter your new key or type DELETE and click "Save Settings" to reset',
        $field['id'],
        $field['id'],
        $field['id'],
    );
  }

  public static function save($new, $old, $post_id, $field) {
    if (!trim($new)):
      return;
    endif;
    $storage = $field['storage'];
    if ('DELETE' === $new):
      $storage->delete($post_id, $field['id']);
      return;
    endif;
    if (!RWMB_Helpers_Value::is_valid_for_field($new)) :
      return;
    endif;

    $storage->update($post_id, $field['id'], $new, '');
  }

}
