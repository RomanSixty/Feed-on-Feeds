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
include_once("fof-main.php");

$result = fof_db_get_feeds();

while($row = fof_db_get_row($result))
{
	$title = $row['feed_title'];
	$id = $row['feed_id'];
	fof_update_feed($id);
}

fof_db_optimize();

ob_end_clean();
?>
