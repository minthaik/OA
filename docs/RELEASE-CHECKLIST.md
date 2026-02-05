# Release Checklist (v1.0)

Use this before tagging a release.

## 1) Code + QA
- [ ] `php -l` passes for all plugin PHP files
- [ ] `node --check assets/admin.js` passes
- [ ] `wp ordelix self-test --strict --format=table` passes
- [ ] `wp ordelix regression-smoke --from=YYYY-MM-DD --to=YYYY-MM-DD --strict --format=table` passes
- [ ] Key admin pages load without warnings (Dashboard, Goals, Funnels, Retention, Campaigns, Coupons, Revenue, Health, Settings)

## 2) Data Safety
- [ ] `wp ordelix health --format=table` shows no FAIL checks
- [ ] `wp ordelix data-quality --from=YYYY-MM-DD --to=YYYY-MM-DD --format=table` has no FAIL findings
- [ ] Compliance export runs: `wp ordelix data-export --from=YYYY-MM-DD --to=YYYY-MM-DD --format=json`
- [ ] Range erase validated in non-production first

## 3) UI/UX
- [ ] Filter bars and card spacing are consistent across pages
- [ ] Paged tables render + navigate correctly
- [ ] Save View and Advanced Filters overlays open/close correctly on desktop/mobile
- [ ] No unrelated third-party notices visible in Ordelix screens

## 4) Docs
- [ ] `README.md` commands and features match implementation
- [ ] `docs/ROADMAP.md` status is current
- [ ] `docs/SPRINT-*.md` updated with latest sprint outcomes
- [ ] `docs/TROUBLESHOOTING.md` reflects current common issues

## 5) Git/Release
- [ ] All intended files committed to `main`
- [ ] CI is green
- [ ] Version/tag created
- [ ] Release notes published (highlights + breaking changes + upgrade notes)
