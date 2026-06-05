# EDOA Review Sync

Nightly WP-Cron sync of curated 4-5★ text reviews from Airtable into the `testimonial` CPT.

## Config — credentials (never commit values)
- `EDOA_AIRTABLE_PAT` — Airtable personal access token
- `EDOA_AIRTABLE_BASE_ID` — `apprT9J3TtYkidSbl`

Defined by an **mu-plugin** (`deploy/edoa-rs-config.php` → install to `wp-content/mu-plugins/`),
NOT wp-config.php — survives Local's wp-config rewrites and keeps creds out of this repo.
On Local it reads `AIRTABLE_PAT` / `AIRTABLE_BASE_ID` from `website/plugins/gmb-reviews/.env`.
On prod/staging: define the two constants via host env, or edit the mu-plugin source.

### WP-Cron note
This Local site has `DISABLE_WP_CRON` set (`mu-plugins/disable-wp-cron.php`), so the daily
event will NOT auto-fire here — use the manual "Run Sync Now" button, or run via WP-CLI.
On prod, WP-Cron fires on traffic; for low-traffic sites add a real cron hitting `wp-cron.php`.

### Dev: WP-CLI DB access
Local's MySQL is socket-only (TCP root blocked). wp-config `DB_HOST` honors
`EDOA_WP_DB_HOST`; `verify-live.sh` auto-detects the running socket and sets it.

## Behaviour
- Filters reviews to Stars >= 4 with non-empty text.
- Keeps top 15 per location + top 30 best-overall (union), deduped.
- Resolves Airtable City/State -> `location` post via `loc_city` / `loc_state` meta.
- Resolves Airtable Tags -> `service` post IDs via hardcoded map (see `class-tag-service-map.php`).
- Upserts by Airtable Review ID; deletes stale synced testimonials (enforces caps).
- Cron: daily 3am (`edoa_rs_daily_sync`). Manual trigger: Testimonials → Review Sync
  (requires `manage_options` + nonce).

## Testimonial post meta written by sync
- `_edoa_airtable_id` — Airtable Review ID (upsert key)
- `_edoa_location_id` — matched `location` post ID
- `_edoa_location_name` — "City, State"
- `_edoa_service_ids` — serialized array of `service` post IDs
- `_edoa_tags` — serialized array of Airtable tags
- `_edoa_is_best_overall` — 1 if in best-overall pool
- plus display fields: `testimonial_quote`, `testimonial_author`, `testimonial_rating`, `testimonial_location`

## Display
Theme component `template-parts/acf-components/testimonials.php`, sub-field `source_mode`:
`auto` (by page context) | `location` | `service` | `tags` | `best_overall`.
`auto` resolves: location page -> location reviews; service page -> service reviews;
otherwise best-overall (or tag-filtered if `filter_tags` set). A pinned
`featured_testimonials` relationship still overrides everything.

## Tests
Standalone PHP assertions (no WP needed), run with the Local PHP binary:
```
php tests/test-tag-service-map.php
php tests/test-review-ranker.php
```

## Tag → Service map
| Airtable tag | Service slug(s) |
|---|---|
| emergency | emergency-pain-relief, emergency-dental-exam |
| staff | comprehensive-dental-exam |
| wait_time | emergency-pain-relief |
| cleanliness | comprehensive-dental-exam |
| pricing | (none — no matching service) |
| insurance | (none — no matching service) |
