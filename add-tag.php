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
ob_start("ob_gzhandler");

header("Content-Type: text/plain; charset=utf-8");

include_once("fof-main.php");

$tags = $_GET['tag'];
$item = $_GET['item'];
$remove = $_GET['remove'];

foreach(explode(" ", $tags) as $tag)
{
    if($remove == 'true')
    {
    fof_untag_item(fof_current_user(), $item, $tag);
    }
    else
    {
    fof_tag_item(fof_current_user(), $item, $tag);
    }
}
?>
