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

$sidebar_style = $fof_prefs_obj->get('sidebar_style');
/* honor old pref, if user hasn't saved new-style yet */
if (empty($sidebar_style) && $fof_prefs_obj->get('simple_sidebar'))
    $sidebar_style = 'simple';

?>
<img id="throbber" src="<?php echo $fof_asset['throbber_image']; ?>" align="left" style="display: none;" />
<?php
$welcomecontent = '<span>Welcome, <span class="userid">' . $fof_user_name . '</span>!</span>';
$welcomecontent .= ' | <a href="prefs.php">prefs</a>';
if ( ! defined('FOF_AUTH_EXTERNAL'))
    $welcomecontent .= ' | <a href="logout.php">log out</a>';
$welcomecontent .= ' | <a href="http://feedonfeeds.com/">about</a>';

echo '<div id="welcome" class="banner ontop">' . $welcomecontent . "</div>\n";
echo '<div id="welcome-spacer" class="banner">' . $welcomecontent . "</div>\n";
echo '<br class="clearer">' . "\n";
echo '<div id="feed-actions"><a href="add.php"><b>Add Feeds</b></a> / ';
if ($sidebar_style == 'fancy')
    echo '<a href="#" title="Update all subscribed feeds" onclick="' . htmlentities('throb(); new Ajax.Request("feed-action.php", {method:"post", parameters:{"update_subscribed_sources": true}});', ENT_QUOTES) . '"><b>Update Feeds</b></a>';
else
    echo '<a href="update.php"><b>Update Feeds</b></a>';
echo '</div>' . "\n";
?>

<ul id="nav">
<?php

$order = $fof_prefs_obj->get('feed_order');
$direction = $fof_prefs_obj->get('feed_direction');
$sharing = $fof_prefs_obj->get('sharing');

/* these parameters control highlighting of the active view */
$what = fof_sidebar_get_key_($_GET, 'what', 'unread');
$what_a = explode(' ', $what);
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
    search=<?php echo json_encode($search); ?>;
    feed=<?php echo json_encode($feed); ?>;
    starred=<?php echo json_encode($starred); ?>;
  </script>

<?php
    echo '  <li' . (empty($feed) && $what == 'unread' ? " class='current-view'" : '') . '>';
    echo '<a href="' . fof_url('.', array('what'=>'unread', 'how'=>'paged')) . '"><span style="color:red"><b>Unread' . ($unread ? " ($unread)" : '') . '</b></span></a>';
    echo ' [<a href="' . fof_url('.', array('what'=>'unread', 'how'=>'unpaged')) . '">unpaged</a>]';
    echo "</li>\n";

    echo '  <li' . (empty($feed) && $what == 'star' ? " class='current-view'" : '') . '>';
    echo '<a href="' . fof_url('.', array('what'=>'star', 'how'=>'paged')). '"><img class="star-icon-small" src="' . $fof_asset['star_on_image'] . '" /> Starred <span id="starredcount">' . ($starred ? "($starred)" : '') . '</span></a>';
    echo ' [<a href="' . fof_url('.', array('what'=>'star', 'how'=>'unpaged')). '">unpaged</a>]';
    echo "</li>\n";

    echo '  <li' . (empty($feed) && $what == 'all' && isset($when) ? " class='current-view'" : '') . '>';
    echo '<a href="' . fof_url('.', array('what'=>'all', 'when'=>'today')). '">&lt; Today</a>';
    echo "</li>\n";

    echo '  <li' . (empty($feed) && $what == 'all' && empty($when) ? " class='current-view'" : '') . '>';
    echo '<a href="' . fof_url('.', array('what'=>'all', 'how'=>'paged')) . '">All Items' . ($total ? "($total)" : '') . "</a>";
    echo "</li>\n";

    echo '  <li' . ( ! empty($search) ? ' class="current-view"' : '') . '>';
    echo '<a href="#" onclick="$(\'search\').toggle();$(\'searchfield\').focus();return false;">Search</a>' . "\n";
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
    global $fof_asset;
    global $sharing;
    global $what_a;

    $taglines = array();
    $n = 0;
    foreach (fof_get_tags(fof_current_user()) as $tag) {
        if (in_array($tag['tag_name'], array('unread', 'star', 'folded')))
            continue;

        $tag_name_html = htmlentities($tag['tag_name']);
        $tag_name_json = htmlentities(json_encode($tag['tag_name']), ENT_QUOTES);

        $tag_view_unread_url = fof_url('.', array('what'=>implode(' ', array($tag['tag_name'], 'unread')), 'how'=>'paged'));
        $tag_view_all_url = fof_url('.', array('what'=>$tag['tag_name'], 'how'=>'paged'));

        $tagline = '	<tr class="tag' . (++$n % 2 ? ' odd-row' : '') . (in_array($tag['tag_name'], $what_a) ? ' current-view' : '') . '" id="tagid_' . $tag['tag_id']. '">';

        $tagline .= '<td class="source"><img src="' . ( empty($tag['tag_icon']) ? $fof_asset['tag_icon'] : $tag['tag_icon']) . '" class="feed-icon" /></td>';
        $tagline .= '<td class="latest"></td>';

        $tagline .= '<td class="unread"><span class="unread">';
        if ($tag['unread'])
            $tagline .= '<a class="unread" href="' . $tag_view_unread_url . '" title="' . $tag['unread'] . ' unread items">' . $tag['unread'] . '</a>';
        $tagline .= '</span></td>';

        $tagline .= '<td class="title"><a href="' . ($tag['unread'] ? $tag_view_unread_url : $tag_view_all_url) . '" title="' . ($tag['unread'] ? ($tag['unread'] . ' new of ') : '') . $tag['count'] . ' total items">' . $tag['tag_name'] . '</a></td>';

        $tagline .= '<td class="controls">';
        $tagline .= '<ul class="feedmenu"><li>';
        $tagline .= '<a href="#" title="tag controls">&Delta;</a>';
        $tagline .=   '<ul>';
        $tagline .=     '<li><a href="' . $tag_view_all_url . '" title="' . $tag['count'] . ' total items">View all items</a></li>';
        $tagline .=     '<li><a href="#" title="update all source feeds" onclick="return sb_update_tag_sources(' . $tag_name_json . ');">Update contributing feeds</a></li>';
        $tagline .=     '<li><a href="#" title="mark all read" onclick="return sb_mark_tag_read(' . $tag_name_json . ')">Mark all items as read</a></li>';
        $tagline .=     '<li><a href="#" title="untag all items" onclick="return sb_del_tag_conf(' . $tag_name_json . ');">Delete tag from all items</a></li>';
        $tagline .=   '</ul>';
        $tagline .= '</li></ul>';
        $tagline .= '</td>';

        if ($sharing == 'all_tagged')
            $tagline .= '<td class="sharing"><a href="' . fof_url('./shared.php', array('user'=>fof_current_user(), 'which'=>$tag_name_html, 'how'=>'paged')) . '">' . $tag['tag_name'] . '</a></td>';

        $tagline .= '</tr>';

        $taglines[] = $tagline;
    }

    /* headers */
    if ( ! empty($taglines)) {
        echo "<div id=\"tags\">\n";
        echo "<table cellspacing=\"0\" cellpadding=\"1\" border=\"0\" class=\"taglist\">\n";
        echo "<thead>\n";
        echo '	<tr class="heading">';
        echo '<th class="source"></th>';
        echo '<th class="latest" style="visibility:hidden;">latest&darr;</th>';
        echo '<th class="unread"><span class="unread">#</span></th>';
        echo '<th class="title">tag name</th>';
        echo '<th class="controls"><span></span></th>';
        if ($sharing == 'all_tagged')
            echo '<th class="sharing">shared page</th>';
        echo "</tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";
        echo implode("\n", $taglines);
        echo "\n</tbody>\n</table>\n</div>\n<!--tags end-->\n<br>\n\n";
    }
}

function fof_sidebar_tags_default() {
    global $sharing;
    global $what_a;

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

        $tag_classes = array();
        if (++$n % 2)
            $tag_classes[] = 'odd-row';
        if (in_array($tag_name, $what_a))
            $tag_classes[] = 'current-view';
        $tag_classes = implode(' ', $tag_classes);
        if ( ! empty($tag_classes))
            $tag_classes = ' class="' . $tag_classes . '"';

        $tagline .= '    <tr' . $tag_classes . '>';

        $tagline .= '<td class="unread">';
        if ($unread)
            $tagline .= '<a class="unread" href="' . fof_url('.', array('what' => "$tag_name unread", 'how' => 'paged')) . "\">$unread</a>/";
        $tagline .= '<a href="' . fof_url('.', array('what' => $tag_name, 'how' => 'paged')) . "\">$count</a>";
        $tagline .= '</td>';

        $tagline .= '<td class="title"><b><a href="' . fof_url('.', array('what' => $tag_name, 'how' => 'paged')). '">' . $tag_name_html . '</a></b></td>';

        $tagline .= '<td class="controls"><a href="#" title="untag all items" onclick="return sb_del_tag_conf(' . $tag_name_json . ');">[x]</a></td>';

        if ($sharing == 'all_tagged')
            $tagline .= '<td class="sharing"><a href="' . fof_url('./shared.php', array('user' => $fof_user_id, 'which' => $tag_name, 'how' => 'paged')) . '">[' . $tag_name_html . ']</a>';

        $tagline .= '</tr>';

        $taglines[] = $tagline;
    }

    if ( ! empty($taglines)) { ?>
    <div id="tags">
      <table cellspacing="0" cellpadding="1" border="0" id="taglist">
        <tr class="heading"><td><span class="unread">#</span></td><td class="title">tag name</td><td class="controls">untag</td><?php if ($sharing == 'all_tagged') echo '<td class="sharing">shared page</td>'; ?></tr>
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

$column_class = array('feed_age' => 'update',
                      'max_date' => 'latest',
                      'feed_unread' => 'unread',
                      'feed_url' => 'source',
                      'feed_title' => 'title'
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

    echo '		<th';
    if ( ! empty($column_class[$col])) {
        echo ' class="' . $column_class[$col] . '"';
    }
    echo '>';

    echo '<span class="nowrap"><a href="#" title="' . $title[$col] . '" onclick="' . $onclick . '">';
    echo $name[$col];

    if ($col == $order)
        echo $direction == 'asc' ? '&darr;' : '&uarr;';

    echo "</a></span></th>\n";
}
?>
        <th class="controls"></th>
      </tr>
    </thead>
    <tbody>
<?php

$what_id = array_map('fof_db_get_tag_by_name', $what_a);
$t = 0;
foreach ($feeds as $row) {
    $view_contrib = array_intersect($what_id, $row['subscription_prefs']['tags']);
    $view_feed = (! empty($_GET['feed']) && $_GET['feed'] == $row['feed_id']);
    echo '<tr id="f' . $row['feed_id'] . '" class="feed' . (++$t % 2 ? ' odd-row' : '') . ((count($view_contrib) || $view_feed) ? ' current-view' : '') . "\">\n";
    echo fof_render_feed_row($row);
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
