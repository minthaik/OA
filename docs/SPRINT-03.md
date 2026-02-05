# Sprint 03 - Phase 1 Closure (Week 5-6)

This sprint closes remaining Phase 1 (v1.0) P0 items.

## Objectives
- Deliver actionable data-quality audit utilities.
- Expand automated regression coverage for report + ingestion paths.
- Publish release/troubleshooting docs for handoff readiness.

## Scope
1. Data quality audit model + UI surface + CLI output
2. Regression smoke expansion (pagination + ingestion upsert checks)
3. Release checklist + troubleshooting documentation

## Delivered
- [x] `OA_Reports::data_quality_audit()` with:
  - [x] Future-dated row checks
  - [x] Ingestion freshness check
  - [x] Campaign/revenue/coupon sanity checks
  - [x] Duplicate natural-key detection across daily tables
  - [x] Unknown-device share and localhost referrer-noise checks
  - [x] Event-name format checks
- [x] Health page data-quality section (`includes/views/health.php`)
- [x] Health snapshot now includes `data_quality_audit`
- [x] New WP-CLI action: `wp ordelix data-quality --from=YYYY-MM-DD --to=YYYY-MM-DD`
- [x] Regression smoke additions:
  - [x] Data-quality audit assertion
  - [x] Paged report payload contract assertion
  - [x] Ingestion pipeline smoke (collect endpoint + upsert guardrails)
- [x] Release docs:
  - [x] `docs/RELEASE-CHECKLIST.md`
  - [x] `docs/TROUBLESHOOTING.md`

## Validation
- PHP lint passes for updated files.
- Existing CI smoke entrypoint (`wp ordelix regression-smoke`) now includes ingestion coverage.

## Exit
- Phase 1 P0 backlog items are complete in `docs/ROADMAP.md`.
