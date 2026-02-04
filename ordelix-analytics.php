<?php
/**
 * Plugin Name: Ordelix Analytics
 * Description: Privacy-first, lightweight, local-first analytics for WordPress (no external services).
 * Version: 0.4.8
 * Author: Ordelix
 * Text Domain: ordelix-analytics
 */
if (!defined('ABSPATH')) exit;

define('OA_VERSION', '0.4.8');
define('OA_PLUGIN_FILE', __FILE__);
define('OA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OA_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once OA_PLUGIN_DIR . 'includes/class-oa-util.php';
require_once OA_PLUGIN_DIR . 'includes/class-oa-db.php';
require_once OA_PLUGIN_DIR . 'includes/class-oa-rest.php';
require_once OA_PLUGIN_DIR . 'includes/class-oa-tracker.php';
require_once OA_PLUGIN_DIR . 'includes/class-oa-admin.php';
require_once OA_PLUGIN_DIR . 'includes/class-oa-reports.php';

function oa_init_plugin() {
  OA_DB::init();
  OA_REST::init();
  OA_Tracker::init();
  OA_Admin::init();
  OA_Reports::init();
  if (defined('WP_CLI') && WP_CLI) {
    require_once OA_PLUGIN_DIR . 'includes/class-oa-cli.php';
    OA_CLI::init();
  }
  if (class_exists('WooCommerce')) {
    require_once OA_PLUGIN_DIR . 'modules/woocommerce.php';
    OA_WooCommerce::init();
  }
}
add_action('plugins_loaded', 'oa_init_plugin');

function oa_activate() {
  OA_DB::install_or_upgrade();
  OA_Admin::ensure_caps();
  OA_Reports::ensure_cron();
}
register_activation_hook(__FILE__, 'oa_activate');

function oa_deactivate() { OA_Reports::clear_cron(); }
register_deactivation_hook(__FILE__, 'oa_deactivate');


function oa_register_shortcodes() {
  add_shortcode('ordelix_analytics_consent', function($atts){
    $atts = shortcode_atts(['style'=>'buttons'], $atts);
    $opt = get_option('oa_settings', []);
    $consent_cookie = sanitize_key($opt['consent_cookie'] ?? 'oa_consent');
    $optout_cookie = sanitize_key($opt['optout_cookie'] ?? 'oa_optout');
    ob_start(); ?>
    <div class="oa-consent-widget" data-consent-cookie="<?php echo esc_attr($consent_cookie); ?>" data-optout-cookie="<?php echo esc_attr($optout_cookie); ?>">
      <button type="button" class="oa-consent-allow">Allow analytics</button>
      <button type="button" class="oa-consent-deny">Disable analytics</button>
    </div>
    <script>
      (function(){
        function setCookie(n,v,days){
          var d=new Date(); d.setTime(d.getTime()+days*24*60*60*1000);
          document.cookie=n+'='+encodeURIComponent(v)+'; Path=/; SameSite=Lax; Expires='+d.toUTCString();
        }
        var w=document.currentScript && document.currentScript.previousElementSibling;
        if(!w || !w.classList || !w.classList.contains('oa-consent-widget')) return;
        var cc=w.getAttribute('data-consent-cookie')||'oa_consent';
        var oc=w.getAttribute('data-optout-cookie')||'oa_optout';
        w.querySelector('.oa-consent-allow').addEventListener('click', function(){
          setCookie(oc,'0',365); setCookie(cc,'1',365);
          if(window.ordelixAnalyticsSetConsent) window.ordelixAnalyticsSetConsent(true);
          alert('Analytics enabled.');
        });
        w.querySelector('.oa-consent-deny').addEventListener('click', function(){
          setCookie(cc,'0',365); setCookie(oc,'1',365);
          if(window.ordelixAnalyticsSetConsent) window.ordelixAnalyticsSetConsent(false);
          alert('Analytics disabled.');
        });
      })();
    </script>
    <?php return ob_get_clean();
  });
}
add_action('init', 'oa_register_shortcodes');
