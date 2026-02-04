<?php
if (!defined('ABSPATH')) exit;
class OA_DB {
  const SCHEMA_VERSION = '0.4.0';
  public static function init() { self::upgrade_if_needed(); }
  public static function expected_schema_map(){
    return [
      'daily_pages'=>[
        'columns'=>['day','path_hash','path','device_class','count','approx_uniques'],
        'primary'=>['day','path_hash','device_class'],
        'indexes'=>['day_idx','path_idx'],
      ],
      'daily_referrers'=>[
        'columns'=>['day','ref_hash','ref_domain','count'],
        'primary'=>['day','ref_hash'],
        'indexes'=>['day_idx'],
      ],
      'daily_events'=>[
        'columns'=>['day','event_hash','event_name','meta_hash','meta','count'],
        'primary'=>['day','event_hash','meta_hash'],
        'indexes'=>['day_idx','event_idx'],
      ],
      'daily_campaigns'=>[
        'columns'=>['day','camp_hash','source','medium','campaign','landing_hash','landing_path','views','conversions','value_sum'],
        'primary'=>['day','camp_hash','landing_hash'],
        'indexes'=>['day_idx','camp_idx'],
      ],
      'goals'=>[
        'columns'=>['id','name','type','match_value','meta_key','meta_value','value','is_enabled','created_at'],
        'primary'=>['id'],
        'indexes'=>['type_idx','enabled_idx'],
      ],
      'daily_goals'=>[
        'columns'=>['day','goal_id','hits','value_sum'],
        'primary'=>['day','goal_id'],
        'indexes'=>['day_idx'],
      ],
      'funnels'=>[
        'columns'=>['id','name','is_enabled','created_at'],
        'primary'=>['id'],
        'indexes'=>['enabled_idx'],
      ],
      'funnel_steps'=>[
        'columns'=>['funnel_id','step_num','step_type','step_value','meta_key','meta_value'],
        'primary'=>['funnel_id','step_num'],
        'indexes'=>['funnel_idx'],
      ],
      'daily_funnels'=>[
        'columns'=>['day','funnel_id','step_num','hits'],
        'primary'=>['day','funnel_id','step_num'],
        'indexes'=>['day_idx'],
      ],
      'daily_revenue'=>[
        'columns'=>['day','orders','revenue'],
        'primary'=>['day'],
        'indexes'=>['day_idx'],
      ],
      'daily_coupons'=>[
        'columns'=>['day','coupon_hash','coupon_code','orders','discount_total','revenue_total'],
        'primary'=>['day','coupon_hash'],
        'indexes'=>['day_idx','coupon_idx'],
      ],
    ];
  }
  public static function expected_table_names(){
    return array_keys(self::expected_schema_map());
  }
  public static function schema_audit(){
    global $wpdb; $prefix=$wpdb->prefix.'oa_';
    $out=[];
    foreach(self::expected_schema_map() as $table=>$spec){
      $full=$prefix.$table;
      $exists=((string)$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$full))===$full);
      $row=[
        'table'=>$table,
        'exists'=>$exists,
        'missing_columns'=>[],
        'primary_ok'=>false,
        'missing_indexes'=>[],
        'status'=>'ok',
      ];
      if (!$exists){
        $row['status']='fail';
        $out[]=$row;
        continue;
      }
      $cols=$wpdb->get_results("SHOW COLUMNS FROM {$full}", ARRAY_A);
      $col_map=[];
      foreach((array)$cols as $c) $col_map[(string)$c['Field']]=1;
      foreach((array)$spec['columns'] as $c){
        if (empty($col_map[$c])) $row['missing_columns'][]=$c;
      }

      $idx_rows=$wpdb->get_results("SHOW INDEX FROM {$full}", ARRAY_A);
      $pk_cols=[];
      $idx_names=[];
      foreach((array)$idx_rows as $i){
        $key=(string)$i['Key_name'];
        if ($key==='PRIMARY'){
          $pk_cols[(int)$i['Seq_in_index']] = (string)$i['Column_name'];
        } else {
          $idx_names[$key]=1;
        }
      }
      ksort($pk_cols);
      $pk_vals=array_values($pk_cols);
      $row['primary_ok']=($pk_vals===array_values((array)$spec['primary']));

      foreach((array)$spec['indexes'] as $idx){
        if (empty($idx_names[$idx])) $row['missing_indexes'][]=$idx;
      }

      if (!empty($row['missing_columns']) || !$row['primary_ok']) {
        $row['status']='fail';
      } elseif (!empty($row['missing_indexes'])) {
        $row['status']='warn';
      } else {
        $row['status']='ok';
      }
      $out[]=$row;
    }
    return $out;
  }
  private static function append_migration_log($entry){
    $log=get_option('oa_db_migration_log',[]);
    if (!is_array($log)) $log=[];
    $log[]=$entry;
    if (count($log)>25) $log=array_slice($log,-25);
    update_option('oa_db_migration_log',$log,false);
  }
  public static function get_migration_log($limit=20){
    $log=get_option('oa_db_migration_log',[]);
    if (!is_array($log)) return [];
    $log=array_reverse($log);
    return array_slice($log,0,max(1,intval($limit)));
  }
  public static function install_or_upgrade($context='upgrade') {
    global $wpdb;
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    $charset=$wpdb->get_charset_collate();
    $p=$wpdb->prefix.'oa_';
    $from_version=(string)get_option('oa_db_version','');
    $expected=self::expected_table_names();
    $missing_before=0;
    foreach($expected as $name){
      $tbl=$p.$name;
      $exists=((string)$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$tbl))===$tbl);
      if (!$exists) $missing_before++;
    }
    $sql=[];
    $sql[]="CREATE TABLE {$p}daily_pages (
      day date NOT NULL,
      path_hash char(16) NOT NULL,
      path varchar(255) NOT NULL,
      device_class varchar(12) NOT NULL DEFAULT 'unknown',
      count bigint unsigned NOT NULL DEFAULT 0,
      approx_uniques bigint unsigned NOT NULL DEFAULT 0,
      PRIMARY KEY (day, path_hash, device_class),
      KEY day_idx (day),
      KEY path_idx (path_hash)
    ) $charset;";
    $sql[]="CREATE TABLE {$p}daily_referrers (
      day date NOT NULL,
      ref_hash char(16) NOT NULL,
      ref_domain varchar(190) NOT NULL,
      count bigint unsigned NOT NULL DEFAULT 0,
      PRIMARY KEY (day, ref_hash),
      KEY day_idx (day)
    ) $charset;";
    $sql[]="CREATE TABLE {$p}daily_events (
      day date NOT NULL,
      event_hash char(16) NOT NULL,
      event_name varchar(64) NOT NULL,
      meta_hash char(16) NOT NULL,
      meta varchar(190) NOT NULL,
      count bigint unsigned NOT NULL DEFAULT 0,
      PRIMARY KEY (day, event_hash, meta_hash),
      KEY day_idx (day),
      KEY event_idx (event_hash)
    ) $charset;";
    $sql[]="CREATE TABLE {$p}daily_campaigns (
      day date NOT NULL,
      camp_hash char(16) NOT NULL,
      source varchar(64) NOT NULL,
      medium varchar(64) NOT NULL,
      campaign varchar(64) NOT NULL,
      landing_hash char(16) NOT NULL,
      landing_path varchar(255) NOT NULL,
      views bigint unsigned NOT NULL DEFAULT 0,
      conversions bigint unsigned NOT NULL DEFAULT 0,
      value_sum decimal(12,2) NOT NULL DEFAULT 0.00,
      PRIMARY KEY (day, camp_hash, landing_hash),
      KEY day_idx (day),
      KEY camp_idx (camp_hash)
    ) $charset;";
    $sql[]="CREATE TABLE {$p}goals (
      id bigint unsigned NOT NULL AUTO_INCREMENT,
      name varchar(64) NOT NULL,
      type varchar(12) NOT NULL,
      match_value varchar(255) NOT NULL,
      meta_key varchar(64) NOT NULL DEFAULT '',
      meta_value varchar(128) NOT NULL DEFAULT '',
      value decimal(12,2) NOT NULL DEFAULT 0.00,
      is_enabled tinyint(1) NOT NULL DEFAULT 1,
      created_at datetime NOT NULL,
      PRIMARY KEY (id),
      KEY type_idx (type),
      KEY enabled_idx (is_enabled)
    ) $charset;";
    $sql[]="CREATE TABLE {$p}daily_goals (
      day date NOT NULL,
      goal_id bigint unsigned NOT NULL,
      hits bigint unsigned NOT NULL DEFAULT 0,
      value_sum decimal(12,2) NOT NULL DEFAULT 0.00,
      PRIMARY KEY (day, goal_id),
      KEY day_idx (day)
    ) $charset;";
    $sql[]="CREATE TABLE {$p}funnels (
      id bigint unsigned NOT NULL AUTO_INCREMENT,
      name varchar(64) NOT NULL,
      is_enabled tinyint(1) NOT NULL DEFAULT 1,
      created_at datetime NOT NULL,
      PRIMARY KEY (id),
      KEY enabled_idx (is_enabled)
    ) $charset;";
    $sql[]="CREATE TABLE {$p}funnel_steps (
      funnel_id bigint unsigned NOT NULL,
      step_num int unsigned NOT NULL,
      step_type varchar(12) NOT NULL,
      step_value varchar(255) NOT NULL,
      meta_key varchar(64) NOT NULL DEFAULT '',
      meta_value varchar(128) NOT NULL DEFAULT '',
      PRIMARY KEY (funnel_id, step_num),
      KEY funnel_idx (funnel_id)
    ) $charset;";
    $sql[]="CREATE TABLE {$p}daily_funnels (
      day date NOT NULL,
      funnel_id bigint unsigned NOT NULL,
      step_num int unsigned NOT NULL,
      hits bigint unsigned NOT NULL DEFAULT 0,
      PRIMARY KEY (day, funnel_id, step_num),
      KEY day_idx (day)
    ) $charset;";
    $sql[]="CREATE TABLE {$p}daily_revenue (
      day date NOT NULL,
      orders bigint unsigned NOT NULL DEFAULT 0,
      revenue decimal(12,2) NOT NULL DEFAULT 0.00,
      PRIMARY KEY (day),
      KEY day_idx (day)
    ) $charset;";
    $sql[]="CREATE TABLE {$p}daily_coupons (
      day date NOT NULL,
      coupon_hash char(16) NOT NULL,
      coupon_code varchar(100) NOT NULL,
      orders bigint unsigned NOT NULL DEFAULT 0,
      discount_total decimal(12,2) NOT NULL DEFAULT 0.00,
      revenue_total decimal(12,2) NOT NULL DEFAULT 0.00,
      PRIMARY KEY (day, coupon_hash),
      KEY day_idx (day),
      KEY coupon_idx (coupon_hash)
    ) $charset;";
    for ($i=0;$i<count($sql);$i++) dbDelta($sql[$i]);
    $missing_after=0;
    foreach($expected as $name){
      $tbl=$p.$name;
      $exists=((string)$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$tbl))===$tbl);
      if (!$exists) $missing_after++;
    }
    update_option('oa_db_version',self::SCHEMA_VERSION);
    if ($context==='upgrade' && $from_version==='') $context='install';
    self::append_migration_log([
      'at'=>current_time('mysql'),
      'from'=>$from_version ?: '-',
      'to'=>self::SCHEMA_VERSION,
      'context'=>sanitize_key((string)$context),
      'missing_before'=>$missing_before,
      'missing_after'=>$missing_after,
    ]);
    if (!get_option('oa_settings')) {
      add_option('oa_settings', [
        'enabled'=>1,'strip_query'=>1,'track_logged_in'=>0,'respect_dnt'=>0,'sample_rate'=>1,
        'rate_limit_per_min'=>120,'approx_uniques'=>0,'retention_days'=>180,'auto_events'=>1,
        'auto_outbound'=>1,'auto_downloads'=>1,'auto_tel'=>1,'auto_mailto'=>1,'auto_forms'=>1,
        'utm_attribution_days'=>30,
        'email_reports'=>0,'email_reports_freq'=>'weekly','email_reports_to'=>get_option('admin_email'),
        'anomaly_alerts'=>1,'anomaly_threshold_pct'=>35,'anomaly_baseline_days'=>7,
        'anomaly_min_views'=>60,'anomaly_min_conversions'=>5,
        'consent_mode'=>'off','consent_cookie'=>'oa_consent','optout_cookie'=>'oa_optout',
        'trust_proxy_headers'=>0,'keep_data_on_uninstall'=>0,
      ]);
    }
  }
  public static function upgrade_if_needed(){ if (get_option('oa_db_version','')!==self::SCHEMA_VERSION) self::install_or_upgrade('upgrade'); }
}
