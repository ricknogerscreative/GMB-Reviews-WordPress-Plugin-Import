# PROGRESS — edoa-review-sync Display Layer v2

Plan: `EDOA/docs/superpowers/plans/2026-06-06-edoa-review-sync-display-v2.md`
Execution: subagent-driven (implementer → spec review → code-quality review per task).

1. [DONE] **Custom taxonomies** — `edoa_topic`+`edoa_service` registered, seeded on activation. Plugin `1d372ad`.
2. [DONE] **Quota selector (TDD)** — `EDOA_Review_Selector` by Value Score across per-context buckets; ranker retired; 7/7 tests pass. Plugin `7f6067d`,`31fe3f8`.
3. [DONE] **Sync rewrite** — reads Display Ready/Value Score/Display Text/Topics/Services; writes taxonomies + post_date; drops legacy meta + tag-map. Plugin `db820a0`,`088311f`. Verified live: 1007 testimonials, 1007/1007 loc-matched, terms populated.
4. [DONE] **Renderer + shortcode** — `EDOA_Testimonials_Renderer::get/render/shortcode`, `[edoa_testimonials]`, guarded wrappers, line-clamp CSS/JS. Plugin `3c6f8d1`,`ba51aee`. Verified live: all sources + alias + guards work.
5. [DONE] **Homepage ACF component** — `template-parts/acf-components/testimonials.php` delegates to renderer (DRY). Theme `601f5e0`.
6. [DONE] **Location + service templates + ACF flex bug** — `single-location.php` (edoa_testimonials_get, foreach/$tid), `single-service.php` (edoa_testimonials_render, $show_testimonials gate), `templates/acf-full.php`+`templates/acf-hybrid.php` (edoa_pc_sections→edoa_hp_sections). Linted clean. Theme `d8d8f5b`.
7. [DONE] **verify-live.sh v2** — spot-check reads `_edoa_value_score`, taxonomy terms, `legacy_svc=stripped`; topic/service coverage block added. All checks pass live. Plugin `9435c0e`.
8. [IN PROGRESS — needs browser] **Front-end verify + place shortcodes** — automated checks blocked (WP-CLI DB socket issue in inline eval); verify-live.sh already confirmed data layer. Browser checks needed by Rick: (a) location page shows loc-specific cards, (b) root-canals service page shows service-scoped cards + root-canals-2 alias, (c) homepage ACF component renders, (d) place `[edoa_testimonials topic="financing,insurance" count="4" heading="What patients say about cost & coverage"]` on a financing page.
9. [TODO] **Push** — both branches. GATED: confirm with Rick first.

Branches: plugin `feat/review-display-v2` (off `229c57e`, now at `9435c0e`), theme `feat/testimonials-display` (off `7c169b8`, now at `d8d8f5b`).
