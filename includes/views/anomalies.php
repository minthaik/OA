<?php if(!defined('ABSPATH')) exit; ?>
<div class="wrap oa-wrap oa-page-anomalies">
  <h1>Anomaly Drilldown</h1>

  <div class="oa-card oa-flow">
    <form method="get" class="oa-range-form">
      <input type="hidden" name="page" value="ordelix-analytics-anomalies">
      <label>Date
        <input type="date" name="day" value="<?php echo esc_attr($detail['day']); ?>">
      </label>
      <button class="button button-primary">Inspect</button>
      <button type="button" class="button" data-oa-copy-link>Copy link</button>
    </form>
    <p class="oa-muted">Baseline: <?php echo esc_html($detail['baseline_days']); ?> days (<?php echo esc_html($detail['baseline_from']); ?> -> <?php echo esc_html($detail['baseline_to']); ?>), threshold: <?php echo esc_html(number_format_i18n((float)$detail['threshold_pct'],1)); ?>%.</p>
  </div>

  <?php if (!empty($detail['alerts'])): ?>
  <div class="oa-card oa-alert-card oa-flow">
    <div class="oa-card-h">Triggered alerts</div>
    <ul class="oa-list">
      <?php foreach($detail['alerts'] as $a): ?>
        <li><strong><?php echo esc_html($a['title']); ?>:</strong> <?php echo esc_html($a['message']); ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php else: ?>
  <div class="oa-card oa-flow">
    <div class="oa-card-h">Triggered alerts</div>
    <p class="oa-muted">No alert was triggered for this day using current thresholds.</p>
  </div>
  <?php endif; ?>

  <div class="oa-card oa-flow">
    <div class="oa-card-h">Metric math</div>
    <table class="widefat striped">
      <thead><tr><th>Metric</th><th>Day value</th><th>Baseline avg/day</th><th>Change</th><th>Rule</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach($detail['metrics'] as $m): ?>
        <?php
          $is_pct=!empty($m['is_percent']);
          $target=number_format_i18n((float)$m['target'], $is_pct ? 2 : 0).($is_pct ? '%' : '');
          $base=number_format_i18n((float)$m['baseline_avg'], $is_pct ? 2 : 1).($is_pct ? '%' : '');
          $chg=($m['change_pct']===null) ? 'new' : (($m['change_pct']>=0?'+':'').number_format_i18n((float)$m['change_pct'],1).'%');
          $status=!empty($m['eligible']) ? (!empty($m['triggered']) ? 'Alert' : 'Normal') : 'Low baseline';
          $status_class=!empty($m['eligible']) ? (!empty($m['triggered']) ? 'oa-badge-alert' : 'oa-badge-ok') : 'oa-badge-muted';
        ?>
        <tr>
          <td><?php echo esc_html($m['label']); ?></td>
          <td><?php echo esc_html($target); ?></td>
          <td><?php echo esc_html($base); ?></td>
          <td><?php echo esc_html($chg); ?></td>
          <td><?php echo esc_html($m['rule']); ?></td>
          <td><span class="oa-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status); ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="oa-card oa-flow">
    <div class="oa-card-h">Why this alert fired</div>
    <p class="oa-muted">Expand a metric to inspect raw totals and SQL used for anomaly checks.</p>
    <?php foreach($detail['metrics'] as $m): ?>
      <?php
        $status=!empty($m['eligible']) ? (!empty($m['triggered']) ? 'Alert' : 'Normal') : 'Low baseline';
        $status_class=!empty($m['eligible']) ? (!empty($m['triggered']) ? 'oa-badge-alert' : 'oa-badge-ok') : 'oa-badge-muted';
        $chg=($m['change_pct']===null) ? 'new' : (($m['change_pct']>=0?'+':'').number_format_i18n((float)$m['change_pct'],1).'%');
        if (empty($m['eligible'])) $reason='Baseline is below minimum threshold, so alerting is suppressed.';
        elseif (!empty($m['triggered'])) $reason='Change breaches alert rule: '.$m['rule'];
        else $reason='Change is within configured bounds.';
      ?>
      <details class="oa-explain" <?php echo !empty($m['triggered']) ? 'open' : ''; ?>>
        <summary>
          <span><?php echo esc_html($m['label']); ?> (<?php echo esc_html($chg); ?>)</span>
          <span class="oa-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status); ?></span>
        </summary>
        <div class="oa-explain-body">
          <p class="oa-muted"><?php echo esc_html($reason); ?></p>
          <?php if (!empty($m['sql_items'])): ?>
            <table class="widefat striped">
              <thead><tr><th>Check</th><th>SQL</th><th>Params</th><th>Raw value</th><th></th></tr></thead>
              <tbody>
              <?php foreach($m['sql_items'] as $row): ?>
                <?php
                  $row_params=wp_json_encode($row['params']);
                  $copy_payload=((string)$row['sql'])."\nPARAMS: ".$row_params;
                ?>
                <tr>
                  <td><?php echo esc_html($row['label']); ?></td>
                  <td><code><?php echo esc_html($row['sql']); ?></code></td>
                  <td><code><?php echo esc_html($row_params); ?></code></td>
                  <td><?php echo esc_html(number_format_i18n((float)$row['value'], (strpos(strtolower((string)$row['label']),'rate')!==false ? 2 : 1))); ?></td>
                  <td><button type="button" class="button button-small" data-oa-copy-sql data-sql="<?php echo esc_attr($copy_payload); ?>">Copy SQL</button></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </details>
    <?php endforeach; ?>
  </div>

  <div class="oa-card oa-flow">
    <div class="oa-card-h">Daily baseline table</div>
    <table class="widefat striped">
      <thead><tr><th>Date</th><th>Views</th><th>Conversions</th><th>Conversion rate</th><th>Role</th></tr></thead>
      <tbody>
      <?php foreach($detail['daily_rows'] as $r): ?>
        <tr class="<?php echo !empty($r['is_target']) ? 'oa-row-target' : ''; ?>">
          <td><?php echo esc_html($r['day']); ?></td>
          <td><?php echo esc_html(number_format_i18n((float)$r['views'],0)); ?></td>
          <td><?php echo esc_html(number_format_i18n((float)$r['conversions'],0)); ?></td>
          <td><?php echo esc_html(number_format_i18n((float)$r['conversion_rate'],2)); ?>%</td>
          <td><?php echo !empty($r['is_target']) ? 'Target day' : 'Baseline'; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="oa-grid oa-2">
    <div class="oa-card oa-flow">
      <div class="oa-card-h">Top page movers (day vs baseline avg/day)</div>
      <table class="widefat striped">
        <thead><tr><th>Page</th><th>Day views</th><th>Baseline avg</th><th>Delta</th><th>Change</th></tr></thead>
        <tbody>
        <?php if (empty($detail['top_pages'])): ?>
          <tr><td colspan="5">No significant page movers for this day.</td></tr>
        <?php else: foreach($detail['top_pages'] as $r): ?>
          <?php $chg=($r['change_pct']===null)?'new':(($r['change_pct']>=0?'+':'').number_format_i18n((float)$r['change_pct'],1).'%'); ?>
          <tr>
            <td><?php echo esc_html($r['label']); ?></td>
            <td><?php echo esc_html(number_format_i18n((float)$r['target'],0)); ?></td>
            <td><?php echo esc_html(number_format_i18n((float)$r['baseline_avg'],1)); ?></td>
            <td><?php echo esc_html(number_format_i18n((float)$r['delta'],1)); ?></td>
            <td><?php echo esc_html($chg); ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="oa-card oa-flow">
      <div class="oa-card-h">Top event movers (day vs baseline avg/day)</div>
      <table class="widefat striped">
        <thead><tr><th>Event</th><th>Day hits</th><th>Baseline avg</th><th>Delta</th><th>Change</th></tr></thead>
        <tbody>
        <?php if (empty($detail['top_events'])): ?>
          <tr><td colspan="5">No significant event movers for this day.</td></tr>
        <?php else: foreach($detail['top_events'] as $r): ?>
          <?php $chg=($r['change_pct']===null)?'new':(($r['change_pct']>=0?'+':'').number_format_i18n((float)$r['change_pct'],1).'%'); ?>
          <tr>
            <td><?php echo esc_html($r['label']); ?></td>
            <td><?php echo esc_html(number_format_i18n((float)$r['target'],0)); ?></td>
            <td><?php echo esc_html(number_format_i18n((float)$r['baseline_avg'],1)); ?></td>
            <td><?php echo esc_html(number_format_i18n((float)$r['delta'],1)); ?></td>
            <td><?php echo esc_html($chg); ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
