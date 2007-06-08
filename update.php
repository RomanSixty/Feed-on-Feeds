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

set_time_limit(60*10);

echo '<br>';

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

foreach($feeds as $feed)
{
	$title = $feed['feed_title'];
	$id = $feed['feed_id'];
	print "Updating <b>$title</b>...";
	
	$count = fof_update_feed($id);

	print "done. ";
	
	if($count)
	{
		print "<b><font color=red>$count new items</font></b>";
	}
	print "<br>";
}

fof_db_optimize();
?>

<br>Update complete.

<?php include("footer.php") ?>
