# edoa-review-sync — Display Layer v2 (session snapshot 2026-06-06)

WordPress plugin: syncs curated Google reviews from Airtable → `testimonial` CPT, displays them context-aware (homepage / location / service / generic) via a shared renderer + `[edoa_testimonials]` shortcode.

## Workflow in progress
Building **Display Layer v2** after an Airtable restructure moved all classification/curation to the source (tagger.js). Executing via superpowers **subagent-driven-development** (fresh implementer per task + two-stage review: spec compliance, then code quality).

- **Spec:** `EDOA/docs/superpowers/specs/2026-06-03-edoa-review-sync-design.md` (see the "v2 — Display Layer After Airtable Restructure" section)
- **Plan (authoritative, full code per task):** `EDOA/docs/superpowers/plans/2026-06-06-edoa-review-sync-display-v2.md`
- **Original handoffs:** `EDOA/docs/superpowers/handoff/2026-06-05-*.md`

## Key paths
| What | Path |
|---|---|
| Plugin GIT SOURCE (EDIT HERE) | `EDOA/website/plugins/edoa-review-sync` — branch `feat/review-display-v2` |
| Deployed copy (Local serves) | `EDOA/website/local-env/app/public/wp-content/plugins/edoa-review-sync` |
| Theme (live = source) | `EDOA/website/local-env/app/public/wp-content/themes/edoa` — branch `feat/testimonials-display` |
| Local-bundled PHP CLI (no system php) | `/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php` |

**Deploy plugin (after every plugin edit, before verify-live):**
```bash
rsync -a --delete --exclude tests --exclude deploy --exclude verify-live.sh --exclude .git --exclude README.md \
  "EDOA/website/plugins/edoa-review-sync/" \
  "EDOA/website/local-env/app/public/wp-content/plugins/edoa-review-sync/"
```
Theme needs NO deploy (live = source).
**Live verify:** `bash EDOA/website/plugins/edoa-review-sync/verify-live.sh` (auto-detects Local MySQL socket; Local site must be running).

## Decisions locked this session
1. **Pool = per-context quotas** (not flat top-N): per location 15, per location×topic 5, per location×service 5, per topic 10, per service 10, best-overall 30. Union ≈ 1007 posts live.
2. **Storage = custom taxonomies** `edoa_topic` + `edoa_service` (replaced serialized `_edoa_service_ids` + fragile `LIKE ':37;'`).
3. **Retired** `class-tag-service-map.php` — Airtable tagger is sole source of truth for Topics/Services.
4. **Default order:** Value Score desc; `source=recent` → Review Date desc. `orderby=value|date|rand` overrides.
5. **Airtable verified live** (base `apprT9J3TtYkidSbl`, table `Reviews`): Topics(8): financing,insurance,emergency,pain_relief,staff_quality,wait_time,cleanliness,results. Services(26 slugs). `Value Score` 0–100 (no recency). `Display Ready` checkbox = master gate (16,068 true). `Display Text` ~280ch. `Reviewer Display` "First L.". Stray field-name spaces fixed at source.
6. **root-canals dup** is WP-side only (two `service` posts; second slug `root-canals-2`). Renderer `SERVICE_SLUG_ALIASES` maps `root-canals-2`→`root-canals` term. Confirmed working live.
7. **Branch per repo;** stage ONLY my files.

## ⚠️ Theme repo hygiene (IMPORTANT)
The theme working tree has PRE-EXISTING uncommitted changes from UNRELATED work — DO NOT touch/stage/commit:
`acf-json/group_edoa_page_content.json`, `assets/css/sections.css`, `template-parts/acf-components/faq.php`, `template-parts/acf-components/services-grid.php`.
Always `git add <explicit file>` — never `git add .`/`-A`.

## Completed (committed)
**Plugin `feat/review-display-v2`** (off main @ 229c57e, HEAD @ 9435c0e):
- Task 1 — taxonomies `edoa_topic`/`edoa_service` + activation seed. `includes/class-taxonomies.php`, `edoa-review-sync.php`. (`1d372ad`)
- Task 2 — `EDOA_Review_Selector` quota selector (TDD, retire ranker). `includes/class-review-selector.php`, `tests/test-review-selector.php`; deleted `class-review-ranker.php`+test. (`7f6067d`, `31fe3f8`)
- Task 3 — sync rewrite: Display-Ready filter, Value Score sort+cap 5000, Display Text/Reviewer Display, taxonomy upsert, post_date=Review Date, drop legacy meta + tag-map. `includes/class-review-sync.php`, `edoa-review-sync.php`; deleted `class-tag-service-map.php`+test. (`db820a0`, `088311f`) — **verified live: 1007 testimonials, 1007/1007 location-matched, terms populated (financing 532, root-canals 147, emergency 699).**
- Task 4 — shared renderer + `[edoa_testimonials]` shortcode + guarded wrappers `edoa_testimonials_render()`/`edoa_testimonials_get()` + line-clamp CSS/JS. `includes/class-testimonials-renderer.php`, `edoa-review-sync.php`, `assets/testimonials.css`, `assets/testimonials.js`. (`3c6f8d1`, `ba51aee`) — **verified live: all sources work, alias works, empty-arg guard works, recent=date order works.**
- Task 7 — `verify-live.sh` v2 spot-checks: `_edoa_value_score`, `edoa_topic`/`edoa_service` taxonomy terms, `legacy_svc=stripped`; topic/service coverage block (financing/root-canals/emergency). All pass live. (`9435c0e`)

**Theme `feat/testimonials-display`** (off main @ 7c169b8, HEAD @ d8d8f5b):
- Task 5 — homepage Testimonials ACF component delegates to renderer (DRY). `template-parts/acf-components/testimonials.php`. (`601f5e0`)
- Task 6 — `single-location.php`: replaced WP_Query with `edoa_testimonials_get(source=location)`, converted `while`/`wp_reset_postdata` to `foreach`/$tid with $tid passed to `get_field()`. `single-service.php`: deleted `$test_q` block, replaced section with `edoa_testimonials_render(source=service)`; `$show_testimonials` gate preserved. `templates/acf-full.php`+`templates/acf-hybrid.php`: `edoa_pc_sections`→`edoa_hp_sections`. All linted clean. (`d8d8f5b`)

## STOPPING POINT
**Task 8** (front-end browser verification + shortcode placement) — automated data-layer checks pass (verify-live.sh). Visual browser checks not yet done by Rick.

## Remaining (in order)
- **Task 8** — browser: (a) location page → testimonials section shows location-specific cards; (b) root-canals service page → service-scoped cards + root-canals-2 alias works; (c) homepage → ACF Testimonials component renders; (d) place shortcode `[edoa_testimonials topic="financing,insurance" count="4" heading="What patients say about cost & coverage"]` on financing page; check debug.log for notices.
- **Task 9** — push both branches. **GATED: confirm with Rick before pushing.**

## Notes
- Live sync already ran (1007 testimonials). Re-running is safe/idempotent (upsert on Review ID + cleanup).
- Cron `edoa_rs_daily_sync` scheduled 03:00. Manual: Testimonials → Review Sync admin page.
- Creds via mu-plugin `wp-content/mu-plugins/edoa-rs-config.php` (reads gmb-reviews/.env on Local).
