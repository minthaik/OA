<?php
if (!defined('ABSPATH')) exit;
class OA_Admin {
  public static function init(){
    self::ensure_caps();
    add_action('admin_menu',[__CLASS__,'menu']);
    add_action('admin_init',[__CLASS__,'register_settings']);
    add_action('admin_enqueue_scripts',[__CLASS__,'assets']);
  }
  public static function can_view(){
    return current_user_can('ordelix_analytics_view') || current_user_can('ordelix_analytics_manage');
  }
  public static function can_manage(){
    return current_user_can('ordelix_analytics_manage');
  }
  public static function ensure_caps(){
    $admin=get_role('administrator');
    if ($admin){
      if (!$admin->has_cap('ordelix_analytics_view')) $admin->add_cap('ordelix_analytics_view');
      if (!$admin->has_cap('ordelix_analytics_manage')) $admin->add_cap('ordelix_analytics_manage');
    }
    $editor=get_role('editor');
    if ($editor && !$editor->has_cap('ordelix_analytics_view')) $editor->add_cap('ordelix_analytics_view');
    if (class_exists('WooCommerce')){
      $shop=get_role('shop_manager');
      if ($shop && !$shop->has_cap('ordelix_analytics_view')) $shop->add_cap('ordelix_analytics_view');
    }
  }
  public static function assets($hook){
    if (strpos($hook,'ordelix-analytics')===false) return;
    wp_enqueue_style('ordelix-analytics-admin', OA_PLUGIN_URL.'assets/admin.css', [], OA_VERSION);
    wp_enqueue_script('ordelix-analytics-admin', OA_PLUGIN_URL.'assets/admin.js', ['jquery'], OA_VERSION, true);
    wp_localize_script('ordelix-analytics-admin','ordelixAnalyticsAdminCfg',[
      'restBase'=>esc_url_raw(rest_url('ordelix-analytics/v1/')),
      'restNonce'=>wp_create_nonce('wp_rest'),
      'siteSlug'=>sanitize_title(get_bloginfo('name')),
    ]);
  }
  public static function menu(){
    add_menu_page('Ordelix Analytics','Ordelix Analytics','ordelix_analytics_view','ordelix-analytics',[__CLASS__,'page_dashboard'],'dashicons-chart-area',56);
    add_submenu_page('ordelix-analytics','Anomalies','Anomalies','ordelix_analytics_view','ordelix-analytics-anomalies',[__CLASS__,'page_anomalies']);
    add_submenu_page('ordelix-analytics','Goals','Goals','ordelix_analytics_view','ordelix-analytics-goals',[__CLASS__,'page_goals']);
    add_submenu_page('ordelix-analytics','Funnels','Funnels','ordelix_analytics_view','ordelix-analytics-funnels',[__CLASS__,'page_funnels']);
    add_submenu_page('ordelix-analytics','Campaigns','Campaigns','ordelix_analytics_view','ordelix-analytics-campaigns',[__CLASS__,'page_campaigns']);
    if (class_exists('WooCommerce')) {
      add_submenu_page('ordelix-analytics','Coupons','Coupons','ordelix_analytics_view','ordelix-analytics-coupons',[__CLASS__,'page_coupons']);
      add_submenu_page('ordelix-analytics','Revenue','Revenue','ordelix_analytics_view','ordelix-analytics-revenue',[__CLASS__,'page_revenue']);
    }
    add_submenu_page('ordelix-analytics','Health','Health','ordelix_analytics_view','ordelix-analytics-health',[__CLASS__,'page_health']);
    add_submenu_page('ordelix-analytics','Settings','Settings','ordelix_analytics_manage','ordelix-analytics-settings',[__CLASS__,'page_settings']);
  }
  public static function register_settings(){
    register_setting('oa_settings_group','oa_settings',['type'=>'array','sanitize_callback'=>[__CLASS__,'sanitize'],'default'=>[]]);
  }
  public static function sanitize($in){
    $in=is_array($in)?$in:[];
    $out=get_option('oa_settings',[]);
    foreach(['enabled','strip_query','track_logged_in','respect_dnt','approx_uniques','auto_events','auto_outbound','auto_downloads','auto_tel','auto_mailto','auto_forms','email_reports','anomaly_alerts','keep_data_on_uninstall'] as $b){
      $out[$b]=empty($in[$b])?0:1;
    }
    $out['trust_proxy_headers']=empty($in['trust_proxy_headers'])?0:1;
    $out['sample_rate']=max(1,intval($in['sample_rate'] ?? 1));
    $out['rate_limit_per_min']=max(10,intval($in['rate_limit_per_min'] ?? 120));
    $out['retention_days']=max(30,intval($in['retention_days'] ?? 180));
    $out['utm_attribution_days']=max(1,min(365,intval($in['utm_attribution_days'] ?? 30)));
    $out['anomaly_threshold_pct']=max(10,min(90,intval($in['anomaly_threshold_pct'] ?? 35)));
    $out['anomaly_baseline_days']=max(3,min(30,intval($in['anomaly_baseline_days'] ?? 7)));
    $out['anomaly_min_views']=max(10,intval($in['anomaly_min_views'] ?? 60));
    $out['anomaly_min_conversions']=max(1,intval($in['anomaly_min_conversions'] ?? 5));
    $out['email_reports_freq']=in_array(($in['email_reports_freq'] ?? 'weekly'),['daily','weekly','monthly'],true)?$in['email_reports_freq']:'weekly';
    $email=sanitize_email($in['email_reports_to'] ?? get_option('admin_email'));
    if (!$email) {
      $email=sanitize_email(get_option('admin_email'));
      add_settings_error('oa_settings','oa_invalid_email',__('Invalid report email. Reverted to admin email.','ordelix-analytics'),'error');
    }
    $out['email_reports_to']=$email;
    $out['consent_mode']=in_array(($in['consent_mode'] ?? 'off'),['off','require','cmp'],true)?$in['consent_mode']:'off';
    $consent_cookie=sanitize_key($in['consent_cookie'] ?? 'oa_consent');
    $optout_cookie=sanitize_key($in['optout_cookie'] ?? 'oa_optout');
    $out['consent_cookie']=$consent_cookie ?: 'oa_consent';
    $out['optout_cookie']=$optout_cookie ?: 'oa_optout';
    return $out;
  }
  private static function range_inputs(){
    $now=current_time('timestamp');
    $default_to=wp_date('Y-m-d',$now);
    $default_from=wp_date('Y-m-d',$now-(27*DAY_IN_SECONDS));
    $from=isset($_GET['from'])?sanitize_text_field($_GET['from']):$default_from;
    $to=isset($_GET['to'])?sanitize_text_field($_GET['to']):$default_to;
    $range=isset($_GET['oa_range'])?sanitize_key($_GET['oa_range']):'';
    $presets=['7d'=>7,'30d'=>30,'90d'=>90];
    if (isset($presets[$range])) {
      $days=$presets[$range];
      $to=$default_to;
      $from=wp_date('Y-m-d',$now-(($days-1)*DAY_IN_SECONDS));
    } else {
      if (!wp_checkdate(substr($from,5,2), substr($from,8,2), substr($from,0,4), $from)) $from=$default_from;
      if (!wp_checkdate(substr($to,5,2), substr($to,8,2), substr($to,0,4), $to)) $to=$default_to;
      if (strtotime($from)>strtotime($to)) { $from=$default_from; $to=$default_to; }
    }
    ob_start();
    echo '<div class="oa-range"><form method="get" class="oa-range-form oa-range-primary">';
    foreach($_GET as $k=>$v){
      if($k==='from'||$k==='to'||$k==='oa_range') continue;
      if(is_array($v)) continue;
      echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr($v).'">';
    }
    echo '<div class="oa-presets oa-segment" role="group" aria-label="Date range presets">';
    foreach($presets as $k=>$days){
      $cls=$range===$k ? ' button-primary' : '';
      echo '<button class="button oa-segment-btn'.$cls.'" type="submit" name="oa_range" value="'.esc_attr($k).'">'.esc_html(strtoupper(rtrim($k,'d')).'D').'</button>';
    }
    echo '<button class="button oa-segment-btn'.($range==='' ? ' button-primary' : '').'" type="submit" name="oa_range" value="">Custom</button>';
    echo '</div>';
    echo '<div class="oa-range-dates">';
    echo '<label class="oa-date-inline"><span>From</span><input type="date" name="from" value="'.esc_attr($from).'"></label>';
    echo '<label class="oa-date-inline"><span>To</span><input type="date" name="to" value="'.esc_attr($to).'"></label>';
    echo '</div>';
    echo '<div class="oa-range-actions">';
    echo '<button class="button button-primary" type="submit">Apply</button>';
    echo '<button type="button" class="button oa-button-quiet" data-oa-copy-link>Copy link</button>';
    echo '</div></form></div>';
    $html=ob_get_clean();
    return [$from,$to,$html];
  }
  private static function sanitize_ymd($value,$fallback){
    $value=sanitize_text_field((string)$value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$value)) return $fallback;
    if (!wp_checkdate(substr($value,5,2), substr($value,8,2), substr($value,0,4), $value)) return $fallback;
    return $value;
  }
  private static function compliance_range_inputs($in_from,$in_to){
    $now=current_time('timestamp');
    $default_to=wp_date('Y-m-d',$now);
    $default_from=wp_date('Y-m-d',$now-(29*DAY_IN_SECONDS));
    $from=self::sanitize_ymd($in_from,$default_from);
    $to=self::sanitize_ymd($in_to,$default_to);
    if (strtotime($from)>strtotime($to)){ $tmp=$from; $from=$to; $to=$tmp; }
    return [$from,$to];
  }
  private static function filter_scope_fields($scope){
    $scopes=[
      'traffic'=>['device','path','event'],
      'goals'=>['path','event'],
      'funnels'=>['path','event'],
      'campaigns'=>['source','medium','campaign','path'],
      'coupons'=>['coupon'],
      'revenue'=>['coupon'],
    ];
    return $scopes[$scope] ?? [];
  }
  private static function get_segments_store(){
    $store=get_option('oa_saved_segments',[]);
    return is_array($store) ? $store : [];
  }
  private static function get_segments($scope){
    $store=self::get_segments_store();
    $rows=(isset($store[$scope]) && is_array($store[$scope])) ? $store[$scope] : [];
    $allowed=self::filter_scope_fields($scope);
    $out=[];
    foreach($rows as $row){
      if (!is_array($row)) continue;
      $id=sanitize_key($row['id'] ?? '');
      $name=sanitize_text_field($row['name'] ?? '');
      if ($id==='' || $name==='') continue;
      $filters=[];
      foreach($allowed as $k){
        $v=sanitize_text_field((string)(is_array($row['filters'] ?? null) ? ($row['filters'][$k] ?? '') : ''));
        if ($k==='device'){
          $v=sanitize_key($v);
          if (!in_array($v,['desktop','mobile','tablet','unknown'],true)) $v='';
        }
        $filters[$k]=$v;
      }
      $out[]=[
        'id'=>$id,
        'name'=>$name,
        'filters'=>$filters,
        'created_at'=>sanitize_text_field((string)($row['created_at'] ?? '')),
      ];
    }
    return $out;
  }
  private static function save_segments($scope,$segments){
    $store=self::get_segments_store();
    $store[$scope]=array_values((array)$segments);
    update_option('oa_saved_segments',$store,false);
  }
  private static function find_segment($segments,$id){
    foreach((array)$segments as $seg){
      if ((string)($seg['id'] ?? '')===$id) return $seg;
    }
    return null;
  }
  private static function segment_notice_message($code){
    $map=[
      'saved'=>'Saved view created.',
      'deleted'=>'Saved view deleted.',
      'name_required'=>'Provide a name to save this view.',
      'empty'=>'Set at least one filter before saving a view.',
      'not_found'=>'Saved view was not found.',
      'invalid'=>'Invalid saved-view action.',
    ];
    return $map[$code] ?? '';
  }
  private static function handle_segments_post($scope,$filters){
    if (empty($_POST['oa_segment_action'])) return;
    if (!self::can_manage()) return;
    $posted_scope=sanitize_key($_POST['oa_segment_scope'] ?? '');
    if ($posted_scope!==$scope) return;
    check_admin_referer('oa_segments_'.$scope);
    $action=sanitize_key($_POST['oa_segment_action']);
    $segments=self::get_segments($scope);
    $notice='invalid';
    if ($action==='save'){
      $name=sanitize_text_field($_POST['oa_segment_name'] ?? '');
      if ($name===''){
        $notice='name_required';
      } else {
        $has=false;
        foreach((array)$filters as $v){ if ((string)$v!==''){ $has=true; break; } }
        if (!$has){
          $notice='empty';
        } else {
          $segments[]=[
            'id'=>'seg_'.wp_generate_password(8,false,false),
            'name'=>$name,
            'filters'=>$filters,
            'created_at'=>current_time('mysql'),
          ];
          if (count($segments)>40) $segments=array_slice($segments,-40);
          self::save_segments($scope,$segments);
          $notice='saved';
        }
      }
    } elseif ($action==='delete'){
      $id=sanitize_key($_POST['oa_segment_delete_id'] ?? '');
      $before=count($segments);
      $segments=array_values(array_filter($segments,function($seg) use ($id){
        return (string)($seg['id'] ?? '')!==$id;
      }));
      if (count($segments)<$before){
        self::save_segments($scope,$segments);
        $notice='deleted';
      } else {
        $notice='not_found';
      }
    }

    $redirect_args=[];
    foreach($_GET as $k=>$v){
      if (is_array($v)) continue;
      if ($k==='oa_seg_notice') continue;
      if (!preg_match('/^[A-Za-z0-9_-]+$/',(string)$k)) continue;
      $redirect_args[$k]=sanitize_text_field(wp_unslash((string)$v));
    }
    $redirect_args['oa_seg_notice']=$notice;
    wp_safe_redirect(add_query_arg($redirect_args,admin_url('admin.php')));
    exit;
  }
  private static function filter_inputs($scope='traffic'){
    $allowed=self::filter_scope_fields($scope);
    $segments=self::get_segments($scope);
    $selected_segment=isset($_GET['oa_segment']) ? sanitize_key($_GET['oa_segment']) : '';
    $segment=(self::find_segment($segments,$selected_segment) ?: []);
    $segment_filters=is_array($segment['filters'] ?? null) ? $segment['filters'] : [];
    $filters=[];
    $active_count=0;
    foreach($allowed as $k){
      $qk='oa_'.$k;
      $v=(isset($_GET[$qk]) && !is_array($_GET[$qk])) ? sanitize_text_field(wp_unslash($_GET[$qk])) : (string)($segment_filters[$k] ?? '');
      if ($k==='device') $v=sanitize_key($v);
      $filters[$k]=$v;
      if ($v!=='') $active_count++;
    }
    self::handle_segments_post($scope,$filters);
    $segments=self::get_segments($scope);
    if (!$selected_segment || !self::find_segment($segments,$selected_segment)) $selected_segment='';
    $preserve_keys=['page','from','to','oa_range'];
    $hidden=[];
    foreach($preserve_keys as $k){
      if (isset($_GET[$k]) && !is_array($_GET[$k])) $hidden[$k]=sanitize_text_field(wp_unslash($_GET[$k]));
    }
    $reset_args=[];
    foreach($preserve_keys as $k){
      if (!isset($hidden[$k])) continue;
      if ($hidden[$k]==='') continue;
      $reset_args[$k]=$hidden[$k];
    }
    $reset_url=add_query_arg($reset_args,admin_url('admin.php'));
    $open=$active_count>0 ? ' open' : '';
    $active_label=$active_count>0 ? ($active_count.' active') : 'No active filters';
    $notice_code=isset($_GET['oa_seg_notice']) ? sanitize_key($_GET['oa_seg_notice']) : '';
    $notice_text=self::segment_notice_message($notice_code);
    ob_start();
    echo '<div class="oa-filter-strip"><details class="oa-filter-panel"'.$open.'>';
    echo '<summary><span>Advanced filters</span><span class="oa-filter-meta">'.esc_html($active_label).'</span></summary>';
    if ($notice_text!=='') echo '<p class="oa-filter-notice">'.esc_html($notice_text).'</p>';
    echo '<form method="get" class="oa-range-form oa-filter-form">';
    foreach($hidden as $k=>$v) echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr($v).'">';
    if (!empty($segments)){
      echo '<label class="oa-segment-row">Saved view <select name="oa_segment"><option value="">None</option>';
      foreach($segments as $seg){
        echo '<option value="'.esc_attr($seg['id']).'"'.selected($selected_segment,$seg['id'],false).'>'.esc_html($seg['name']).'</option>';
      }
      echo '</select></label>';
    }
    if (in_array('device',$allowed,true)){
      $dv=(string)($filters['device'] ?? '');
      echo '<label>Device <select name="oa_device">';
      foreach([''=>'All','desktop'=>'Desktop','mobile'=>'Mobile','tablet'=>'Tablet','unknown'=>'Unknown'] as $k=>$label){
        echo '<option value="'.esc_attr($k).'"'.selected($dv,$k,false).'>'.esc_html($label).'</option>';
      }
      echo '</select></label>';
    }
    if (in_array('source',$allowed,true)) echo '<label>Source <input type="text" name="oa_source" value="'.esc_attr($filters['source'] ?? '').'" placeholder="google"></label>';
    if (in_array('medium',$allowed,true)) echo '<label>Medium <input type="text" name="oa_medium" value="'.esc_attr($filters['medium'] ?? '').'" placeholder="cpc"></label>';
    if (in_array('campaign',$allowed,true)) echo '<label>Campaign <input type="text" name="oa_campaign" value="'.esc_attr($filters['campaign'] ?? '').'" placeholder="spring-sale"></label>';
    if (in_array('path',$allowed,true)) echo '<label>Path contains <input type="text" name="oa_path" value="'.esc_attr($filters['path'] ?? '').'" placeholder="/pricing"></label>';
    if (in_array('event',$allowed,true)) echo '<label>Event name <input type="text" name="oa_event" value="'.esc_attr($filters['event'] ?? '').'" placeholder="form_submit"></label>';
    if (in_array('coupon',$allowed,true)) echo '<label>Coupon contains <input type="text" name="oa_coupon" value="'.esc_attr($filters['coupon'] ?? '').'" placeholder="SUMMER"></label>';
    echo '<button class="button">Apply filters</button>';
    echo '<a class="button" href="'.esc_url($reset_url).'">Reset filters</a>';
    echo '</form></details></div>';
    if (self::can_manage()){
      echo '<div class="oa-segment-admin">';
      echo '<form method="post" class="oa-segment-admin-form">';
      wp_nonce_field('oa_segments_'.$scope);
      echo '<input type="hidden" name="oa_segment_action" value="save">';
      echo '<input type="hidden" name="oa_segment_scope" value="'.esc_attr($scope).'">';
      echo '<label>Save current as <input type="text" name="oa_segment_name" placeholder="My saved view"></label>';
      echo '<button class="button">Save view</button>';
      echo '</form>';
      if (!empty($segments)){
        echo '<form method="post" class="oa-segment-admin-form">';
        wp_nonce_field('oa_segments_'.$scope);
        echo '<input type="hidden" name="oa_segment_action" value="delete">';
        echo '<input type="hidden" name="oa_segment_scope" value="'.esc_attr($scope).'">';
        echo '<label>Delete view <select name="oa_segment_delete_id">';
        foreach($segments as $seg){
          echo '<option value="'.esc_attr($seg['id']).'">'.esc_html($seg['name']).'</option>';
        }
        echo '</select></label>';
        echo '<button class="button" onclick="return confirm(\'Delete this saved view?\');">Delete</button>';
        echo '</form>';
      }
      echo '</div>';
    }
    return [$filters,ob_get_clean()];
  }
  public static function page_dashboard(){
    if(!self::can_view()) wp_die('Nope');
    $can_manage=self::can_manage();
    list($from,$to,$range_html)=self::range_inputs();
    list($filters,$filters_html)=self::filter_inputs('traffic');
    $data=OA_Reports::dashboard($from,$to,$filters);
    include OA_PLUGIN_DIR.'includes/views/dashboard.php';
  }
  public static function page_anomalies(){
    if(!self::can_view()) wp_die('Nope');
    $can_manage=self::can_manage();
    $day=isset($_GET['day'])?sanitize_text_field($_GET['day']):wp_date('Y-m-d', current_time('timestamp')-DAY_IN_SECONDS);
    $detail=OA_Reports::anomaly_drilldown($day);
    include OA_PLUGIN_DIR.'includes/views/anomalies.php';
  }
  public static function page_goals(){
    if(!self::can_view()) wp_die('Nope');
    $can_manage=self::can_manage();
    OA_Reports::handle_goals_post();
    list($from,$to,$range_html)=self::range_inputs();
    list($filters,$filters_html)=self::filter_inputs('goals');
    $stats=OA_Reports::goals_stats($from,$to,$filters);
    include OA_PLUGIN_DIR.'includes/views/goals.php';
  }
  public static function page_funnels(){
    if(!self::can_view()) wp_die('Nope');
    $can_manage=self::can_manage();
    OA_Reports::handle_funnels_post();
    list($from,$to,$range_html)=self::range_inputs();
    list($filters,$filters_html)=self::filter_inputs('funnels');
    $funnels=OA_Reports::get_funnels_with_steps($filters);
    $stats=OA_Reports::funnels_stats($from,$to,50,$filters);
    include OA_PLUGIN_DIR.'includes/views/funnels.php';
  }
  public static function page_campaigns(){
    if(!self::can_view()) wp_die('Nope');
    $can_manage=self::can_manage();
    list($from,$to,$range_html)=self::range_inputs();
    list($filters,$filters_html)=self::filter_inputs('campaigns');
    $rows=OA_Reports::campaigns($from,$to,$filters);
    include OA_PLUGIN_DIR.'includes/views/campaigns.php';
  }
  public static function page_coupons(){
    if(!self::can_view()) wp_die('Nope');
    $can_manage=self::can_manage();
    list($from,$to,$range_html)=self::range_inputs();
    list($filters,$filters_html)=self::filter_inputs('coupons');
    $rows=OA_Reports::coupons($from,$to,$filters);
    $daily=OA_Reports::coupons_daily($from,$to,$filters);
    include OA_PLUGIN_DIR.'includes/views/coupons.php';
  }
  public static function page_revenue(){
    if(!self::can_view()) wp_die('Nope');
    $can_manage=self::can_manage();
    list($from,$to,$range_html)=self::range_inputs();
    list($filters,$filters_html)=self::filter_inputs('revenue');
    $rows=OA_Reports::revenue($from,$to,$filters);
    include OA_PLUGIN_DIR.'includes/views/revenue.php';
  }
  public static function page_health(){
    if(!self::can_view()) wp_die('Nope');
    $can_manage=self::can_manage();
    $health_notice='';
    $health_self_test=[];
    if ($can_manage && !empty($_POST['oa_health_action'])) {
      check_admin_referer('oa_health');
      $action=sanitize_key($_POST['oa_health_action']);
      if ($action==='run_maintenance'){
        OA_Reports::run_maintenance_now();
        $health_notice='Maintenance run completed.';
      } elseif ($action==='flush_cache'){
        $n=OA_Reports::flush_dashboard_cache();
        $health_notice='Dashboard cache cleared ('.$n.' option row(s) removed).';
      } elseif ($action==='repair_schema'){
        OA_DB::install_or_upgrade('repair');
        $health_notice='Schema repair completed (dbDelta run executed).';
      } elseif ($action==='reschedule_cron'){
        OA_Reports::reschedule_cron_now();
        $health_notice='Daily cron rescheduled.';
      } elseif ($action==='repair_caps'){
        self::ensure_caps();
        $health_notice='Capability matrix repaired.';
      } elseif ($action==='export_diagnostics'){
        $payload=OA_Reports::diagnostics_payload();
        $stamp=wp_date('Ymd_His', current_time('timestamp'));
        $fname='ordelix_diagnostics_'.$stamp.'.json';
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$fname.'"');
        echo wp_json_encode($payload, JSON_PRETTY_PRINT);
        exit;
      } elseif ($action==='self_test'){
        $strict=!empty($_POST['strict']);
        $health_self_test=OA_Reports::health_test_suite($strict);
        $summary=(array)($health_self_test['summary'] ?? []);
        $health_notice='Self-test complete: '.intval($summary['passed'] ?? 0).' passed, '.intval($summary['failed'] ?? 0).' failed, '.intval($summary['warned'] ?? 0).' warned.';
      }
    }
    $health=OA_Reports::health_snapshot();
    include OA_PLUGIN_DIR.'includes/views/health.php';
  }
  public static function page_settings(){
    if(!self::can_manage()) wp_die('Nope');
    $compliance_notice='';
    $compliance_error='';
    list($compliance_from,$compliance_to)=self::compliance_range_inputs($_POST['oa_compliance_from'] ?? '',$_POST['oa_compliance_to'] ?? '');
    if (!empty($_POST['oa_compliance_action'])) {
      check_admin_referer('oa_compliance_tools');
      $action=sanitize_key($_POST['oa_compliance_action']);
      if ($action==='export_bundle'){
        $payload=OA_Reports::compliance_export_bundle($compliance_from,$compliance_to);
        $stamp=wp_date('Ymd_His', current_time('timestamp'));
        $fname='ordelix_compliance_export_'.$compliance_from.'_to_'.$compliance_to.'_'.$stamp.'.json';
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$fname.'"');
        echo wp_json_encode($payload, JSON_PRETTY_PRINT);
        exit;
      } elseif ($action==='erase_range'){
        $res=OA_Reports::erase_data_range($compliance_from,$compliance_to);
        $compliance_notice='Analytics rows deleted for '.$compliance_from.' -> '.$compliance_to.' ('.$res['total'].' row(s)).';
      } elseif ($action==='erase_all'){
        $confirm=sanitize_text_field((string)($_POST['oa_confirm'] ?? ''));
        if ($confirm!=='ERASE ALL'){
          $compliance_error='Type "ERASE ALL" exactly before running full analytics data erase.';
        } else {
          $res=OA_Reports::erase_all_analytics_data();
          $compliance_notice='All analytics daily rows removed ('.$res['total'].' row(s)).';
        }
      } else {
        $compliance_error='Unknown compliance action.';
      }
    }
    $opt=get_option('oa_settings',[]);
    include OA_PLUGIN_DIR.'includes/views/settings.php';
  }
}
