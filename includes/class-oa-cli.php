<?php
if (!defined('ABSPATH')) exit;

class OA_CLI {
  public static function init(){
    if (!defined('WP_CLI') || !WP_CLI || !class_exists('WP_CLI')) return;
    WP_CLI::add_command('ordelix', [__CLASS__,'command']);
  }

  /**
   * Manage Ordelix Analytics maintenance and diagnostics.
   *
   * ## OPTIONS
   *
   * <action>
   * : Action to run: health|data-quality|cleanup|cache-flush|schema-repair|cron-reschedule|caps-repair|table-optimize|diagnostics|self-test|regression-smoke|data-export|data-erase-range|data-erase-all
   *
   * [--format=<format>]
   * : Output format for read actions. json|table
   * ---
   * default: table
   * ---
   *
   * [--strict]
   * : Treat warnings as failures for self-test.
   *
   * [--from=<ymd>]
   * : Start date (YYYY-MM-DD) for range actions.
   *
   * [--to=<ymd>]
   * : End date (YYYY-MM-DD) for range actions.
   *
   * [--confirm=<value>]
   * : Required for destructive all-data erase. Use --confirm=YES
   */
  public static function command($args,$assoc){
    $action=isset($args[0]) ? sanitize_key($args[0]) : 'health';
    $format=isset($assoc['format']) ? sanitize_key($assoc['format']) : 'table';
    if (!in_array($format,['table','json'],true)) $format='table';
    $strict=!empty($assoc['strict']);

    if ($action==='cleanup'){
      OA_Reports::run_maintenance_now();
      WP_CLI::success('Cleanup completed.');
      return;
    }
    if ($action==='cache-flush'){
      $n=OA_Reports::flush_dashboard_cache();
      WP_CLI::success('Dashboard cache cleared ('.$n.' option row(s) removed).');
      return;
    }
    if ($action==='schema-repair'){
      OA_DB::install_or_upgrade('cli_repair');
      WP_CLI::success('Schema repair completed.');
      return;
    }
    if ($action==='cron-reschedule'){
      OA_Reports::reschedule_cron_now();
      WP_CLI::success('Cron rescheduled.');
      return;
    }
    if ($action==='caps-repair'){
      OA_Admin::ensure_caps();
      WP_CLI::success('Capabilities repaired.');
      return;
    }
    if ($action==='table-optimize'){
      $res=OA_Reports::optimize_tables();
      $msg='Tables optimized: '.intval($res['optimized'] ?? 0).'; skipped: '.intval($res['skipped'] ?? 0).'.';
      if (!empty($res['failed'])) $msg.=' Failed: '.implode(', ',(array)$res['failed']).'.';
      WP_CLI::success($msg);
      return;
    }
    if ($action==='health'){
      $health=OA_Reports::health_snapshot();
      if ($format==='json'){
        WP_CLI::line(wp_json_encode($health, JSON_PRETTY_PRINT));
        return;
      }
      $rows=[];
      foreach((array)$health['checks'] as $c){
        $rows[]=[
          'check'=>(string)$c['label'],
          'status'=>strtoupper((string)$c['status']),
          'detail'=>(string)$c['detail'],
        ];
      }
      WP_CLI\Utils\format_items('table',$rows,['check','status','detail']);
      return;
    }
    if ($action==='data-quality'){
      list($from,$to)=self::range_from_assoc($assoc);
      $audit=OA_Reports::data_quality_audit($from,$to,50);
      if ($format==='json'){
        WP_CLI::line(wp_json_encode($audit, JSON_PRETTY_PRINT));
        return;
      }
      $rows=[];
      foreach((array)($audit['findings'] ?? []) as $f){
        $rows[]=[
          'finding'=>(string)($f['label'] ?? '-'),
          'status'=>strtoupper((string)($f['status'] ?? 'ok')),
          'detail'=>(string)($f['detail'] ?? ''),
        ];
      }
      WP_CLI\Utils\format_items('table',$rows,['finding','status','detail']);
      return;
    }
    if ($action==='diagnostics'){
      $d=OA_Reports::diagnostics_payload();
      if ($format==='json'){
        WP_CLI::line(wp_json_encode($d, JSON_PRETTY_PRINT));
        return;
      }
      $rows=[
        ['key'=>'generated_at','value'=>(string)($d['generated_at'] ?? '-')],
        ['key'=>'site_host','value'=>(string)($d['site_host'] ?? '-')],
        ['key'=>'wp_version','value'=>(string)($d['environment']['wp_version'] ?? '-')],
        ['key'=>'php_version','value'=>(string)($d['environment']['php_version'] ?? '-')],
        ['key'=>'mysql_version','value'=>(string)($d['environment']['mysql_version'] ?? '-')],
      ];
      WP_CLI\Utils\format_items('table',$rows,['key','value']);
      return;
    }
    if ($action==='self-test'){
      $suite=OA_Reports::health_test_suite($strict);
      if ($format==='json'){
        WP_CLI::line(wp_json_encode($suite, JSON_PRETTY_PRINT));
      } else {
        $rows=[];
        foreach((array)$suite['tests'] as $t){
          $rows[]=[
            'test'=>(string)$t['label'],
            'result'=>strtoupper((string)$t['result']),
            'detail'=>(string)$t['detail'],
          ];
        }
        WP_CLI\Utils\format_items('table',$rows,['test','result','detail']);
      }
      $sum=(array)$suite['summary'];
      if (intval($sum['failed'] ?? 0)>0){
        WP_CLI::halt(1, 'Self-test failed (passed '.intval($sum['passed'] ?? 0).', failed '.intval($sum['failed'] ?? 0).', warned '.intval($sum['warned'] ?? 0).').');
      }
      WP_CLI::success('Self-test passed (passed '.intval($sum['passed'] ?? 0).', warned '.intval($sum['warned'] ?? 0).').');
      return;
    }
    if ($action==='regression-smoke'){
      list($from,$to)=self::range_from_assoc($assoc);
      $tests=[];

      $health_suite=OA_Reports::health_test_suite(false);
      $hs=(array)($health_suite['summary'] ?? []);
      $failed=intval($hs['failed'] ?? 0);
      $warned=intval($hs['warned'] ?? 0);
      $tests[]=[
        'id'=>'health_self_test',
        'label'=>'Health self-test baseline',
        'result'=>($failed>0 ? 'fail' : ($warned>0 ? 'warn' : 'pass')),
        'detail'=>'passed='.intval($hs['passed'] ?? 0).', failed='.$failed.', warned='.$warned,
      ];

      $dashboard=OA_Reports::dashboard($from,$to,[]);
      $dashboard_ok=(is_array($dashboard) && isset($dashboard['kpis']) && isset($dashboard['trend']) && isset($dashboard['tables']));
      $tests[]=[
        'id'=>'dashboard_contract',
        'label'=>'Dashboard payload contract',
        'result'=>($dashboard_ok ? 'pass' : 'fail'),
        'detail'=>($dashboard_ok ? 'kpis/trend/tables present' : 'Missing expected dashboard keys'),
      ];

      $diag=OA_Reports::diagnostics_payload();
      $diag_ok=(is_array($diag) && !empty($diag['generated_at']) && isset($diag['environment']) && isset($diag['health']) && isset($diag['settings']));
      $tests[]=[
        'id'=>'diagnostics_contract',
        'label'=>'Diagnostics payload contract',
        'result'=>($diag_ok ? 'pass' : 'fail'),
        'detail'=>($diag_ok ? 'generated_at/environment/health/settings present' : 'Missing diagnostics keys'),
      ];

      $bundle=OA_Reports::compliance_export_bundle($from,$to,2000);
      $required_tables=['daily_pages','daily_referrers','daily_events','daily_campaigns','daily_goals','daily_funnels','daily_revenue','daily_coupons','goals','funnels','funnel_steps'];
      $missing=[];
      foreach($required_tables as $name){ if (!isset($bundle['tables'][$name])) $missing[]=$name; }
      $bundle_ok=empty($missing);
      $tests[]=[
        'id'=>'compliance_export_contract',
        'label'=>'Compliance export contract',
        'result'=>($bundle_ok ? 'pass' : 'fail'),
        'detail'=>($bundle_ok ? 'All required tables exported' : ('Missing: '.implode(', ',$missing))),
      ];

      $cap_status='ok'; $cap_detail='Capability check not found.';
      $health=(array)($diag['health'] ?? []);
      foreach((array)($health['checks'] ?? []) as $c){
        if ((string)($c['key'] ?? '')==='capability_matrix'){
          $cap_status=(string)($c['status'] ?? 'ok');
          $cap_detail=(string)($c['detail'] ?? '-');
          break;
        }
      }
      $tests[]=[
        'id'=>'capability_matrix',
        'label'=>'Capability matrix check',
        'result'=>($cap_status==='fail' ? 'fail' : ($cap_status==='warn' ? 'warn' : 'pass')),
        'detail'=>$cap_detail,
      ];

      $opt=get_option('oa_settings',[]);
      $attr_mode=sanitize_key((string)($opt['attribution_mode'] ?? 'first_touch'));
      if (!in_array($attr_mode,['first_touch','last_touch'],true)) $attr_mode='first_touch';
      $attr_valid=in_array($attr_mode,['first_touch','last_touch'],true);
      $tests[]=[
        'id'=>'attribution_mode_setting',
        'label'=>'Attribution mode setting',
        'result'=>($attr_valid ? 'pass' : 'fail'),
        'detail'=>($attr_valid ? ('Configured as '.$attr_mode) : 'Invalid attribution mode setting'),
      ];

      $export_attr_mode='';
      $export_ok=false;
      if (class_exists('WP_REST_Request')){
        $request=new WP_REST_Request('GET','/ordelix-analytics/v1/export');
        $request->set_param('type','campaigns');
        $request->set_param('from',$from);
        $request->set_param('to',$to);
        $response=OA_REST::export($request);
        if ($response instanceof WP_REST_Response){
          $payload=(array)$response->get_data();
          $export_attr_mode=sanitize_key((string)($payload['meta']['attribution_mode'] ?? ''));
          $export_ok=(
            !empty($payload['ok'])
            && isset($payload['meta'])
            && is_array($payload['meta'])
            && in_array($export_attr_mode,['first_touch','last_touch'],true)
            && $export_attr_mode===$attr_mode
          );
        }
      }
      $tests[]=[
        'id'=>'campaign_export_metadata',
        'label'=>'Campaign export attribution metadata',
        'result'=>($export_ok ? 'pass' : 'fail'),
        'detail'=>($export_ok ? ('meta.attribution_mode='.$export_attr_mode) : 'Campaign export metadata is missing/invalid'),
      ];

      $dq=OA_Reports::data_quality_audit($from,$to,20);
      $dq_summary=(array)($dq['summary'] ?? []);
      $dq_failed=max(0,intval($dq_summary['failed'] ?? 0));
      $dq_warned=max(0,intval($dq_summary['warned'] ?? 0));
      $tests[]=[
        'id'=>'data_quality_audit',
        'label'=>'Data quality audit',
        'result'=>($dq_failed>0 ? 'fail' : ($dq_warned>0 ? 'warn' : 'pass')),
        'detail'=>'failed='.$dq_failed.', warned='.$dq_warned,
      ];

      $pages_paged=OA_Reports::dashboard_pages_paged($from,$to,[],1,10);
      $paged_ok=(
        is_array($pages_paged)
        && isset($pages_paged['rows'])
        && is_array($pages_paged['rows'])
        && isset($pages_paged['total'])
        && isset($pages_paged['page'])
        && isset($pages_paged['per_page'])
        && isset($pages_paged['total_pages'])
      );
      $tests[]=[
        'id'=>'pagination_contract',
        'label'=>'Paged report contract',
        'result'=>($paged_ok ? 'pass' : 'fail'),
        'detail'=>($paged_ok ? 'rows,total,page,per_page,total_pages present' : 'Paged report payload shape is invalid'),
      ];

      $ingestion=self::run_ingestion_smoke();
      $tests[]=[
        'id'=>'ingestion_pipeline',
        'label'=>'Ingestion pipeline smoke',
        'result'=>(string)($ingestion['result'] ?? 'fail'),
        'detail'=>(string)($ingestion['detail'] ?? 'Unknown ingestion smoke failure'),
      ];

      $summary=self::summarize_tests($tests);
      if ($format==='json'){
        WP_CLI::line(wp_json_encode([
          'ok'=>($summary['failed']===0 && (!$strict || $summary['warned']===0)),
          'strict'=>!empty($strict),
          'range'=>['from'=>$from,'to'=>$to],
          'summary'=>$summary,
          'tests'=>$tests,
        ], JSON_PRETTY_PRINT));
      } else {
        $rows=[];
        foreach($tests as $t){
          $rows[]=[
            'test'=>(string)$t['label'],
            'result'=>strtoupper((string)$t['result']),
            'detail'=>(string)$t['detail'],
          ];
        }
        WP_CLI\Utils\format_items('table',$rows,['test','result','detail']);
      }
      if ($summary['failed']>0 || ($strict && $summary['warned']>0)){
        WP_CLI::halt(1,'Regression smoke failed (passed '.$summary['passed'].', failed '.$summary['failed'].', warned '.$summary['warned'].').');
      }
      WP_CLI::success('Regression smoke passed (passed '.$summary['passed'].', warned '.$summary['warned'].').');
      return;
    }
    if ($action==='data-export'){
      list($from,$to)=self::range_from_assoc($assoc);
      $bundle=OA_Reports::compliance_export_bundle($from,$to);
      if ($format==='json'){
        WP_CLI::line(wp_json_encode($bundle, JSON_PRETTY_PRINT));
        return;
      }
      $rows=[];
      foreach((array)($bundle['tables'] ?? []) as $name=>$t){
        $rows[]=[
          'table'=>(string)$name,
          'count'=>intval($t['count'] ?? 0),
          'exported'=>intval($t['exported'] ?? 0),
          'truncated'=>!empty($t['truncated']) ? 'yes' : 'no',
        ];
      }
      WP_CLI\Utils\format_items('table',$rows,['table','count','exported','truncated']);
      return;
    }
    if ($action==='data-erase-range'){
      list($from,$to)=self::range_from_assoc($assoc);
      $res=OA_Reports::erase_data_range($from,$to);
      WP_CLI::success('Erased '.intval($res['total'] ?? 0).' row(s) for '.$from.' -> '.$to.'.');
      return;
    }
    if ($action==='data-erase-all'){
      $confirm=isset($assoc['confirm']) ? strtoupper(sanitize_text_field($assoc['confirm'])) : '';
      if ($confirm!=='YES'){
        WP_CLI::error('This action is destructive. Re-run with --confirm=YES');
      }
      $res=OA_Reports::erase_all_analytics_data();
      WP_CLI::success('Erased all analytics daily rows ('.intval($res['total'] ?? 0).' row(s)).');
      return;
    }

    WP_CLI::error('Unknown action. Use: health, data-quality, cleanup, cache-flush, schema-repair, cron-reschedule, caps-repair, table-optimize, diagnostics, self-test, regression-smoke, data-export, data-erase-range, data-erase-all');
  }

  private static function run_ingestion_smoke(){
    if (!class_exists('WP_REST_Request') || !class_exists('WP_REST_Response')){
      return ['result'=>'fail','detail'=>'WP REST classes are unavailable.'];
    }
    global $wpdb;
    $pfx=$wpdb->prefix.'oa_';
    $day=OA_Util::today_ymd();
    $marker='cli'.strtolower(wp_generate_password(8,false,false));
    $path='/oa-smoke/'.$marker;
    $event_name='oa_smoke_'.$marker;
    $event_meta='suite='.$marker;
    $source='smoke';
    $medium='cli';
    $campaign='phase1_'.$marker;
    $path_hash=OA_Util::hash_key($path);
    $event_hash=OA_Util::hash_key($event_name);
    $meta_hash=OA_Util::hash_key($event_meta);
    $camp_hash=OA_Util::hash_key(strtolower($source.'|'.$medium.'|'.$campaign));
    $landing_hash=OA_Util::hash_key($path);
    $device='desktop';

    $settings_before=get_option('oa_settings',[]);
    $settings=is_array($settings_before) ? $settings_before : [];
    $settings['enabled']=1;
    $settings['sample_rate']=1;
    $settings['rate_limit_per_min']=9999;
    $settings['track_logged_in']=1;
    update_option('oa_settings',$settings,false);

    $page_before=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(count),0) FROM {$pfx}daily_pages WHERE day=%s AND path_hash=%s AND device_class=%s",$day,$path_hash,$device));
    $camp_before=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(views),0) FROM {$pfx}daily_campaigns WHERE day=%s AND camp_hash=%s AND landing_hash=%s",$day,$camp_hash,$landing_hash));
    $camp_rows_before=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$pfx}daily_campaigns WHERE day=%s AND camp_hash=%s AND landing_hash=%s",$day,$camp_hash,$landing_hash));
    $event_before=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(count),0) FROM {$pfx}daily_events WHERE day=%s AND event_hash=%s AND meta_hash=%s",$day,$event_hash,$meta_hash));
    $event_rows_before=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$pfx}daily_events WHERE day=%s AND event_hash=%s AND meta_hash=%s",$day,$event_hash,$meta_hash));

    $call_collect=function($payload){
      $req=new WP_REST_Request('POST','/ordelix-analytics/v1/collect');
      $req->set_header('content-type','application/json');
      $req->set_body(wp_json_encode($payload));
      $res=OA_REST::collect($req);
      if (!($res instanceof WP_REST_Response)) return ['ok'=>false,'detail'=>'Non-REST response'];
      $data=(array)$res->get_data();
      if (!empty($data['disabled'])) return ['ok'=>false,'detail'=>'Collector disabled'];
      if (!empty($data['skipped'])) return ['ok'=>false,'detail'=>'Collector skipped event'];
      if (!empty($data['rate_limited'])) return ['ok'=>false,'detail'=>'Collector rate-limited'];
      if (empty($data['ok'])) return ['ok'=>false,'detail'=>'Collector returned not-ok payload'];
      return ['ok'=>true,'detail'=>'ok'];
    };

    $pv_payload=[
      't'=>'pv',
      'p'=>$path,
      'd'=>$device,
      'utm'=>['s'=>$source,'m'=>$medium,'c'=>$campaign],
    ];
    $ev_payload=[
      't'=>'ev',
      'p'=>$path,
      'd'=>$device,
      'utm'=>['s'=>$source,'m'=>$medium,'c'=>$campaign],
      'e'=>['n'=>$event_name,'k'=>$event_meta],
      'v'=>0,
    ];
    $r1=$call_collect($pv_payload);
    $r2=$call_collect($pv_payload);
    $r3=$call_collect($ev_payload);
    $r4=$call_collect($ev_payload);

    $page_after=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(count),0) FROM {$pfx}daily_pages WHERE day=%s AND path_hash=%s AND device_class=%s",$day,$path_hash,$device));
    $camp_after=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(views),0) FROM {$pfx}daily_campaigns WHERE day=%s AND camp_hash=%s AND landing_hash=%s",$day,$camp_hash,$landing_hash));
    $camp_rows_after=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$pfx}daily_campaigns WHERE day=%s AND camp_hash=%s AND landing_hash=%s",$day,$camp_hash,$landing_hash));
    $event_after=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(count),0) FROM {$pfx}daily_events WHERE day=%s AND event_hash=%s AND meta_hash=%s",$day,$event_hash,$meta_hash));
    $event_rows_after=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$pfx}daily_events WHERE day=%s AND event_hash=%s AND meta_hash=%s",$day,$event_hash,$meta_hash));

    $ok_calls=(!empty($r1['ok']) && !empty($r2['ok']) && !empty($r3['ok']) && !empty($r4['ok']));
    $page_delta=max(0,$page_after-$page_before);
    $camp_delta=max(0,$camp_after-$camp_before);
    $event_delta=max(0,$event_after-$event_before);
    $camp_row_growth=max(0,$camp_rows_after-$camp_rows_before);
    $event_row_growth=max(0,$event_rows_after-$event_rows_before);

    // Keep smoke runs idempotent by deleting synthetic rows after assertions.
    $wpdb->query($wpdb->prepare("DELETE FROM {$pfx}daily_pages WHERE day=%s AND path_hash=%s AND device_class=%s",$day,$path_hash,$device));
    $wpdb->query($wpdb->prepare("DELETE FROM {$pfx}daily_campaigns WHERE day=%s AND camp_hash=%s AND landing_hash=%s",$day,$camp_hash,$landing_hash));
    $wpdb->query($wpdb->prepare("DELETE FROM {$pfx}daily_events WHERE day=%s AND event_hash=%s AND meta_hash=%s",$day,$event_hash,$meta_hash));
    update_option('oa_settings',$settings_before,false);

    if (!$ok_calls){
      $reasons=[];
      foreach([$r1,$r2,$r3,$r4] as $idx=>$row){ if (empty($row['ok'])) $reasons[]='call'.($idx+1).': '.($row['detail'] ?? 'fail'); }
      return ['result'=>'fail','detail'=>'Collector call failure ('.implode('; ',$reasons).').'];
    }
    if ($page_delta!==2 || $camp_delta!==2 || $event_delta!==2){
      return ['result'=>'fail','detail'=>'Unexpected deltas page='.$page_delta.', campaign_views='.$camp_delta.', events='.$event_delta.' (expected 2/2/2).'];
    }
    if ($camp_row_growth!==1 || $event_row_growth!==1){
      return ['result'=>'fail','detail'=>'Upsert guard failed camp_row_growth='.$camp_row_growth.', event_row_growth='.$event_row_growth.' (expected 1/1).'];
    }
    return ['result'=>'pass','detail'=>'Page/event ingestion and upsert guardrails are stable.'];
  }

  private static function summarize_tests($tests){
    $summary=['passed'=>0,'failed'=>0,'warned'=>0,'total'=>0];
    foreach((array)$tests as $t){
      $summary['total']++;
      $r=(string)($t['result'] ?? 'pass');
      if ($r==='fail') $summary['failed']++;
      elseif ($r==='warn') $summary['warned']++;
      else $summary['passed']++;
    }
    return $summary;
  }

  private static function range_from_assoc($assoc){
    $now=current_time('timestamp');
    $default_to=wp_date('Y-m-d',$now);
    $default_from=wp_date('Y-m-d',$now-(29*DAY_IN_SECONDS));
    $from=isset($assoc['from']) ? sanitize_text_field((string)$assoc['from']) : $default_from;
    $to=isset($assoc['to']) ? sanitize_text_field((string)$assoc['to']) : $default_to;
    foreach(['from'=>$from,'to'=>$to] as $k=>$v){
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)) WP_CLI::error('Invalid --'.$k.' value. Expected YYYY-MM-DD.');
      if (!wp_checkdate(substr($v,5,2), substr($v,8,2), substr($v,0,4), $v)) WP_CLI::error('Invalid --'.$k.' calendar date.');
    }
    if (strtotime($from)>strtotime($to)) WP_CLI::error('--from must be <= --to');
    return [$from,$to];
  }
}
