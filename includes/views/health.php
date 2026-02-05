<?php if(!defined('ABSPATH')) exit; ?>
<?php
$db_ok=((string)$health['db_version']===(string)$health['db_expected']);
$cron_ok=!empty($health['cron_next_ts']);
$last_report=!empty($health['last_report_sent']) ? wp_date('Y-m-d H:i', intval($health['last_report_sent'])) : '-';
$next_cron=$cron_ok ? wp_date('Y-m-d H:i', intval($health['cron_next_ts'])) : 'Not scheduled';
$views_7d=(int)($health['last_7d']['views'] ?? 0);
$conv_7d=(int)($health['last_7d']['conversions'] ?? 0);
$rev_7d=(float)($health['last_7d']['revenue'] ?? 0.0);
$cr_7d=$views_7d>0 ? (($conv_7d/$views_7d)*100.0) : 0.0;
$storage_total_mb=round((float)($health['storage_total_bytes'] ?? 0)/(1024*1024),1);
?>
<div class="wrap oa-wrap oa-page-health">
  <div class="oa-hero">
    <div>
      <h1>System Health</h1>
      <p class="oa-subtitle">Operational checks for schema, cron, cache, and table integrity.</p>
    </div>
  </div>

  <?php if (!empty($health_notice)): ?>
    <div class="notice notice-success is-dismissible oa-notice"><p><?php echo esc_html($health_notice); ?></p></div>
  <?php endif; ?>

  <div class="oa-grid oa-kpis oa-kpis-modern oa-kpis-compact">
    <div class="oa-card oa-kpi-card <?php echo $db_ok ? 'oa-kpi-tone-green' : 'oa-kpi-tone-red'; ?>">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Schema version</span></div>
      <div class="oa-kpi-value oa-small"><?php echo esc_html($health['db_version'] ?: '-'); ?></div>
      <div class="oa-kpi-foot">Expected <?php echo esc_html($health['db_expected']); ?></div>
    </div>
    <div class="oa-card oa-kpi-card <?php echo $cron_ok ? 'oa-kpi-tone-blue' : 'oa-kpi-tone-red'; ?>">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Cron status</span></div>
      <div class="oa-kpi-value oa-small"><?php echo $cron_ok ? 'Scheduled' : 'Missing'; ?></div>
      <div class="oa-kpi-foot">Next run: <?php echo esc_html($next_cron); ?></div>
    </div>
    <div class="oa-card oa-kpi-card oa-kpi-tone-indigo">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Retention days</span></div>
      <div class="oa-kpi-value"><?php echo esc_html(number_format_i18n((int)$health['retention_days'])); ?></div>
      <div class="oa-kpi-foot">Configured in Settings</div>
    </div>
    <div class="oa-card oa-kpi-card oa-kpi-tone-slate">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Last report</span></div>
      <div class="oa-kpi-value oa-small"><?php echo esc_html($last_report); ?></div>
      <div class="oa-kpi-foot">Last anomaly day: <?php echo esc_html($health['last_anomaly_alert_day'] ?: '-'); ?></div>
    </div>
    <div class="oa-card oa-kpi-card oa-kpi-tone-teal">
      <div class="oa-kpi-top"><span class="oa-kpi-label">7d traffic</span></div>
      <div class="oa-kpi-value"><?php echo esc_html(number_format_i18n($views_7d)); ?></div>
      <div class="oa-kpi-foot">Conversions: <?php echo esc_html(number_format_i18n($conv_7d)); ?> (<?php echo esc_html(number_format_i18n($cr_7d,2)); ?>%)</div>
    </div>
    <div class="oa-card oa-kpi-card oa-kpi-tone-orange">
      <div class="oa-kpi-top"><span class="oa-kpi-label">7d revenue</span></div>
      <div class="oa-kpi-value"><?php echo esc_html(number_format_i18n($rev_7d,2)); ?></div>
      <div class="oa-kpi-foot">From daily revenue table</div>
    </div>
    <div class="oa-card oa-kpi-card oa-kpi-tone-indigo">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Storage footprint</span></div>
      <div class="oa-kpi-value"><?php echo esc_html(number_format_i18n($storage_total_mb,1)); ?> MB</div>
      <div class="oa-kpi-foot">Analytics tables only</div>
    </div>
  </div>

  <div class="oa-grid oa-2">
    <div class="oa-card">
      <div class="oa-card-h">Maintenance actions</div>
      <?php if (!empty($can_manage)): ?>
      <form method="post" class="oa-form-actions">
        <?php wp_nonce_field('oa_health'); ?>
        <input type="hidden" name="oa_health_action" value="run_maintenance">
        <button type="submit" class="button button-primary">Run cleanup now</button>
      </form>
      <form method="post" class="oa-form-actions">
        <?php wp_nonce_field('oa_health'); ?>
        <input type="hidden" name="oa_health_action" value="flush_cache">
        <button type="submit" class="button">Clear dashboard cache</button>
      </form>
      <form method="post" class="oa-form-actions">
        <?php wp_nonce_field('oa_health'); ?>
        <input type="hidden" name="oa_health_action" value="repair_schema">
        <button type="submit" class="button">Repair schema</button>
      </form>
      <form method="post" class="oa-form-actions">
        <?php wp_nonce_field('oa_health'); ?>
        <input type="hidden" name="oa_health_action" value="reschedule_cron">
        <button type="submit" class="button">Reschedule cron</button>
      </form>
      <form method="post" class="oa-form-actions">
        <?php wp_nonce_field('oa_health'); ?>
        <input type="hidden" name="oa_health_action" value="repair_caps">
        <button type="submit" class="button">Repair capabilities</button>
      </form>
      <form method="post" class="oa-form-actions">
        <?php wp_nonce_field('oa_health'); ?>
        <input type="hidden" name="oa_health_action" value="optimize_tables">
        <button type="submit" class="button">Optimize analytics tables</button>
      </form>
      <form method="post" class="oa-form-actions">
        <?php wp_nonce_field('oa_health'); ?>
        <input type="hidden" name="oa_health_action" value="export_diagnostics">
        <button type="submit" class="button">Download diagnostics JSON</button>
      </form>
      <form method="post" class="oa-form-actions">
        <?php wp_nonce_field('oa_health'); ?>
        <input type="hidden" name="oa_health_action" value="self_test">
        <label class="oa-toggle"><input type="checkbox" name="strict" value="1"> Strict mode (warnings fail)</label>
        <button type="submit" class="button">Run self-test</button>
      </form>
      <p class="oa-muted">Maintenance removes rows older than retention settings. Cache clear only removes dashboard transients.</p>
      <?php else: ?>
      <p class="oa-muted">Read-only access: maintenance operations are available only to users with manage permission.</p>
      <?php endif; ?>
    </div>

    <div class="oa-card">
      <div class="oa-card-h">Runtime info</div>
      <ul class="oa-list oa-list-tight">
        <li>Plugin version: <strong><?php echo esc_html($health['plugin_version']); ?></strong></li>
        <li>Schema version: <strong><?php echo esc_html($health['db_version'] ?: '-'); ?></strong></li>
        <li>Cron hook: <code><?php echo esc_html(OA_Reports::CRON_HOOK); ?></code></li>
      </ul>
    </div>
  </div>

  <div class="oa-card">
    <div class="oa-card-h">Health checks</div>
    <table class="widefat striped">
      <thead><tr><th>Check</th><th>Status</th><th>Detail</th></tr></thead>
      <tbody>
      <?php foreach($health['checks'] as $c): ?>
        <?php
          $cls='oa-badge-ok';
          $label='OK';
          if (($c['status'] ?? '')==='warn'){ $cls='oa-badge-muted'; $label='Warning'; }
          elseif (($c['status'] ?? '')==='fail'){ $cls='oa-badge-alert'; $label='Fail'; }
        ?>
        <tr>
          <td><?php echo esc_html($c['label']); ?></td>
          <td><span class="oa-badge <?php echo esc_attr($cls); ?>"><?php echo esc_html($label); ?></span></td>
          <td><?php echo esc_html($c['detail']); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="oa-card">
    <div class="oa-card-h">Data quality audit</div>
    <?php $dq=(array)($health['data_quality_audit'] ?? []); ?>
    <?php $dq_findings=(array)($dq['findings'] ?? []); ?>
    <?php $dq_range=(array)($dq['range'] ?? []); ?>
    <p class="oa-muted">Range: <?php echo esc_html((string)($dq_range['from'] ?? '-')); ?> -> <?php echo esc_html((string)($dq_range['to'] ?? '-')); ?></p>
    <table class="widefat striped">
      <thead><tr><th>Finding</th><th>Status</th><th>Detail</th></tr></thead>
      <tbody>
      <?php if (empty($dq_findings)): ?>
        <tr><td colspan="3">No data-quality findings available.</td></tr>
      <?php else: foreach($dq_findings as $f): ?>
        <?php
          $status=(string)($f['status'] ?? 'ok');
          $cls='oa-badge-ok';
          $label='OK';
          if ($status==='warn'){ $cls='oa-badge-muted'; $label='Warning'; }
          elseif ($status==='fail'){ $cls='oa-badge-alert'; $label='Fail'; }
        ?>
        <tr>
          <td><?php echo esc_html((string)($f['label'] ?? '-')); ?></td>
          <td><span class="oa-badge <?php echo esc_attr($cls); ?>"><?php echo esc_html($label); ?></span></td>
          <td><?php echo esc_html((string)($f['detail'] ?? '')); ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if (!empty($health_self_test)): ?>
  <div class="oa-card">
    <div class="oa-card-h">Self-test results</div>
    <?php $sum=(array)($health_self_test['summary'] ?? []); ?>
    <p class="oa-muted">
      Passed: <?php echo esc_html(number_format_i18n((int)($sum['passed'] ?? 0))); ?>,
      Failed: <?php echo esc_html(number_format_i18n((int)($sum['failed'] ?? 0))); ?>,
      Warned: <?php echo esc_html(number_format_i18n((int)($sum['warned'] ?? 0))); ?>.
    </p>
    <table class="widefat striped">
      <thead><tr><th>Test</th><th>Status</th><th>Detail</th></tr></thead>
      <tbody>
      <?php foreach((array)($health_self_test['tests'] ?? []) as $t): ?>
        <?php
          $r=(string)($t['result'] ?? 'pass');
          $cls='oa-badge-ok';
          $label='PASS';
          if ($r==='warn'){ $cls='oa-badge-muted'; $label='WARN'; }
          elseif ($r==='fail'){ $cls='oa-badge-alert'; $label='FAIL'; }
        ?>
        <tr>
          <td><?php echo esc_html((string)($t['label'] ?? '-')); ?></td>
          <td><span class="oa-badge <?php echo esc_attr($cls); ?>"><?php echo esc_html($label); ?></span></td>
          <td><?php echo esc_html((string)($t['detail'] ?? '')); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <div class="oa-card">
    <div class="oa-card-h">Table integrity</div>
    <table class="widefat striped">
      <thead><tr><th>Table</th><th>Status</th><th>Rows</th><th>Size</th></tr></thead>
      <tbody>
      <?php foreach($health['tables'] as $t): ?>
        <tr>
          <td><code><?php echo esc_html($t['name']); ?></code></td>
          <td>
            <?php if (!empty($t['exists'])): ?>
              <span class="oa-badge oa-badge-ok">OK</span>
            <?php else: ?>
              <span class="oa-badge oa-badge-alert">Missing</span>
            <?php endif; ?>
          </td>
          <td><?php echo !empty($t['exists']) ? esc_html(number_format_i18n((int)$t['rows'])) : '-'; ?></td>
          <td><?php echo !empty($t['exists']) ? esc_html(number_format_i18n(round(((int)($t['size_bytes'] ?? 0))/(1024*1024),1),1).' MB') : '-'; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="oa-card">
    <div class="oa-card-h">Schema audit</div>
    <table class="widefat striped">
      <thead><tr><th>Table</th><th>Status</th><th>Missing columns</th><th>Primary key</th><th>Missing indexes</th></tr></thead>
      <tbody>
      <?php if (empty($health['schema_audit'])): ?>
        <tr><td colspan="5">Schema audit data is unavailable.</td></tr>
      <?php else: foreach($health['schema_audit'] as $row): ?>
        <?php
          $status=(string)($row['status'] ?? 'ok');
          $cls='oa-badge-ok';
          $label='OK';
          if ($status==='warn'){ $cls='oa-badge-muted'; $label='Warning'; }
          elseif ($status==='fail'){ $cls='oa-badge-alert'; $label='Fail'; }
          $missing_cols=empty($row['missing_columns']) ? '-' : implode(', ', (array)$row['missing_columns']);
          $missing_idx=empty($row['missing_indexes']) ? '-' : implode(', ', (array)$row['missing_indexes']);
          $pk_ok=!empty($row['primary_ok']);
        ?>
        <tr>
          <td><code><?php echo esc_html((string)$row['table']); ?></code></td>
          <td><span class="oa-badge <?php echo esc_attr($cls); ?>"><?php echo esc_html($label); ?></span></td>
          <td><?php echo esc_html($missing_cols); ?></td>
          <td><?php echo $pk_ok ? '<span class="oa-badge oa-badge-ok">OK</span>' : '<span class="oa-badge oa-badge-alert">Mismatch</span>'; ?></td>
          <td><?php echo esc_html($missing_idx); ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div class="oa-card">
    <div class="oa-card-h">Migration history</div>
    <table class="widefat striped">
      <thead><tr><th>Time</th><th>Context</th><th>From</th><th>To</th><th>Missing before</th><th>Missing after</th></tr></thead>
      <tbody>
      <?php if (empty($health['migration_log'])): ?>
        <tr><td colspan="6">No migration history yet.</td></tr>
      <?php else: foreach($health['migration_log'] as $row): ?>
        <tr>
          <td><?php echo esc_html((string)$row['at']); ?></td>
          <td><?php echo esc_html((string)$row['context']); ?></td>
          <td><?php echo esc_html((string)$row['from']); ?></td>
          <td><?php echo esc_html((string)$row['to']); ?></td>
          <td><?php echo esc_html(number_format_i18n((int)($row['missing_before'] ?? 0))); ?></td>
          <td><?php echo esc_html(number_format_i18n((int)($row['missing_after'] ?? 0))); ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
