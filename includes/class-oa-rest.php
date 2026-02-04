<?php
if (!defined('ABSPATH')) exit;
class OA_REST {
  public static function init(){ add_action('rest_api_init',[__CLASS__,'routes']); }
  public static function routes(){
    register_rest_route('ordelix-analytics/v1','/collect',[
      'methods'=>'POST','permission_callback'=>'__return_true','callback'=>[__CLASS__,'collect']
    ]);
    register_rest_route('ordelix-analytics/v1','/export',[
      'methods'=>'GET','permission_callback'=>[__CLASS__,'can_view'],'callback'=>[__CLASS__,'export']
    ]);
    register_rest_route('ordelix-analytics/v1','/health',[
      'methods'=>'GET','permission_callback'=>[__CLASS__,'can_view'],'callback'=>[__CLASS__,'health']
    ]);
    register_rest_route('ordelix-analytics/v1','/diagnostics',[
      'methods'=>'GET','permission_callback'=>[__CLASS__,'can_view'],'callback'=>[__CLASS__,'diagnostics']
    ]);
  }
  public static function can_view(){
    return current_user_can('ordelix_analytics_view') || current_user_can('ordelix_analytics_manage');
  }
  public static function can_manage(){ return current_user_can('ordelix_analytics_manage'); }

  public static function collect(WP_REST_Request $req){
    OA_DB::upgrade_if_needed();
    $opt=get_option('oa_settings',[]);
    if (empty($opt['enabled'])) return new WP_REST_Response(['ok'=>false,'disabled'=>true],200);
    if (!OA_Util::can_track() || !OA_Util::sampled_in()) return new WP_REST_Response(['ok'=>true,'skipped'=>true],200);
    $rl=intval($opt['rate_limit_per_min'] ?? 120);
    if (!OA_Util::rate_limit_ok('collect',$rl)) return new WP_REST_Response(['ok'=>false,'rate_limited'=>true],429);

    $d=$req->get_json_params(); if(!is_array($d)) $d=[];
    $type=sanitize_key($d['t'] ?? 'pv');
    $path=OA_Util::normalize_path((string)($d['p'] ?? '/'), !empty($opt['strip_query']));
    $ref_dom=OA_Util::ref_domain((string)($d['r'] ?? ''));
    $ua=(string)($d['u'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $dc=sanitize_key($d['d'] ?? OA_Util::device_class_from_ua($ua));
    $utm=is_array($d['utm'] ?? null) ? $d['utm'] : [];
    $utm_source=sanitize_text_field($utm['s'] ?? '');
    $utm_medium=sanitize_text_field($utm['m'] ?? '');
    $utm_campaign=sanitize_text_field($utm['c'] ?? '');
    $event=is_array($d['e'] ?? null) ? $d['e'] : null;
    $event_name=$event && !empty($event['n']) ? sanitize_key($event['n']) : '';
    $event_meta=$event && isset($event['k']) ? sanitize_text_field($event['k']) : '';
    $value=floatval($d['v'] ?? 0);

    $day=OA_Util::today_ymd();
    global $wpdb; $pfx=$wpdb->prefix.'oa_';

    $approx=0;
    if (!empty($opt['approx_uniques'])) {
      $iphash=OA_Util::truncated_ip_hash(OA_Util::client_ip());
      if ($iphash) {
        $key='oa_uni_'.$day.'_'.$iphash;
        if (!get_transient($key)) { set_transient($key,1,DAY_IN_SECONDS+3600); $approx=1; }
      }
    }

    if ($type==='pv') {
      $ph=OA_Util::hash_key($path);
      $wpdb->query($wpdb->prepare(
        "INSERT INTO {$pfx}daily_pages (day,path_hash,path,device_class,count,approx_uniques)
         VALUES (%s,%s,%s,%s,1,%d)
         ON DUPLICATE KEY UPDATE count=count+1, approx_uniques=approx_uniques+%d",
         $day,$ph,$path,$dc,$approx,$approx
      ));
      if ($ref_dom!=='') {
        $rh=OA_Util::hash_key($ref_dom);
        $wpdb->query($wpdb->prepare(
          "INSERT INTO {$pfx}daily_referrers (day,ref_hash,ref_domain,count)
           VALUES (%s,%s,%s,1)
           ON DUPLICATE KEY UPDATE count=count+1",
           $day,$rh,$ref_dom
        ));
      }
      if ($utm_source!=='' || $utm_medium!=='' || $utm_campaign!=='') {
        $camp_key=strtolower($utm_source.'|'.$utm_medium.'|'.$utm_campaign);
        $ch=OA_Util::hash_key($camp_key);
        $lh=OA_Util::hash_key($path);
        $wpdb->query($wpdb->prepare(
          "INSERT INTO {$pfx}daily_campaigns (day,camp_hash,source,medium,campaign,landing_hash,landing_path,views,conversions,value_sum)
           VALUES (%s,%s,%s,%s,%s,%s,%s,1,0,0.00)
           ON DUPLICATE KEY UPDATE views=views+1",
           $day,$ch,$utm_source,$utm_medium,$utm_campaign,$lh,$path
        ));
      }
      self::apply_page_goals_and_funnels($day,$path,$utm_source,$utm_medium,$utm_campaign);
      return new WP_REST_Response(['ok'=>true],200);
    }

    if ($type==='ev' && $event_name!=='') {
      $eh=OA_Util::hash_key($event_name);
      $mh=OA_Util::hash_key($event_meta);
      $meta=substr((string)$event_meta,0,180);
      $wpdb->query($wpdb->prepare(
        "INSERT INTO {$pfx}daily_events (day,event_hash,event_name,meta_hash,meta,count)
         VALUES (%s,%s,%s,%s,%s,1)
         ON DUPLICATE KEY UPDATE count=count+1",
         $day,$eh,$event_name,$mh,$meta
      ));
      self::apply_event_goals_and_funnels($day,$event_name,$meta,$value,$utm_source,$utm_medium,$utm_campaign,$path);
      return new WP_REST_Response(['ok'=>true],200);
    }

    return new WP_REST_Response(['ok'=>true,'ignored'=>true],200);
  }

  private static function apply_page_goals_and_funnels($day,$path,$utm_source,$utm_medium,$utm_campaign){
    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    $goals=$wpdb->get_results($wpdb->prepare(
      "SELECT * FROM {$pfx}goals WHERE is_enabled=1 AND type='page' AND match_value=%s",$path
    ), ARRAY_A);
    foreach($goals as $g){
      $gid=(int)$g['id']; $val=(float)$g['value'];
      $wpdb->query($wpdb->prepare(
        "INSERT INTO {$pfx}daily_goals (day,goal_id,hits,value_sum)
         VALUES (%s,%d,1,%f)
         ON DUPLICATE KEY UPDATE hits=hits+1, value_sum=value_sum+%f",
         $day,$gid,$val,$val
      ));
      if ($utm_source!=='' || $utm_medium!=='' || $utm_campaign!=='') {
        $camp_key=strtolower($utm_source.'|'.$utm_medium.'|'.$utm_campaign);
        $ch=OA_Util::hash_key($camp_key);
        $lh=OA_Util::hash_key($path);
        $wpdb->query($wpdb->prepare(
          "INSERT INTO {$pfx}daily_campaigns (day,camp_hash,source,medium,campaign,landing_hash,landing_path,views,conversions,value_sum)
           VALUES (%s,%s,%s,%s,%s,%s,%s,0,1,%f)
           ON DUPLICATE KEY UPDATE conversions=conversions+1, value_sum=value_sum+%f",
          $day,$ch,$utm_source,$utm_medium,$utm_campaign,$lh,$path,$val,$val
        ));
      }
    }
    $funnels=$wpdb->get_results("SELECT id FROM {$pfx}funnels WHERE is_enabled=1", ARRAY_A);
    foreach($funnels as $f){
      $fid=(int)$f['id'];
      $steps=$wpdb->get_results($wpdb->prepare(
        "SELECT step_num FROM {$pfx}funnel_steps WHERE funnel_id=%d AND step_type='page' AND step_value=%s",
        $fid,$path
      ), ARRAY_A);
      foreach($steps as $s){
        $sn=(int)$s['step_num'];
        $wpdb->query($wpdb->prepare(
          "INSERT INTO {$pfx}daily_funnels (day,funnel_id,step_num,hits)
           VALUES (%s,%d,%d,1)
           ON DUPLICATE KEY UPDATE hits=hits+1",
           $day,$fid,$sn
        ));
      }
    }
  }

  private static function apply_event_goals_and_funnels($day,$event_name,$meta,$value,$utm_source,$utm_medium,$utm_campaign,$path){
    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    $goals=$wpdb->get_results($wpdb->prepare(
      "SELECT * FROM {$pfx}goals WHERE is_enabled=1 AND type='event' AND match_value=%s",$event_name
    ), ARRAY_A);
    foreach($goals as $g){
      $gid=(int)$g['id']; $cfg=(float)$g['value'];
      $mk=(string)$g['meta_key']; $mv=(string)$g['meta_value'];
      if ($mk!=='' && $mv!=='') {
        $ok=false;
        if (strpos($meta,'=')!==false){ list($k,$v)=array_map('trim', explode('=',$meta,2)); if($k===$mk && $v===$mv) $ok=true; }
        if(!$ok) continue;
      }
      $use = ($value>0.0)?$value:$cfg;
      $wpdb->query($wpdb->prepare(
        "INSERT INTO {$pfx}daily_goals (day,goal_id,hits,value_sum)
         VALUES (%s,%d,1,%f)
         ON DUPLICATE KEY UPDATE hits=hits+1, value_sum=value_sum+%f",
         $day,$gid,$use,$use
      ));
      if ($utm_source!=='' || $utm_medium!=='' || $utm_campaign!=='') {
        $camp_key=strtolower($utm_source.'|'.$utm_medium.'|'.$utm_campaign);
        $ch=OA_Util::hash_key($camp_key);
        $lh=OA_Util::hash_key($path);
        $wpdb->query($wpdb->prepare(
          "INSERT INTO {$pfx}daily_campaigns (day,camp_hash,source,medium,campaign,landing_hash,landing_path,views,conversions,value_sum)
           VALUES (%s,%s,%s,%s,%s,%s,%s,0,1,%f)
           ON DUPLICATE KEY UPDATE conversions=conversions+1, value_sum=value_sum+%f",
          $day,$ch,$utm_source,$utm_medium,$utm_campaign,$lh,$path,$use,$use
        ));
      }
    }
    $funnels=$wpdb->get_results("SELECT id FROM {$pfx}funnels WHERE is_enabled=1", ARRAY_A);
    foreach($funnels as $f){
      $fid=(int)$f['id'];
      $steps=$wpdb->get_results($wpdb->prepare(
        "SELECT step_num, meta_key, meta_value FROM {$pfx}funnel_steps WHERE funnel_id=%d AND step_type='event' AND step_value=%s",
        $fid,$event_name
      ), ARRAY_A);
      foreach($steps as $s){
        $mk=(string)$s['meta_key']; $mv=(string)$s['meta_value'];
        if ($mk!=='' && $mv!=='') {
          $ok=false;
          if (strpos($meta,'=')!==false){ list($k,$v)=array_map('trim', explode('=',$meta,2)); if($k===$mk && $v===$mv) $ok=true; }
          if(!$ok) continue;
        }
        $sn=(int)$s['step_num'];
        $wpdb->query($wpdb->prepare(
          "INSERT INTO {$pfx}daily_funnels (day,funnel_id,step_num,hits)
           VALUES (%s,%d,%d,1)
           ON DUPLICATE KEY UPDATE hits=hits+1",
          $day,$fid,$sn
        ));
      }
    }
  }

  public static function export(WP_REST_Request $req){
    $type=sanitize_key($req->get_param('type'));
    $now=current_time('timestamp');
    $from=sanitize_text_field($req->get_param('from')) ?: wp_date('Y-m-d',$now-(28*DAY_IN_SECONDS));
    $to=sanitize_text_field($req->get_param('to')) ?: wp_date('Y-m-d',$now);
    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    $meta=['from'=>$from,'to'=>$to,'type'=>$type];
    switch($type){
      case 'pages':
        $rows=$wpdb->get_results($wpdb->prepare("SELECT day,path,device_class,count,approx_uniques FROM {$pfx}daily_pages WHERE day BETWEEN %s AND %s ORDER BY day DESC, count DESC LIMIT 5000",$from,$to), ARRAY_A); break;
      case 'referrers':
        $rows=$wpdb->get_results($wpdb->prepare("SELECT day,ref_domain,count FROM {$pfx}daily_referrers WHERE day BETWEEN %s AND %s ORDER BY day DESC, count DESC LIMIT 5000",$from,$to), ARRAY_A); break;
      case 'events':
        $rows=$wpdb->get_results($wpdb->prepare("SELECT day,event_name,meta,count FROM {$pfx}daily_events WHERE day BETWEEN %s AND %s ORDER BY day DESC, count DESC LIMIT 5000",$from,$to), ARRAY_A); break;
      case 'goals':
        $rows=$wpdb->get_results($wpdb->prepare("SELECT g.name,g.type,g.match_value,dg.day,dg.hits,dg.value_sum FROM {$pfx}daily_goals dg JOIN {$pfx}goals g ON g.id=dg.goal_id WHERE dg.day BETWEEN %s AND %s ORDER BY dg.day DESC, dg.hits DESC LIMIT 5000",$from,$to), ARRAY_A); break;
      case 'campaigns':
        $opt=get_option('oa_settings',[]);
        $attr_mode=sanitize_key((string)($opt['attribution_mode'] ?? 'first_touch'));
        $meta['attribution_mode']=in_array($attr_mode,['first_touch','last_touch'],true)?$attr_mode:'first_touch';
        $rows=$wpdb->get_results($wpdb->prepare("SELECT day,source,medium,campaign,landing_path,views,conversions,value_sum FROM {$pfx}daily_campaigns WHERE day BETWEEN %s AND %s ORDER BY day DESC, conversions DESC, views DESC LIMIT 5000",$from,$to), ARRAY_A); break;
      case 'revenue':
        $rows=$wpdb->get_results($wpdb->prepare("SELECT day,orders,revenue FROM {$pfx}daily_revenue WHERE day BETWEEN %s AND %s ORDER BY day DESC LIMIT 5000",$from,$to), ARRAY_A); break;
      case 'coupons':
        $rows=$wpdb->get_results($wpdb->prepare("SELECT day,coupon_code,orders,discount_total,revenue_total FROM {$pfx}daily_coupons WHERE day BETWEEN %s AND %s ORDER BY day DESC, discount_total DESC LIMIT 5000",$from,$to), ARRAY_A); break;
      default:
        return new WP_REST_Response(['ok'=>false,'error'=>'Unknown export type'],400);
    }
    return new WP_REST_Response(['ok'=>true,'rows'=>$rows,'meta'=>$meta],200);
  }

  public static function health(WP_REST_Request $req){
    return new WP_REST_Response(['ok'=>true,'health'=>OA_Reports::health_snapshot()],200);
  }

  public static function diagnostics(WP_REST_Request $req){
    return new WP_REST_Response(['ok'=>true,'diagnostics'=>OA_Reports::diagnostics_payload()],200);
  }
}
