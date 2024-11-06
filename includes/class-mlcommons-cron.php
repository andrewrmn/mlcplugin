<?php

class Mlcommons_Cron {

  function mlc_register_crons() {
    if (!wp_next_scheduled('mlcron_hourly')) {
      wp_schedule_event(time(), 'hourly', 'mlcron_hourly');
    }
    if (!wp_next_scheduled('mlcron_daily')) {
      wp_schedule_event(time(), 'daily', 'mlcron_daily');
    }
  }

  function do_hourly_cron() {
    $mc = new Mlcommons_Mailchimp();
    $mc->cleanup_hash_table();
  }

  function do_daily_cron() {
    
  }

}
