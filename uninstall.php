<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
$opt=get_option('oa_settings',[]);
if (!empty($opt['keep_data_on_uninstall'])) return;
global $wpdb; $pfx=$wpdb->prefix.'oa_';
$tables=['daily_pages','daily_referrers','daily_events','daily_campaigns','goals','daily_goals','funnels','funnel_steps','daily_funnels','daily_revenue','daily_coupons'];
foreach($tables as $t){ $wpdb->query("DROP TABLE IF EXISTS {$pfx}{$t}"); }
delete_option('oa_settings');
delete_option('oa_db_version');
delete_option('oa_last_report_sent');
delete_option('oa_last_anomaly_alert_day');
