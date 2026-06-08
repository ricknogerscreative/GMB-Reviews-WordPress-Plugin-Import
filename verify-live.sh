#!/bin/bash
# Live verification for edoa-review-sync. Run AFTER starting the Local site (DB must be up).
# Auto-detects Local's MySQL socket + binaries. Activates plugin, runs sync, spot-checks, verifies cron.
set -e

PHP=/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php
WPCLI=/tmp/wp-cli.phar
ROOT=/Users/ricknogers/ClaudeCode/Projects/EDOA/website/local-env/app/public

[ -f "$WPCLI" ] || curl -sS -o "$WPCLI" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar

# Discover the running Local MySQL socket (run-id changes per restart).
SOCK="$(ps aux | grep -i "[m]ysqld" | tr ' ' '\n' | grep -E "socket=" | head -1 | sed 's/^socket=//')"
if [ -z "$SOCK" ]; then
  SOCK="$(find "$HOME/Library/Application Support/Local/run" -name mysqld.sock 2>/dev/null | head -1)"
fi
[ -n "$SOCK" ] || { echo "FATAL: no running Local MySQL socket found — is the site started?"; exit 1; }
echo "Using socket: $SOCK"

# DB_HOST override (wp-config honors EDOA_WP_DB_HOST). Add Local's mysql client to PATH for db subcommands.
export EDOA_WP_DB_HOST="localhost:${SOCK}"
MYSQLBIN="$(dirname "$(find "$HOME/Library/Application Support/Local/lightning-services" -path "*/bin/darwin-arm64/bin/mysql" 2>/dev/null | head -1)")"
[ -n "$MYSQLBIN" ] && export PATH="$MYSQLBIN:$PATH"

wp() { "$PHP" "$WPCLI" --path="$ROOT" "$@"; }

echo "== DB connectivity =="
wp eval 'global $wpdb; echo $wpdb->get_var("SELECT 1") === "1" ? "DB OK".PHP_EOL : "DB FAIL".PHP_EOL;'

echo "== Airtable constants loaded? =="
wp eval 'echo "PAT="  . ( defined("EDOA_AIRTABLE_PAT")     && EDOA_AIRTABLE_PAT     ? "set" : "MISSING" ) . PHP_EOL;
         echo "BASE=" . ( defined("EDOA_AIRTABLE_BASE_ID") ? EDOA_AIRTABLE_BASE_ID : "MISSING" ) . PHP_EOL;'

echo "== Activate plugin =="
wp plugin activate edoa-review-sync

echo "== Run sync =="
wp eval 'print_r( ( new EDOA_Review_Sync() )->run() );'

echo "== Counts =="
wp eval 'echo "testimonials total (publish): " . wp_count_posts("testimonial")->publish . PHP_EOL;'
wp eval '$n=get_posts(["post_type"=>"testimonial","numberposts"=>-1,"fields"=>"ids","meta_key"=>"_edoa_is_best_overall","meta_value"=>1]); echo "best-overall flagged: ".count($n).PHP_EOL;'

echo "== Spot-check one synced post =="
wp eval '$p = get_posts(["post_type"=>"testimonial","numberposts"=>1,"meta_key"=>"_edoa_airtable_id"]);
         if ($p){ $id=$p[0]->ID;
           echo "rating="     . get_post_meta($id,"testimonial_rating",true) . PHP_EOL;
           echo "value="      . get_post_meta($id,"_edoa_value_score",true) . PHP_EOL;
           echo "loc_id="     . get_post_meta($id,"_edoa_location_id",true) . PHP_EOL;
           echo "quote_len="  . strlen((string)get_post_meta($id,"testimonial_quote",true)) . PHP_EOL;
           echo "topics="     . implode(",", wp_get_object_terms($id,"edoa_topic",["fields"=>"slugs"])) . PHP_EOL;
           echo "services="   . implode(",", wp_get_object_terms($id,"edoa_service",["fields"=>"slugs"])) . PHP_EOL;
           echo "legacy_svc=" . (get_post_meta($id,"_edoa_service_ids",true) === "" ? "stripped" : "PRESENT(bad)") . PHP_EOL;
         } else { echo "NO synced posts found" . PHP_EOL; }'

echo "== Location-match coverage =="
wp eval '$all=get_posts(["post_type"=>"testimonial","numberposts"=>-1,"fields"=>"ids","meta_key"=>"_edoa_airtable_id"]);
         $matched=0; foreach($all as $id){ if((int)get_post_meta($id,"_edoa_location_id",true)>0) $matched++; }
         echo "matched ".$matched." / ".count($all)." synced posts to a location".PHP_EOL;'

echo "== Topic/Service term coverage =="
wp eval 'foreach(["financing","root-canals","emergency"] as $t){
           $tax = in_array($t,["financing","emergency"],true) ? "edoa_topic" : "edoa_service";
           $n = (new WP_Query(["post_type"=>"testimonial","posts_per_page"=>-1,"fields"=>"ids","no_found_rows"=>true,
                 "tax_query"=>[["taxonomy"=>$tax,"field"=>"slug","terms"=>[$t]]]]))->posts;
           echo $tax."/".$t." => ".count($n)." posts".PHP_EOL;
         }'

echo "== Cron scheduled? =="
wp cron event list --fields=hook,next_run 2>/dev/null | grep edoa_rs_daily_sync || echo "cron NOT scheduled (re-activate plugin)"

echo "== Done =="
