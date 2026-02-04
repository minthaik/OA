<?php if(!defined('ABSPATH')) exit; ?>
<?php
$kpis=(array)($retention['kpis'] ?? []);
$trend=(array)($retention['trend'] ?? []);
$buckets=(array)($retention['gap_buckets'] ?? []);
$bucket_labels=[
  '1d'=>'1 day',
  '2_3d'=>'2-3 days',
  '4_7d'=>'4-7 days',
  '8_14d'=>'8-14 days',
  '15_plus_d'=>'15+ days',
];
$max_bucket=0;
foreach($buckets as $n) $max_bucket=max($max_bucket,intval($n));
?>
<div class="wrap oa-wrap">
  <div class="oa-hero">
    <div>
      <h1>Retention</h1>
      <p class="oa-subtitle">New vs returning visitor signals and return-gap distribution.</p>
    </div>
    <div class="oa-card-tools">
      <button type="button" class="button button-primary" data-oa-export="retention" data-from="<?php echo esc_attr($from); ?>" data-to="<?php echo esc_attr($to); ?>">Export Retention CSV</button>
    </div>
  </div>
  <div class="oa-controls">
    <?php if (!empty($range_html)) echo $range_html; ?>
  </div>

  <div class="oa-grid oa-kpis oa-kpis-modern oa-kpis-compact">
    <div class="oa-card oa-kpi-card oa-kpi-tone-blue">
      <div class="oa-kpi-top"><span class="oa-kpi-label">New visitors</span></div>
      <div class="oa-kpi-value"><?php echo esc_html(number_format_i18n(intval($kpis['new_total'] ?? 0))); ?></div>
    </div>
    <div class="oa-card oa-kpi-card oa-kpi-tone-teal">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Returning visitors</span></div>
      <div class="oa-kpi-value"><?php echo esc_html(number_format_i18n(intval($kpis['returning_total'] ?? 0))); ?></div>
    </div>
    <div class="oa-card oa-kpi-card oa-kpi-tone-indigo">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Avg returning rate</span></div>
      <div class="oa-kpi-value"><?php echo esc_html(number_format_i18n((float)($kpis['returning_rate'] ?? 0),1)); ?>%</div>
    </div>
    <div class="oa-card oa-kpi-card oa-kpi-tone-slate">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Active days</span></div>
      <div class="oa-kpi-value"><?php echo esc_html(number_format_i18n(intval($kpis['active_days'] ?? 0))); ?></div>
    </div>
  </div>

  <div class="oa-grid oa-2">
    <div class="oa-card">
      <div class="oa-card-h">Daily retention trend</div>
      <table class="widefat striped">
        <thead><tr><th>Date</th><th>New</th><th>Returning</th><th>Returning rate</th></tr></thead>
        <tbody>
        <?php if (empty($trend)): ?>
          <tr><td colspan="4">No retention signal data in this range.</td></tr>
        <?php else: foreach($trend as $row): ?>
          <tr>
            <td><?php echo esc_html((string)($row['day'] ?? '')); ?></td>
            <td><?php echo esc_html(number_format_i18n(intval($row['new'] ?? 0))); ?></td>
            <td><?php echo esc_html(number_format_i18n(intval($row['returning'] ?? 0))); ?></td>
            <td><?php echo esc_html(number_format_i18n((float)($row['returning_rate'] ?? 0),1)); ?>%</td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="oa-card">
      <div class="oa-card-h">Return gap profile</div>
      <ul class="oa-mini-bars">
        <?php foreach($bucket_labels as $key=>$label): ?>
          <?php
          $n=intval($buckets[$key] ?? 0);
          $w=($max_bucket>0) ? max(4,min(100,(int)round(($n/$max_bucket)*100.0))) : 4;
          ?>
          <li>
            <span class="oa-mini-bars__label"><?php echo esc_html($label); ?></span>
            <span class="oa-mini-bars__bar"><i style="width:<?php echo esc_attr($w); ?>%"></i></span>
            <span class="oa-mini-bars__value"><?php echo esc_html(number_format_i18n($n)); ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="oa-muted">Retention uses browser-side signals (<code>visitor_first_seen</code>, <code>visitor_returned</code>) and improves as data accumulates.</p>
    </div>
  </div>
</div>
