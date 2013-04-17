<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * view-action.php - marks selected items as read (or unread)
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once("fof-main.php");

$items = array();
while (list ($key, $val) = each ($_POST))
{
    if($val == "checked")
    {
        $key = substr($key, 1);
        $items[] = $key;
    }
}

if ( ! empty($_POST['deltag']))
{
    fof_untag(fof_current_user(), $_REQUEST['deltag']);
}
else if ( ! empty($_POST['feed']))
{
    fof_db_mark_feed_read(fof_current_user(), $_POST['feed']);
}
else if ( ! empty($_POST['mark_read']))
{
    fof_db_mark_read(fof_current_user(), array($_POST['mark_read']));
}
else if ( ! empty($_POST['fold']))
{
    fof_db_fold(fof_current_user(), array($_POST['fold']));
}
else if ( ! empty($_POST['unfold']))
{
    fof_db_unfold(fof_current_user(), array($_POST['unfold']));
}
else if ( ! empty($_POST['tag_read']))
{
    fof_db_mark_tag_read(fof_current_user(), $_POST['tag_read']);
}
else
{
    if ( ! empty($items) && ! empty($_POST['action']))
    {
        if($_POST['action'] == 'read')
        {
            fof_db_mark_read(fof_current_user(), $items);
        }

        if($_POST['action'] == 'unread')
        {
            fof_db_mark_unread(fof_current_user(), $items);
        }
    }

    if ( ! empty($_POST['return']))
        header("Location: " . urldecode($_POST['return']));
}
?>
