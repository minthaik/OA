<?php if(!defined('ABSPATH')) exit; ?>
<?php
$total_orders=0;
$total_discount=0.0;
$total_revenue=0.0;
foreach($rows as $r){
  $total_orders+=(int)$r['orders'];
  $total_discount+=(float)$r['discount_total'];
  $total_revenue+=(float)$r['revenue_total'];
}
$avg_discount=$total_orders>0 ? ($total_discount/$total_orders) : 0.0;
$top_coupon=!empty($rows) ? ((string)$rows[0]['coupon_code'] ?: '-') : '-';
$discount_rate=$total_revenue>0.0 ? (($total_discount/$total_revenue)*100.0) : 0.0;
$max_daily_discount=0.0;
foreach($daily as $d){
  $max_daily_discount=max($max_daily_discount,(float)$d['discount_total']);
}
?>
<div class="wrap oa-wrap">
  <div class="oa-hero">
    <div>
      <h1>Coupons</h1>
      <p class="oa-subtitle">Understand discount efficiency and coupon-led revenue at a glance.</p>
    </div>
    <div class="oa-card-tools">
      <button type="button" class="button button-primary" data-oa-export="coupons" data-from="<?php echo esc_attr($from); ?>" data-to="<?php echo esc_attr($to); ?>">Export Coupons CSV</button>
      <span class="oa-save-slot" data-oa-save-slot></span>
    </div>
  </div>
  <div class="oa-controls">
    <?php if (!empty($range_html)) echo $range_html; ?>
    <?php if (!empty($filters_html)) echo $filters_html; ?>
  </div>

  <div class="oa-grid oa-kpis oa-kpis-modern oa-kpis-compact">
    <div class="oa-card oa-kpi-card oa-kpi-tone-teal">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Coupon orders</span></div>
      <div class="oa-kpi-value"><?php echo esc_html(number_format_i18n($total_orders)); ?></div>
    </div>
    <div class="oa-card oa-kpi-card oa-kpi-tone-orange">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Total discount</span></div>
      <div class="oa-kpi-value"><?php echo esc_html(number_format_i18n($total_discount,2)); ?></div>
    </div>
    <div class="oa-card oa-kpi-card oa-kpi-tone-blue">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Coupon revenue</span></div>
      <div class="oa-kpi-value"><?php echo esc_html(number_format_i18n($total_revenue,2)); ?></div>
    </div>
    <div class="oa-card oa-kpi-card oa-kpi-tone-green">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Avg discount / order</span></div>
      <div class="oa-kpi-value"><?php echo esc_html(number_format_i18n($avg_discount,2)); ?></div>
    </div>
    <div class="oa-card oa-kpi-card oa-kpi-tone-indigo">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Discount rate</span></div>
      <div class="oa-kpi-value"><?php echo esc_html(number_format_i18n($discount_rate,2)); ?>%</div>
    </div>
    <div class="oa-card oa-kpi-card oa-kpi-tone-slate">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Top coupon</span></div>
      <div class="oa-kpi-value oa-small"><?php echo esc_html($top_coupon); ?></div>
    </div>
  </div>

  <div class="oa-grid oa-2">
    <div class="oa-card">
      <div class="oa-card-h">Top coupons</div>
      <table class="widefat striped">
        <thead><tr><th>Coupon</th><th>Orders</th><th>Discount</th><th>Revenue</th><th>Avg/order</th></tr></thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="5">No coupon usage in the selected range.</td></tr>
        <?php else: foreach($rows as $r): ?>
          <?php $avg=((int)$r['orders']>0)?(((float)$r['discount_total'])/((int)$r['orders'])):0.0; ?>
          <tr>
            <td><span class="oa-pill"><?php echo esc_html($r['coupon_code']); ?></span></td>
            <td><?php echo esc_html(number_format_i18n((int)$r['orders'])); ?></td>
            <td><?php echo esc_html(number_format_i18n((float)$r['discount_total'],2)); ?></td>
            <td><?php echo esc_html(number_format_i18n((float)$r['revenue_total'],2)); ?></td>
            <td><?php echo esc_html(number_format_i18n($avg,2)); ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="oa-card">
      <div class="oa-card-h">Daily coupon flow</div>
      <?php if (empty($daily)): ?>
        <p class="oa-muted">No daily coupon trend for this date range.</p>
      <?php else: ?>
        <ul class="oa-mini-bars">
          <?php foreach($daily as $d): ?>
            <?php
              $width=$max_daily_discount>0.0 ? (int)round(((float)$d['discount_total']/$max_daily_discount)*100.0) : 0;
              $width=max(4,min(100,$width));
            ?>
            <li>
              <span class="oa-mini-bars__label"><?php echo esc_html($d['day']); ?></span>
              <span class="oa-mini-bars__bar"><i style="width:<?php echo esc_attr($width); ?>%"></i></span>
              <span class="oa-mini-bars__value"><?php echo esc_html(number_format_i18n((float)$d['discount_total'],2)); ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <p class="oa-muted">Bar length represents discount total per day.</p>
    </div>
  </div>
</div>
