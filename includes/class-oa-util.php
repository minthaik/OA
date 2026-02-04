<?php
if (!defined('ABSPATH')) exit;
class OA_Util {
  public static function today_ymd() {
    $tz = wp_timezone();
    return (new DateTime('now', $tz))->format('Y-m-d');
  }
  public static function normalize_path($path, $strip_query=true) {
    $path = is_string($path)?trim($path):'';
    if ($path==='') return '/';
    if ($path[0] !== '/') $path = '/'.$path;
    $path = preg_replace('/#.*/','',$path);
    if ($strip_query) $path = preg_replace('/\?.*/','',$path);
    $path = preg_replace('#/+#','/',$path);
    return $path===''?'/':$path;
  }
  public static function ref_domain($ref) {
    if (!is_string($ref) || $ref==='') return '';
    $host = wp_parse_url($ref, PHP_URL_HOST);
    if (!$host) return '';
    $host = strtolower($host);
    return preg_replace('/^www\./','',$host);
  }
  public static function device_class_from_ua($ua) {
    $ua = strtolower((string)$ua);
    if ($ua==='') return 'unknown';
    if (strpos($ua,'ipad')!==false || strpos($ua,'tablet')!==false) return 'tablet';
    if (strpos($ua,'mobile')!==false || strpos($ua,'android')!==false || strpos($ua,'iphone')!==false) return 'mobile';
    return 'desktop';
  }
  public static function hash_key($s){ return substr(sha1((string)$s),0,16); }
  public static function client_ip() {
    $opt=get_option('oa_settings',[]);
    $trust_proxy=!empty($opt['trust_proxy_headers']);
    $candidates=[];
    if ($trust_proxy) {
      if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) $candidates[]=(string)$_SERVER['HTTP_CF_CONNECTING_IP'];
      if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $p=explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
        if (!empty($p[0])) $candidates[]=trim($p[0]);
      }
      if (!empty($_SERVER['HTTP_X_REAL_IP'])) $candidates[]=(string)$_SERVER['HTTP_X_REAL_IP'];
    }
    if (!empty($_SERVER['REMOTE_ADDR'])) $candidates[]=(string)$_SERVER['REMOTE_ADDR'];
    foreach($candidates as $ip){
      $ip=trim($ip);
      if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return '';
  }
  public static function truncated_ip_hash($ip) {
    $ip=(string)$ip; if ($ip==='') return '';
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
      $parts=explode('.',$ip); if (count($parts)===4) $parts[3]='0'; $ip=implode('.',$parts);
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
      $parts=explode(':',$ip); $ip=implode(':', array_slice($parts,0,3)).'::';
    }
    return substr(sha1($ip.wp_salt('auth')),0,16);
  }
  public static function can_track() {
    $opt=get_option('oa_settings',[]);
    if (!empty($opt['respect_dnt']) && !empty($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT']==='1') return false;
    if (is_user_logged_in() && empty($opt['track_logged_in'])) return false;
    return (bool) apply_filters('ordelix_analytics_can_track', true);
  }
  public static function sampled_in() {
    $opt=get_option('oa_settings',[]);
    $n=intval($opt['sample_rate'] ?? 1);
    if ($n<=1) return true;
    return wp_rand(1,$n)===1;
  }
  public static function rate_limit_ok($bucket, $limit_per_minute) {
    $limit_per_minute=max(1,intval($limit_per_minute));
    $ip=self::client_ip(); if ($ip==='') return true;
    $key='oa_rl_'.$bucket.'_'.md5($ip);
    $count=(int)get_transient($key);
    if ($count >= $limit_per_minute) return false;
    set_transient($key, $count+1, MINUTE_IN_SECONDS);
    return true;
  }
}
