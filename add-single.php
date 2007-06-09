<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * add.php - displays form to add a feed
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once("fof-main.php");

set_time_limit(60*10);

$url = $_POST['url'];
if(!$url) $url = $_GET['url'];

print(fof_subscribe(fof_current_user(), $url));
?>
