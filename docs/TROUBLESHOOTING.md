# Troubleshooting Guide

## 1) "Cannot modify header information" after export/download
Cause: another plugin/theme outputs content before headers are sent.

What Ordelix does:
- Falls back to browser-side blob download when headers already started.

What to check:
1. Disable other admin customizations/plugins temporarily.
2. Re-test download from Settings/Health.
3. Keep `WP_DEBUG_LOG` enabled and inspect early output source.

## 2) White screen after download
Cause: browser landed on response stream endpoint after direct output.

Fix:
- Update to latest plugin version (download fallback + redirect is implemented).
- If it still happens, check browser extensions blocking redirects.

## 3) No data appears in reports
Checklist:
1. Confirm plugin tracking is enabled in Settings.
2. Confirm consent mode is not blocking tracking.
3. Run `wp ordelix health --format=table`.
4. Run `wp ordelix data-quality --from=YYYY-MM-DD --to=YYYY-MM-DD --format=table`.
5. Validate collector manually by opening site pages in an incognito window.

## 4) Third-party notices still visible in Ordelix screens
Expected behavior:
- Non-Ordelix notices are hidden on Ordelix admin pages.

If visible:
1. Hard refresh browser cache.
2. Check for custom admin CSS/JS that overrides display rules.
3. Inspect DOM for notices rendered inside Ordelix container.

## 5) Save View / Advanced Filters panel odd behavior
Checklist:
1. Ensure `assets/admin.js` is loading (no JS console errors).
2. Confirm panel closes on outside click / Escape.
3. Check for conflicting scripts from admin optimization plugins.

## 6) Data quality warnings in Health
Common findings:
- Future-dated rows
- Unknown-device share too high
- Duplicate natural keys
- Campaign/revenue sanity issues

Action:
1. Export diagnostics JSON from Health.
2. Run `wp ordelix data-quality --from=YYYY-MM-DD --to=YYYY-MM-DD --format=json`.
3. Run `wp ordelix schema-repair`.
4. Re-run self-test and regression smoke.

## 7) Useful recovery commands
```bash
wp ordelix health --format=table
wp ordelix self-test --strict --format=table
wp ordelix regression-smoke --from=YYYY-MM-DD --to=YYYY-MM-DD --strict --format=table
wp ordelix schema-repair
wp ordelix cron-reschedule
wp ordelix cache-flush
wp ordelix table-optimize
```
