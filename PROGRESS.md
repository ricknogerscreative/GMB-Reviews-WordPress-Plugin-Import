# PROGRESS — edoa-review-sync Display Layer v2

Plan: `EDOA/docs/superpowers/plans/2026-06-06-edoa-review-sync-display-v2.md`
Execution: subagent-driven (implementer → spec review → code-quality review per task).

1. [DONE] **Custom taxonomies** — `edoa_topic`+`edoa_service` registered, seeded on activation. Plugin `1d372ad`.
2. [DONE] **Quota selector (TDD)** — `EDOA_Review_Selector` by Value Score across per-context buckets; ranker retired; 7/7 tests pass. Plugin `7f6067d`,`31fe3f8`.
3. [DONE] **Sync rewrite** — reads Display Ready/Value Score/Display Text/Topics/Services; writes taxonomies + post_date; drops legacy meta + tag-map. Plugin `db820a0`,`088311f`. Verified live: 1007 testimonials, 1007/1007 loc-matched, terms populated.
4. [DONE] **Renderer + shortcode** — `EDOA_Testimonials_Renderer::get/render/shortcode`, `[edoa_testimonials]`, guarded wrappers, line-clamp CSS/JS. Plugin `3c6f8d1`,`ba51aee`. Verified live: all sources + alias + guards work.
5. [DONE] **Homepage ACF component** — `template-parts/acf-components/testimonials.php` delegates to renderer (DRY). Theme `601f5e0`.
6. [IN PROGRESS / NOT STARTED] **Location + service templates + ACF flex bug** — partial broken edit to `single-location.php` was REVERTED. To do: single-location.php (keep markup, `edoa_testimonials_get(source=location)`; convert the section's `endwhile;wp_reset_postdata()` ~L532 → `endforeach;`), single-service.php (`edoa_testimonials_render(source=service)`), `templates/acf-full.php` + `templates/acf-hybrid.php` (`edoa_pc_sections`→`edoa_hp_sections`). Commit ONLY these 4 (theme has unrelated WIP — see CLAUDE.md).
7. [TODO] **verify-live.sh v2** — spot-check taxonomies, value score, legacy-meta stripped, topic/service coverage. Plugin branch.
8. [TODO] **Front-end verify + place shortcodes** — location/service/homepage/financing render correctly; place production shortcodes; check debug.log.
9. [TODO] **Push** — both branches. GATED: confirm with Rick first.

Branches: plugin `feat/review-display-v2` (off `229c57e`), theme `feat/testimonials-display` (off `7c169b8`).
