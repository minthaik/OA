<?php
if (!defined('ABSPATH')) exit;

class OA_Reports {
  const CRON_HOOK = 'oa_analytics_cron_cleanup_and_reports';

  public static function init(){ add_action(self::CRON_HOOK,[__CLASS__,'cron_run']); }

  public static function ensure_cron(){
    if (!wp_next_scheduled(self::CRON_HOOK)) wp_schedule_event(time()+3600,'daily',self::CRON_HOOK);
  }

  public static function clear_cron(){
    $ts=wp_next_scheduled(self::CRON_HOOK);
    if ($ts) wp_unschedule_event($ts,self::CRON_HOOK);
  }

  public static function cron_run(){
    self::cleanup_old_rows();
    self::maybe_send_report();
    self::maybe_send_anomaly_alert();
  }

  public static function cleanup_old_rows(){
    global $wpdb; $opt=get_option('oa_settings',[]);
    $days=max(30,intval($opt['retention_days'] ?? 180));
    $cut=wp_date('Y-m-d', current_time('timestamp')-($days*DAY_IN_SECONDS));
    $pfx=$wpdb->prefix.'oa_';
    foreach (['daily_pages','daily_referrers','daily_events','daily_campaigns','daily_goals','daily_funnels','daily_revenue','daily_coupons'] as $t){
      $wpdb->query($wpdb->prepare("DELETE FROM {$pfx}{$t} WHERE day < %s",$cut));
    }
  }

  private static function maybe_send_report(){
    $opt=get_option('oa_settings',[]);
    if (empty($opt['email_reports'])) return;

    $to=sanitize_email($opt['email_reports_to'] ?? get_option('admin_email'));
    if (!$to) return;

    $freq=$opt['email_reports_freq'] ?? 'weekly';
    $last=intval(get_option('oa_last_report_sent',0));
    $now=current_time('timestamp');
    $interval=DAY_IN_SECONDS*7;
    if ($freq==='daily') $interval=DAY_IN_SECONDS;
    if ($freq==='monthly') $interval=DAY_IN_SECONDS*30;
    if ($now-$last < $interval) return;

    $days=self::report_period_days($freq);
    $to_d=wp_date('Y-m-d',$now);
    $from=wp_date('Y-m-d',$now-(($days-1)*DAY_IN_SECONDS));
    $dash=self::dashboard($from,$to_d);
    $funnels=self::funnels_stats($from,$to_d,5);

    $subject=sprintf('[%s] Ordelix Analytics summary', get_bloginfo('name'));
    $body="Summary for {$from} -> {$to_d}\n\n";
    $body.="Views: {$dash['kpis']['views']}\n";
    $body.="Visits (approx): {$dash['kpis']['visits']}\n";
    $body.="Top page: {$dash['kpis']['top_page']}\n";
    $body.="Top referrer: {$dash['kpis']['top_referrer']}\n";
    $body.="Conversions: {$dash['kpis']['conversions']} (Value: {$dash['kpis']['value']})\n";
    $body.="Conversion rate: {$dash['kpis']['conversion_rate']}\n\n";

    if (!empty($dash['insights'])){
      $body.="Insights:\n";
      foreach($dash['insights'] as $line) $body.='- '.$line."\n";
      $body.="\n";
    }
    if (!empty($dash['anomalies'])){
      $body.="Watch-outs:\n";
      foreach($dash['anomalies'] as $line) $body.='- '.$line."\n";
      $body.="\n";
    }
    if (!empty($funnels)){
      $body.="Funnels (approx):\n";
      foreach($funnels as $f){
        $body.="- {$f['name']}: {$f['step1']} -> {$f['step_last']} ({$f['conversion']}%)\n";
      }
    }

    wp_mail($to,$subject,$body);
    update_option('oa_last_report_sent',$now);
  }

  private static function maybe_send_anomaly_alert(){
    $opt=get_option('oa_settings',[]);
    if (isset($opt['anomaly_alerts']) && empty($opt['anomaly_alerts'])) return;

    $to=sanitize_email($opt['email_reports_to'] ?? get_option('admin_email'));
    if (!$to) return;

    $now=current_time('timestamp');
    $today=wp_date('Y-m-d',$now);
    if (get_option('oa_last_anomaly_alert_day','')===$today) return;

    $day=wp_date('Y-m-d',$now-DAY_IN_SECONDS);
    $alerts=self::detect_daily_anomalies($day);
    if (empty($alerts)) return;

    $subject=sprintf('[%s] Analytics anomaly alert (%s)', get_bloginfo('name'), $day);
    $body="Anomaly watch for {$day}\n\n";
    foreach($alerts as $a){
      $body.='- '.$a['title'].': '.$a['message']."\n";
    }
    $body.="\nTip: open Ordelix Analytics dashboard and inspect Trend + Top Pages/Events for the same date.\n";

    wp_mail($to,$subject,$body);
    update_option('oa_last_anomaly_alert_day',$today);
  }

  private static function report_period_days($freq){
    if ($freq==='daily') return 1;
    if ($freq==='monthly') return 30;
    return 7;
  }

  private static function normalize_filters($filters){
    $f=is_array($filters)?$filters:[];
    $out=[
      'device'=>sanitize_key($f['device'] ?? ''),
      'path'=>sanitize_text_field((string)($f['path'] ?? '')),
      'event'=>sanitize_text_field((string)($f['event'] ?? '')),
      'source'=>sanitize_text_field((string)($f['source'] ?? '')),
      'medium'=>sanitize_text_field((string)($f['medium'] ?? '')),
      'campaign'=>sanitize_text_field((string)($f['campaign'] ?? '')),
      'coupon'=>sanitize_text_field((string)($f['coupon'] ?? '')),
    ];
    if (!in_array($out['device'],['desktop','mobile','tablet','unknown'],true)) $out['device']='';
    return $out;
  }

  private static function page_filter_sql($filters,&$params,$path_col='path',$device_col='device_class'){
    $w=[];
    if (!empty($filters['device'])){ $w[]="{$device_col}=%s"; $params[]=$filters['device']; }
    if (!empty($filters['path'])){ $w[]="{$path_col} LIKE %s"; $params[]='%'.$filters['path'].'%'; }
    return $w ? (' AND '.implode(' AND ',$w)) : '';
  }

  private static function event_filter_sql($filters,&$params,$event_col='event_name'){
    $w=[];
    if (!empty($filters['event'])){ $w[]="{$event_col} LIKE %s"; $params[]='%'.$filters['event'].'%'; }
    return $w ? (' AND '.implode(' AND ',$w)) : '';
  }

  private static function campaign_filter_sql($filters,&$params,$source_col='source',$medium_col='medium',$campaign_col='campaign',$path_col='landing_path'){
    $w=[];
    if (!empty($filters['source'])){ $w[]="{$source_col} LIKE %s"; $params[]='%'.$filters['source'].'%'; }
    if (!empty($filters['medium'])){ $w[]="{$medium_col} LIKE %s"; $params[]='%'.$filters['medium'].'%'; }
    if (!empty($filters['campaign'])){ $w[]="{$campaign_col} LIKE %s"; $params[]='%'.$filters['campaign'].'%'; }
    if (!empty($filters['path'])){ $w[]="{$path_col} LIKE %s"; $params[]='%'.$filters['path'].'%'; }
    return $w ? (' AND '.implode(' AND ',$w)) : '';
  }

  private static function coupon_filter_sql($filters,&$params,$coupon_col='coupon_code'){
    if (empty($filters['coupon'])) return '';
    $params[]='%'.$filters['coupon'].'%';
    return " AND {$coupon_col} LIKE %s";
  }

  private static function goals_filter_join_where($filters,&$params,$goal_alias='g',$day_alias='dg'){
    $parts=[];
    if (!empty($filters['path'])){ $parts[]="({$goal_alias}.type='page' AND {$goal_alias}.match_value LIKE %s)"; $params[]='%'.$filters['path'].'%'; }
    if (!empty($filters['event'])){ $parts[]="({$goal_alias}.type='event' AND {$goal_alias}.match_value LIKE %s)"; $params[]='%'.$filters['event'].'%'; }
    if (empty($parts)) return '';
    return ' AND ('.implode(' OR ',$parts).')';
  }

  private static function aggregate_metrics($from,$to,$filters=[]){
    $filters=self::normalize_filters($filters);
    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    $p_params=[$from,$to];
    $p_where=self::page_filter_sql($filters,$p_params,'path','device_class');
    $views=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(count),0) FROM {$pfx}daily_pages WHERE day BETWEEN %s AND %s{$p_where}",$p_params));

    $conv=0; $val=0.0;
    if (!empty($filters['source']) || !empty($filters['medium']) || !empty($filters['campaign'])){
      $c_params=[$from,$to];
      $c_where=self::campaign_filter_sql($filters,$c_params,'source','medium','campaign','landing_path');
      $conv=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(conversions),0) FROM {$pfx}daily_campaigns WHERE day BETWEEN %s AND %s{$c_where}",$c_params));
      $val=(float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(value_sum),0) FROM {$pfx}daily_campaigns WHERE day BETWEEN %s AND %s{$c_where}",$c_params));
    } elseif (!empty($filters['path']) || !empty($filters['event'])){
      $g_params=[$from,$to];
      $g_where=self::goals_filter_join_where($filters,$g_params,'g','dg');
      $conv=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(dg.hits),0) FROM {$pfx}daily_goals dg JOIN {$pfx}goals g ON g.id=dg.goal_id WHERE dg.day BETWEEN %s AND %s{$g_where}",$g_params));
      $val=(float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(dg.value_sum),0) FROM {$pfx}daily_goals dg JOIN {$pfx}goals g ON g.id=dg.goal_id WHERE dg.day BETWEEN %s AND %s{$g_where}",$g_params));
    } else {
      $conv=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(hits),0) FROM {$pfx}daily_goals WHERE day BETWEEN %s AND %s",$from,$to));
      $val=(float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(value_sum),0) FROM {$pfx}daily_goals WHERE day BETWEEN %s AND %s",$from,$to));
    }

    $opt=get_option('oa_settings',[]);
    if (!empty($opt['approx_uniques'])) {
      $au=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(approx_uniques),0) FROM {$pfx}daily_pages WHERE day BETWEEN %s AND %s{$p_where}",$p_params));
      $visits=min($views,max(0,$au));
    } else {
      $visits=(int)round($views/1.3);
    }

    $cr=$views>0 ? (($conv/$views)*100.0) : 0.0;
    return ['views'=>$views,'visits'=>$visits,'conversions'=>$conv,'value'=>$val,'conversion_rate'=>$cr];
  }

  private static function period_bounds($from,$to){
    $from_ts=strtotime($from.' 00:00:00');
    $to_ts=strtotime($to.' 00:00:00');
    if (!$from_ts || !$to_ts){
      $to_ts=current_time('timestamp');
      $from_ts=$to_ts-(6*DAY_IN_SECONDS);
    }
    if ($to_ts < $from_ts){
      $tmp=$to_ts; $to_ts=$from_ts; $from_ts=$tmp;
    }
    $days=max(1, (int)floor(($to_ts-$from_ts)/DAY_IN_SECONDS)+1);
    $prev_to_ts=$from_ts-DAY_IN_SECONDS;
    $prev_from_ts=$prev_to_ts-(($days-1)*DAY_IN_SECONDS);
    return [
      'days'=>$days,
      'current_from'=>wp_date('Y-m-d',$from_ts),
      'current_to'=>wp_date('Y-m-d',$to_ts),
      'prev_from'=>wp_date('Y-m-d',$prev_from_ts),
      'prev_to'=>wp_date('Y-m-d',$prev_to_ts),
    ];
  }

  private static function pct_change($current,$previous){
    $current=(float)$current; $previous=(float)$previous;
    if ($previous<=0.0) return $current>0.0 ? null : 0.0;
    return (($current-$previous)/$previous)*100.0;
  }

  private static function format_pct_change($pct){
    if ($pct===null) return 'new';
    return (($pct>=0)?'+':'').number_format_i18n((float)$pct,1).'%';
  }

  private static function period_comparison($from,$to,$filters=[]){
    $bounds=self::period_bounds($from,$to);
    $current=self::aggregate_metrics($bounds['current_from'],$bounds['current_to'],$filters);
    $previous=self::aggregate_metrics($bounds['prev_from'],$bounds['prev_to'],$filters);
    $changes=[
      'views_pct'=>self::pct_change($current['views'],$previous['views']),
      'conversions_pct'=>self::pct_change($current['conversions'],$previous['conversions']),
      'value_pct'=>self::pct_change($current['value'],$previous['value']),
      'conversion_rate_pct'=>self::pct_change($current['conversion_rate'],$previous['conversion_rate']),
    ];
    return ['bounds'=>$bounds,'current'=>$current,'previous'=>$previous,'changes'=>$changes];
  }

  private static function get_top_mover($type,$from,$to,$prev_from,$prev_to,$filters=[]){
    $filters=self::normalize_filters($filters);
    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    $sql_current='';
    $sql_prev='';
    $curr_params=[$from,$to];
    $prev_params=[$prev_from,$prev_to];
    $min_current=10;
    if ($type==='pages'){
      $page_curr_where=self::page_filter_sql($filters,$curr_params,'path','device_class');
      $page_prev_where=self::page_filter_sql($filters,$prev_params,'path','device_class');
      $sql_current="SELECT path as label, SUM(count) as n FROM {$pfx}daily_pages WHERE day BETWEEN %s AND %s{$page_curr_where} GROUP BY path ORDER BY n DESC LIMIT 100";
      $sql_prev="SELECT path as label, SUM(count) as n FROM {$pfx}daily_pages WHERE day BETWEEN %s AND %s{$page_prev_where} GROUP BY path";
      $min_current=15;
    } elseif ($type==='events'){
      $event_curr_where=self::event_filter_sql($filters,$curr_params,'event_name');
      $event_prev_where=self::event_filter_sql($filters,$prev_params,'event_name');
      $sql_current="SELECT event_name as label, SUM(count) as n FROM {$pfx}daily_events WHERE day BETWEEN %s AND %s{$event_curr_where} GROUP BY event_name ORDER BY n DESC LIMIT 100";
      $sql_prev="SELECT event_name as label, SUM(count) as n FROM {$pfx}daily_events WHERE day BETWEEN %s AND %s{$event_prev_where} GROUP BY event_name";
      $min_current=8;
    } elseif ($type==='referrers'){
      $sql_current="SELECT ref_domain as label, SUM(count) as n FROM {$pfx}daily_referrers WHERE day BETWEEN %s AND %s GROUP BY ref_domain ORDER BY n DESC LIMIT 100";
      $sql_prev="SELECT ref_domain as label, SUM(count) as n FROM {$pfx}daily_referrers WHERE day BETWEEN %s AND %s GROUP BY ref_domain";
      $min_current=8;
    } else return null;

    $curr_rows=$wpdb->get_results($wpdb->prepare($sql_current,$curr_params), ARRAY_A);
    if (empty($curr_rows)) return null;
    $prev_rows=$wpdb->get_results($wpdb->prepare($sql_prev,$prev_params), ARRAY_A);
    $prev_map=[];
    foreach($prev_rows as $r) $prev_map[(string)$r['label']]=(int)$r['n'];

    $best=null; $best_score=-1.0;
    foreach($curr_rows as $r){
      $label=(string)$r['label'];
      $curr=(int)$r['n'];
      if ($label==='' || $curr<$min_current) continue;
      $prev=(int)($prev_map[$label] ?? 0);
      $chg=self::pct_change($curr,$prev);
      if ($chg!==null && abs($chg)<20.0) continue;
      $score=($chg===null ? 200.0 : abs($chg)) + min(60.0, $curr/4.0);
      if ($score>$best_score){
        $best=['label'=>$label,'current'=>$curr,'previous'=>$prev,'change_pct'=>$chg];
        $best_score=$score;
      }
    }
    return $best;
  }

  private static function build_insights($from,$to,$limit=4,$comparison=null,$filters=[]){
    $filters=self::normalize_filters($filters);
    if (!$comparison) $comparison=self::period_comparison($from,$to,$filters);
    $insights=[];
    $chg=$comparison['changes'];
    $bounds=$comparison['bounds'];

    if ($chg['views_pct']!==null && abs($chg['views_pct'])>=8.0){
      $dir=$chg['views_pct']>0 ? 'up' : 'down';
      $insights[]="Traffic is {$dir} ".self::format_pct_change($chg['views_pct'])." vs prior {$bounds['days']}-day period.";
    }
    if ($chg['conversion_rate_pct']!==null && abs($chg['conversion_rate_pct'])>=8.0){
      $dir=$chg['conversion_rate_pct']>0 ? 'improved' : 'dropped';
      $insights[]="Conversion rate {$dir} ".self::format_pct_change($chg['conversion_rate_pct'])." vs prior period.";
    }

    $page=self::get_top_mover('pages',$from,$to,$bounds['prev_from'],$bounds['prev_to'],$filters);
    if ($page){
      $insights[]='Page momentum: '.$page['label'].' ('.self::format_pct_change($page['change_pct']).', '.$page['current'].' vs '.$page['previous'].').';
    }
    $event=self::get_top_mover('events',$from,$to,$bounds['prev_from'],$bounds['prev_to'],$filters);
    if ($event){
      $insights[]='Event momentum: '.$event['label'].' ('.self::format_pct_change($event['change_pct']).', '.$event['current'].' vs '.$event['previous'].').';
    }

    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    $camp_params=[$from,$to];
    $camp_where=self::campaign_filter_sql($filters,$camp_params,'source','medium','campaign','landing_path');
    $camp=$wpdb->get_row($wpdb->prepare(
      "SELECT source,medium,campaign,SUM(conversions) as conv,SUM(value_sum) as val
       FROM {$pfx}daily_campaigns WHERE day BETWEEN %s AND %s{$camp_where}
       GROUP BY source,medium,campaign ORDER BY conv DESC, val DESC LIMIT 1",
      $camp_params
    ), ARRAY_A);
    if (!empty($camp) && ((int)$camp['conv']>0 || (float)$camp['val']>0.0)){
      $src=$camp['source']!=='' ? $camp['source'] : '-';
      $med=$camp['medium']!=='' ? $camp['medium'] : '-';
      $name=$camp['campaign']!=='' ? $camp['campaign'] : '-';
      $insights[]="Top converting campaign: {$src} / {$med} / {$name} ({$camp['conv']} conversions, ".number_format_i18n((float)$camp['val'],2)." value).";
    }

    return array_slice(array_values(array_unique($insights)),0,max(1,intval($limit)));
  }

  private static function detect_period_anomalies($from,$to,$comparison=null,$filters=[]){
    $filters=self::normalize_filters($filters);
    if (!$comparison) $comparison=self::period_comparison($from,$to,$filters);
    $opt=get_option('oa_settings',[]);
    $threshold=max(10,min(90,intval($opt['anomaly_threshold_pct'] ?? 35)));
    $min_views=max(10,intval($opt['anomaly_min_views'] ?? 60));
    $min_conv=max(1,intval($opt['anomaly_min_conversions'] ?? 5));

    $alerts=[];
    $prev=$comparison['previous'];
    $chg=$comparison['changes'];

    if ($prev['views'] >= $min_views && $chg['views_pct']!==null){
      if ($chg['views_pct'] <= -$threshold){
        $alerts[]='Views are '.self::format_pct_change($chg['views_pct'])." vs prior period.";
      } elseif ($chg['views_pct'] >= ($threshold*1.5)){
        $alerts[]='Views spiked '.self::format_pct_change($chg['views_pct'])." vs prior period.";
      }
    }
    if ($prev['conversions'] >= $min_conv && $chg['conversions_pct']!==null){
      if ($chg['conversions_pct'] <= -$threshold){
        $alerts[]='Conversions are '.self::format_pct_change($chg['conversions_pct'])." vs prior period.";
      } elseif ($chg['conversions_pct'] >= ($threshold*1.5)){
        $alerts[]='Conversions spiked '.self::format_pct_change($chg['conversions_pct'])." vs prior period.";
      }
    }
    if ($prev['views'] >= $min_views && $prev['conversion_rate'] > 0.0 && $chg['conversion_rate_pct']!==null && $chg['conversion_rate_pct'] <= -$threshold){
      $alerts[]='Conversion rate declined '.self::format_pct_change($chg['conversion_rate_pct'])." vs prior period.";
    }

    return $alerts;
  }

  private static function daily_metric_map($table,$metric_col,$from,$to){
    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    $rows=$wpdb->get_results($wpdb->prepare(
      "SELECT day, COALESCE(SUM({$metric_col}),0) as n FROM {$pfx}{$table} WHERE day BETWEEN %s AND %s GROUP BY day",
      $from,$to
    ), ARRAY_A);
    $map=[];
    foreach($rows as $r) $map[(string)$r['day']]=(float)$r['n'];
    return $map;
  }

  private static function sql_items_for_metric($metric_key,$pfx,$day,$base_from,$base_to,$baseline_days,$target_views,$target_conv,$base_views,$base_conv){
    $items=[];
    if ($metric_key==='views'){
      $items[]=[
        'label'=>'Target day total',
        'sql'=>"SELECT COALESCE(SUM(count),0) FROM {$pfx}daily_pages WHERE day=%s",
        'params'=>[$day],
        'value'=>$target_views,
      ];
      $items[]=[
        'label'=>'Baseline total',
        'sql'=>"SELECT COALESCE(SUM(count),0) FROM {$pfx}daily_pages WHERE day BETWEEN %s AND %s",
        'params'=>[$base_from,$base_to],
        'value'=>$base_views,
      ];
      $items[]=[
        'label'=>'Baseline average/day',
        'sql'=>'baseline_total / baseline_days',
        'params'=>[(int)$baseline_days],
        'value'=>($baseline_days>0 ? ($base_views/$baseline_days) : 0.0),
      ];
      return $items;
    }
    if ($metric_key==='conversions'){
      $items[]=[
        'label'=>'Target day total',
        'sql'=>"SELECT COALESCE(SUM(hits),0) FROM {$pfx}daily_goals WHERE day=%s",
        'params'=>[$day],
        'value'=>$target_conv,
      ];
      $items[]=[
        'label'=>'Baseline total',
        'sql'=>"SELECT COALESCE(SUM(hits),0) FROM {$pfx}daily_goals WHERE day BETWEEN %s AND %s",
        'params'=>[$base_from,$base_to],
        'value'=>$base_conv,
      ];
      $items[]=[
        'label'=>'Baseline average/day',
        'sql'=>'baseline_total / baseline_days',
        'params'=>[(int)$baseline_days],
        'value'=>($baseline_days>0 ? ($base_conv/$baseline_days) : 0.0),
      ];
      return $items;
    }
    if ($metric_key==='conversion_rate'){
      $items[]=[
        'label'=>'Target views total',
        'sql'=>"SELECT COALESCE(SUM(count),0) FROM {$pfx}daily_pages WHERE day=%s",
        'params'=>[$day],
        'value'=>$target_views,
      ];
      $items[]=[
        'label'=>'Target conversions total',
        'sql'=>"SELECT COALESCE(SUM(hits),0) FROM {$pfx}daily_goals WHERE day=%s",
        'params'=>[$day],
        'value'=>$target_conv,
      ];
      $items[]=[
        'label'=>'Baseline views total',
        'sql'=>"SELECT COALESCE(SUM(count),0) FROM {$pfx}daily_pages WHERE day BETWEEN %s AND %s",
        'params'=>[$base_from,$base_to],
        'value'=>$base_views,
      ];
      $items[]=[
        'label'=>'Baseline conversions total',
        'sql'=>"SELECT COALESCE(SUM(hits),0) FROM {$pfx}daily_goals WHERE day BETWEEN %s AND %s",
        'params'=>[$base_from,$base_to],
        'value'=>$base_conv,
      ];
      $items[]=[
        'label'=>'Target conversion rate',
        'sql'=>'(target_conversions / target_views) * 100',
        'params'=>[],
        'value'=>($target_views>0.0 ? (($target_conv/$target_views)*100.0) : 0.0),
      ];
      $items[]=[
        'label'=>'Baseline conversion rate',
        'sql'=>'((baseline_conversions / baseline_days) / (baseline_views / baseline_days)) * 100',
        'params'=>[(int)$baseline_days],
        'value'=>($base_views>0.0 ? (($base_conv/$base_views)*100.0) : 0.0),
      ];
      return $items;
    }
    return $items;
  }

  private static function normalize_day($day){
    $day=sanitize_text_field((string)$day);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$day)) return wp_date('Y-m-d', current_time('timestamp')-DAY_IN_SECONDS);
    if (!wp_checkdate((int)substr($day,5,2),(int)substr($day,8,2),(int)substr($day,0,4),$day)) return wp_date('Y-m-d', current_time('timestamp')-DAY_IN_SECONDS);
    return $day;
  }

  private static function daily_anomaly_context($day){
    $day=self::normalize_day($day);
    $ts=strtotime($day.' 00:00:00');
    if (!$ts) $ts=current_time('timestamp')-DAY_IN_SECONDS;
    global $wpdb; $pfx=$wpdb->prefix.'oa_';

    $opt=get_option('oa_settings',[]);
    $baseline_days=max(3,min(30,intval($opt['anomaly_baseline_days'] ?? 7)));
    $threshold=max(10,min(90,intval($opt['anomaly_threshold_pct'] ?? 35)));
    $min_views=max(10,intval($opt['anomaly_min_views'] ?? 60));
    $min_conv=max(1,intval($opt['anomaly_min_conversions'] ?? 5));

    $base_from=wp_date('Y-m-d',$ts-($baseline_days*DAY_IN_SECONDS));
    $base_to=wp_date('Y-m-d',$ts-DAY_IN_SECONDS);
    if (strtotime($base_to.' 00:00:00') < strtotime($base_from.' 00:00:00')) {
      return [
        'day'=>$day,'baseline_days'=>$baseline_days,'baseline_from'=>$base_from,'baseline_to'=>$base_to,
        'threshold_pct'=>$threshold,'min_views'=>$min_views,'min_conversions'=>$min_conv,
        'metrics'=>[],'alerts'=>[],'daily_rows'=>[],
      ];
    }

    $views_map=self::daily_metric_map('daily_pages','count',$base_from,$day);
    $conv_map=self::daily_metric_map('daily_goals','hits',$base_from,$day);

    $target_views=(float)($views_map[$day] ?? 0.0);
    $target_conv=(float)($conv_map[$day] ?? 0.0);

    $base_views=0.0; $base_conv=0.0; $n=0;
    for($i=$baseline_days;$i>=1;$i--){
      $d=wp_date('Y-m-d',$ts-($i*DAY_IN_SECONDS));
      $base_views+=(float)($views_map[$d] ?? 0.0);
      $base_conv+=(float)($conv_map[$d] ?? 0.0);
      $n++;
    }
    if ($n<=0) {
      return [
        'day'=>$day,'baseline_days'=>$baseline_days,'baseline_from'=>$base_from,'baseline_to'=>$base_to,
        'threshold_pct'=>$threshold,'min_views'=>$min_views,'min_conversions'=>$min_conv,
        'metrics'=>[],'alerts'=>[],'daily_rows'=>[],
      ];
    }
    $avg_views=$base_views/$n;
    $avg_conv=$base_conv/$n;

    $target_rate=$target_views>0.0 ? (($target_conv/$target_views)*100.0) : 0.0;
    $base_rate=$avg_views>0.0 ? (($avg_conv/$avg_views)*100.0) : 0.0;
    $rate_pct=self::pct_change($target_rate,$base_rate);

    $metrics=[];
    $alerts=[];

    $views_pct=self::pct_change($target_views,$avg_views);
    $views_eligible=($avg_views >= $min_views);
    $views_triggered=false;
    if ($views_eligible && $views_pct!==null){
      if ($views_pct <= -$threshold){
        $alerts[]=['title'=>'Traffic drop','message'=>'Views dropped '.self::format_pct_change($views_pct).' ('.number_format_i18n($target_views,0).' vs baseline '.number_format_i18n($avg_views,1).').'];
        $views_triggered=true;
      } elseif ($views_pct >= ($threshold*1.5)){
        $alerts[]=['title'=>'Traffic spike','message'=>'Views increased '.self::format_pct_change($views_pct).' ('.number_format_i18n($target_views,0).' vs baseline '.number_format_i18n($avg_views,1).').'];
        $views_triggered=true;
      }
    }
    $metrics[]=[
      'key'=>'views','label'=>'Views','is_percent'=>false,'target'=>$target_views,'baseline_avg'=>$avg_views,
      'change_pct'=>$views_pct,'threshold_pct'=>$threshold,'eligible'=>$views_eligible,'triggered'=>$views_triggered,
      'rule'=>'Drop <= -'.$threshold.'% or spike >= +'.($threshold*1.5).'%',
      'sql_items'=>self::sql_items_for_metric('views',$pfx,$day,$base_from,$base_to,$baseline_days,$target_views,$target_conv,$base_views,$base_conv),
    ];

    $conv_pct=self::pct_change($target_conv,$avg_conv);
    $conv_eligible=($avg_conv >= $min_conv);
    $conv_triggered=false;
    if ($conv_eligible && $conv_pct!==null){
      if ($conv_pct <= -$threshold){
        $alerts[]=['title'=>'Conversion drop','message'=>'Conversions dropped '.self::format_pct_change($conv_pct).' ('.number_format_i18n($target_conv,0).' vs baseline '.number_format_i18n($avg_conv,1).').'];
        $conv_triggered=true;
      } elseif ($conv_pct >= ($threshold*1.5)){
        $alerts[]=['title'=>'Conversion spike','message'=>'Conversions increased '.self::format_pct_change($conv_pct).' ('.number_format_i18n($target_conv,0).' vs baseline '.number_format_i18n($avg_conv,1).').'];
        $conv_triggered=true;
      }
    }
    $metrics[]=[
      'key'=>'conversions','label'=>'Conversions','is_percent'=>false,'target'=>$target_conv,'baseline_avg'=>$avg_conv,
      'change_pct'=>$conv_pct,'threshold_pct'=>$threshold,'eligible'=>$conv_eligible,'triggered'=>$conv_triggered,
      'rule'=>'Drop <= -'.$threshold.'% or spike >= +'.($threshold*1.5).'%',
      'sql_items'=>self::sql_items_for_metric('conversions',$pfx,$day,$base_from,$base_to,$baseline_days,$target_views,$target_conv,$base_views,$base_conv),
    ];

    $rate_eligible=($avg_views >= $min_views && $base_rate>0.0);
    $rate_triggered=($rate_eligible && $rate_pct!==null && $rate_pct <= -$threshold);
    if ($rate_triggered){
      $alerts[]=['title'=>'Conversion-rate decline','message'=>'Conversion rate dropped '.self::format_pct_change($rate_pct).' ('.number_format_i18n($target_rate,2).'% vs baseline '.number_format_i18n($base_rate,2).'%).'];
    }
    $metrics[]=[
      'key'=>'conversion_rate','label'=>'Conversion rate','is_percent'=>true,'target'=>$target_rate,'baseline_avg'=>$base_rate,
      'change_pct'=>$rate_pct,'threshold_pct'=>$threshold,'eligible'=>$rate_eligible,'triggered'=>$rate_triggered,
      'rule'=>'Drop <= -'.$threshold.'%',
      'sql_items'=>self::sql_items_for_metric('conversion_rate',$pfx,$day,$base_from,$base_to,$baseline_days,$target_views,$target_conv,$base_views,$base_conv),
    ];

    $daily_rows=[];
    for($i=$baseline_days;$i>=0;$i--){
      $d=wp_date('Y-m-d',$ts-($i*DAY_IN_SECONDS));
      $dv=(float)($views_map[$d] ?? 0.0);
      $dc=(float)($conv_map[$d] ?? 0.0);
      $dr=$dv>0.0 ? (($dc/$dv)*100.0) : 0.0;
      $daily_rows[]=['day'=>$d,'views'=>$dv,'conversions'=>$dc,'conversion_rate'=>$dr,'is_target'=>($i===0)];
    }

    return [
      'day'=>$day,
      'baseline_days'=>$baseline_days,
      'baseline_from'=>$base_from,
      'baseline_to'=>$base_to,
      'threshold_pct'=>$threshold,
      'min_views'=>$min_views,
      'min_conversions'=>$min_conv,
      'metrics'=>$metrics,
      'alerts'=>$alerts,
      'daily_rows'=>$daily_rows,
    ];
  }

  private static function detect_daily_anomalies($day){
    $ctx=self::daily_anomaly_context($day);
    return $ctx['alerts'];
  }

  private static function daily_label_movers($table,$label_col,$metric_col,$base_from,$base_to,$baseline_days,$day,$limit=10,$min_target=8){
    $allowed=[
      'daily_pages'=>['label'=>'path','metric'=>'count'],
      'daily_events'=>['label'=>'event_name','metric'=>'count'],
    ];
    if (empty($allowed[$table])) return [];
    if ($allowed[$table]['label']!==$label_col || $allowed[$table]['metric']!==$metric_col) return [];
    if ($baseline_days<=0) return [];

    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    $target_rows=$wpdb->get_results($wpdb->prepare(
      "SELECT {$label_col} as label, COALESCE(SUM({$metric_col}),0) as n
       FROM {$pfx}{$table} WHERE day=%s GROUP BY {$label_col} ORDER BY n DESC LIMIT 200",
      $day
    ), ARRAY_A);
    $base_rows=$wpdb->get_results($wpdb->prepare(
      "SELECT {$label_col} as label, COALESCE(SUM({$metric_col}),0) as n
       FROM {$pfx}{$table} WHERE day BETWEEN %s AND %s GROUP BY {$label_col} ORDER BY n DESC LIMIT 300",
      $base_from,$base_to
    ), ARRAY_A);

    $target_map=[]; $base_map=[]; $labels=[];
    foreach($target_rows as $r){ $label=(string)$r['label']; if($label==='') continue; $target_map[$label]=(float)$r['n']; $labels[$label]=1; }
    foreach($base_rows as $r){ $label=(string)$r['label']; if($label==='') continue; $base_map[$label]=(float)$r['n']; $labels[$label]=1; }

    $items=[];
    foreach(array_keys($labels) as $label){
      $target=(float)($target_map[$label] ?? 0.0);
      $baseline_avg=((float)($base_map[$label] ?? 0.0))/max(1,$baseline_days);
      if ($target < $min_target && $baseline_avg < $min_target) continue;
      $chg=self::pct_change($target,$baseline_avg);
      if ($chg!==null && abs($chg)<20.0) continue;
      $score=($chg===null ? 200.0 : abs($chg)) + min(50.0, $target*2.0);
      $items[]=[
        'label'=>$label,
        'target'=>$target,
        'baseline_avg'=>$baseline_avg,
        'delta'=>($target-$baseline_avg),
        'change_pct'=>$chg,
        '_score'=>$score,
      ];
    }
    usort($items,function($a,$b){ if($a['_score']===$b['_score']) return 0; return ($a['_score']>$b['_score'])?-1:1; });
    $items=array_slice($items,0,max(1,(int)$limit));
    foreach($items as &$it) unset($it['_score']);
    return $items;
  }

  public static function anomaly_drilldown($day){
    $ctx=self::daily_anomaly_context($day);
    $ctx['top_pages']=self::daily_label_movers(
      'daily_pages','path','count',
      $ctx['baseline_from'],$ctx['baseline_to'],$ctx['baseline_days'],$ctx['day'],
      10,10
    );
    $ctx['top_events']=self::daily_label_movers(
      'daily_events','event_name','count',
      $ctx['baseline_from'],$ctx['baseline_to'],$ctx['baseline_days'],$ctx['day'],
      10,6
    );
    return $ctx;
  }

  public static function health_snapshot(){
    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    $tables=method_exists('OA_DB','expected_table_names') ? OA_DB::expected_table_names() : ['daily_pages','daily_referrers','daily_events','daily_campaigns','goals','daily_goals','funnels','funnel_steps','daily_funnels','daily_revenue','daily_coupons'];
    $table_rows=[];
    $missing_tables=[];
    foreach($tables as $name){
      $tbl=$pfx.$name;
      $exists=((string)$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$tbl))===$tbl);
      $rows=null;
      if ($exists){
        $count=$wpdb->get_var("SELECT COUNT(*) FROM {$tbl}");
        $rows=($count===null) ? null : intval($count);
      } else {
        $missing_tables[]=$name;
      }
      $table_rows[]=['name'=>$name,'exists'=>$exists,'rows'=>$rows];
    }
    $cron_ts=wp_next_scheduled(self::CRON_HOOK);
    $opt=get_option('oa_settings',[]);
    $db_expected=defined('OA_DB::SCHEMA_VERSION') ? OA_DB::SCHEMA_VERSION : '0.4.0';
    $db_version=(string)get_option('oa_db_version','');

    $goal_orphans=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$pfx}daily_goals dg LEFT JOIN {$pfx}goals g ON g.id=dg.goal_id WHERE g.id IS NULL");
    $step_orphans=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$pfx}funnel_steps fs LEFT JOIN {$pfx}funnels f ON f.id=fs.funnel_id WHERE f.id IS NULL");
    $daily_funnel_orphans=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$pfx}daily_funnels df LEFT JOIN {$pfx}funnels f ON f.id=df.funnel_id WHERE f.id IS NULL");

    $to=wp_date('Y-m-d', current_time('timestamp'));
    $from=wp_date('Y-m-d', current_time('timestamp')-(6*DAY_IN_SECONDS));
    $last_7d=[
      'views'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(count),0) FROM {$pfx}daily_pages WHERE day BETWEEN %s AND %s",$from,$to)),
      'events'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(count),0) FROM {$pfx}daily_events WHERE day BETWEEN %s AND %s",$from,$to)),
      'conversions'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(hits),0) FROM {$pfx}daily_goals WHERE day BETWEEN %s AND %s",$from,$to)),
      'revenue'=>(float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(revenue),0) FROM {$pfx}daily_revenue WHERE day BETWEEN %s AND %s",$from,$to)),
    ];

    $checks=[];
    $checks[]=[
      'key'=>'schema_version',
      'label'=>'Schema version',
      'status'=>($db_version===$db_expected ? 'ok' : 'fail'),
      'detail'=>($db_version===$db_expected ? 'Schema matches expected version.' : 'Current '.$db_version.'; expected '.$db_expected.'.'),
    ];
    $checks[]=[
      'key'=>'table_presence',
      'label'=>'Required tables',
      'status'=>(empty($missing_tables) ? 'ok' : 'fail'),
      'detail'=>(empty($missing_tables) ? 'All required tables exist.' : ('Missing: '.implode(', ',$missing_tables))),
    ];
    $checks[]=[
      'key'=>'cron_schedule',
      'label'=>'Daily cron',
      'status'=>($cron_ts ? 'ok' : 'fail'),
      'detail'=>($cron_ts ? 'Scheduled for '.wp_date('Y-m-d H:i',intval($cron_ts)) : 'Cron event not scheduled.'),
    ];
    $checks[]=[
      'key'=>'orphan_rows',
      'label'=>'Orphaned rows',
      'status'=>(($goal_orphans+$step_orphans+$daily_funnel_orphans)===0 ? 'ok' : 'warn'),
      'detail'=>'daily_goals='.$goal_orphans.', funnel_steps='.$step_orphans.', daily_funnels='.$daily_funnel_orphans,
    ];
    $retention=max(30,intval($opt['retention_days'] ?? 180));
    $checks[]=[
      'key'=>'retention_window',
      'label'=>'Retention policy',
      'status'=>($retention>1095 ? 'warn' : 'ok'),
      'detail'=>'Retention set to '.$retention.' day(s).',
    ];
    $cap_fail=[]; $cap_warn=[];
    $admin_role=get_role('administrator');
    if (!$admin_role){
      $cap_fail[]='administrator role missing';
    } else {
      if (!$admin_role->has_cap('ordelix_analytics_view')) $cap_fail[]='administrator missing ordelix_analytics_view';
      if (!$admin_role->has_cap('ordelix_analytics_manage')) $cap_fail[]='administrator missing ordelix_analytics_manage';
    }
    $editor_role=get_role('editor');
    if ($editor_role && !$editor_role->has_cap('ordelix_analytics_view')) $cap_warn[]='editor missing ordelix_analytics_view';
    if (class_exists('WooCommerce')){
      $shop_role=get_role('shop_manager');
      if ($shop_role && !$shop_role->has_cap('ordelix_analytics_view')) $cap_warn[]='shop_manager missing ordelix_analytics_view';
    }
    $checks[]=[
      'key'=>'capability_matrix',
      'label'=>'Capability matrix',
      'status'=>(!empty($cap_fail) ? 'fail' : (!empty($cap_warn) ? 'warn' : 'ok')),
      'detail'=>(!empty($cap_fail) ? implode('; ',$cap_fail) : (!empty($cap_warn) ? implode('; ',$cap_warn) : 'Role capability matrix is aligned.')),
    ];
    $schema_audit=method_exists('OA_DB','schema_audit') ? OA_DB::schema_audit() : [];
    $schema_fail=0; $schema_warn=0;
    foreach($schema_audit as $s){
      if (($s['status'] ?? '')==='fail') $schema_fail++;
      elseif (($s['status'] ?? '')==='warn') $schema_warn++;
    }
    $checks[]=[
      'key'=>'schema_structure',
      'label'=>'Schema structure',
      'status'=>($schema_fail>0 ? 'fail' : ($schema_warn>0 ? 'warn' : 'ok')),
      'detail'=>($schema_fail>0 ? ($schema_fail.' table(s) failing structure checks.') : ($schema_warn>0 ? ($schema_warn.' table(s) missing secondary indexes.') : 'All structure checks passed.')),
    ];
    $campaign_inconsistent=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$pfx}daily_campaigns WHERE conversions > views");
    $revenue_negative=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$pfx}daily_revenue WHERE orders < 0 OR revenue < 0");
    $coupon_negative=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$pfx}daily_coupons WHERE orders < 0 OR discount_total < 0 OR revenue_total < 0");
    $quality_issues=$campaign_inconsistent+$revenue_negative+$coupon_negative;
    $checks[]=[
      'key'=>'data_quality',
      'label'=>'Data quality',
      'status'=>($quality_issues>0 ? 'warn' : 'ok'),
      'detail'=>($quality_issues>0
        ? ('campaign_conversions_gt_views='.$campaign_inconsistent.', revenue_negative='.$revenue_negative.', coupon_negative='.$coupon_negative)
        : 'No baseline data consistency issues detected.'),
    ];
    return [
      'plugin_version'=>OA_VERSION,
      'db_version'=>$db_version,
      'db_expected'=>$db_expected,
      'cron_next_ts'=>$cron_ts ? intval($cron_ts) : 0,
      'retention_days'=>$retention,
      'last_report_sent'=>intval(get_option('oa_last_report_sent',0)),
      'last_anomaly_alert_day'=>(string)get_option('oa_last_anomaly_alert_day',''),
      'last_7d'=>$last_7d,
      'checks'=>$checks,
      'migration_log'=>method_exists('OA_DB','get_migration_log') ? OA_DB::get_migration_log(12) : [],
      'schema_audit'=>$schema_audit,
      'tables'=>$table_rows,
    ];
  }

  public static function flush_dashboard_cache(){
    global $wpdb;
    $like_a=$wpdb->esc_like('_transient_oa_dash_').'%';
    $like_b=$wpdb->esc_like('_transient_timeout_oa_dash_').'%';
    $a=$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",$like_a));
    $b=$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",$like_b));
    return max(0,intval($a))+max(0,intval($b));
  }

  public static function run_maintenance_now(){
    self::cleanup_old_rows();
  }

  public static function reschedule_cron_now(){
    self::clear_cron();
    self::ensure_cron();
  }

  private static function analytics_daily_tables(){
    return ['daily_pages','daily_referrers','daily_events','daily_campaigns','daily_goals','daily_funnels','daily_revenue','daily_coupons'];
  }

  public static function erase_data_range($from,$to){
    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    $from=sanitize_text_field((string)$from);
    $to=sanitize_text_field((string)$to);
    $per_table=[]; $total=0;
    foreach(self::analytics_daily_tables() as $name){
      $tbl=$pfx.$name;
      $rows=$wpdb->query($wpdb->prepare("DELETE FROM {$tbl} WHERE day BETWEEN %s AND %s",$from,$to));
      $n=max(0,intval($rows));
      $per_table[$name]=$n;
      $total+=$n;
    }
    return ['from'=>$from,'to'=>$to,'total'=>$total,'tables'=>$per_table];
  }

  public static function erase_all_analytics_data(){
    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    $per_table=[]; $total=0;
    foreach(self::analytics_daily_tables() as $name){
      $tbl=$pfx.$name;
      $rows=$wpdb->query("DELETE FROM {$tbl}");
      $n=max(0,intval($rows));
      $per_table[$name]=$n;
      $total+=$n;
    }
    return ['total'=>$total,'tables'=>$per_table];
  }

  public static function compliance_export_bundle($from,$to,$limit=50000){
    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    $from=sanitize_text_field((string)$from);
    $to=sanitize_text_field((string)$to);
    $limit=max(1000,min(200000,intval($limit)));
    $tables=[];
    foreach(self::analytics_daily_tables() as $name){
      $count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$pfx}{$name} WHERE day BETWEEN %s AND %s",$from,$to));
      $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$pfx}{$name} WHERE day BETWEEN %s AND %s ORDER BY day DESC LIMIT %d",$from,$to,$limit), ARRAY_A);
      $tables[$name]=[
        'count'=>$count,
        'exported'=>count($rows),
        'truncated'=>($count>$limit),
        'rows'=>$rows,
      ];
    }
    foreach(['goals','funnels','funnel_steps'] as $name){
      $rows=$wpdb->get_results("SELECT * FROM {$pfx}{$name} ORDER BY 1 ASC", ARRAY_A);
      $tables[$name]=[
        'count'=>count($rows),
        'exported'=>count($rows),
        'truncated'=>false,
        'rows'=>$rows,
      ];
    }
    return [
      'generated_at'=>current_time('mysql'),
      'range'=>['from'=>$from,'to'=>$to],
      'limit_per_table'=>$limit,
      'tables'=>$tables,
    ];
  }

  public static function diagnostics_payload(){
    global $wpdb;
    $opt=get_option('oa_settings',[]);
    if (isset($opt['email_reports_to'])) {
      $email=(string)$opt['email_reports_to'];
      if ($email!=='') {
        $parts=explode('@',$email,2);
        if (count($parts)===2) $opt['email_reports_to']=substr($parts[0],0,2).'***@'.$parts[1];
      }
    }
    return [
      'generated_at'=>current_time('mysql'),
      'site_host'=>wp_parse_url(home_url(), PHP_URL_HOST),
      'environment'=>[
        'wp_version'=>get_bloginfo('version'),
        'php_version'=>PHP_VERSION,
        'mysql_version'=>method_exists($wpdb,'db_version') ? $wpdb->db_version() : '',
      ],
      'health'=>self::health_snapshot(),
      'settings'=>$opt,
    ];
  }

  public static function health_test_suite($strict=false){
    $health=self::health_snapshot();
    $tests=[];

    $db_ok=((string)($health['db_version'] ?? '')===(string)($health['db_expected'] ?? ''));
    $tests[]=[
      'id'=>'schema_version',
      'label'=>'Schema version matches expected',
      'result'=>$db_ok ? 'pass' : 'fail',
      'detail'=>$db_ok ? 'Schema version is aligned.' : ('Current '.$health['db_version'].' expected '.$health['db_expected']),
    ];

    $missing_tables=0;
    foreach((array)($health['tables'] ?? []) as $t){
      if (empty($t['exists'])) $missing_tables++;
    }
    $tests[]=[
      'id'=>'required_tables',
      'label'=>'Required tables exist',
      'result'=>($missing_tables===0 ? 'pass' : 'fail'),
      'detail'=>($missing_tables===0 ? 'All required tables are present.' : ($missing_tables.' required table(s) missing.')),
    ];

    $cron_ok=!empty($health['cron_next_ts']);
    $tests[]=[
      'id'=>'cron_scheduled',
      'label'=>'Daily cron scheduled',
      'result'=>$cron_ok ? 'pass' : 'fail',
      'detail'=>$cron_ok ? ('Next run '.wp_date('Y-m-d H:i',intval($health['cron_next_ts']))) : 'Cron is not scheduled.',
    ];

    $schema_fail=0; $schema_warn=0;
    foreach((array)($health['schema_audit'] ?? []) as $r){
      $st=(string)($r['status'] ?? 'ok');
      if ($st==='fail') $schema_fail++;
      if ($st==='warn') $schema_warn++;
    }
    $tests[]=[
      'id'=>'schema_audit',
      'label'=>'Schema structure audit',
      'result'=>($schema_fail>0 ? 'fail' : ($schema_warn>0 ? 'warn' : 'pass')),
      'detail'=>($schema_fail>0 ? ($schema_fail.' table(s) failing.') : ($schema_warn>0 ? ($schema_warn.' table(s) with warnings.') : 'No schema audit issues.')),
    ];

    $orphan_check='-';
    foreach((array)($health['checks'] ?? []) as $c){
      if ((string)($c['key'] ?? '')==='orphan_rows'){ $orphan_check=(string)($c['detail'] ?? '-'); break; }
    }
    $orphan_sum=0;
    if (preg_match_all('/=(\d+)/',$orphan_check,$all_nums) && !empty($all_nums[1])){
      foreach($all_nums[1] as $n) $orphan_sum+=intval($n);
    }
    $tests[]=[
      'id'=>'orphan_rows',
      'label'=>'No orphaned rows',
      'result'=>($orphan_sum===0 ? 'pass' : 'warn'),
      'detail'=>$orphan_check,
    ];

    $dq_status='ok'; $dq_detail='-';
    foreach((array)($health['checks'] ?? []) as $c){
      if ((string)($c['key'] ?? '')==='data_quality'){
        $dq_status=(string)($c['status'] ?? 'ok');
        $dq_detail=(string)($c['detail'] ?? '-');
        break;
      }
    }
    $tests[]=[
      'id'=>'data_quality',
      'label'=>'Data quality consistency',
      'result'=>($dq_status==='warn' ? 'warn' : 'pass'),
      'detail'=>$dq_detail,
    ];

    $cap_status='ok'; $cap_detail='-';
    foreach((array)($health['checks'] ?? []) as $c){
      if ((string)($c['key'] ?? '')==='capability_matrix'){
        $cap_status=(string)($c['status'] ?? 'ok');
        $cap_detail=(string)($c['detail'] ?? '-');
        break;
      }
    }
    $tests[]=[
      'id'=>'capability_matrix',
      'label'=>'Role capability matrix',
      'result'=>($cap_status==='fail' ? 'fail' : ($cap_status==='warn' ? 'warn' : 'pass')),
      'detail'=>$cap_detail,
    ];

    $passed=0; $failed=0; $warned=0;
    foreach($tests as $t){
      $r=(string)$t['result'];
      if ($r==='pass') $passed++;
      elseif ($r==='fail') $failed++;
      else $warned++;
    }
    if ($strict && $warned>0){
      $failed+=$warned;
      $warned=0;
    }

    return [
      'strict'=>!empty($strict),
      'summary'=>[
        'passed'=>$passed,
        'failed'=>$failed,
        'warned'=>$warned,
        'total'=>count($tests),
      ],
      'tests'=>$tests,
      'health'=>$health,
    ];
  }

  public static function dashboard($from,$to,$filters=[]){
    $filters=self::normalize_filters($filters);
    $ck='oa_dash_'.md5($from.'|'.$to.'|'.wp_json_encode($filters));
    if ($c=get_transient($ck)) return $c;
    global $wpdb; $pfx=$wpdb->prefix.'oa_';

    $metrics=self::aggregate_metrics($from,$to,$filters);
    $page_params=[$from,$to];
    $page_where=self::page_filter_sql($filters,$page_params,'path','device_class');
    $top_page=(string)$wpdb->get_var($wpdb->prepare("SELECT path FROM {$pfx}daily_pages WHERE day BETWEEN %s AND %s{$page_where} GROUP BY path ORDER BY SUM(count) DESC LIMIT 1",$page_params));
    $top_ref=(string)$wpdb->get_var($wpdb->prepare("SELECT ref_domain FROM {$pfx}daily_referrers WHERE day BETWEEN %s AND %s GROUP BY ref_domain ORDER BY SUM(count) DESC LIMIT 1",$from,$to));

    $views_params=[$from,$to];
    $views_where=self::page_filter_sql($filters,$views_params,'path','device_class');
    $views_trend=$wpdb->get_results($wpdb->prepare("SELECT day, SUM(count) as views FROM {$pfx}daily_pages WHERE day BETWEEN %s AND %s{$views_where} GROUP BY day ORDER BY day ASC",$views_params), ARRAY_A);
    $conv_params=[$from,$to];
    $conv_where=self::goals_filter_join_where($filters,$conv_params,'g','dg');
    $conv_trend=$wpdb->get_results($wpdb->prepare("SELECT dg.day as day, SUM(dg.hits) as conversions FROM {$pfx}daily_goals dg JOIN {$pfx}goals g ON g.id=dg.goal_id WHERE dg.day BETWEEN %s AND %s{$conv_where} GROUP BY dg.day ORDER BY dg.day ASC",$conv_params), ARRAY_A);
    $trend_map=[];
    foreach($views_trend as $r){
      $day=(string)$r['day'];
      $trend_map[$day]=['day'=>$day,'views'=>(int)$r['views'],'conversions'=>0];
    }
    foreach($conv_trend as $r){
      $day=(string)$r['day'];
      if (!isset($trend_map[$day])) $trend_map[$day]=['day'=>$day,'views'=>0,'conversions'=>0];
      $trend_map[$day]['conversions']=(int)$r['conversions'];
    }
    ksort($trend_map);
    $trend=array_values($trend_map);
    $pages_params=[$from,$to];
    $pages_where=self::page_filter_sql($filters,$pages_params,'path','device_class');
    $pages=$wpdb->get_results($wpdb->prepare("SELECT path, SUM(count) as views FROM {$pfx}daily_pages WHERE day BETWEEN %s AND %s{$pages_where} GROUP BY path ORDER BY views DESC LIMIT 25",$pages_params), ARRAY_A);
    $refs=$wpdb->get_results($wpdb->prepare("SELECT ref_domain, SUM(count) as views FROM {$pfx}daily_referrers WHERE day BETWEEN %s AND %s GROUP BY ref_domain ORDER BY views DESC LIMIT 25",$from,$to), ARRAY_A);
    $events_params=[$from,$to];
    $events_where=self::event_filter_sql($filters,$events_params,'event_name');
    $events=$wpdb->get_results($wpdb->prepare("SELECT event_name, SUM(count) as hits FROM {$pfx}daily_events WHERE day BETWEEN %s AND %s{$events_where} GROUP BY event_name ORDER BY hits DESC LIMIT 25",$events_params), ARRAY_A);
    $goals_params=[$from,$to];
    $goals_where=self::goals_filter_join_where($filters,$goals_params,'g','dg');
    $goals=$wpdb->get_results($wpdb->prepare("SELECT g.name, SUM(dg.hits) as hits, SUM(dg.value_sum) as value FROM {$pfx}daily_goals dg JOIN {$pfx}goals g ON g.id=dg.goal_id WHERE dg.day BETWEEN %s AND %s{$goals_where} GROUP BY dg.goal_id ORDER BY hits DESC LIMIT 25",$goals_params), ARRAY_A);

    $comparison=self::period_comparison($from,$to,$filters);
    $coupon_discount=(float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(discount_total),0) FROM {$pfx}daily_coupons WHERE day BETWEEN %s AND %s",$from,$to));
    $top_coupon=(string)$wpdb->get_var($wpdb->prepare("SELECT coupon_code FROM {$pfx}daily_coupons WHERE day BETWEEN %s AND %s GROUP BY coupon_code ORDER BY SUM(discount_total) DESC, SUM(orders) DESC LIMIT 1",$from,$to));
    $prev_coupon_discount=(float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(discount_total),0) FROM {$pfx}daily_coupons WHERE day BETWEEN %s AND %s",$comparison['bounds']['prev_from'],$comparison['bounds']['prev_to']));
    $res=[
      'kpis'=>[
        'views'=>$metrics['views'],
        'visits'=>$metrics['visits'],
        'top_page'=>$top_page ?: '-',
        'top_referrer'=>$top_ref ?: '-',
        'conversions'=>$metrics['conversions'],
        'value'=>number_format_i18n($metrics['value'],2),
        'conversion_rate'=>number_format_i18n($metrics['conversion_rate'],2).'%',
        'coupon_discount'=>number_format_i18n($coupon_discount,2),
        'top_coupon'=>$top_coupon ?: '-',
      ],
      'compare'=>[
        'label'=>'vs previous '.$comparison['bounds']['days'].'d',
        'views_pct'=>$comparison['changes']['views_pct'],
        'visits_pct'=>self::pct_change($comparison['current']['visits'],$comparison['previous']['visits']),
        'conversions_pct'=>$comparison['changes']['conversions_pct'],
        'value_pct'=>$comparison['changes']['value_pct'],
        'conversion_rate_pct'=>$comparison['changes']['conversion_rate_pct'],
        'coupon_discount_pct'=>self::pct_change($coupon_discount,$prev_coupon_discount),
      ],
      'trend'=>$trend,
      'tables'=>['pages'=>$pages,'referrers'=>$refs,'events'=>$events,'goals'=>$goals],
      'funnels'=>self::funnels_stats($from,$to,5),
      'insights'=>self::build_insights($from,$to,4,$comparison,$filters),
      'anomalies'=>self::detect_period_anomalies($from,$to,$comparison,$filters),
    ];
    set_transient($ck,$res,5*MINUTE_IN_SECONDS);
    return $res;
  }

  public static function get_goals(){ global $wpdb; $pfx=$wpdb->prefix.'oa_'; return $wpdb->get_results("SELECT * FROM {$pfx}goals ORDER BY created_at DESC", ARRAY_A); }

  public static function goals_stats($from,$to,$filters=[]){
    $filters=self::normalize_filters($filters);
    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    $params=[$from,$to];
    $goal_where=self::goals_filter_join_where($filters,$params,'g','dg');
    return $wpdb->get_results($wpdb->prepare(
      "SELECT g.*, COALESCE(SUM(dg.hits),0) as hits, COALESCE(SUM(dg.value_sum),0) as value_sum
       FROM {$pfx}goals g
       LEFT JOIN {$pfx}daily_goals dg ON dg.goal_id=g.id AND dg.day BETWEEN %s AND %s
       WHERE 1=1{$goal_where}
       GROUP BY g.id
       ORDER BY hits DESC",$params
    ), ARRAY_A);
  }

  public static function handle_goals_post(){
    if (empty($_POST['oa_action'])) return;
    if (!current_user_can('ordelix_analytics_manage')) return;
    check_admin_referer('oa_goals');
    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    $action=sanitize_key($_POST['oa_action']);
    if ($action==='add_goal'){
      $name=sanitize_text_field($_POST['name'] ?? '');
      $type=in_array(($_POST['type'] ?? ''),['page','event'],true) ? $_POST['type'] : 'page';
      $match=sanitize_text_field($_POST['match_value'] ?? '');
      $mk=sanitize_text_field($_POST['meta_key'] ?? '');
      $mv=sanitize_text_field($_POST['meta_value'] ?? '');
      $val=floatval($_POST['value'] ?? 0);
      if ($name && $match){
        $wpdb->insert("{$pfx}goals",['name'=>$name,'type'=>$type,'match_value'=>$match,'meta_key'=>$mk,'meta_value'=>$mv,'value'=>$val,'is_enabled'=>1,'created_at'=>current_time('mysql')]);
      }
    } elseif ($action==='toggle_goal'){
      $id=intval($_POST['id'] ?? 0); $en=intval($_POST['is_enabled'] ?? 0)?1:0;
      if ($id) $wpdb->update("{$pfx}goals",['is_enabled'=>$en],['id'=>$id]);
    } elseif ($action==='delete_goal'){
      $id=intval($_POST['id'] ?? 0);
      if ($id){ $wpdb->delete("{$pfx}goals",['id'=>$id]); $wpdb->delete("{$pfx}daily_goals",['goal_id'=>$id]); }
    }
  }

  public static function get_funnels_with_steps($filters=[]){
    $filters=self::normalize_filters($filters);
    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    $params=[];
    $where='';
    $parts=[];
    if (!empty($filters['path'])){ $parts[]="(fs.step_type='page' AND fs.step_value LIKE %s)"; $params[]='%'.$filters['path'].'%'; }
    if (!empty($filters['event'])){ $parts[]="(fs.step_type='event' AND fs.step_value LIKE %s)"; $params[]='%'.$filters['event'].'%'; }
    if (!empty($parts)){
      $where=" WHERE EXISTS (SELECT 1 FROM {$pfx}funnel_steps fs WHERE fs.funnel_id=f.id AND (".implode(' OR ',$parts)."))";
    }
    $sql="SELECT f.* FROM {$pfx}funnels f{$where} ORDER BY f.created_at DESC";
    $funnels=empty($params) ? $wpdb->get_results($sql, ARRAY_A) : $wpdb->get_results($wpdb->prepare($sql,$params), ARRAY_A);
    foreach($funnels as &$f){
      $f['steps']=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$pfx}funnel_steps WHERE funnel_id=%d ORDER BY step_num ASC",$f['id']), ARRAY_A);
    }
    return $funnels;
  }

  public static function funnels_stats($from,$to,$limit=10,$filters=[]){
    $filters=self::normalize_filters($filters);
    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    $params=[];
    $where=' WHERE is_enabled=1';
    $parts=[];
    if (!empty($filters['path'])){ $parts[]="(fs.step_type='page' AND fs.step_value LIKE %s)"; $params[]='%'.$filters['path'].'%'; }
    if (!empty($filters['event'])){ $parts[]="(fs.step_type='event' AND fs.step_value LIKE %s)"; $params[]='%'.$filters['event'].'%'; }
    if (!empty($parts)){
      $where.=" AND EXISTS (SELECT 1 FROM {$pfx}funnel_steps fs WHERE fs.funnel_id={$pfx}funnels.id AND (".implode(' OR ',$parts)."))";
    }
    $params[]=intval($limit);
    $sql="SELECT id,name FROM {$pfx}funnels{$where} ORDER BY created_at DESC LIMIT %d";
    $funnels=$wpdb->get_results($wpdb->prepare($sql,$params), ARRAY_A);
    $out=[];
    foreach($funnels as $f){
      $fid=(int)$f['id'];
      $steps=$wpdb->get_results($wpdb->prepare("SELECT step_num FROM {$pfx}funnel_steps WHERE funnel_id=%d ORDER BY step_num ASC",$fid), ARRAY_A);
      if (count($steps)<2) continue;
      $first=(int)$steps[0]['step_num']; $last=(int)$steps[count($steps)-1]['step_num'];
      $s1=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(hits),0) FROM {$pfx}daily_funnels WHERE funnel_id=%d AND step_num=%d AND day BETWEEN %s AND %s",$fid,$first,$from,$to));
      $sl=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(hits),0) FROM {$pfx}daily_funnels WHERE funnel_id=%d AND step_num=%d AND day BETWEEN %s AND %s",$fid,$last,$from,$to));
      $conv=($s1>0)?round(($sl/$s1)*100,1):0.0;
      $out[]=['id'=>$fid,'name'=>$f['name'],'step1'=>$s1,'step_last'=>$sl,'conversion'=>$conv];
    }
    return $out;
  }
  public static function funnels_diagnostics($from,$to,$filters=[],$limit=10){
    $filters=self::normalize_filters($filters);
    $limit=max(1,intval($limit));
    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    $bounds=self::period_bounds($from,$to);
    $funnels=self::get_funnels_with_steps($filters);
    if (!empty($funnels) && count($funnels)>$limit) $funnels=array_slice($funnels,0,$limit);
    $out=[];
    foreach($funnels as $f){
      $fid=intval($f['id'] ?? 0);
      $steps=(array)($f['steps'] ?? []);
      if ($fid<=0 || count($steps)<2) continue;
      $step_nums=[];
      foreach($steps as $step){
        $sn=intval($step['step_num'] ?? 0);
        if ($sn>0) $step_nums[]=$sn;
      }
      if (count($step_nums)<2) continue;
      sort($step_nums);
      $current_rows=$wpdb->get_results($wpdb->prepare(
        "SELECT step_num, COALESCE(SUM(hits),0) as hits
         FROM {$pfx}daily_funnels
         WHERE funnel_id=%d AND day BETWEEN %s AND %s
         GROUP BY step_num",
        $fid,$bounds['current_from'],$bounds['current_to']
      ), ARRAY_A);
      $prev_rows=$wpdb->get_results($wpdb->prepare(
        "SELECT step_num, COALESCE(SUM(hits),0) as hits
         FROM {$pfx}daily_funnels
         WHERE funnel_id=%d AND day BETWEEN %s AND %s
         GROUP BY step_num",
        $fid,$bounds['prev_from'],$bounds['prev_to']
      ), ARRAY_A);
      $curr_map=[]; $prev_map=[];
      foreach((array)$current_rows as $r) $curr_map[intval($r['step_num'])]=max(0,intval($r['hits']));
      foreach((array)$prev_rows as $r) $prev_map[intval($r['step_num'])]=max(0,intval($r['hits']));
      $first=intval($step_nums[0]);
      $last=intval($step_nums[count($step_nums)-1]);
      $s1=max(0,intval($curr_map[$first] ?? 0));
      $sl=max(0,intval($curr_map[$last] ?? 0));
      $prev_s1=max(0,intval($prev_map[$first] ?? 0));
      $prev_sl=max(0,intval($prev_map[$last] ?? 0));
      $conv=($s1>0)?round(($sl/$s1)*100,1):0.0;
      $prev_conv=($prev_s1>0)?round(($prev_sl/$prev_s1)*100,1):0.0;
      $step_metrics=[];
      $top_drop=null;
      for($i=0;$i<count($steps);$i++){
        $step=(array)$steps[$i];
        $sn=intval($step['step_num'] ?? 0);
        if ($sn<=0) continue;
        $hits=max(0,intval($curr_map[$sn] ?? 0));
        $prev_hits=max(0,intval($prev_map[$sn] ?? 0));
        $next_step=(isset($steps[$i+1]) ? (array)$steps[$i+1] : null);
        $next_sn=intval($next_step['step_num'] ?? 0);
        $next_hits=$next_sn>0 ? max(0,intval($curr_map[$next_sn] ?? 0)) : 0;
        $drop_count=($next_sn>0) ? max(0,$hits-$next_hits) : 0;
        $drop_rate=($next_sn>0 && $hits>0) ? round(($drop_count/$hits)*100,1) : 0.0;
        $advance_rate=($next_sn>0 && $hits>0) ? round(($next_hits/$hits)*100,1) : 0.0;
        $metric=[
          'step_num'=>$sn,
          'type'=>sanitize_key((string)($step['step_type'] ?? '')),
          'value'=>sanitize_text_field((string)($step['step_value'] ?? '')),
          'hits'=>$hits,
          'prev_hits'=>$prev_hits,
          'hits_delta_pct'=>self::pct_change($hits,$prev_hits),
          'next_step_num'=>$next_sn,
          'next_hits'=>$next_hits,
          'drop_count'=>$drop_count,
          'drop_rate'=>$drop_rate,
          'advance_rate'=>$advance_rate,
        ];
        if ($next_sn>0 && ($top_drop===null || $drop_rate>floatval($top_drop['drop_rate']))){
          $top_drop=$metric;
        }
        $step_metrics[]=$metric;
      }
      $drop_reason='Stable progression';
      if ($s1<=0){
        $drop_reason='No step-1 traffic in selected range';
      } elseif (!empty($top_drop)){
        $label='Step '.intval($top_drop['step_num']).' -> '.intval($top_drop['next_step_num']);
        $rate=floatval($top_drop['drop_rate']);
        if ($rate>=60){
          $drop_reason='Major drop at '.$label.' ('.number_format_i18n($rate,1).'%)';
        } elseif ($rate>=35){
          $drop_reason='Moderate drop at '.$label.' ('.number_format_i18n($rate,1).'%)';
        } else {
          $drop_reason='Healthy progression; biggest drop '.$label.' ('.number_format_i18n($rate,1).'%)';
        }
      }
      $out[]=[
        'id'=>$fid,
        'name'=>sanitize_text_field((string)($f['name'] ?? '')),
        'step1'=>$s1,
        'step_last'=>$sl,
        'conversion'=>$conv,
        'prev_conversion'=>$prev_conv,
        'conversion_delta_pct'=>self::pct_change($conv,$prev_conv),
        'top_drop_reason'=>$drop_reason,
        'top_drop_rate'=>(!empty($top_drop) ? floatval($top_drop['drop_rate']) : 0.0),
        'steps'=>$step_metrics,
        'period'=>[
          'from'=>$bounds['current_from'],
          'to'=>$bounds['current_to'],
          'prev_from'=>$bounds['prev_from'],
          'prev_to'=>$bounds['prev_to'],
        ],
      ];
    }
    return $out;
  }

  private static function parse_gap_days($meta){
    $meta=(string)$meta;
    if (preg_match('/gap_days\s*=\s*(\d+)/i',$meta,$m)) return max(1,intval($m[1]));
    return 1;
  }

  public static function retention_stats($from,$to){
    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    $rows=$wpdb->get_results($wpdb->prepare(
      "SELECT day,event_name,meta,COALESCE(SUM(count),0) as n
       FROM {$pfx}daily_events
       WHERE day BETWEEN %s AND %s
         AND event_name IN ('visitor_first_seen','visitor_returned')
       GROUP BY day,event_name,meta
       ORDER BY day ASC",
      $from,$to
    ), ARRAY_A);
    $trend=[];
    $start=strtotime($from.' 00:00:00');
    $end=strtotime($to.' 00:00:00');
    if (!$start || !$end || $end<$start){
      $end=current_time('timestamp');
      $start=$end-(29*DAY_IN_SECONDS);
    }
    for($ts=$start;$ts<=$end;$ts+=DAY_IN_SECONDS){
      $day=wp_date('Y-m-d',$ts);
      $trend[$day]=['day'=>$day,'new'=>0,'returning'=>0,'returning_rate'=>0.0];
    }
    $buckets=['1d'=>0,'2_3d'=>0,'4_7d'=>0,'8_14d'=>0,'15_plus_d'=>0];
    $total_new=0;
    $total_returning=0;
    foreach((array)$rows as $r){
      $day=(string)($r['day'] ?? '');
      if (!isset($trend[$day])) continue;
      $name=sanitize_key((string)($r['event_name'] ?? ''));
      $n=max(0,intval($r['n'] ?? 0));
      if ($name==='visitor_first_seen'){
        $trend[$day]['new']+=$n;
        $total_new+=$n;
        continue;
      }
      if ($name!=='visitor_returned') continue;
      $trend[$day]['returning']+=$n;
      $total_returning+=$n;
      $gap=self::parse_gap_days((string)($r['meta'] ?? ''));
      if ($gap<=1) $buckets['1d']+=$n;
      elseif ($gap<=3) $buckets['2_3d']+=$n;
      elseif ($gap<=7) $buckets['4_7d']+=$n;
      elseif ($gap<=14) $buckets['8_14d']+=$n;
      else $buckets['15_plus_d']+=$n;
    }
    foreach($trend as $day=>$row){
      $den=max(0,intval($row['new']))+max(0,intval($row['returning']));
      $trend[$day]['returning_rate']=$den>0 ? round((($row['returning']/$den)*100.0),1) : 0.0;
    }
    $active_days=0;
    $sum_rate=0.0;
    foreach($trend as $row){
      if ((intval($row['new'])+intval($row['returning']))<=0) continue;
      $active_days++;
      $sum_rate+=floatval($row['returning_rate']);
    }
    $avg_rate=$active_days>0 ? round($sum_rate/$active_days,1) : 0.0;
    return [
      'kpis'=>[
        'new_total'=>$total_new,
        'returning_total'=>$total_returning,
        'returning_rate'=>$avg_rate,
        'active_days'=>$active_days,
      ],
      'trend'=>array_values($trend),
      'gap_buckets'=>$buckets,
    ];
  }

  public static function handle_funnels_post(){
    if (empty($_POST['oa_action'])) return;
    if (!current_user_can('ordelix_analytics_manage')) return;
    check_admin_referer('oa_funnels');
    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    $action=sanitize_key($_POST['oa_action']);
    if ($action==='add_funnel'){
      $name=sanitize_text_field($_POST['name'] ?? '');
      if(!$name) return;
      $wpdb->insert("{$pfx}funnels",['name'=>$name,'is_enabled'=>1,'created_at'=>current_time('mysql')]);
      $fid=(int)$wpdb->insert_id;
      $types=$_POST['step_type'] ?? []; $vals=$_POST['step_value'] ?? []; $mks=$_POST['step_meta_key'] ?? []; $mvs=$_POST['step_meta_value'] ?? [];
      $sn=1;
      for($i=0;$i<count($types);$i++){
        $t=in_array($types[$i],['page','event'],true)?$types[$i]:'page';
        $v=sanitize_text_field($vals[$i] ?? '');
        if(!$v) continue;
        $mk=sanitize_text_field($mks[$i] ?? '');
        $mv=sanitize_text_field($mvs[$i] ?? '');
        $wpdb->insert("{$pfx}funnel_steps",['funnel_id'=>$fid,'step_num'=>$sn,'step_type'=>$t,'step_value'=>$v,'meta_key'=>$mk,'meta_value'=>$mv]);
        $sn++;
      }
    } elseif ($action==='toggle_funnel'){
      $id=intval($_POST['id'] ?? 0); $en=intval($_POST['is_enabled'] ?? 0)?1:0;
      if($id) $wpdb->update("{$pfx}funnels",['is_enabled'=>$en],['id'=>$id]);
    } elseif ($action==='delete_funnel'){
      $id=intval($_POST['id'] ?? 0);
      if($id){ $wpdb->delete("{$pfx}funnels",['id'=>$id]); $wpdb->delete("{$pfx}funnel_steps",['funnel_id'=>$id]); $wpdb->delete("{$pfx}daily_funnels",['funnel_id'=>$id]); }
    }
  }

  public static function campaigns($from,$to,$filters=[]){
    $filters=self::normalize_filters($filters);
    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    $params=[$from,$to];
    $where=self::campaign_filter_sql($filters,$params,'source','medium','campaign','landing_path');
    return $wpdb->get_results($wpdb->prepare(
      "SELECT source,medium,campaign,SUM(views) as views,SUM(conversions) as conversions,SUM(value_sum) as value
       FROM {$pfx}daily_campaigns WHERE day BETWEEN %s AND %s{$where}
       GROUP BY source,medium,campaign
       ORDER BY conversions DESC, views DESC LIMIT 100",$params
    ), ARRAY_A);
  }

  public static function coupons($from,$to,$filters=[]){
    $filters=self::normalize_filters($filters);
    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    $params=[$from,$to];
    $where=self::coupon_filter_sql($filters,$params,'coupon_code');
    return $wpdb->get_results($wpdb->prepare(
      "SELECT coupon_code, SUM(orders) as orders, SUM(discount_total) as discount_total, SUM(revenue_total) as revenue_total
       FROM {$pfx}daily_coupons WHERE day BETWEEN %s AND %s{$where}
       GROUP BY coupon_code
       ORDER BY discount_total DESC, orders DESC LIMIT 150",
      $params
    ), ARRAY_A);
  }

  public static function coupons_daily($from,$to,$filters=[]){
    $filters=self::normalize_filters($filters);
    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    $params=[$from,$to];
    $where=self::coupon_filter_sql($filters,$params,'coupon_code');
    return $wpdb->get_results($wpdb->prepare(
      "SELECT day, SUM(orders) as orders, SUM(discount_total) as discount_total, SUM(revenue_total) as revenue_total
       FROM {$pfx}daily_coupons
       WHERE day BETWEEN %s AND %s{$where}
       GROUP BY day
       ORDER BY day DESC",
      $params
    ), ARRAY_A);
  }

  public static function revenue($from,$to,$filters=[]){
    $filters=self::normalize_filters($filters);
    global $wpdb; $pfx=$wpdb->prefix.'oa_';
    if (!empty($filters['coupon'])){
      $params=[$from,$to];
      $where=self::coupon_filter_sql($filters,$params,'coupon_code');
      return $wpdb->get_results($wpdb->prepare(
        "SELECT day, SUM(orders) as orders, SUM(revenue_total) as revenue
         FROM {$pfx}daily_coupons
         WHERE day BETWEEN %s AND %s{$where}
         GROUP BY day
         ORDER BY day DESC",
        $params
      ), ARRAY_A);
    }
    return $wpdb->get_results($wpdb->prepare("SELECT day,orders,revenue FROM {$pfx}daily_revenue WHERE day BETWEEN %s AND %s ORDER BY day DESC",$from,$to), ARRAY_A);
  }
}
