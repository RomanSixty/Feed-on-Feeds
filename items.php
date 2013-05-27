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

include_once('fof-main.php');
include_once('fof-render.php');

$what = (isset($_GET['what'])) ? $_GET['what'] : NULL;
$how = (isset($_GET['how'])) ? $_GET['how'] : NULL;

/* default to viewing unread items */
if (empty($what)) {
    $what = 'unread';
    /* default to page view only if we defaulted on what to view */
    if (empty($how)) {
        $how = 'paged';
    }
}

$which = (isset($_GET['which'])) ? $_GET['which'] : NULL;
if (isset($how) && $how == 'paged' && ! isset($which)) {
    $which = 0;
}
$order = (isset($_GET['order'])) ? $_GET['order'] : $fof_prefs_obj->get('order');
if ($order != 'desc' && $order != 'asc')
    $order = 'desc';

$feed = (isset($_GET['feed'])) ? $_GET['feed'] : NULL;
$when = (isset($_GET['when'])) ? $_GET['when'] : NULL;
$howmany = (isset($_GET['howmany'])) ? $_GET['howmany'] : NULL;
$search = (isset($_GET['search'])) ? $_GET['search'] : NULL;
$noedit = (isset($_GET['noedit'])) ? $_GET['noedit'] : NULL;

$itemcount = 0;
$result = fof_db_get_item_count(fof_current_user(), $what, $feed, $search);
while ($cnt = fof_db_get_row($result, 'count'))
    $itemcount += $cnt;

$title = fof_view_title($feed, $what, $when, $which, $howmany, $search, $itemcount);

// Placeholder to push content down:
?>
<div id="item-display-controls" class="banner ontop">
<ul class="inline-list">
<?php
    $item_controls = '';

    $qv = array('feed' => $feed,
                'what' => $what,
                'when' => $when,
                'how' => $how,
                'howmany' => $howmany);

    $item_controls .= '	<li class="orderby">';
    if ($order == 'asc')
        $item_controls .= '<a href="' . fof_url('.', array_merge($qv, array('order' => 'desc'))) . '">';
    $item_controls .= '[new to old]';
    if ($order == 'asc')
        $item_controls .= '</a>';
    $item_controls .= "</li>\n";

    $item_controls .= '	<li class="orderby">';
    if ($order == 'desc')
        $item_controls .= '<a href="' . fof_url('.', array_merge($qv, array('order' => 'asc'))) . '">';
    $item_controls .= '[old to new]';
    if ($order == 'desc')
        $item_controls .= '</a>';
    $item_controls .= "</li>\n";

    $item_controls .= '	<li><a href="javascript:flag_all();mark_read()"><strong>Mark all read</strong></a></li>';
    $item_controls .= '	<li><a href="javascript:flag_all()">Flag all</a></li>';
    $item_controls .= '	<li><a href="javascript:unflag_all()">Unflag all</a></li>';
    $item_controls .= '	<li><a href="javascript:toggle_all()">Toggle all</a></li>';
    $item_controls .= '	<li><a href="javascript:mark_read()"><strong>Mark flagged read</strong></a></li>';
    $item_controls .= '	<li><a href="javascript:mark_unread()">Mark flagged unread</a></li>';
    $item_controls .= '	<li><a href="javascript:show_all()">Show all</a></li>';
    $item_controls .= '	<li><a href="javascript:hide_all()">Hide all</a></li>';
    $item_controls .= '	<li><a href="javascript:untag_all()">Untag all</a></li>';

    echo $item_controls;
?>
</ul>
</div>

<div id="item-display-controls-spacer" class="banner">
<ul class="inline-list">
<?php echo $item_controls; ?>
</ul>
</div>

<br class="clearer">

<div id="display-summary"><?php echo $title; ?></div>

<?php
if ($fof_prefs_obj->get('keyboard')) {
?>
<div id="keyboard-legend" style="display:none">
	<span style="font-variant:small-caps;">Keyboard Commands</span>
	<dl>
		<dt>?</dt><dd>Toggle this display.</dd>
		<dt>H</dt><dd>Toggle folding of all visible items.</dd>
		<dt>h</dt><dd>Toggle folding of current item.</dd>
		<dt>s</dt><dd>Toggle starring of current item.</dd>
		<dt>f</dt><dd>Flag current item.</dd>
		<dt>F</dt><dd>Flag current and all previous items.</dd>
		<dt>U</dt><dd>Unflag all items.</dd>
		<dt>j</dt><dd>Scroll current item, flag if at end of item, move to next item.</dd>
		<dt>J</dt><dd>Flag current item, move to next.</dd>
		<dt>n</dt><dd>Skip to next item.</dd>
		<dt>p</dt><dd>Skip to previous item.</dd>
		<dt>N</dt><dd>Skip to last item.</dd>
		<dt>P</dt><dd>Skip to first item.</dd>
		<dt>r</dt><dd>Refresh sidebar.</dd>
	</dl>
</div>

<?php
}
?>

<!-- close this form to fix first item! -->
		<form id="itemform" name="items" action="view-action.php" method="post" onSubmit="return false;">
		<input type="hidden" name="action" />
		<input type="hidden" name="return" />

<?php
    $links = fof_get_nav_links($feed, $what, $when, $which, $howmany, $search, $itemcount);
    $links = ($links ? '<div class="pagination-nav">' : '') . $links . ($links ? "</div>\n" : '');

    echo $links;

$items = fof_get_items(fof_current_user(), $feed, $what, $when, $which, $howmany, $order, $search);

if ( ! empty($items)) {
    list($first_item) = $items;
    echo "<script>firstItem = 'i" . $first_item['item_id'] . "';</script>\n";

    foreach ($items as $item) {
        fof_render_item($item, true);
    }
} else {
    echo "<p><i>No items found.</i></p>\n";
}

?>
<div id="end-of-items"></div>
		</form>


<?php
    echo $links;
?>

<script>itemElements = $$('.item');</script>
