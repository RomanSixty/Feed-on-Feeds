<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * items.php - displays right hand side "frame"
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once("fof-main.php");
include_once("fof-render.php");

if($_GET['how'] == 'paged' && !isset($_GET['which']))
{
	$which = 0;
}
else
{
	$which = $_GET['which'];
}

$order = $_GET['order'];

if(!isset($_GET['what']))
{
    $what = "unread";
}
else
{
    $what = $_GET['what'];
}

if(!isset($_GET['order']))
{
	$order = $fof_prefs_obj->get("order");
}

$how = $_GET['how'];
$feed = $_GET['feed'];
$when = $_GET['when'];
$howmany = $_GET['howmany'];

$title = fof_view_title($_GET['feed'], $what, $_GET['when'], $which, $_GET['howmany'], $_GET['search']);
$noedit = $_GET['noedit'];

?>

<ul id="item-display-controls-spacer" class="inline-list">
	<li class="orderby">[new to old]</li>
	<li class="orderby">[old to new]</li>
	<li><a href="javascript:flag_all();mark_read()"><strong>Mark all read</strong></a></li>
	<li><a href="javascript:flag_all()">Flag all</a></li>
	<li><a href="javascript:unflag_all()">Unflag all</a></li>
	<li><a href="javascript:toggle_all()">Toggle all</a></li>
	<li><a href="javascript:mark_read()">Mark flagged read</a></li>
	<li><a href="javascript:mark_unread()">Mark flagged unread</a></li>
	<li><a href="javascript:show_all()">Show all</a></li>
	<li><a href="javascript:hide_all()">Hide all</a></li>
</ul>

<br style="clear: both"><br>

<p><?php echo $title?></p>


<ul id="item-display-controls" class="inline-list">
	<li class="orderby"><?php

	echo ($order == "desc") ? '[new to old]' : "<a href=\".?feed=$feed&amp;what=$what&amp;when=$when&amp;how=$how&amp;howmany=$howmany&amp;order=desc\">[new to old]</a>" ;
	
	?></li>
	<li class="orderby"><?php

	echo ($order == "asc") ? '[old to new]' : "<a href=\".?feed=$feed&amp;what=$what&amp;when=$when&amp;how=$how&amp;howmany=$howmany&amp;order=asc\">[old to new]</a>" ;
	
	?></li>
	<li><a href="javascript:flag_all();mark_read()"><strong>Mark all read</strong></a></li>
	<li><a href="javascript:flag_all()">Flag all</a></li>
	<li><a href="javascript:unflag_all()">Unflag all</a></li>
	<li><a href="javascript:toggle_all()">Toggle all</a></li>
	<li><a href="javascript:mark_read()">Mark flagged read</a></li>
	<li><a href="javascript:mark_unread()">Mark flagged unread</a></li>
	<li><a href="javascript:show_all()">Show all</a></li>
	<li><a href="javascript:hide_all()">Hide all</a></li>
</ul>



<!-- close this form to fix first item! -->

		<form id="itemform" name="items" action="view-action.php" method="post" onSubmit="return false;">
		<input type="hidden" name="action" />
		<input type="hidden" name="return" />

<?php
	$links = fof_get_nav_links($_GET['feed'], $what, $_GET['when'], $which, $_GET['howmany']);

	if($links)
	{
?>
		<center><?php echo $links ?></center>

<?php
	}


$result = fof_get_items(fof_current_user(), $_GET['feed'], $what, $_GET['when'], $which, $_GET['howmany'], $order, $_GET['search']);

$first = true;

foreach($result as $row)
{
	$item_id = $row['item_id'];
	if($first) print "<script>firstItem = 'i$item_id'; </script>";
	$first = false;
	print '<div class="item shown" id="i' . $item_id . '"  onclick="return itemClicked(event)">';
	fof_render_item($row);
	print '</div>';
}

if(count($result) == 0)
{
	echo "<p><i>No items found.</i></p>";
}

?>
		</form>
        
        <div id="end-of-items"></div>

<script>itemElements = $$('.item');</script>