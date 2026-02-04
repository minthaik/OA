<?php
if (!defined('ABSPATH')) exit;
class OA_Tracker {
  public static function init(){ add_action('wp_enqueue_scripts',[__CLASS__,'enqueue']); }
  public static function enqueue(){
    $opt=get_option('oa_settings',[]);
    if (empty($opt['enabled'])) return;
    if (!OA_Util::can_track()) return;
    wp_register_script('ordelix-analytics-tracker', OA_PLUGIN_URL.'assets/tracker.js', [], OA_VERSION, true);
    $cfg=[
      'endpoint'=>esc_url_raw(rest_url('ordelix-analytics/v1/collect')),
      'stripQuery'=>!empty($opt['strip_query']),
      'autoEvents'=>!empty($opt['auto_events']),
      'autoOutbound'=>!empty($opt['auto_outbound']),
      'autoDownloads'=>!empty($opt['auto_downloads']),
      'autoTel'=>!empty($opt['auto_tel']),
      'autoMailto'=>!empty($opt['auto_mailto']),
      'autoForms'=>!empty($opt['auto_forms']),
      'utmAttributionDays'=>max(1,min(365,intval($opt['utm_attribution_days'] ?? 30))),
      'consentMode'=>sanitize_key($opt['consent_mode'] ?? 'off'),
      'consentCookie'=>sanitize_key($opt['consent_cookie'] ?? 'oa_consent'),
      'optoutCookie'=>sanitize_key($opt['optout_cookie'] ?? 'oa_optout'),
    ];
    wp_add_inline_script('ordelix-analytics-tracker','window.ordelixAnalyticsCfg='.wp_json_encode($cfg).';','before');
    wp_enqueue_script('ordelix-analytics-tracker');
  }
}
