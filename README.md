# Ordelix Analytics (v0.4.6)
Privacy-first, lightweight, local-first analytics for WordPress.

## Features
- Pageviews, top pages, referrers
- Auto events: outbound, downloads, tel:, mailto:, form submits
- Goals (page or event) + value attribution
- Funnels (multi-step page/event)
- Campaigns (UTM source/medium/campaign) with conversions/value
- Saved views (segments) for reusable report filters
- WooCommerce revenue aggregation (optional)
- WooCommerce coupon analytics (orders, discount, attributed revenue)
- Email reports + retention cleanup
- Automated anomaly alerts + insight summaries
- Anomaly drilldown view with baseline math, top movers, and explainable SQL checks
- System Health page (schema checks, cron status, cache/cleanup actions, migration history)
- Diagnostics export (JSON) + health/diagnostics REST endpoints
- WP-CLI maintenance commands (`wp ordelix ...`) for health and repair workflows
- Compliance tools (date-range export + erase helpers)
- Role/capability split (view vs manage) with repair tooling
- REST export endpoint for BI/CSV pipelines

## Install
1. Upload plugin folder to `wp-content/plugins/ordelix-analytics`
2. Activate
3. Visit **Ordelix Analytics** in WP admin

## Tracking / Developer API
- Manual event:
```js
window.ordelixTrack('quote_submit', {meta:'form=quote', value: 0});
```

## Roadmap
- Product roadmap and phased execution plan: `docs/ROADMAP.md`
- Active sprint logs: `docs/SPRINT-01.md`, `docs/SPRINT-02.md`

## Operations (WP-CLI)
- Health checks: `wp ordelix health`
- Self-test suite: `wp ordelix self-test --format=table` (use `--strict` to fail on warnings)
- Phase 1 regression smoke: `wp ordelix regression-smoke --from=YYYY-MM-DD --to=YYYY-MM-DD --format=table` (add `--strict` to fail on warnings)
- Diagnostics payload: `wp ordelix diagnostics --format=json`
- Maintenance:
  - `wp ordelix cleanup`
  - `wp ordelix cache-flush`
  - `wp ordelix schema-repair`
  - `wp ordelix cron-reschedule`
  - `wp ordelix caps-repair`
- Compliance:
  - `wp ordelix data-export --from=YYYY-MM-DD --to=YYYY-MM-DD --format=json`
  - `wp ordelix data-erase-range --from=YYYY-MM-DD --to=YYYY-MM-DD`
  - `wp ordelix data-erase-all --confirm=YES`

## Local smoke script (Windows / PowerShell)
- Run `scripts/phase1-smoke.ps1`
- Optional flags:
  - `-Strict`
  - `-From 2026-01-01 -To 2026-01-31`
  - `-Format json`

## CI
- GitHub Actions workflow: `.github/workflows/ci.yml`
- Runs:
  - PHP syntax lint for all plugin PHP files
  - Disposable WordPress + MySQL setup
  - Plugin activation + `wp ordelix regression-smoke`

## Privacy notes
- No cookies required for core usage
- Optional "approx uniques" uses truncated IP + salted hash, stored only as daily approximate count via transients

## Compliance and privacy notes (important)
Ordelix Analytics is designed to be privacy-first and local-first (no external services) with data minimization defaults.

- By default it runs in cookieless mode and stores aggregated counts (no raw hit logs, no fingerprinting).
- Some jurisdictions have additional rules (for example, ePrivacy/cookie laws) that may require consent even for analytics. This varies by country and implementation.
- Use **Settings -> Privacy & Consent** to require consent, or integrate your CMP via the `ordelix_analytics_can_track` filter or by setting `window.ordelixAnalyticsConsent=true`.
- Use **Settings -> Compliance tools** for date-range export/erase operations when responding to privacy/compliance requests.
- This plugin is not legal advice; site owners should review local requirements and update their privacy policy accordingly.
