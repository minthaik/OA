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
    add_submenu_page('ordelix-analytics','Retention','Retention','ordelix_analytics_view','ordelix-analytics-retention',[__CLASS__,'page_retention']);
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
    foreach(['enabled','strip_query','track_logged_in','respect_dnt','approx_uniques','auto_events','auto_outbound','auto_downloads','auto_tel','auto_mailto','auto_forms','retention_signals','email_reports','anomaly_alerts','keep_data_on_uninstall'] as $b){
      $out[$b]=empty($in[$b])?0:1;
    }
    $out['trust_proxy_headers']=empty($in['trust_proxy_headers'])?0:1;
    $out['sample_rate']=max(1,intval($in['sample_rate'] ?? 1));
    $out['rate_limit_per_min']=max(10,intval($in['rate_limit_per_min'] ?? 120));
    $out['retention_days']=max(30,intval($in['retention_days'] ?? 180));
    $out['utm_attribution_days']=max(1,min(365,intval($in['utm_attribution_days'] ?? 30)));
    $attr_mode=sanitize_key((string)($in['attribution_mode'] ?? 'first_touch'));
    $out['attribution_mode']=in_array($attr_mode,['first_touch','last_touch'],true)?$attr_mode:'first_touch';
    $out['anomaly_threshold_pct']=max(10,min(90,intval($in['anomaly_threshold_pct'] ?? 35)));
    $out['anomaly_baseline_days']=max(3,min(30,intval($in['anomaly_baseline_days'] ?? 7)));
    $out['anomaly_min_views']=max(10,intval($in['anomaly_min_views'] ?? 60));
    $out['anomaly_min_conversions']=max(1,intval($in['anomaly_min_conversions'] ?? 5));
    $freq=sanitize_key((string)($in['email_reports_freq'] ?? 'weekly'));
    $out['email_reports_freq']=in_array($freq,['daily','weekly','monthly'],true)?$freq:'weekly';
    $email=sanitize_email($in['email_reports_to'] ?? get_option('admin_email'));
    if (!$email) {
      $email=sanitize_email(get_option('admin_email'));
      add_settings_error('oa_settings','oa_invalid_email',__('Invalid report email. Reverted to admin email.','ordelix-analytics'),'error');
    }
    $out['email_reports_to']=$email;
    $consent_mode=sanitize_key((string)($in['consent_mode'] ?? 'off'));
    $out['consent_mode']=in_array($consent_mode,['off','require','cmp'],true)?$consent_mode:'off';
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
    echo '<div class="oa-range-main">';
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
    echo '</div>';
    echo '</div>';
    echo '<div class="oa-range-advanced-wrap"><span class="oa-advanced-slot" data-oa-advanced-slot></span></div>';
    echo '</form></div>';
    $html=ob_get_clean();
    return [$from,$to,$html];
  }
  private static function sanitize_ymd($value,$fallback){
    $value=sanitize_text_field((string)$value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$value)) return $fallback;
    if (!wp_checkdate(substr($value,5,2), substr($value,8,2), substr($value,0,4), $value)) return $fallback;
    return $value;
  }
  private static function json_download($filename,$payload,$redirect_url=''){
    $json=wp_json_encode($payload, JSON_PRETTY_PRINT);
    if (!is_string($json) || $json==='') $json='{}';
    $redirect_url=esc_url_raw((string)$redirect_url);
    if ($redirect_url===''){
      $redirect_url=wp_get_referer();
      if (!$redirect_url) $redirect_url=admin_url('admin.php');
    }
    if (!headers_sent()){
      nocache_headers();
      header('Content-Type: application/json; charset=utf-8');
      header('Content-Disposition: attachment; filename="'.$filename.'"');
      header('Content-Length: '.strlen($json));
      echo $json;
      exit;
    }
    // Fallback for environments that send output early in admin rendering.
    echo '<script>(function(){try{var text='
      .wp_json_encode($json)
      .';var blob=new Blob([text],{type:"application/json;charset=utf-8"});'
      .'var a=document.createElement("a");a.href=URL.createObjectURL(blob);'
      .'a.download='.wp_json_encode($filename).';document.body.appendChild(a);a.click();'
      .'setTimeout(function(){URL.revokeObjectURL(a.href);a.remove();'
      .'window.location.replace('.wp_json_encode($redirect_url).');},260);}catch(e){'
      .'window.location.replace('.wp_json_encode($redirect_url).');}})();</script>';
    echo '<noscript><p>Download generated.</p><p><a class="button" href="'.esc_url($redirect_url).'">Return</a></p><pre>'.esc_html($json).'</pre></noscript>';
    exit;
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
  private static function get_default_segments_store(){
    $uid=intval(get_current_user_id());
    if ($uid<=0) return [];
    $store=get_user_meta($uid,'oa_default_segments',true);
    return is_array($store) ? $store : [];
  }
  private static function get_default_segment_id($scope){
    $store=self::get_default_segments_store();
    return sanitize_key((string)($store[$scope] ?? ''));
  }
  private static function set_default_segment_id($scope,$segment_id){
    $uid=intval(get_current_user_id());
    if ($uid<=0) return;
    $store=self::get_default_segments_store();
    if ($segment_id===''){
      unset($store[$scope]);
    } else {
      $store[$scope]=$segment_id;
    }
    update_user_meta($uid,'oa_default_segments',$store);
  }
  private static function get_segments($scope){
    $store=self::get_segments_store();
    $rows=(isset($store[$scope]) && is_array($store[$scope])) ? $store[$scope] : [];
    $allowed=self::filter_scope_fields($scope);
    $uid=intval(get_current_user_id());
    $is_admin=current_user_can('manage_options');
    $out=[];
    foreach($rows as $row){
      if (!is_array($row)) continue;
      $id=sanitize_key($row['id'] ?? '');
      $name=sanitize_text_field($row['name'] ?? '');
      if ($id==='' || $name==='') continue;
      $visibility=in_array(($row['visibility'] ?? 'shared'),['shared','private'],true) ? $row['visibility'] : 'shared';
      $owner_id=max(0,intval($row['owner_id'] ?? 0));
      if ($visibility==='private' && ($owner_id<=0 || ($owner_id!==$uid && !$is_admin))) continue;
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
        'visibility'=>$visibility,
        'owner_id'=>$owner_id,
        'created_at'=>sanitize_text_field((string)($row['created_at'] ?? '')),
        'last_used'=>sanitize_text_field((string)($row['last_used'] ?? '')),
        'usage_count'=>max(0,intval($row['usage_count'] ?? 0)),
      ];
    }
    return $out;
  }
  private static function save_segments($scope,$segments){
    $store=self::get_segments_store();
    $store[$scope]=array_values((array)$segments);
    update_option('oa_saved_segments',$store,false);
  }
  private static function normalized_segments_store($store,$override_owner_id=0){
    $out=[];
    $seen=[];
    foreach(['traffic','goals','funnels','campaigns','coupons','revenue'] as $scope){
      $allowed=self::filter_scope_fields($scope);
      $rows=(isset($store[$scope]) && is_array($store[$scope])) ? $store[$scope] : [];
      $out[$scope]=[];
      foreach($rows as $row){
        if (!is_array($row)) continue;
        $name=sanitize_text_field((string)($row['name'] ?? ''));
        if ($name==='') continue;
        $filters=[];
        $has=false;
        foreach($allowed as $k){
          $v=sanitize_text_field((string)(is_array($row['filters'] ?? null) ? ($row['filters'][$k] ?? '') : ''));
          if ($k==='device'){
            $v=sanitize_key($v);
            if (!in_array($v,['desktop','mobile','tablet','unknown'],true)) $v='';
          }
          if ($v!=='') $has=true;
          $filters[$k]=$v;
        }
        if (!$has) continue;
        $id=sanitize_key((string)($row['id'] ?? ''));
        if ($id==='') $id='seg_'.wp_generate_password(8,false,false);
        while(isset($seen[$scope.'|'.$id])) $id='seg_'.wp_generate_password(8,false,false);
        $seen[$scope.'|'.$id]=1;
        $visibility=sanitize_key((string)($row['visibility'] ?? 'shared'));
        if (!in_array($visibility,['shared','private'],true)) $visibility='shared';
        $owner_id=max(0,intval($row['owner_id'] ?? 0));
        if ($override_owner_id>0) $owner_id=$override_owner_id;
        if ($owner_id<=0) $owner_id=max(1,intval(get_current_user_id()));
        $out[$scope][]=[
          'id'=>$id,
          'name'=>$name,
          'filters'=>$filters,
          'visibility'=>$visibility,
          'owner_id'=>$owner_id,
          'created_at'=>sanitize_text_field((string)($row['created_at'] ?? current_time('mysql'))),
          'last_used'=>sanitize_text_field((string)($row['last_used'] ?? '')),
          'usage_count'=>max(0,intval($row['usage_count'] ?? 0)),
        ];
      }
      if (count($out[$scope])>120) $out[$scope]=array_slice($out[$scope],-120);
    }
    return $out;
  }
  private static function touch_segment_usage($scope,$segment_id){
    if ($segment_id==='') return false;
    $store=self::get_segments_store();
    if (empty($store[$scope]) || !is_array($store[$scope])) return false;
    $rows=$store[$scope];
    $changed=false;
    foreach($rows as $i=>$row){
      if (!is_array($row)) continue;
      $id=sanitize_key((string)($row['id'] ?? ''));
      if ($id!==$segment_id) continue;
      $rows[$i]['last_used']=current_time('mysql');
      $rows[$i]['usage_count']=max(0,intval($row['usage_count'] ?? 0))+1;
      $changed=true;
      break;
    }
    if (!$changed) return false;
    $store[$scope]=$rows;
    update_option('oa_saved_segments',$store,false);
    return true;
  }
  private static function quick_segments($segments,$limit=6){
    $rows=array_values((array)$segments);
    usort($rows,function($a,$b){
      $a_count=max(0,intval($a['usage_count'] ?? 0));
      $b_count=max(0,intval($b['usage_count'] ?? 0));
      if ($a_count!==$b_count) return ($b_count <=> $a_count);
      $a_last=strtotime((string)($a['last_used'] ?? '')) ?: 0;
      $b_last=strtotime((string)($b['last_used'] ?? '')) ?: 0;
      if ($a_last!==$b_last) return ($b_last <=> $a_last);
      $a_created=strtotime((string)($a['created_at'] ?? '')) ?: 0;
      $b_created=strtotime((string)($b['created_at'] ?? '')) ?: 0;
      return ($b_created <=> $a_created);
    });
    return array_slice($rows,0,max(1,intval($limit)));
  }
  private static function count_segments_store($store){
    $n=0;
    foreach((array)$store as $scope=>$rows){
      if (!is_array($rows)) continue;
      $n+=count($rows);
    }
    return $n;
  }
  private static function merge_segments_store($base,$add){
    $merged=[];
    foreach(['traffic','goals','funnels','campaigns','coupons','revenue'] as $scope){
      $a=(isset($base[$scope]) && is_array($base[$scope])) ? $base[$scope] : [];
      $b=(isset($add[$scope]) && is_array($add[$scope])) ? $add[$scope] : [];
      $merged[$scope]=array_merge($a,$b);
    }
    return self::normalized_segments_store($merged,0);
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
      'default_set'=>'Default view saved.',
      'default_cleared'=>'Default view cleared.',
      'name_required'=>'Provide a name to save this view.',
      'empty'=>'Set at least one filter before saving a view.',
      'not_found'=>'Saved view was not found.',
      'forbidden'=>'You cannot modify this saved view.',
      'invalid'=>'Invalid saved-view action.',
    ];
    return $map[$code] ?? '';
  }
  private static function can_edit_segment($segment){
    if (!self::can_manage()) return false;
    if (current_user_can('manage_options')) return true;
    $owner_id=max(0,intval($segment['owner_id'] ?? 0));
    return ($owner_id>0 && $owner_id===intval(get_current_user_id()));
  }
  private static function handle_segments_post($scope,$filters){
    if (empty($_POST['oa_segment_action'])) return;
    $posted_scope=sanitize_key($_POST['oa_segment_scope'] ?? '');
    if ($posted_scope!==$scope) return;
    check_admin_referer('oa_segments_'.$scope);
    $action=sanitize_key($_POST['oa_segment_action']);
    $segments=self::get_segments($scope);
    $notice='invalid';
    if ($action==='save'){
      if (!self::can_manage()) return;
      $name=sanitize_text_field($_POST['oa_segment_name'] ?? '');
      if ($name===''){
        $notice='name_required';
      } else {
        $has=false;
        foreach((array)$filters as $v){ if ((string)$v!==''){ $has=true; break; } }
        if (!$has){
          $notice='empty';
        } else {
          $visibility=in_array(($_POST['oa_segment_visibility'] ?? 'private'),['private','shared'],true) ? $_POST['oa_segment_visibility'] : 'private';
          $segments[]=[
            'id'=>'seg_'.wp_generate_password(8,false,false),
            'name'=>$name,
            'filters'=>$filters,
            'visibility'=>$visibility,
            'owner_id'=>intval(get_current_user_id()),
            'created_at'=>current_time('mysql'),
            'last_used'=>'',
            'usage_count'=>0,
          ];
          if (count($segments)>40) $segments=array_slice($segments,-40);
          self::save_segments($scope,$segments);
          $notice='saved';
        }
      }
    } elseif ($action==='delete'){
      if (!self::can_manage()) return;
      $id=sanitize_key($_POST['oa_segment_delete_id'] ?? '');
      $segment=self::find_segment($segments,$id);
      if (!$segment){
        $notice='not_found';
      } elseif (!self::can_edit_segment($segment)){
        $notice='forbidden';
      } else {
        $segments=array_values(array_filter($segments,function($seg) use ($id){
          return (string)($seg['id'] ?? '')!==$id;
        }));
        self::save_segments($scope,$segments);
        $notice='deleted';
      }
    } elseif ($action==='set_default'){
      if (!self::can_view()) return;
      $id=sanitize_key($_POST['oa_segment_default_id'] ?? '');
      if ($id!=='' && !self::find_segment($segments,$id)){
        $notice='not_found';
      } else {
        self::set_default_segment_id($scope,$id);
        $notice=($id==='' ? 'default_cleared' : 'default_set');
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
    $redirect_url=add_query_arg($redirect_args,admin_url('admin.php'));
    if (!headers_sent()){
      wp_safe_redirect($redirect_url);
      exit;
    }
    // Fallback when output has started (some admin stacks print early styles/notices).
    echo '<script>window.location.replace('.wp_json_encode($redirect_url).');</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url='.esc_url($redirect_url).'"></noscript>';
    exit;
  }
  private static function filter_inputs($scope='traffic'){
    $allowed=self::filter_scope_fields($scope);
    $segments=self::get_segments($scope);
    $explicit=false;
    foreach($allowed as $k){
      $qk='oa_'.$k;
      if (isset($_GET[$qk]) && !is_array($_GET[$qk])) { $explicit=true; break; }
    }
    $has_segment_query=(isset($_GET['oa_segment']) && !is_array($_GET['oa_segment']));
    $selected_segment=isset($_GET['oa_segment']) ? sanitize_key($_GET['oa_segment']) : '';
    if ($selected_segment==='' && !$explicit && !$has_segment_query){
      $selected_segment=self::get_default_segment_id($scope);
    }
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
    $default_segment_id=self::get_default_segment_id($scope);
    if ($default_segment_id!=='' && !self::find_segment($segments,$default_segment_id)) $default_segment_id='';
    if ($selected_segment!=='' && self::touch_segment_usage($scope,$selected_segment)){
      $segments=self::get_segments($scope);
    }
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
    $quick_segments=self::quick_segments($segments,6);
    ob_start();
    echo '<div class="oa-filter-strip" data-oa-advanced-panel>';
    if (!empty($quick_segments)){
      echo '<div class="oa-quick-segments" role="group" aria-label="Quick saved views">';
      foreach($quick_segments as $seg){
        $quick_args=$reset_args;
        $quick_args['oa_segment']=(string)$seg['id'];
        $quick_url=add_query_arg($quick_args,admin_url('admin.php'));
        $classes='oa-quick-segment';
        if ($selected_segment===(string)$seg['id']) $classes.=' is-active';
        if ($default_segment_id===(string)$seg['id']) $classes.=' is-default';
        echo '<a class="'.esc_attr($classes).'" href="'.esc_url($quick_url).'">'.esc_html($seg['name']).'</a>';
      }
      if ($selected_segment!==''){
        echo '<a class="oa-quick-segment oa-quick-segment-clear" href="'.esc_url($reset_url).'">Clear</a>';
      }
      echo '</div>';
    }
    echo '<details class="oa-filter-panel"'.$open.'>';
    echo '<summary><span>Advanced filters</span><span class="oa-filter-meta">'.esc_html($active_label).'</span></summary>';
    if ($notice_text!=='') echo '<p class="oa-filter-notice">'.esc_html($notice_text).'</p>';
    echo '<form method="get" class="oa-range-form oa-filter-form">';
    foreach($hidden as $k=>$v) echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr($v).'">';
    if (!empty($segments)){
      echo '<label class="oa-segment-row">Saved view <select name="oa_segment"><option value="">None</option>';
      foreach($segments as $seg){
        $tag=(($seg['visibility'] ?? 'shared')==='private') ? ' (Private)' : ' (Shared)';
        echo '<option value="'.esc_attr($seg['id']).'"'.selected($selected_segment,$seg['id'],false).'>'.esc_html($seg['name'].$tag).'</option>';
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
    $can_default=(self::can_view() && !empty($segments));
    $can_manage=self::can_manage();
    if ($can_default || $can_manage){
      echo '<div class="oa-segment-strip" data-oa-save-panel>';
      echo '<details class="oa-save-panel">';
      echo '<summary><span>Save view</span></summary>';
      echo '<div class="oa-save-panel-body">';
      if ($can_default){
        echo '<div class="oa-segment-admin">';
        echo '<form method="post" class="oa-segment-admin-form">';
        wp_nonce_field('oa_segments_'.$scope);
        echo '<input type="hidden" name="oa_segment_action" value="set_default">';
        echo '<input type="hidden" name="oa_segment_scope" value="'.esc_attr($scope).'">';
        echo '<label>Default view <select name="oa_segment_default_id"><option value="">None</option>';
        foreach($segments as $seg){
          $tag=(($seg['visibility'] ?? 'shared')==='private') ? ' (Private)' : ' (Shared)';
          echo '<option value="'.esc_attr($seg['id']).'"'.selected($default_segment_id,$seg['id'],false).'>'.esc_html($seg['name'].$tag).'</option>';
        }
        echo '</select></label>';
        echo '<button class="button">Save default</button>';
        echo '</form>';
        echo '</div>';
      }
      if ($can_manage){
        echo '<div class="oa-segment-admin">';
        echo '<form method="post" class="oa-segment-admin-form">';
        wp_nonce_field('oa_segments_'.$scope);
        echo '<input type="hidden" name="oa_segment_action" value="save">';
        echo '<input type="hidden" name="oa_segment_scope" value="'.esc_attr($scope).'">';
        echo '<label>Save current as <input type="text" name="oa_segment_name" placeholder="My saved view"></label>';
        echo '<label>Visibility <select name="oa_segment_visibility"><option value="private">Private</option><option value="shared">Shared</option></select></label>';
        echo '<button class="button">Save view</button>';
        echo '</form>';
        $editable=array_values(array_filter($segments,function($seg){ return self::can_edit_segment($seg); }));
        if (!empty($editable)){
          echo '<form method="post" class="oa-segment-admin-form">';
          wp_nonce_field('oa_segments_'.$scope);
          echo '<input type="hidden" name="oa_segment_action" value="delete">';
          echo '<input type="hidden" name="oa_segment_scope" value="'.esc_attr($scope).'">';
          echo '<label>Delete view <select name="oa_segment_delete_id">';
          foreach($editable as $seg){
            $tag=(($seg['visibility'] ?? 'shared')==='private') ? ' (Private)' : ' (Shared)';
            echo '<option value="'.esc_attr($seg['id']).'">'.esc_html($seg['name'].$tag).'</option>';
          }
          echo '</select></label>';
          echo '<button class="button" onclick="return confirm(\'Delete this saved view?\');">Delete</button>';
          echo '</form>';
        }
        echo '</div>';
      }
      echo '</div>';
      echo '</details>';
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
    $diagnostics=OA_Reports::funnels_diagnostics($from,$to,$filters,50);
    include OA_PLUGIN_DIR.'includes/views/funnels.php';
  }
  public static function page_retention(){
    if(!self::can_view()) wp_die('Nope');
    $can_manage=self::can_manage();
    list($from,$to,$range_html)=self::range_inputs();
    $retention=OA_Reports::retention_stats($from,$to);
    include OA_PLUGIN_DIR.'includes/views/retention.php';
  }
  public static function page_campaigns(){
    if(!self::can_view()) wp_die('Nope');
    $can_manage=self::can_manage();
    list($from,$to,$range_html)=self::range_inputs();
    list($filters,$filters_html)=self::filter_inputs('campaigns');
    $opt=get_option('oa_settings',[]);
    $attribution_mode=sanitize_key((string)($opt['attribution_mode'] ?? 'first_touch'));
    if (!in_array($attribution_mode,['first_touch','last_touch'],true)) $attribution_mode='first_touch';
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
        self::json_download($fname,$payload,admin_url('admin.php?page=ordelix-analytics-health'));
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
    $segments_notice='';
    $segments_error='';
    $segments_json_input='';
    list($compliance_from,$compliance_to)=self::compliance_range_inputs($_POST['oa_compliance_from'] ?? '',$_POST['oa_compliance_to'] ?? '');
    if (!empty($_POST['oa_compliance_action'])) {
      check_admin_referer('oa_compliance_tools');
      $action=sanitize_key($_POST['oa_compliance_action']);
      if ($action==='export_bundle'){
        $payload=OA_Reports::compliance_export_bundle($compliance_from,$compliance_to);
        $stamp=wp_date('Ymd_His', current_time('timestamp'));
        $fname='ordelix_compliance_export_'.$compliance_from.'_to_'.$compliance_to.'_'.$stamp.'.json';
        self::json_download($fname,$payload,admin_url('admin.php?page=ordelix-analytics-settings'));
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
    if (!empty($_POST['oa_segments_tools_action'])) {
      check_admin_referer('oa_segments_tools');
      $action=sanitize_key($_POST['oa_segments_tools_action']);
      if ($action==='export_segments'){
        $payload=[
          'version'=>OA_VERSION,
          'exported_at'=>current_time('mysql'),
          'segments'=>self::normalized_segments_store(self::get_segments_store(),0),
        ];
        $stamp=wp_date('Ymd_His', current_time('timestamp'));
        $fname='ordelix_segments_export_'.$stamp.'.json';
        self::json_download($fname,$payload,admin_url('admin.php?page=ordelix-analytics-settings'));
      } elseif ($action==='import_merge' || $action==='import_replace'){
        $segments_json_input=(string)wp_unslash($_POST['oa_segments_json'] ?? '');
        $decoded=json_decode($segments_json_input,true);
        if (!is_array($decoded)){
          $segments_error='Invalid JSON payload for segments import.';
        } else {
          $source=(isset($decoded['segments']) && is_array($decoded['segments'])) ? $decoded['segments'] : $decoded;
          $import=self::normalized_segments_store($source,intval(get_current_user_id()));
          $import_count=self::count_segments_store($import);
          if ($import_count===0){
            $segments_error='No valid segments found in imported JSON.';
          } else {
            if ($action==='import_merge'){
              $base=self::normalized_segments_store(self::get_segments_store(),0);
              $final=self::merge_segments_store($base,$import);
              $segments_notice='Segments merged successfully ('.self::count_segments_store($final).' total saved views).';
            } else {
              $final=$import;
              $segments_notice='Segments replaced successfully ('.self::count_segments_store($final).' total saved views).';
            }
            update_option('oa_saved_segments',$final,false);
            $segments_json_input='';
          }
        }
      } else {
        $segments_error='Unknown segments-tools action.';
      }
    }
    $opt=get_option('oa_settings',[]);
    include OA_PLUGIN_DIR.'includes/views/settings.php';
  }
}
