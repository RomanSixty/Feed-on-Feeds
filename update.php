<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * update.php - updates feeds with feedback
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include 'header.php';

echo "<br>\n";

$p = &FoF_Prefs::instance();
$admin_prefs = $p->admin_prefs;

$feeds = array();

if (!empty($_GET['feed'])) {
	$feed = fof_db_get_feed_by_id($_GET['feed']);
	if (empty($feed)) {
		$feed_status = "<span style=\"color:red\">Error: unknown feed '" . $_GET['feed'] . "'</span>";
	} else {
		$feeds[] = $feed;
		$feed_status = " is waiting to update...";
	}
	echo '<div id="feed_id_' . $feed['feed_id'] . '">'
	. fof_render_feed_link($feed) . ' ' . $feed_status
	. "</div>\n";
} else {
	if ($fof_user_id == 1) {
		$result = fof_db_get_feeds_needing_attempt();
	} else {
		$result = fof_db_get_subscriptions(fof_current_user(), true);
	}

	while (($feed = fof_db_get_row($result)) !== false) {
		if ((time() - $feed['feed_cache_date']) < ($admin_prefs['manualtimeout'] * 60)) {
			list($timestamp) = fof_nice_time_stamp($feed['feed_cache_date']);
			$feed_status = " was just updated $timestamp!";
		} else if (time() < $feed['feed_cache_next_attempt']) {
			list($timestamp) = fof_nice_time_stamp($feed['feed_cache_next_attempt']);
			$feed_status = " isn't due for an update for $timestamp.";
		} else {
			$feeds[] = $feed;
			$feed_status = " is waiting to update...";
		}
		echo '<div id="feed_id_' . $feed['feed_id'] . '">'
		. fof_render_feed_link($feed) . ' ' . $feed_status
		. "</div>\n";
	}
}

$feeds = fof_multi_sort($feeds, 'feed_cache_attempt_date', false);

$feedjson = array();
foreach ($feeds as $feed) {
	$feedjson[] = json_encode(array('id' => $feed['feed_id'], 'title' => $feed['feed_title']));
}

echo "<script>\nwindow.onload = ajaxupdate;\nfeedslist = [ " . implode(', ', $feedjson) . " ];\n</script>\n";

include 'footer.php';
?>

