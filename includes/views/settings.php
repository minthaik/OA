<?php if(!defined('ABSPATH')) exit; ?>
<div class="wrap oa-wrap oa-page-settings">
  <?php
  ob_start();
  settings_errors('oa_settings');
  $oa_settings_notices=ob_get_clean();
  if (!empty($oa_settings_notices)) echo str_replace('class="','class="oa-notice ', $oa_settings_notices);
  ?>
  <?php if (!empty($_GET['settings-updated'])): ?>
    <div class="notice notice-success is-dismissible oa-notice"><p><?php esc_html_e('Settings saved.','ordelix-analytics'); ?></p></div>
  <?php endif; ?>
  <?php if (!empty($compliance_notice)): ?>
    <div class="notice notice-success is-dismissible oa-notice"><p><?php echo esc_html($compliance_notice); ?></p></div>
  <?php endif; ?>
  <?php if (!empty($compliance_error)): ?>
    <div class="notice notice-error is-dismissible oa-notice"><p><?php echo esc_html($compliance_error); ?></p></div>
  <?php endif; ?>
  <?php if (!empty($segments_notice)): ?>
    <div class="notice notice-success is-dismissible oa-notice"><p><?php echo esc_html($segments_notice); ?></p></div>
  <?php endif; ?>
  <?php if (!empty($segments_error)): ?>
    <div class="notice notice-error is-dismissible oa-notice"><p><?php echo esc_html($segments_error); ?></p></div>
  <?php endif; ?>
  <h1>Settings</h1>
  <form method="post" action="options.php" class="oa-settings-form">
    <?php settings_fields('oa_settings_group'); ?>
    <div class="oa-tabs" role="tablist" aria-label="Settings tabs">
      <button type="button" class="oa-tab is-active" data-tab="tracking" id="oa-tab-tracking" role="tab" aria-selected="true" aria-controls="oa-panel-tracking">Tracking</button>
      <button type="button" class="oa-tab" data-tab="privacy" id="oa-tab-privacy" role="tab" aria-selected="false" aria-controls="oa-panel-privacy">Privacy</button>
      <button type="button" class="oa-tab" data-tab="events" id="oa-tab-events" role="tab" aria-selected="false" aria-controls="oa-panel-events">Auto events</button>
      <button type="button" class="oa-tab" data-tab="reports" id="oa-tab-reports" role="tab" aria-selected="false" aria-controls="oa-panel-reports">Reports</button>
    </div>

    <div class="oa-card oa-tab-panel oa-settings-panel is-active" data-tab-panel="tracking" id="oa-panel-tracking" role="tabpanel" aria-labelledby="oa-tab-tracking">
      <div class="oa-card-h">Tracking defaults</div>
      <div class="oa-settings-check-grid">
        <label class="oa-toggle"><input type="checkbox" name="oa_settings[enabled]" value="1" <?php checked(!empty($opt['enabled'])); ?>> Enable tracking</label>
        <label class="oa-toggle"><input type="checkbox" name="oa_settings[strip_query]" value="1" <?php checked(!empty($opt['strip_query'])); ?>> Strip query strings</label>
        <label class="oa-toggle"><input type="checkbox" name="oa_settings[track_logged_in]" value="1" <?php checked(!empty($opt['track_logged_in'])); ?>> Track logged-in users</label>
        <label class="oa-toggle"><input type="checkbox" name="oa_settings[respect_dnt]" value="1" <?php checked(!empty($opt['respect_dnt'])); ?>> Respect Do Not Track</label>
        <label class="oa-toggle"><input type="checkbox" name="oa_settings[approx_uniques]" value="1" <?php checked(!empty($opt['approx_uniques'])); ?>> Approx uniques (privacy-friendly IP hash)</label>
      </div>
      <div class="oa-form-row oa-form-row-tracking">
        <label>Sampling (1 out of N) <input type="number" min="1" name="oa_settings[sample_rate]" value="<?php echo esc_attr($opt['sample_rate'] ?? 1); ?>"></label>
        <label>Rate limit (per IP / minute) <input type="number" min="10" name="oa_settings[rate_limit_per_min]" value="<?php echo esc_attr($opt['rate_limit_per_min'] ?? 120); ?>"></label>
        <label>Retention days <input type="number" min="30" name="oa_settings[retention_days]" value="<?php echo esc_attr($opt['retention_days'] ?? 180); ?>"></label>
        <label>UTM attribution days <input type="number" min="1" max="365" name="oa_settings[utm_attribution_days]" value="<?php echo esc_attr($opt['utm_attribution_days'] ?? 30); ?>"></label>
        <label>Attribution mode
          <select name="oa_settings[attribution_mode]">
            <?php $attribution_mode = sanitize_key((string)($opt['attribution_mode'] ?? 'first_touch')); if (!in_array($attribution_mode, ['first_touch','last_touch'], true)) $attribution_mode='first_touch'; ?>
            <option value="first_touch" <?php selected($attribution_mode, 'first_touch'); ?>>First touch</option>
            <option value="last_touch" <?php selected($attribution_mode, 'last_touch'); ?>>Last touch</option>
          </select>
        </label>
      </div>
      <p class="oa-muted">Choose how campaign conversion credit is assigned: first landing UTM or most recent UTM before conversion.</p>
    </div>

    <div class="oa-card oa-tab-panel oa-settings-panel" data-tab-panel="privacy" id="oa-panel-privacy" role="tabpanel" aria-labelledby="oa-tab-privacy" hidden>
      <div class="oa-card-h">Privacy &amp; Consent</div>
      <p class="oa-muted">For strict jurisdictions, you can require user consent before analytics runs. Ordelix Analytics is cookieless by default, but consent requirements can vary by country and your use-case.</p>
      <div class="oa-form-row oa-form-row-privacy">
        <label>Consent mode
          <select name="oa_settings[consent_mode]">
            <?php $cm = $opt['consent_mode'] ?? 'off'; ?>
            <option value="off" <?php selected($cm,'off'); ?>>Off (track when enabled)</option>
            <option value="require" <?php selected($cm,'require'); ?>>Require consent cookie</option>
            <option value="cmp" <?php selected($cm,'cmp'); ?>>CMP-controlled (via JS/API)</option>
          </select>
        </label>
        <label>Consent cookie name <input type="text" name="oa_settings[consent_cookie]" value="<?php echo esc_attr($opt['consent_cookie'] ?? 'oa_consent'); ?>"></label>
        <label>Opt-out cookie name <input type="text" name="oa_settings[optout_cookie]" value="<?php echo esc_attr($opt['optout_cookie'] ?? 'oa_optout'); ?>"></label>
      </div>
      <p class="oa-muted">Optional shortcode for a simple on-site toggle: <code>[ordelix_analytics_consent]</code></p>
      <div class="oa-settings-check-grid oa-settings-check-grid--single">
        <label class="oa-toggle"><input type="checkbox" name="oa_settings[trust_proxy_headers]" value="1" <?php checked(!empty($opt['trust_proxy_headers'])); ?>> Trust proxy IP headers (only enable behind a trusted reverse proxy/CDN)</label>
        <label class="oa-toggle"><input type="checkbox" name="oa_settings[keep_data_on_uninstall]" value="1" <?php checked(!empty($opt['keep_data_on_uninstall'])); ?>> Keep analytics data on plugin uninstall</label>
      </div>
    </div>


    <div class="oa-card oa-tab-panel oa-settings-panel" data-tab-panel="events" id="oa-panel-events" role="tabpanel" aria-labelledby="oa-tab-events" hidden>
      <div class="oa-card-h">Auto events</div>
      <div class="oa-settings-check-grid">
        <label class="oa-toggle"><input type="checkbox" name="oa_settings[auto_events]" value="1" <?php checked(!empty($opt['auto_events'])); ?>> Enable auto events</label>
        <label class="oa-toggle"><input type="checkbox" name="oa_settings[auto_outbound]" value="1" <?php checked(!empty($opt['auto_outbound'])); ?>> Outbound clicks</label>
        <label class="oa-toggle"><input type="checkbox" name="oa_settings[auto_downloads]" value="1" <?php checked(!empty($opt['auto_downloads'])); ?>> Downloads</label>
        <label class="oa-toggle"><input type="checkbox" name="oa_settings[auto_tel]" value="1" <?php checked(!empty($opt['auto_tel'])); ?>> Phone clicks</label>
        <label class="oa-toggle"><input type="checkbox" name="oa_settings[auto_mailto]" value="1" <?php checked(!empty($opt['auto_mailto'])); ?>> Email clicks</label>
        <label class="oa-toggle"><input type="checkbox" name="oa_settings[auto_forms]" value="1" <?php checked(!empty($opt['auto_forms'])); ?>> Form submits</label>
      </div>
    </div>

    <div class="oa-card oa-tab-panel oa-settings-panel" data-tab-panel="reports" id="oa-panel-reports" role="tabpanel" aria-labelledby="oa-tab-reports" hidden>
      <div class="oa-card-h">Email reports</div>
      <div class="oa-settings-check-grid oa-settings-check-grid--single">
        <label class="oa-toggle"><input type="checkbox" name="oa_settings[email_reports]" value="1" <?php checked(!empty($opt['email_reports'])); ?>> Send email reports</label>
      </div>
      <div class="oa-form-row">
        <label>Frequency
          <select name="oa_settings[email_reports_freq]">
            <option value="daily" <?php selected(($opt['email_reports_freq'] ?? '')==='daily'); ?>>Daily</option>
            <option value="weekly" <?php selected(($opt['email_reports_freq'] ?? 'weekly')==='weekly'); ?>>Weekly</option>
            <option value="monthly" <?php selected(($opt['email_reports_freq'] ?? '')==='monthly'); ?>>Monthly</option>
          </select>
        </label>
        <label>Send to <input type="email" name="oa_settings[email_reports_to]" value="<?php echo esc_attr($opt['email_reports_to'] ?? get_option('admin_email')); ?>"></label>
      </div>
      <p class="description">Reports include KPIs + conversion value + funnel conversion (approx).</p>
      <hr>
      <div class="oa-card-h">Anomaly alerts</div>
      <div class="oa-settings-check-grid oa-settings-check-grid--single">
        <label class="oa-toggle"><input type="checkbox" name="oa_settings[anomaly_alerts]" value="1" <?php checked(!empty($opt['anomaly_alerts']) || !isset($opt['anomaly_alerts'])); ?>> Send anomaly alert emails</label>
      </div>
      <div class="oa-form-row">
        <label>Alert threshold (% change) <input type="number" min="10" max="90" name="oa_settings[anomaly_threshold_pct]" value="<?php echo esc_attr($opt['anomaly_threshold_pct'] ?? 35); ?>"></label>
        <label>Baseline days <input type="number" min="3" max="30" name="oa_settings[anomaly_baseline_days]" value="<?php echo esc_attr($opt['anomaly_baseline_days'] ?? 7); ?>"></label>
        <label>Min baseline views <input type="number" min="10" name="oa_settings[anomaly_min_views]" value="<?php echo esc_attr($opt['anomaly_min_views'] ?? 60); ?>"></label>
        <label>Min baseline conversions <input type="number" min="1" name="oa_settings[anomaly_min_conversions]" value="<?php echo esc_attr($opt['anomaly_min_conversions'] ?? 5); ?>"></label>
      </div>
      <p class="description">Alerts trigger when yesterday is significantly above/below baseline.</p>
    </div>

    <?php submit_button(); ?>
  </form>

  <div class="oa-card">
    <div class="oa-card-h">Compliance tools</div>
    <p class="oa-muted">Export or erase analytics event aggregates for a date range. These actions only affect daily analytics tables (goals/funnels definitions are preserved).</p>

    <form method="post" class="oa-compliance-form">
      <?php wp_nonce_field('oa_compliance_tools'); ?>
      <input type="hidden" name="oa_compliance_action" value="export_bundle">
      <div class="oa-form-row">
        <label>From <input type="date" name="oa_compliance_from" value="<?php echo esc_attr($compliance_from); ?>"></label>
        <label>To <input type="date" name="oa_compliance_to" value="<?php echo esc_attr($compliance_to); ?>"></label>
      </div>
      <div class="oa-form-actions">
        <button class="button">Download compliance export (JSON)</button>
      </div>
    </form>

    <form method="post" class="oa-compliance-form">
      <?php wp_nonce_field('oa_compliance_tools'); ?>
      <input type="hidden" name="oa_compliance_action" value="erase_range">
      <div class="oa-form-row">
        <label>From <input type="date" name="oa_compliance_from" value="<?php echo esc_attr($compliance_from); ?>"></label>
        <label>To <input type="date" name="oa_compliance_to" value="<?php echo esc_attr($compliance_to); ?>"></label>
      </div>
      <div class="oa-form-actions">
        <button class="button" onclick="return confirm('Erase analytics rows for the selected date range?');">Erase selected range</button>
      </div>
    </form>

    <form method="post" class="oa-compliance-form oa-compliance-form-danger">
      <?php wp_nonce_field('oa_compliance_tools'); ?>
      <input type="hidden" name="oa_compliance_action" value="erase_all">
      <div class="oa-form-row">
        <label>Confirmation phrase <input type="text" name="oa_confirm" value="" placeholder="ERASE ALL"></label>
      </div>
      <div class="oa-form-actions">
        <button class="button button-link-delete" onclick="return confirm('Permanently erase all analytics daily rows?');">Erase all analytics daily rows</button>
      </div>
      <p class="oa-muted">Type <code>ERASE ALL</code> exactly to confirm.</p>
    </form>
  </div>

  <div class="oa-card">
    <div class="oa-card-h">Segments migration</div>
    <p class="oa-muted">Export/import saved views (segments) across sites. Imported private segments are assigned to your current user.</p>

    <form method="post" class="oa-compliance-form">
      <?php wp_nonce_field('oa_segments_tools'); ?>
      <input type="hidden" name="oa_segments_tools_action" value="export_segments">
      <div class="oa-form-actions">
        <button class="button">Download segments JSON</button>
      </div>
    </form>

    <form method="post" class="oa-compliance-form">
      <?php wp_nonce_field('oa_segments_tools'); ?>
      <div class="oa-form-row">
        <label>Segments JSON
          <textarea name="oa_segments_json" rows="8" placeholder='{"segments":{"traffic":[{"name":"Mobile Pricing","filters":{"device":"mobile","path":"/pricing"}}]}}'><?php echo esc_textarea($segments_json_input ?? ''); ?></textarea>
        </label>
      </div>
      <div class="oa-form-actions">
        <button class="button" type="submit" name="oa_segments_tools_action" value="import_merge">Import and merge</button>
        <button class="button" type="submit" name="oa_segments_tools_action" value="import_replace" onclick="return confirm('Replace all existing saved views with imported segments?');">Import and replace</button>
      </div>
    </form>
  </div>

  <div class="oa-card">
    <div class="oa-card-h">Developer</div>
    <p class="description">Manual event API: <code>window.ordelixTrack('event_name', {meta:'key=value', value: 0})</code></p>
    <p class="description">Consent hook: <code>add_filter('ordelix_analytics_can_track', fn($ok)=> $ok && my_cmp_allows());</code></p>
  </div>
</div>
