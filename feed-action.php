<?php
/*
 * This file is part of Feed On Feeds
 *
 * feed-action.php - performs various ajaxy actions on feeds
 *
 * Copyright (C) 2013 Justin Wind <justin.wind@gmail.com>
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once 'fof-main.php';

/* mark all items in feed as read, return updated sidebar entry */
if (!empty($_POST['read_feed'])) {
	fof_db_mark_feed_read(fof_current_user(), $_POST['read_feed']);
	$feed_row = fof_get_feed(fof_current_user(), $_POST['read_feed']);
	echo fof_render_feed_row($feed_row);

	exit();
}

/* update one feed, return replacement sidebar feed list content */
if (!empty($_POST['update_feedid'])) {
	list($count, $error) = fof_update_feed($_POST['update_feedid']);
	$feed_row = fof_get_feed(fof_current_user(), $_POST['update_feedid']);
	if (!empty($error)) {
        echo fof_render_feed_row($feed_row, $error);
	} else {
		echo fof_render_feed_row($feed_row);
	}
	exit();
}

/* returns a script block which updates a list of the subscribed sources for a tag */
if (!empty($_POST['update_tag_sources'])) {
	fof_set_content_type('application/javascript');

	$tag_id = fof_db_get_tag_by_name($_POST['update_tag_sources']);
	$subs = fof_db_subscriptions_by_tags(fof_current_user());

	/* FIXME: check timeouts, like below */

	if (!empty($subs[$tag_id])) {
		echo 'pendingUpdates(' . json_encode($subs[$tag_id]) . ');';
	}

	exit();
}

/* returns a script block which updates all updatable subscribed feeds */
if (!empty($_POST['update_subscribed_sources'])) {
	fof_set_content_type('application/javascript');

	$now = time();
	$timeout = $fof_prefs_obj->admin_prefs['manualtimeout'] * 60;

	$sources = array();
	$statement = fof_db_get_subscriptions(fof_current_user(), true);
	while (($feed = fof_db_get_row($statement)) !== false) {
		if (($now - $feed['feed_cache_date']) < $timeout) {
			continue;
		}

		if ($now < $feed['feed_cache_next_attempt']) {
			continue;
		}

		$sources[] = $feed['feed_id'];
	}

	if (!empty($sources)) {
		echo 'pendingUpdates(' . json_encode($sources) . ');';
	}

	exit();
}

/* returns a list of tags for a feed, with markup */
if (!empty($_POST['subscription_tag_list'])) {
	fof_set_content_type();
	$feed_id = $_POST['subscription_tag_list'];
	$feed_row = fof_get_feed(fof_current_user(), $feed_id);
	if (empty($feed_row)) {
		header('Status: 404 Not Found');
		echo 'No data for feed.';
		exit();
	}
	echo "\n";
	foreach ($feed_row['tags'] as $tag) {
		echo '		<li>'
		. $tag
		. ' <a href="#" onclick="subscription_tag_modify(' . htmlentities(implode(',', array(json_encode($feed_id), json_encode($tag), json_encode('delete'))), ENT_QUOTES) . '); return false;">'
			. '[x]'
			. '</a>'
			. '</li>'
			. "\n";
	}
	echo "	";

	exit();
}

/* modify a user's feed subscription */
if (!empty($_POST['subscription_tag'])) {
	if (empty($_POST['feed'])) {
		header('Status: 400 Bad Request');
		echo 'Incomplete data.';
		exit();
	}
	$tag = $_POST['subscription_tag'];
	$feed_id = $_POST['feed'];

	if (!empty($_POST['delete'])) {
		fof_untag_feed(fof_current_user(), $feed_id, $tag);
	} else {
		fof_tag_feed(fof_current_user(), $feed_id, $tag);
	}

	exit();
}

/** Serve out an SVG image showing a feed's activity history.
 */
if (!empty($_GET['feed_history'])) {
	include_once 'classes/svghistogram.php';

	fof_set_content_type('image/svg+xml');

	$history = fof_db_feed_history($_GET['feed_history']);

	$options = array(
		'title' => 'Feed History',
		'description' => 'Items added to feed.',
		'min_items' => max(31, $fof_prefs_obj->admin_prefs['purge'] + 7),
		'max_items' => 366,
		'label_zero' => '&#8593; today',
		'label_x' => 'days ago &#8594;',
	);
	if (!empty($fof_prefs_obj->admin_prefs['purge'])) {
		$options['shade_over'] = $fof_prefs_obj->admin_prefs['purge'];
		$options['max_items'] = min($options['max_items'], ceil($fof_prefs_obj->admin_prefs['purge'] * SVGHistogram::PHI));
	}

	$graph = new SVGHistogram($options);

	$graph->render($history);

	exit();
}

header('Status: 400 Bad Request');
echo 'Unknown request.';
?>
