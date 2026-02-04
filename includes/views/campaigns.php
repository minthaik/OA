<?php if(!defined('ABSPATH')) exit; ?>
<div class="wrap oa-wrap">
  <h1>Campaigns</h1>
  <div class="oa-controls">
    <?php if (!empty($range_html)) echo $range_html; ?>
    <?php if (!empty($filters_html)) echo $filters_html; ?>
  </div>
  <div class="oa-card">
    <div class="oa-card-head">
      <div class="oa-card-h">Top campaigns</div>
      <div class="oa-card-tools"><button type="button" class="button" data-oa-export="campaigns" data-from="<?php echo esc_attr($from); ?>" data-to="<?php echo esc_attr($to); ?>">Export CSV</button></div>
    </div>
    <table class="widefat striped">
      <thead><tr><th>Source</th><th>Medium</th><th>Campaign</th><th>Views</th><th>Conversions</th><th>Value</th></tr></thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="6">No campaign data in selected range.</td></tr>
      <?php else: foreach($rows as $r): ?>
        <tr>
          <td><?php echo esc_html($r['source'] ?: '-'); ?></td>
          <td><?php echo esc_html($r['medium'] ?: '-'); ?></td>
          <td><?php echo esc_html($r['campaign'] ?: '-'); ?></td>
          <td><?php echo esc_html(number_format_i18n((int)$r['views'])); ?></td>
          <td><?php echo esc_html(number_format_i18n((int)$r['conversions'])); ?></td>
          <td><?php echo esc_html(number_format_i18n((float)$r['value'],2)); ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
    <p class="description">Campaigns appear when UTM parameters exist. Conversions/value require goals.</p>
  </div>
</div>
