<?php if(!defined('ABSPATH')) exit; ?>
<?php
$compare=(isset($data['compare']) && is_array($data['compare'])) ? $data['compare'] : [];
$compare_label=!empty($compare['label']) ? (string)$compare['label'] : 'vs prior period';
$delta_meta=function($key) use ($compare){
  $pct=$compare[$key] ?? null;
  if ($pct===null) return ['text'=>'New','class'=>'oa-delta oa-delta-neutral'];
  $pct=(float)$pct;
  $text=(($pct>=0)?'+':'').number_format_i18n($pct,1).'%';
  if ($pct>0.01) return ['text'=>$text,'class'=>'oa-delta oa-delta-up'];
  if ($pct<-0.01) return ['text'=>$text,'class'=>'oa-delta oa-delta-down'];
  return ['text'=>$text,'class'=>'oa-delta oa-delta-neutral'];
};
$views_delta=$delta_meta('views_pct');
$visits_delta=$delta_meta('visits_pct');
$conv_delta=$delta_meta('conversions_pct');
$value_delta=$delta_meta('value_pct');
$rate_delta=$delta_meta('conversion_rate_pct');
$coupon_delta=$delta_meta('coupon_discount_pct');

$trend_rows=(isset($data['trend']) && is_array($data['trend'])) ? $data['trend'] : [];
$peak_day='-';
$peak_views=0;
$avg_views=0.0;
$sum_views=0.0;
$best_rate_day='-';
$best_rate=0.0;
foreach($trend_rows as $r){
  $views=(float)($r['views'] ?? 0);
  $conv=(float)($r['conversions'] ?? 0);
  $day=(string)($r['day'] ?? '');
  $sum_views+=$views;
  if ($views>$peak_views){
    $peak_views=$views;
    $peak_day=$day;
  }
  if ($views>0.0){
    $rate=($conv/$views)*100.0;
    if ($rate>$best_rate){
      $best_rate=$rate;
      $best_rate_day=$day;
    }
  }
}
if (!empty($trend_rows)) $avg_views=$sum_views/count($trend_rows);

$export_types=['pages','referrers','events','goals','campaigns','revenue'];
if (class_exists('WooCommerce')) $export_types[]='coupons';
?>
<div class="wrap oa-wrap">
  <div class="oa-hero">
    <div>
      <h1>Ordelix Analytics</h1>
      <p class="oa-subtitle">Signal-rich overview for <?php echo esc_html($from); ?> -> <?php echo esc_html($to); ?>. <span class="oa-muted"><?php echo esc_html($compare_label); ?></span></p>
    </div>
    <div class="oa-card-tools">
      <button type="button" class="button button-primary" data-oa-export-all="<?php echo esc_attr(implode(',',$export_types)); ?>" data-from="<?php echo esc_attr($from); ?>" data-to="<?php echo esc_attr($to); ?>">Export all datasets</button>
      <span class="oa-save-slot" data-oa-save-slot></span>
      <?php if (!empty($data['anomalies'])): ?>
        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=ordelix-analytics-anomalies&day='.rawurlencode(wp_date('Y-m-d', current_time('timestamp')-DAY_IN_SECONDS)))); ?>">Investigate anomalies</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="oa-controls">
    <?php if (!empty($range_html)) echo $range_html; ?>
    <?php if (!empty($filters_html)) echo $filters_html; ?>
  </div>

  <div class="oa-grid oa-kpis oa-kpis-modern">
    <div class="oa-card oa-kpi-card oa-kpi-tone-blue">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Views</span><span class="<?php echo esc_attr($views_delta['class']); ?>"><?php echo esc_html($views_delta['text']); ?></span></div>
      <div class="oa-kpi-value"><?php echo esc_html(number_format_i18n($data['kpis']['views'])); ?></div>
      <div class="oa-kpi-foot"><?php echo esc_html($compare_label); ?></div>
    </div>
    <div class="oa-card oa-kpi-card oa-kpi-tone-teal">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Visits (approx)</span><span class="<?php echo esc_attr($visits_delta['class']); ?>"><?php echo esc_html($visits_delta['text']); ?></span></div>
      <div class="oa-kpi-value"><?php echo esc_html(number_format_i18n($data['kpis']['visits'])); ?></div>
      <div class="oa-kpi-foot"><?php echo esc_html($compare_label); ?></div>
    </div>
    <div class="oa-card oa-kpi-card oa-kpi-tone-green">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Conversions</span><span class="<?php echo esc_attr($conv_delta['class']); ?>"><?php echo esc_html($conv_delta['text']); ?></span></div>
      <div class="oa-kpi-value"><?php echo esc_html(number_format_i18n($data['kpis']['conversions'])); ?></div>
      <div class="oa-kpi-foot"><?php echo esc_html($compare_label); ?></div>
    </div>
    <div class="oa-card oa-kpi-card oa-kpi-tone-orange">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Value</span><span class="<?php echo esc_attr($value_delta['class']); ?>"><?php echo esc_html($value_delta['text']); ?></span></div>
      <div class="oa-kpi-value"><?php echo esc_html($data['kpis']['value']); ?></div>
      <div class="oa-kpi-foot"><?php echo esc_html($compare_label); ?></div>
    </div>
    <div class="oa-card oa-kpi-card oa-kpi-tone-indigo">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Conversion rate</span><span class="<?php echo esc_attr($rate_delta['class']); ?>"><?php echo esc_html($rate_delta['text']); ?></span></div>
      <div class="oa-kpi-value"><?php echo esc_html($data['kpis']['conversion_rate']); ?></div>
      <div class="oa-kpi-foot"><?php echo esc_html($compare_label); ?></div>
    </div>
    <?php if (class_exists('WooCommerce')): ?>
      <div class="oa-card oa-kpi-card oa-kpi-tone-red">
        <div class="oa-kpi-top"><span class="oa-kpi-label">Coupon discounts</span><span class="<?php echo esc_attr($coupon_delta['class']); ?>"><?php echo esc_html($coupon_delta['text']); ?></span></div>
        <div class="oa-kpi-value"><?php echo esc_html($data['kpis']['coupon_discount']); ?></div>
        <div class="oa-kpi-foot"><?php echo esc_html($data['kpis']['top_coupon']!=='-' ? ('Top coupon: '.$data['kpis']['top_coupon']) : 'No coupon leader'); ?></div>
      </div>
    <?php endif; ?>
    <div class="oa-card oa-kpi-card oa-kpi-tone-slate">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Top page</span></div>
      <div class="oa-kpi-value oa-small"><?php echo esc_html($data['kpis']['top_page']); ?></div>
      <div class="oa-kpi-foot">Top referrer: <?php echo esc_html($data['kpis']['top_referrer']); ?></div>
    </div>
  </div>

  <div class="oa-grid oa-2">
    <div class="oa-card">
      <div class="oa-card-head">
        <div class="oa-card-h">Anomaly watch</div>
        <?php if (!empty($data['anomalies'])): ?>
          <span class="oa-badge oa-badge-alert"><?php echo esc_html(count($data['anomalies'])); ?> active</span>
        <?php else: ?>
          <span class="oa-badge oa-badge-ok">No active alert</span>
        <?php endif; ?>
      </div>
      <?php if (!empty($data['anomalies'])): ?>
        <ul class="oa-list oa-list-tight">
          <?php foreach($data['anomalies'] as $line): ?><li><?php echo esc_html($line); ?></li><?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="oa-muted">No period-level anomalies for this range.</p>
      <?php endif; ?>
    </div>
    <div class="oa-card">
      <div class="oa-card-h">Insights</div>
      <?php if (!empty($data['insights'])): ?>
        <ul class="oa-list oa-list-tight">
          <?php foreach($data['insights'] as $line): ?><li><?php echo esc_html($line); ?></li><?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="oa-muted">Insights will appear as data accumulates.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="oa-card oa-chart-card">
    <div class="oa-card-head">
      <div class="oa-card-h">Trend spotlight</div>
      <div class="oa-card-tools">
        <span class="oa-pill">Peak views: <?php echo esc_html(number_format_i18n($peak_views)); ?> on <?php echo esc_html($peak_day); ?></span>
        <span class="oa-pill">Avg views/day: <?php echo esc_html(number_format_i18n($avg_views,1)); ?></span>
        <span class="oa-pill">Best conversion day: <?php echo esc_html($best_rate_day); ?> (<?php echo esc_html(number_format_i18n($best_rate,2)); ?>%)</span>
      </div>
    </div>
    <div id="oa-trend" data-series="<?php echo esc_attr(wp_json_encode($trend_rows)); ?>"></div>
  </div>

  <?php if (!empty($data['funnels'])): ?>
  <div class="oa-card">
    <div class="oa-card-h">Funnels (approx)</div>
    <table class="widefat striped">
      <thead><tr><th>Funnel</th><th>Step 1</th><th>Last step</th><th>Conversion</th></tr></thead>
      <tbody>
      <?php foreach($data['funnels'] as $f): ?>
        <tr><td><?php echo esc_html($f['name']); ?></td><td><?php echo esc_html(number_format_i18n($f['step1'])); ?></td><td><?php echo esc_html(number_format_i18n($f['step_last'])); ?></td><td><?php echo esc_html($f['conversion']); ?>%</td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <div class="oa-grid oa-2">
    <div class="oa-card">
      <div class="oa-card-head">
        <div class="oa-card-h">Top pages</div>
        <div class="oa-card-tools"><button type="button" class="button" data-oa-export="pages" data-from="<?php echo esc_attr($from); ?>" data-to="<?php echo esc_attr($to); ?>">Export CSV</button></div>
      </div>
      <table class="widefat striped">
        <thead><tr><th>Path</th><th>Views</th></tr></thead>
        <tbody>
        <?php if (empty($data['tables']['pages'])): ?>
          <tr><td colspan="2">No data in selected range.</td></tr>
        <?php else: foreach($data['tables']['pages'] as $r): ?><tr><td><?php echo esc_html($r['path']); ?></td><td><?php echo esc_html(number_format_i18n((int)$r['views'])); ?></td></tr><?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <div class="oa-card">
      <div class="oa-card-head">
        <div class="oa-card-h">Top referrers</div>
        <div class="oa-card-tools"><button type="button" class="button" data-oa-export="referrers" data-from="<?php echo esc_attr($from); ?>" data-to="<?php echo esc_attr($to); ?>">Export CSV</button></div>
      </div>
      <table class="widefat striped">
        <thead><tr><th>Domain</th><th>Views</th></tr></thead>
        <tbody>
        <?php if (empty($data['tables']['referrers'])): ?>
          <tr><td colspan="2">No data in selected range.</td></tr>
        <?php else: foreach($data['tables']['referrers'] as $r): ?><tr><td><?php echo esc_html($r['ref_domain']); ?></td><td><?php echo esc_html(number_format_i18n((int)$r['views'])); ?></td></tr><?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <div class="oa-card">
      <div class="oa-card-head">
        <div class="oa-card-h">Top events</div>
        <div class="oa-card-tools"><button type="button" class="button" data-oa-export="events" data-from="<?php echo esc_attr($from); ?>" data-to="<?php echo esc_attr($to); ?>">Export CSV</button></div>
      </div>
      <table class="widefat striped">
        <thead><tr><th>Event</th><th>Hits</th></tr></thead>
        <tbody>
        <?php if (empty($data['tables']['events'])): ?>
          <tr><td colspan="2">No data in selected range.</td></tr>
        <?php else: foreach($data['tables']['events'] as $r): ?><tr><td><?php echo esc_html($r['event_name']); ?></td><td><?php echo esc_html(number_format_i18n((int)$r['hits'])); ?></td></tr><?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <div class="oa-card">
      <div class="oa-card-head">
        <div class="oa-card-h">Goals</div>
        <div class="oa-card-tools"><button type="button" class="button" data-oa-export="goals" data-from="<?php echo esc_attr($from); ?>" data-to="<?php echo esc_attr($to); ?>">Export CSV</button></div>
      </div>
      <table class="widefat striped">
        <thead><tr><th>Goal</th><th>Hits</th><th>Value</th></tr></thead>
        <tbody>
        <?php if (empty($data['tables']['goals'])): ?>
          <tr><td colspan="3">No goals matched in selected range.</td></tr>
        <?php else: foreach($data['tables']['goals'] as $r): ?><tr><td><?php echo esc_html($r['name']); ?></td><td><?php echo esc_html(number_format_i18n((int)$r['hits'])); ?></td><td><?php echo esc_html(number_format_i18n((float)$r['value'],2)); ?></td></tr><?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
