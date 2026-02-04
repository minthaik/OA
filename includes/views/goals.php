<?php if(!defined('ABSPATH')) exit; ?>
<div class="wrap oa-wrap">
  <div class="oa-page-head">
    <h1>Goals</h1>
    <span class="oa-save-slot" data-oa-save-slot></span>
  </div>
  <div class="oa-controls">
    <?php if (!empty($range_html)) echo $range_html; ?>
    <?php if (!empty($filters_html)) echo $filters_html; ?>
  </div>

  <?php if (!empty($can_manage)): ?>
  <div class="oa-card">
    <div class="oa-card-h">Add goal</div>
    <form method="post" class="oa-goal-form">
      <?php wp_nonce_field('oa_goals'); ?>
      <input type="hidden" name="oa_action" value="add_goal">
      <div class="oa-form-row">
        <label>Name <input type="text" name="name" required></label>
        <label>Type <select name="type"><option value="page">Page</option><option value="event">Event</option></select></label>
        <label>Match (path/event) <input type="text" name="match_value" required placeholder="/thank-you or form_submit"></label>
        <label>Meta key (optional) <input type="text" name="meta_key" placeholder="form"></label>
        <label>Meta value (optional) <input type="text" name="meta_value" placeholder="contact"></label>
        <label>Value (optional) <input type="number" step="0.01" name="value" value="0"></label>
      </div>
      <div class="oa-form-actions">
        <button class="button button-primary">Add</button>
      </div>
    </form>
  </div>
  <?php else: ?>
  <div class="oa-card">
    <p class="oa-muted">Read-only access: only users with manage permission can add or edit goals.</p>
  </div>
  <?php endif; ?>

  <div class="oa-card">
    <div class="oa-card-head">
      <div class="oa-card-h">Goal performance</div>
      <div class="oa-card-tools"><button type="button" class="button" data-oa-export="goals" data-from="<?php echo esc_attr($from); ?>" data-to="<?php echo esc_attr($to); ?>">Export CSV</button></div>
    </div>
    <table class="widefat striped">
      <thead><tr><th>Name</th><th>Type</th><th>Match</th><th>Meta</th><th>Hits</th><th>Value</th><th>Status</th><?php if (!empty($can_manage)): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
      <?php if (empty($stats)): ?>
        <tr><td colspan="<?php echo !empty($can_manage) ? '8' : '7'; ?>">No goals yet. <?php echo !empty($can_manage) ? 'Add your first goal above.' : 'Ask an admin to add one.'; ?></td></tr>
      <?php else: foreach($stats as $g): ?>
        <tr>
          <td><?php echo esc_html($g['name']); ?></td>
          <td><?php echo esc_html($g['type']); ?></td>
          <td><?php echo esc_html($g['match_value']); ?></td>
          <td><?php echo esc_html(($g['meta_key'] && $g['meta_value']) ? ($g['meta_key'].'='.$g['meta_value']) : '-'); ?></td>
          <td><?php echo esc_html(number_format_i18n((int)$g['hits'])); ?></td>
          <td><?php echo esc_html(number_format_i18n((float)$g['value_sum'],2)); ?></td>
          <td><?php echo !empty($g['is_enabled']) ? 'Enabled':'Disabled'; ?></td>
          <?php if (!empty($can_manage)): ?>
          <td class="oa-actions">
            <form method="post" style="display:inline">
              <?php wp_nonce_field('oa_goals'); ?>
              <input type="hidden" name="oa_action" value="toggle_goal">
              <input type="hidden" name="id" value="<?php echo esc_attr($g['id']); ?>">
              <input type="hidden" name="is_enabled" value="<?php echo !empty($g['is_enabled'])?0:1; ?>">
              <button class="button"><?php echo !empty($g['is_enabled'])?'Disable':'Enable'; ?></button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this goal?');">
              <?php wp_nonce_field('oa_goals'); ?>
              <input type="hidden" name="oa_action" value="delete_goal">
              <input type="hidden" name="id" value="<?php echo esc_attr($g['id']); ?>">
              <button class="button button-link-delete">Delete</button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
