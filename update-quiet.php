<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * update-quiet.php - updates all feeds without producing output
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

set_time_limit(60*10);

ob_start();

$fof_no_login = true;
$fof_user_id = 1;
include_once("fof-main.php");

$p =& FoF_Prefs::instance();
$fof_admin_prefs = $p->prefs;

fof_log("=== update started, timeout = $fof_admin_prefs[autotimeout], purge = $fof_admin_prefs[purge] ===", "update");

$result = fof_db_get_feeds_needing_attempt();

$feeds = array();

while($feed = fof_db_get_row($result))
{
    if((time() - $feed["feed_cache_date"]) > ($fof_admin_prefs["autotimeout"] * 60)
	&& (time() > $feed["feed_cache_next_attempt"]))
    {
        $feeds[] = $feed;
    }
    else
    {
        fof_log("skipping $feed[feed_url]", "update");
    }
}

$feeds = fof_multi_sort($feeds, 'feed_cache_attempt_date', false);

foreach($feeds as $feed)
{
	$id = $feed['feed_id'];
    fof_log("updating $feed[feed_url]", "update");
	fof_update_feed($id);
}

fof_log("optimizing database", "update");

fof_db_optimize();

fof_log("=== update complete ===", "update");

ob_end_clean();
?>
