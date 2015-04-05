<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * update-single.php - updates a single feed
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once 'fof-main.php';

$feed_id = $_POST['feed'];
list($count, $error) = fof_update_feed($feed_id);
$feed = fof_db_get_feed_by_id($feed_id);

if ($count) {
	echo "<span style=\"color:green\">" . fof_render_feed_link($feed) . " has $count new items.</span>";
} else {
	echo "<span>" . fof_render_feed_link($feed) . " has no new items.</span>";
}

if ($error) {
	echo " <span style=\"color:red\">$error</span>";
}

?>
