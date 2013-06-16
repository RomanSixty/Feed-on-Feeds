<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * delete.php - deletes a feed and all items
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once("fof-main.php");

$feed = $_GET['feed'];

fof_delete_subscription(fof_current_user(), $feed);

include("header.php");
?>

Deleted.

<?php include("footer.php") ?>
