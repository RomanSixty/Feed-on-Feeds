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

$how = (isset($_GET['how'])) ? $_GET['how'] : NULL;
$which = (isset($_GET['which'])) ? $_GET['which'] : NULL;
if (isset($how) && $how == 'paged' && ! isset($which)) {
    $which = 0;
}
$order = (isset($_GET['order'])) ? $_GET['order'] : $fof_prefs_obj->get('order');
$what = (isset($_GET['what'])) ? $_GET['what'] : 'unread';
$feed = (isset($_GET['feed'])) ? $_GET['feed'] : NULL;
$when = (isset($_GET['when'])) ? $_GET['when'] : NULL;
$howmany = (isset($_GET['howmany'])) ? $_GET['howmany'] : NULL;
$search = (isset($_GET['search'])) ? $_GET['search'] : NULL;
$noedit = (isset($_GET['noedit'])) ? $_GET['noedit'] : NULL;

$result = fof_db_get_item_count(fof_current_user(), $what, $feed, $search);

$itemcount = 0;

while ($cnt = fof_db_get_row($result, 'count'))
    $itemcount += $cnt;

$title = fof_view_title($feed, $what, $when, $which, $howmany, $search, $itemcount);

// Placeholder to push content down:
?>
<ul id="item-display-controls-spacer" class="inline-list">
	<li class="orderby">[new to old]</li>
	<li class="orderby">[old to new]</li>
	<li><strong>Mark all read</strong></li>
	<li>Flag all</li>
	<li>Unflag all</li>
	<li>Toggle all</li>
	<li><strong>Mark flagged read</strong></li>
	<li>Mark flagged unread</li>
	<li>Show all</li>
	<li>Hide all</li>
	<li>Untag all</li>
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
	<li><a href="javascript:mark_read()"><strong>Mark flagged read</strong></a></li>
	<li><a href="javascript:mark_unread()">Mark flagged unread</a></li>
	<li><a href="javascript:show_all()">Show all</a></li>
	<li><a href="javascript:hide_all()">Hide all</a></li>
	<li><a href="javascript:untag_all()">Untag all</a></li>
</ul>



<!-- close this form to fix first item! -->

		<form id="itemform" name="items" action="view-action.php" method="post" onSubmit="return false;">
		<input type="hidden" name="action" />
		<input type="hidden" name="return" />

<?php
	$links = fof_get_nav_links($feed, $what, $when, $which, $howmany, $search, $itemcount);

	if($links)
	{
		echo "<center>$links</center>";
	}

$items = fof_get_items(fof_current_user(), $feed, $what, $when, $which, $howmany, $order, $search);

$first = true;

foreach ($items as $item) {
    $item_id = $item['item_id'];
    $visibility = in_array("folded", $item['tags']) ? "hidden" : "shown";
    if ($first) {
        print "<script>firstItem = 'i$item_id'; </script>";
        $first = false;
    }
    print '<div class="item ' . $visibility . '" id="i' . $item_id . '"  onclick="return itemClicked(event)">';
    fof_render_item($item);
    print '</div>';
}

if(count($result) == 0)
{
	echo "<p><i>No items found.</i></p>";
}

?>
		</form>

        <div id="end-of-items"></div>

<?php
        if($links)
        {
            echo "<center>$links</center>";
        }
?>

<script>itemElements = $$('.item');</script>
