<?php if(!defined('ABSPATH')) exit; ?>
<?php
$order_total=0;
$revenue_total=0.0;
$peak_day='-';
$peak_revenue=0.0;
foreach($rows as $r){
  $orders=(int)$r['orders'];
  $rev=(float)$r['revenue'];
  $order_total+=$orders;
  $revenue_total+=$rev;
  if ($rev>$peak_revenue){
    $peak_revenue=$rev;
    $peak_day=(string)$r['day'];
  }
}
$avg_order_value=$order_total>0 ? ($revenue_total/$order_total) : 0.0;
$coupons_url_args=['page'=>'ordelix-analytics-coupons','from'=>$from,'to'=>$to];
if (!empty($filters['coupon'])) $coupons_url_args['oa_coupon']=(string)$filters['coupon'];
$coupons_url=add_query_arg($coupons_url_args,admin_url('admin.php'));
?>
<div class="wrap oa-wrap">
  <div class="oa-hero">
    <div>
      <h1>Revenue (WooCommerce)</h1>
      <p class="oa-subtitle">Sales performance captured from completed paid orders.</p>
    </div>
    <div class="oa-card-tools">
      <button type="button" class="button button-primary" data-oa-export="revenue" data-from="<?php echo esc_attr($from); ?>" data-to="<?php echo esc_attr($to); ?>">Export Revenue CSV</button>
      <a class="button" href="<?php echo esc_url($coupons_url); ?>">Open coupons</a>
      <span class="oa-save-slot" data-oa-save-slot></span>
    </div>
  </div>
  <div class="oa-controls">
    <?php if (!empty($range_html)) echo $range_html; ?>
    <?php if (!empty($filters_html)) echo $filters_html; ?>
  </div>
  <?php if (!empty($filters['coupon'])): ?>
    <p class="oa-muted">Coupon filter active: totals reflect coupon-attributed orders/revenue only.</p>
  <?php endif; ?>

  <div class="oa-grid oa-kpis oa-kpis-modern oa-kpis-compact">
    <div class="oa-card oa-kpi-card oa-kpi-tone-green">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Orders</span></div>
      <div class="oa-kpi-value"><?php echo esc_html(number_format_i18n($order_total)); ?></div>
    </div>
    <div class="oa-card oa-kpi-card oa-kpi-tone-blue">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Revenue</span></div>
      <div class="oa-kpi-value"><?php echo esc_html(number_format_i18n($revenue_total,2)); ?></div>
    </div>
    <div class="oa-card oa-kpi-card oa-kpi-tone-orange">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Avg order value</span></div>
      <div class="oa-kpi-value"><?php echo esc_html(number_format_i18n($avg_order_value,2)); ?></div>
    </div>
    <div class="oa-card oa-kpi-card oa-kpi-tone-indigo">
      <div class="oa-kpi-top"><span class="oa-kpi-label">Peak day</span></div>
      <div class="oa-kpi-value oa-small"><?php echo esc_html($peak_day); ?></div>
      <div class="oa-kpi-foot"><?php echo esc_html(number_format_i18n($peak_revenue,2)); ?></div>
    </div>
  </div>

  <div class="oa-card">
    <div class="oa-card-h">Daily revenue</div>
    <table class="widefat striped">
      <thead><tr><th>Date</th><th>Orders</th><th>Revenue</th></tr></thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="3">No revenue data in selected range.</td></tr>
      <?php else: foreach($rows as $r): ?>
        <tr>
          <td><?php echo esc_html($r['day']); ?></td>
          <td><?php echo esc_html(number_format_i18n((int)$r['orders'])); ?></td>
          <td><?php echo esc_html(number_format_i18n((float)$r['revenue'],2)); ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
    <?php if (!empty($rows_pager)) echo $rows_pager; ?>
  </div>
</div>
