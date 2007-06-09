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

include("header.php");

$feed = $_GET['feed'];
$feeds = array();

if($feed)
{
    $feed = fof_db_get_feed_by_id($feed);
    $feeds[] = $feed;
}
else
{
    $result = fof_db_get_feeds();
    while($feed = fof_db_get_row($result))
    {
        $feeds[] = $feed;
    }
}

$feeds = fof_multi_sort($feeds, 'feed_cache_attempt_date', false);

print("<script>\nwindow.onload = ajaxupdate;\nfeedslist = [");
    
foreach($feeds as $feed)
{
	$title = $feed['feed_title'];
	$id = $feed['feed_id'];
    
    $feedjson[] = "{'id': $id, 'title': '" . addslashes($title) . "'}";
}

print(join($feedjson, ", "));
print("];\n</script>");

print("<br>");

include("footer.php");
?>

