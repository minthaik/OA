<?php if(!defined('ABSPATH')) exit; ?>
<div class="wrap oa-wrap">
  <div class="oa-hero">
    <div>
      <h1>Funnels</h1>
      <p class="oa-subtitle">Build multi-step journeys and track progression quality over time.</p>
    </div>
    <div class="oa-card-tools">
      <span class="oa-save-slot" data-oa-save-slot></span>
    </div>
  </div>
  <div class="oa-controls">
    <?php if (!empty($range_html)) echo $range_html; ?>
    <?php if (!empty($filters_html)) echo $filters_html; ?>
  </div>
  <?php
  $diag_map=[];
  foreach((array)($diagnostics ?? []) as $d){
    $diag_map[intval($d['id'] ?? 0)]=$d;
  }
  $fmt_delta=function($pct){
    if ($pct===null) return 'new';
    return (($pct>=0)?'+':'').number_format_i18n((float)$pct,1).'%';
  };
  ?>

  <?php if (!empty($can_manage)): ?>
  <div class="oa-card">
    <div class="oa-card-h">Add funnel</div>
    <form method="post" class="oa-funnel-form">
      <?php wp_nonce_field('oa_funnels'); ?>
      <input type="hidden" name="oa_action" value="add_funnel">
      <div class="oa-form-row">
        <label>Name <input type="text" name="name" required></label>
      </div>
      <p class="oa-muted">Use drag-and-drop to reorder steps. Keep steps specific for cleaner conversion signals.</p>
      <div class="oa-steps" id="oa-steps">
        <div class="oa-step">
          <span class="oa-step-index">1</span>
          <select name="step_type[]"><option value="page">Page</option><option value="event">Event</option></select>
          <input type="text" name="step_value[]" placeholder="/pricing or form_submit" required>
          <input type="text" name="step_meta_key[]" placeholder="meta key (optional)">
          <input type="text" name="step_meta_value[]" placeholder="meta value (optional)">
        </div>
        <div class="oa-step">
          <span class="oa-step-index">2</span>
          <select name="step_type[]"><option value="page">Page</option><option value="event">Event</option></select>
          <input type="text" name="step_value[]" placeholder="/thank-you or booking_complete" required>
          <input type="text" name="step_meta_key[]" placeholder="meta key (optional)">
          <input type="text" name="step_meta_value[]" placeholder="meta value (optional)">
        </div>
      </div>
      <div class="oa-form-actions">
        <button type="button" class="button" id="oa-add-step">Add another step</button>
        <button class="button button-primary">Create funnel</button>
      </div>
    </form>
  </div>
  <?php else: ?>
  <div class="oa-card">
    <p class="oa-muted">Read-only access: only users with manage permission can create or modify funnels.</p>
  </div>
  <?php endif; ?>

  <div class="oa-card">
    <div class="oa-card-h">Funnels (approx)</div>
    <table class="widefat striped">
      <thead><tr><th>Funnel</th><th>Step 1</th><th>Last step</th><th>Conversion</th><th>Trend delta</th><th>Top drop-off</th><th>Status</th><?php if (!empty($can_manage)): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
      <?php if (empty($funnels)): ?>
        <tr><td colspan="<?php echo !empty($can_manage) ? '8' : '7'; ?>">No funnels yet. <?php echo !empty($can_manage) ? 'Create one above to start tracking progression.' : 'Ask an admin to create one.'; ?></td></tr>
      <?php else: foreach($funnels as $f): $st=null; foreach($stats as $s){ if($s['id']==$f['id']){$st=$s; break;} } $diag=$diag_map[intval($f['id'])] ?? null; ?>
        <tr>
          <td>
            <div><strong><?php echo esc_html($f['name']); ?></strong></div>
            <div class="oa-small">
              <?php foreach($f['steps'] as $step): ?>
                <span class="oa-pill"><?php echo esc_html($step['step_num'].': '.$step['step_type'].' '.$step['step_value']); ?></span>
              <?php endforeach; ?>
            </div>
            <?php if (!empty($diag['steps'])): ?>
              <details class="oa-explain">
                <summary><span>Step diagnostics</span></summary>
                <div class="oa-explain-body">
                  <ul class="oa-mini-bars oa-list-tight">
                    <?php foreach($diag['steps'] as $sd): ?>
                      <?php if (empty($sd['next_step_num'])) continue; ?>
                      <?php $drop_width=max(0,min(100,floatval($sd['drop_rate'] ?? 0))); ?>
                      <li>
                        <span class="oa-mini-bars__label"><?php echo esc_html('Step '.intval($sd['step_num']).' -> '.intval($sd['next_step_num'])); ?></span>
                        <span class="oa-mini-bars__bar"><i style="width:<?php echo esc_attr(number_format($drop_width,1,'.','')); ?>%"></i></span>
                        <span class="oa-mini-bars__value"><?php echo esc_html('drop '.number_format_i18n((float)$sd['drop_rate'],1).'% ('.number_format_i18n((int)$sd['hits']).' -> '.number_format_i18n((int)$sd['next_hits']).')'); ?></span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              </details>
            <?php endif; ?>
          </td>
          <td><?php echo esc_html($st?number_format_i18n($st['step1']):'-'); ?></td>
          <td><?php echo esc_html($st?number_format_i18n($st['step_last']):'-'); ?></td>
          <td><?php echo esc_html($st?$st['conversion'].'%':'-'); ?></td>
          <td>
            <?php if (!empty($diag)): ?>
              <?php $delta=$diag['conversion_delta_pct'] ?? null; ?>
              <?php $delta_cls='oa-badge oa-badge-muted'; if ($delta!==null && $delta>0) $delta_cls='oa-badge oa-badge-ok'; elseif ($delta!==null && $delta<0) $delta_cls='oa-badge oa-badge-alert'; ?>
              <span class="<?php echo esc_attr($delta_cls); ?>"><?php echo esc_html($fmt_delta($delta)); ?></span>
            <?php else: ?>
              -
            <?php endif; ?>
          </td>
          <td><?php echo !empty($diag['top_drop_reason']) ? esc_html($diag['top_drop_reason']) : '-'; ?></td>
          <td><?php echo !empty($f['is_enabled'])?'Enabled':'Disabled'; ?></td>
          <?php if (!empty($can_manage)): ?>
          <td class="oa-actions">
            <form method="post" style="display:inline">
              <?php wp_nonce_field('oa_funnels'); ?>
              <input type="hidden" name="oa_action" value="toggle_funnel">
              <input type="hidden" name="id" value="<?php echo esc_attr($f['id']); ?>">
              <input type="hidden" name="is_enabled" value="<?php echo !empty($f['is_enabled'])?0:1; ?>">
              <button class="button"><?php echo !empty($f['is_enabled'])?'Disable':'Enable'; ?></button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this funnel?');">
              <?php wp_nonce_field('oa_funnels'); ?>
              <input type="hidden" name="oa_action" value="delete_funnel">
              <input type="hidden" name="id" value="<?php echo esc_attr($f['id']); ?>">
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
