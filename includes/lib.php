<?php

function obscure_text($string, $mode = 'middle', $size = 3) {
  if ($size >= strlen($string) * 2):
    return 'unsecure';
  endif;
  $string = str_replace(' ', '', $string);
  switch ($mode):
    case 'middle':
      $ret = substr($string, 0, $size) . '*******************' . substr($string, -$size);
      break;
    default:
      $len = 15;
      $start = intval(strlen($string) / 2) - $len;
      $ret = substr($string, 0, $size) . '****' . substr($string, $start, $len) . '******' . substr($string, -$size);
      break;
  endswitch;
  return $ret;
}
