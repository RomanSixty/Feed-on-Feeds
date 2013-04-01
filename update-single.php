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

include_once('fof-main.php');

$feed_id = $_GET['feed'];
list ($count, $error) = fof_update_feed($feed_id);

print "<span id=\"update_result_f$feed_id\">";

if ($count) {
    print "<span style=\"color:green\">$count new items.</span>";
} else {
    print "No new items.";
}

if ($error) {
    print " <span style=\"color:red\">$error</span>";
}

print "</span><br>\n";

?>
