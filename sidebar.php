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
<img id="throbber" src="image/throbber.gif" align="left" style="display: none;" />
<center id="welcome">Welcome <b><?php echo $fof_user_name ?></b>! <a href="prefs.php">prefs</a><?php if ( ! defined('FOF_AUTH_EXTERNAL')) { ?> | <a href="logout.php">log out</a><?php } ?> | <a href="http://feedonfeeds.com/">about</a></center>
<br>
<center><a href="add.php"><b>Add Feeds</b></a> / <a href="update.php"><b>Update Feeds</b></a></center>

<ul id="nav">
<?php

$order = $fof_prefs_obj->get('feed_order');
$direction = $fof_prefs_obj->get('feed_direction');
$sharing = $fof_prefs_obj->get('sharing');

$sidebar_style = $fof_prefs_obj->get('sidebar_style');
/* honor old pref, if user hasn't saved new-style yet */
if (empty($sidebar_style) && $fof_prefs_obj->get('simple_sidebar'))
    $sidebar_style = 'simple';

/* these parameters control highlighting of the active view */
$what = fof_sidebar_get_key_($_GET, 'what', 'unread');
$when = fof_sidebar_get_key_($_GET, 'when');
$search = fof_sidebar_get_key_($_GET, 'search');
$feed = fof_sidebar_get_key_($_GET, 'feed');

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
    echo '  <li' . (empty($feed) && $what == 'unread' ? " class='current-view'" : '') . '>';
    echo '<a href="' . fof_url('.', array('what'=>'unread', 'how'=>'paged')) . '"><span style="color:red"><b>Unread' . ($unread ? " ($unread)" : '') . '</b></span></a>';
    echo ' [<a href="' . fof_url('.', array('what'=>'unread')) . '">unpaged</a>]';
    echo "</li>\n";

    echo '  <li' . (empty($feed) && $what == 'star' ? " class='current-view'" : '') . '>';
    echo '<a href="' . fof_url('.', array('what'=>'star', 'how'=>'paged')). '"><img class="star-icon-small" src="image/star-on.gif" /> Starred <span id="starredcount">' . ($starred ? "($starred)" : '') . '</span></a>';
    echo ' [<a href="' . fof_url('.', array('what'=>'star')). '">unpaged</a>]';
    echo "</li>\n";

    echo '  <li' . (empty($feed) && $what == 'all' && isset($when) ? " class='current-view'" : '') . '>';
    echo '<a href="' . fof_url('.', array('what'=>'all', 'when'=>'today')). '">&lt; Today</a>';
    echo "</li>\n";

    echo '  <li' . (empty($feed) && $what == 'all' && empty($when) ? " class='current-view'" : '') . '>';
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
function fof_sidebar_tags_fancy() {
    global $sharing;

    $taglines = array();
    $n = 0;
    foreach (fof_get_tags(fof_current_user()) as $tag) {
        if (in_array($tag['tag_name'], array('unread', 'star', 'folded')))
            continue;

        $tag_name_html = htmlentities($tag['tag_name']);
        $tag_name_json = htmlentities(json_encode($tag['tag_name']), ENT_QUOTES);

        $tagline = '	<tr' . (++$n % 2 ? ' class="odd-row"' : '') . '>';

        $tagline .= '<td style="text-align: right;"><span class="unread"><a ' . ($tag['unread'] ? 'class="unread" ' : '') . 'href="' . fof_url('.', array('what'=>implode(' ', array($tag['tag_name'], 'unread')))) . '" title="unread items">' . $tag['unread'] . '</a></span></td>';

        $tagline .= '<td><b><a href="' . fof_url('.', array('what'=>$tag['tag_name'], 'how'=>'paged')) . '" title="' . $tag['count'] . ' total items">' . $tag['tag_name'] . '</a></b></td>';

        $tagline .= '<td>';
        $tagline .= '<ul class="feedmenu"><li>';
        $tagline .= '<a href="#" title="tag controls">&Delta;</a>';
        $tagline .=   '<ul>';
        $tagline .=     '<li>Update feeds</li>';
        $tagline .=     '<li><a href="#" title="mark all read" onclick="return sb_mark_tag_read(' . $tag_name_json . ')">Mark all items as read</a></li>';
        $tagline .=     '<li><a href="#" title="untag all items" onclick="return sb_del_tag_conf(' . $tag_name_json . ');">Delete tag from all items</a></li>';
        $tagline .=   '</ul>';
        $tagline .= '</li></ul>';
        $tagline .= '</td>';

        if ($sharing == 'all_tagged')
            echo '<td><a href="' . fof_url('./shared.php', array('user'=>$fof_user_id, 'which'=>$tag_name_html, 'how'=>'paged')) . '"></td>';

        $tagline .= '</tr>';

        $taglines[] = $tagline;
    }

    /* headers */
    if ( ! empty($taglines)) {
        echo "<div id=\"tags\">\n";
        echo "<table cellspacing=\"0\" cellpadding=\"1\" border=\"0\" class=\"taglist\">\n";
        echo "<thead>\n";
        echo '	<tr class="heading">';
        echo '<th style="text-align: right;"><span class="unread">#</span></th>';
        echo '<th style="width: 100%;">tag name</th>';
        echo '<th><span></span></th>';
        if ($sharing == 'all_tagged')
            echo '<th>shared page</th>';
        echo "</tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";
        echo implode("\n", $taglines);
        echo "\n</tbody>\n</table>\n</div>\n<!--tags end-->\n<br>\n\n";
    }
}

function fof_sidebar_tags_default() {
    global $sharing;

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

<?php
}

if (function_exists('fof_sidebar_tags_' . $sidebar_style))
    call_user_func('fof_sidebar_tags_' . $sidebar_style);
else
    fof_sidebar_tags_default();


?>
<div id="feeds">
  <table cellspacing="0" cellpadding="1" border="0">
    <thead>
      <tr class="heading">
<?php

/* all possible columns */
$title = array('feed_age' => 'sort by last update time',
               'max_date' => 'sort by last new item',
               'feed_unread' => 'sort by number of unread items',
               'feed_url' => 'sort by feed URL',
               'feed_title' => 'sort by feed title',
               'null' => ''
              );

$name = array('feed_age' => 'age',
              'max_date' => 'latest',
              'feed_unread' => '<span class="unread">#</span>',
              'feed_url' => 'feed',
              'feed_title' => 'title',
              'null' => ''
             );

$column_style = array('feed_title' => 'width:100%;',
                      'feed_unread' => 'text-align:right;'
                     );

switch ($sidebar_style) {
    case 'simple':
        $columns = array('feed_url', 'feed_unread', 'feed_title');
        break;

    case 'fancy':
        $columns = array('null', 'max_date', 'feed_unread', 'feed_title');
        break;

    default:
        $columns = array('feed_age', 'max_date', 'feed_unread', 'feed_url', 'feed_title');
}

foreach ($columns as $col) {
    if ($col == $order)
        $feed_order_toggle = $direction == 'asc' ? 'desc' : 'asc';
    else
        $feed_order_toggle = 'asc';
    $onclick = "return change_feed_order('$col', '$feed_order_toggle')";

    echo '		<th' . (empty($column_style[$col]) ? '' : (' style="' . $column_style[$col] . '"')) . '>';

    echo '<span class="nowrap"><a href="#" title="' . $title[$col] . '" onclick="' . $onclick . '">';
    echo $name[$col];

    if ($col == $order)
        echo $direction == 'asc' ? '&darr;' : '&uarr;';

    echo "</a></span></th>\n";
}
?>
        <th></th>
      </tr>
    </thead>
    <tbody>
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

    switch ($sidebar_style) {
        case 'simple': /* 'feed_unread', 'feed_title' */
            echo '  <td align="center"><a href="' . $url . '" title="feed"><img class="feed-icon" src="' . $image . '" /></a></td>' . "\n";

            echo '  <td><a href="' . ($unread ? $view_feed_url : $view_feed_all_url) . '">' . $title_html . '</a>' . ($unread ? " ($unread)" : '') . "</td>" . "\n";

            /* controls */
            echo '  <td><a href="' . $unsubscribe_feed_url . '" title="delete" onclick="return sb_unsub_conf(' . $title_json . ');">[x]</a></td>' . "\n";
            break;

        case 'fancy': /* 'feed_url', 'max_date', 'feed_unread', 'feed_title' */
            echo '  <td align="center"><a href="' . $link . '" title="site"><img class="feed-icon" src="' . $image . '" /></a></td>' . "\n";

            echo '  <td style="text-align: right"><span title="' . $lateststr . '" id="' . $id . '-lateststr">' . $lateststrabbr . '</span></td>' . "\n";

            echo '  <td style="text-align: right"><span class="nowrap" id="' . $id . '-items">';
            if ($unread)
                echo '<a class="unread" title="unread items" href="' . $view_feed_url . '">' . $unread . '</a>';
            echo '</span></td>' . "\n";

            echo '  <td><a href="' . $view_feed_all_url . '" title="' . $items . ' total items">' . $title_html . '</a></td>' . "\n";

            /* controls */
            echo '  <td>';

            echo '<ul class="feedmenu"><li>';
            echo '<a href="#" title="feed controls">&Delta;</a>';
            echo '<ul>';
            echo '<li><a href="#" title="update" onclick="return sb_update_feed(' . $id . ');">Update Feed</a></li>';
            echo '<li><a href="#" title="mark all as read" onclick="return sb_read_conf(' . $title_json . ', ' . $id . ');">Mark all items as read</a></li>';
            echo '<li><a href="' . $link . '" title="home page"' . ( $fof_prefs_obj->get('item_target') ? ' target="_blank"' : '') . '>Feed Source Site</a></li>';
            echo '<li><a href="' . $unsubscribe_feed_url . '" title="unsubscribe" onclick="return sb_unsub_conf(' . $title_json . ');">Unsubscribe from feed</a></li>';
            echo '</ul>';
            echo '</li></ul>';

            echo '</td>' . "\n";
            break;

        default: /* 'feed_age', 'max_date', 'feed_unread', 'feed_url', 'feed_title' */
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

            /* controls */
            echo '  <td><span class="nowrap">';
            echo '<a href="' . $update_feed_url . '" title="update">u</a>';
            echo ' <a href="#" title="mark all read" onclick="return sb_read_conf(' . $title_json . ', ' . $id . ');">m</a>';
            echo ' <a href="' . $unsubscribe_feed_url . '" title="delete" onclick="return sb_unsub_conf(' . $title_json . ');">d</a>';
            echo "</span></td>\n";
    }

    echo "</tr>\n";
}

?>
    </tbody>
  </table>
</div>
<!--sidebar end-->
<?php
    /* ??? */
    $order = fof_sidebar_get_key_($_GET, 'order', 'title');
    $direction = fof_sidebar_get_key_($_GET, 'direction', 'asc');
?>
