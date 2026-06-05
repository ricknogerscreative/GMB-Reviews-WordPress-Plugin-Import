#!/bin/bash
# Live verification for edoa-review-sync. Run AFTER starting the Local site (DB must be up).
# Activates plugin, runs sync, spot-checks results, verifies cron.
set -e

PHP=/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php
WPCLI=/tmp/wp-cli.phar
ROOT=/Users/ricknogers/ClaudeCode/Projects/EDOA/website/local-env/app/public
wp() { "$PHP" "$WPCLI" --path="$ROOT" "$@"; }

[ -f "$WPCLI" ] || curl -sS -o "$WPCLI" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar

echo "== DB connectivity =="
wp core is-installed && echo "DB OK"

echo "== Airtable constants loaded? =="
wp eval 'echo "PAT="  . ( defined("EDOA_AIRTABLE_PAT")     && EDOA_AIRTABLE_PAT     ? "set" : "MISSING" ) . PHP_EOL;
         echo "BASE=" . ( defined("EDOA_AIRTABLE_BASE_ID") ? EDOA_AIRTABLE_BASE_ID : "MISSING" ) . PHP_EOL;'

echo "== Activate plugin =="
wp plugin activate edoa-review-sync

echo "== Run sync =="
wp eval 'print_r( ( new EDOA_Review_Sync() )->run() );'

echo "== Counts =="
wp eval 'echo "testimonials total: " . wp_count_posts("testimonial")->publish . PHP_EOL;'
wp post list --post_type=testimonial --meta_key=_edoa_is_best_overall --meta_value=1 --format=count --posts_per_page=-1 \
  | xargs -I{} echo "best-overall flagged: {}"

echo "== Spot-check one synced post =="
wp eval '$p = get_posts(["post_type"=>"testimonial","numberposts"=>1,"meta_key"=>"_edoa_airtable_id"]);
         if ($p){ $id=$p[0]->ID;
           echo "rating="    . get_post_meta($id,"testimonial_rating",true) . PHP_EOL;
           echo "loc_id="    . get_post_meta($id,"_edoa_location_id",true) . PHP_EOL;
           echo "loc_name="  . get_post_meta($id,"_edoa_location_name",true) . PHP_EOL;
           echo "quote_len=" . strlen(get_post_meta($id,"testimonial_quote",true)) . PHP_EOL;
         } else { echo "NO synced posts found" . PHP_EOL; }'

echo "== Cron scheduled? =="
wp cron event list --fields=hook,next_run | grep edoa_rs_daily_sync || echo "cron NOT scheduled (re-activate plugin)"

echo "== Done =="
