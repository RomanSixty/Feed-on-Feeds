<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * sidebar.php - sidebar for all pages
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once('fof-main.php');

fof_set_content_type();

/* quell warnings */
function fof_sidebar_get_key_($array, $key, $default=NULL) {
    if ( ! empty($array[$key]))
        return $array[$key];
    return $default;
}

?>
<img id="throbber" src="image/throbber.gif" align="left" style="position: fixed; left: 0; top: 0; display: none;" />
<center id="welcome">Welcome <b><?php echo $fof_user_name ?></b>! <a href="prefs.php">prefs</a><?php if ( ! defined('FOF_AUTH_EXTERNAL')) { ?> | <a href="logout.php">log out</a><?php } ?> | <a href="http://feedonfeeds.com/">about</a></center>
<br>
<center><a href="add.php"><b>Add Feeds</b></a> / <a href="update.php"><b>Update Feeds</b></a></center>

<ul id="nav">
<?php

$order = $fof_prefs_obj->get('feed_order');
$direction = $fof_prefs_obj->get('feed_direction');
$sharing = $fof_prefs_obj->get('sharing');

/* these parameters control highlighting of the active view */
$what = fof_sidebar_get_key_($_GET, 'what', 'unread');
$when = fof_sidebar_get_key_($_GET, 'when');
$search = fof_sidebar_get_key_($_GET, 'search');

$feeds = fof_get_feeds(fof_current_user(), $order, $direction);

$unread = 0;
$starred = 0;
$total = 0;
$n = 0;
foreach ($feeds as $row) {
    $n++;
    $unread += fof_sidebar_get_key_($row, 'feed_unread', 0);
    $starred += fof_sidebar_get_key_($row, 'feed_starred', 0);
    $total += fof_sidebar_get_key_($row, 'feed_items', 0);
}

$page_title_js = 'Feed on Feeds';
if ( ! empty($unread)) {
    $page_title_js .= " ($unread)";
}
?>
  <script>
    document.title=<?php echo json_encode($page_title_js); ?>;
    what=<?php echo json_encode($what); ?>;
    when=<?php echo json_encode($when); ?>;
    starred=<?php echo json_encode($starred); ?>;
  </script>

<?php
    echo '  <li' . ($what == 'unread' ? " class='current-view'" : '') . '>';
    echo '<a href="' . fof_url('.', array('what'=>'unread', 'how'=>'paged')) . '"><span style="color:red"><b>Unread' . ($unread ? " ($unread)" : '') . '</b></span></a>';
    echo ' [<a href="' . fof_url('.', array('what'=>'unread')) . '">unpaged</a>]';
    echo "</li>\n";

    echo '  <li' . ($what == 'star' ? " class='current-view'" : '') . '>';
    echo '<a href="' . fof_url('.', array('what'=>'star', 'how'=>'paged')). '"><img class="star-icon-small" src="image/star-on.gif" /> Starred <span id="starredcount">' . ($starred ? "($starred)" : '') . '</span></a>';
    echo ' [<a href="' . fof_url('.', array('what'=>'star')). '">unpaged</a>]';
    echo "</li>\n";

    echo '  <li' . ($what == 'all' && isset($when) ? " class='current-view'" : '') . '>';
    echo '<a href="' . fof_url('.', array('what'=>'all', 'when'=>'today')). '">&lt; Today</a>';
    echo "</li>\n";

    echo '  <li' . ($what == 'all' && empty($when) ? " class='current-view'" : '') . '>';
    echo '<a href="' . fof_url('.', array('what'=>'all', 'how'=>'paged')) . '">All Items' . ($total ? "($total)" : '') . "</a>";
    echo "</li>\n";

    echo '  <li' . ( ! empty($search) ? ' class="current-view"' : '') . '>';
    echo '<a href="javascript:Element.toggle(\'search\');Field.focus(\'searchfield\');void(0);">Search</a>' . "\n";
    echo '    <form action="." id="search"' . (empty($search) ? ' style="display:none"' : '') . ">\n";
    echo '      <input type="hidden" name="how" value="paged">' . "\n";
    echo '      <input type="hidden" name="what" value="' . (empty($what) || $what == 'unread' ? 'all' : htmlentities($what, ENT_QUOTES)) . "\">\n";
    if ( ! empty($when))
        echo '      <input type="hidden" name="when" value="' . htmlentities($when, ENT_QUOTES) . "\">\n";
    echo '      <input id="searchfield" name="search" value="' . htmlentities($search, ENT_QUOTES) . "\">\n";
    echo "    </form>\n";
    echo "  </li>\n";
?>
</ul>
<!--nav end-->

<?php
$unread_id = fof_db_get_tag_by_name('unread');
$star_id = fof_db_get_tag_by_name('star');
$folded_id = fof_db_get_tag_by_name('folded');

$tags = fof_get_tags(fof_current_user());
$taglines = array();
$n = 0;
foreach ($tags as $tag) {
    $tag_id = $tag['tag_id'];
    if ($tag_id == $unread_id
    ||  $tag_id == $star_id
    ||  $tag_id == $folded_id)
        continue;

    $tagline = '';

    $tag_name = $tag['tag_name'];
    $tag_name_html = htmlentities($tag_name);
    $tag_name_json = htmlentities(json_encode($tag_name), ENT_QUOTES);

    $count = $tag['count'];
    $unread = $tag['unread'];

    $tagline .= '    <tr' . (++$n % 2 ? ' class="odd-row"' : '') . '>';

    $tagline .= '<td>';
    if ($unread)
        $tagline .= '<a class="unread" href="' . fof_url('.', array('what' => "$tag_name unread", 'how' => 'paged')) . "\">$unread</a>/";
    $tagline .= '<a href="' . fof_url('.', array('what' => $tag_name, 'how' => 'paged')) . "\">$count</a>";
    $tagline .= '</td>';

    $tagline .= '<td><b><a href="' . fof_url('.', array('what' => $tag_name, 'how' => 'paged')). '">' . $tag_name_html . '</a></b></td>';

    $tagline .= '<td><a href="#" title="untag all items" onclick="return sb_del_tag_conf(' . $tag_name_json . ');">[x]</a></td>';

    if ($sharing == 'all_tagged')
        $tagline .= '<td><a href="' . fof_url('./shared.php', array('user' => $fof_user_id, 'which' => $tag_name, 'how' => 'paged')) . '">[' . $tag_name_html . ']</a>';

    $tagline .= '</tr>';

    $taglines[] = $tagline;
}

if ( ! empty($taglines)) { ?>
<div id="tags">
  <table cellspacing="0" cellpadding="1" border="0" id="taglist">
    <tr class="heading"><td><span class="unread">#</span></td><td>tag name</td><td>untag</td><?php if ($sharing == 'all_tagged') echo '<td>shared page</td>'; ?></tr>
<?php
    echo implode("\n", $taglines);
?>
  </table>
</div>
<!--tags end-->
<?php
}
?>
<br>

<div id="feeds">
  <div id="feedlist">
    <table cellspacing="0" cellpadding="1" border="0">
      <tr class="heading">
<?php

$title = array('feed_age' => 'sort by last update time',
               'max_date' => 'sort by last new item',
               'feed_unread' => 'sort by number of unread items',
               'feed_url' => 'sort by feed URL',
               'feed_title' => 'sort by feed title'
              );

$name = array('feed_age' => 'age',
              'max_date' => 'latest',
              'feed_unread' => '#',
              'feed_url' => 'feed',
              'feed_title' => 'title'
             );

$simple_sidebar = $fof_prefs_obj->get('simple_sidebar');

if ($simple_sidebar) {
    $columns = array("feed_unread", "feed_title");
} else {
    $columns = array("feed_age", "max_date", "feed_unread", "feed_url", "feed_title");
}

foreach ($columns as $col) {
    if ($col == $order)
        $feed_order_toggle = $direction == 'asc' ? 'desc' : 'asc';
    else
        $feed_order_toggle = 'asc';
    $onclick = "return change_feed_order('$col', '$feed_order_toggle')";

    echo '        <td' . ($col == 'feed_unread' ? ' style="text-align:right"' : '') . '>';
    echo '<span class="nowrap"><a href="#" title="' . $title[$col] . '" onclick="' . $onclick . '">';
    if ($col == 'feed_unread')
        echo '<span class="unread">#</span>';
    else
        echo $name[$col];

    if ($col == $order)
        echo $direction == 'asc' ? '&darr;' : '&uarr;';

    echo "</a></span></td>\n";
}
?>
        <td></td>
      </tr>

<?php

$t = 0;
foreach ($feeds as $row) {
    $id = $row['feed_id'];
    $url = $row['feed_url'];
    $link = $row['feed_link'];
    if ($link == '[no link]')
        $link = $url;
    $title = $row['feed_title'];
    if ($title == '[no title]')
        $title = $link;
    $title_html = htmlentities($title);
    $title_json = htmlentities(json_encode($title), ENT_QUOTES);

    $image = $row['feed_image'];
    $description = $row['feed_description'];
    $age = $row['feed_age'];
    $unread = fof_sidebar_get_key_($row, 'feed_unread', 0);
    $starred = fof_sidebar_get_key_($row, 'feed_starred', 0);
    $tagged = fof_sidebar_get_key_($row, 'feed_tagged', 0);
    $items = fof_sidebar_get_key_($row, 'feed_items', 0);
    $agestr = fof_sidebar_get_key_($row, 'agestr');
    $agestrabbr = fof_sidebar_get_key_($row, 'agestrabbr');
    $lateststr = fof_sidebar_get_key_($row, 'lateststr');
    $lateststrabbr = fof_sidebar_get_key_($row, 'lateststrabbr');

    $qv = array('feed' => $id);
    $view_feed_url = fof_url('.', array_merge($qv, array('how' => 'paged')));
    $view_feed_all_url = fof_url('.', array_merge($qv, array('how' => 'paged', 'what' => 'all')));
    $unsubscribe_feed_url = fof_url('delete.php', $qv);
    $update_feed_url = fof_url('update.php', $qv);

    echo '<tr' . (++$t % 2 ? ' class="odd-row"' : '') . ">\n";

    if (! $image || ! $fof_prefs_obj->get('favicons'))
        $image = 'image/feed-icon.png';

    if ($simple_sidebar) {
        echo '  <td align="center"><a href="' . $url . '" title="feed"><img class="feed-icon" src="' . $image . '" /></a></td>' . "\n";

        echo '  <td><a href="' . ($unread ? $view_feed_url : $view_feed_all_url) . '">' . $title_html . '</a>' . ($unread ? " ($unread)" : '') . "</td>" . "\n";

        echo '  <td><a href="' . $unsubscribe_feed_url . '" title="delete" onclick="return sb_unsub_conf(' . $title_json . ');">[x]</a></td>' . "\n";
    } else {
        echo "  <td style=\"text-align: right\"><span title=\"$agestr\" id=\"${id}-agestr\">$agestrabbr</span></td>\n";

        echo "  <td style=\"text-align: right\"><span title=\"$lateststr\" id=\"${id}-lateststr\">$lateststrabbr</span></td>\n";

        echo "  <td style=\"text-align: right\" class=\"nowrap\" id=\"${id}-items\">";
        if ($unread)
            echo "<a class=\"unread\" title=\"new items\" href=\"$view_feed_url\">$unread</a>/";
        echo "<a href=\"$view_feed_all_url\" title=\"all items, $starred starred, $tagged tagged\">$items</a>";
        echo "</td>\n";

        echo '  <td align="center">';
        echo '<a href="' . $url . '" title="feed"><img class="feed-icon" src="' . $image . '" /></a>';
        echo "</td>\n";

        echo '  <td><a href="' . $link . '" title="home page"' . ($fof_prefs_obj->get('item_target') ? ' target="_blank"' : '') . '><b>' . $title_html . "</b></a></td>\n";

        echo '  <td><span class="nowrap">';
        echo '<a href="' . $update_feed_url . '" title="update">u</a>';
        echo ' <a href="#" title="mark all read" onclick="return sb_read_conf(' . $title_json . ', ' . $id . ');">m</a>';
        echo ' <a href="' . $unsubscribe_feed_url . '" title="delete" onclick="return sb_unsub_conf(' . $title_json . ');">d</a>';
        echo "</span></td>\n";
    }

    echo "</tr>\n";
}

?>
</table>
</div>
</div>
<!--sidebar end-->
<?php
    /* ??? */
    $order = fof_sidebar_get_key_($_GET, 'order', 'title');
    $direction = fof_sidebar_get_key_($_GET, 'direction', 'asc');
?>
