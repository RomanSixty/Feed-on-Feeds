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

include_once("fof-main.php");

fof_set_content_type();

?>
<img id="throbber" src="image/throbber.gif" align="left" style="position: fixed; left: 0; top: 0; display: none;">

<center id="welcome">Welcome <b><?php echo $fof_user_name ?></b>! <a href="prefs.php">prefs</a> | <a href="logout.php">log out</a> | <a href="http://feedonfeeds.com/">about</a></center>
<br>
<center><a href="add.php"><b>Add Feeds</b></a> / <a href="update.php"><b>Update Feeds</b></a></center>

<ul id="nav">

<?php

$order = $fof_prefs_obj->get('feed_order');
$direction = $fof_prefs_obj->get('feed_direction');
$sharing = $fof_prefs_obj->get('sharing');

if(!isset($_GET['what']))
{
    $what = "unread";
}
else
{
    $what = $_GET['what'];
}

$when = $_GET['when'];

$search = $_GET['search'];

echo "<script>what='$what'; when='$when';</script>";


$feeds = fof_get_feeds(fof_current_user(), $order, $direction);

foreach($feeds as $row)
{
    $n++;
    $unread += $row['feed_unread'];
    $starred += $row['feed_starred'];
    $total += $row['feed_items'];
}

if($unread)
{
    echo "<script>document.title = 'Feed on Feeds ($unread)';</script>";
}
else
{
    echo "<script>document.title = 'Feed on Feeds';</script>";
}

echo "<script>starred = $starred;</script>";

?>

<li <?php if($what == "unread") echo "style='background: #ddd'" ?> ><a href=".?what=unread&how=paged"><font color=red><b>Unread <?php if($unread) echo "($unread)" ?></b></font></a> [<a href=".?what=unread">unpaged</a>]</li>
<li <?php if($what == "star") echo "style='background: #ddd'" ?> ><a href=".?what=star&how=paged"><img src="image/star-on.gif" border="0" height="10" width="10"> Starred <span id="starredcount"><?php if($starred) echo "($starred)" ?></span></a> [<a href=".?what=star">unpaged</a>]</li>
<li <?php if($what == "all" && isset($when)) echo "style='background: #ddd'" ?> ><a href=".?what=all&when=today">&lt; Today</a></li>
<li <?php if($what == "all" && !isset($when)) echo "style='background: #ddd'" ?> ><a href=".?what=all&how=paged">All Items <?php if($total) echo "($total)" ?></a></li>
<li <?php if(isset($search)) echo "style='background: #ddd'" ?> ><a href="javascript:Element.toggle('search'); Field.focus('searchfield');void(0);">Search</a>
<form action="." id="search" <?php if(!isset($search)) echo 'style="display: none"' ?>>
<input type="hidden" name="how" value="paged">
<input id="searchfield" name="search" value="<?php echo $search?>">
<?php
	if($what == "unread" || empty ($what))
		echo "<input type='hidden' name='what' value='all'>";
	else
		echo "<input type='hidden' name='what' value='$what'>";
?>
<?php if(!empty($_GET['when'])) echo "<input type='hidden' name='what' value='${_GET['when']}'>" ?>
</form>
</li>
</ul>

<?php

$tags = fof_get_tags(fof_current_user());

$n = 0;

foreach($tags as $tag)
{
    $tag_id = $tag['tag_id'];
    if($tag_id == 1 || $tag_id == 2) continue;
    $n++;
}

if($n)
{
?>

<div id="tags">

<table cellspacing="0" cellpadding="1" border="0" id="taglist">

<tr class="heading">
<td><span class="unread">#</span></td><td>tag name</td><td>untag</td>
<?php if ($sharing == 'all_tagged') { ?><td>shared page</td><?php } ?>

</tr>

<?php
foreach($tags as $tag)
{
   $tag_name = $tag['tag_name'];
   $tag_id = $tag['tag_id'];
   $count = $tag['count'];
   $unread = $tag['unread'];

   if($tag_id == 1 || $tag_id == 2 || $tag_name == "folded") continue;

   if(++$t % 2)
   {
      print "<tr class=\"odd-row\">";
   }
   else
   {
      print "<tr>";
   }

   print "<td>";
   if($unread) print "<a class='unread' href='.?what=$tag_name+unread&how=paged'>$unread</a>/";
   print "<a href='.?what=$tag_name&how=paged'>$count</a></td>";
   print "<td><b><a href='.?what=$tag_name&how=paged'>$tag_name</a></b></td>";
   print "<td><a href=\"#\" title=\"untag all items\" onclick=\"if(confirm('Untag all [$tag_name] items --are you SURE?')) { delete_tag('$tag_name'); return false; }  else { return false; }\">[x]</a></td>";

   if ($sharing == 'all_tagged')
   {
      print "<td><a href=\"./shared.php?user=$fof_user_id&which=$tag_name&how=paged\">[$tag_name]</a>";
   }

   print "</tr>";
}


?>

</table>

</div>

<br>

<?php } ?>


<div id="feeds">

<div id="feedlist">

<table cellspacing="0" cellpadding="1" border="0">

<tr class="heading">

<?php

$title["feed_age"] = "sort by last update time";
$title["max_date"] = "sort by last new item";
$title["feed_unread"] = "sort by number of unread items";
$title["feed_url"] = "sort by feed URL";
$title["feed_title"] = "sort by feed title";

$name["feed_age"] = "age";
$name["max_date"] = "latest";
$name["feed_unread"] = "#";
$name["feed_url"] = "feed";
$name["feed_title"] = "title";

$simple_sidebar = $fof_prefs_obj->get('simple_sidebar');

if($simple_sidebar)
{
    $columns = array("feed_unread", "feed_title");
}
else
{
    $columns = array("feed_age", "max_date", "feed_unread", "feed_url", "feed_title");
}

foreach ($columns as $col)
{
    if($col == $order)
    {
        $url = "return change_feed_order('$col', '" . ($direction == "asc" ? "desc" : "asc") . "')";
    }
    else
    {
        $url = "return change_feed_order('$col', 'asc')";
    }

    echo "<td";

    if($col == "feed_unread")
        echo " style=\"text-align: right\"";

    echo "><nobr><a href='#' title='$title[$col]' onclick=\"$url\">";

    if($col == "feed_unread")
    {
        echo "<span class=\"unread\">#</span>";
    }
    else
    {
        echo $name[$col];
    }

    if($col == $order)
    {
        echo ($direction == "asc") ? "&darr;" : "&uarr;";
    }

    echo "</a></nobr></td>";
}

?>

<td></td>
</tr>

<?php

foreach($feeds as $row)
{
   $id = $row['feed_id'];
   $url = $row['feed_url'];
   $title = $row['feed_title'];
   $image = $row['feed_image'];
   $link = $row['feed_link'];
   $description = $row['feed_description'];
   $age = $row['feed_age'];
   $unread = $row['feed_unread'];
   $starred = $row['feed_starred'];
   $tagged = $row['feed_tagged'];
   $items = $row['feed_items'];
   $agestr = $row['agestr'];
   $agestrabbr = $row['agestrabbr'];
   $lateststr = $row['lateststr'];
   $lateststrabbr = $row['lateststrabbr'];


   if(++$t % 2)
   {
      print "<tr class=\"odd-row\">";
   }
   else
   {
      print "<tr>";
   }

   $u = ".?feed=$id&amp;how=paged";
   $u2 = ".?feed=$id&amp;what=all&amp;how=paged";

   if($simple_sidebar)
   {
      print "<td align='center'>";
      if($image && $fof_prefs_obj->get('favicons'))
      {
         print "<a href=\"$url\" title=\"feed\"><img src='" . $image . "' width='16' height='16' border='0' /></a>";
      }
      else
      {
         print "<a href=\"$url\" title=\"feed\"><img src='image/feed-icon.png' width='16' height='16' border='0' /></a>";
      }
      print "</td>";
      print '<td>';
      if($unread)
      {
        print "<a href=\"$u\">$title</a> ($unread)";
      }
      else
      {
        print "<a href=\"$u2\">$title</a>";
      }
      print '</td>';

      $stitle = htmlspecialchars(addslashes($title));
      print '<td>';
      print " <a href=\"delete.php?feed=$id\" title=\"delete\" onclick=\"return confirm('Unsubscribe [$stitle] --are you SURE?')\">[x]</a>";
      print '</td>';
   }
   else
   {
      print "<td style=\"text-align: right\"><span title=\"$agestr\" id=\"${id}-agestr\">$agestrabbr</span></td>";

      print "<td style=\"text-align: right\"><span title=\"$lateststr\" id=\"${id}-lateststr\">$lateststrabbr</span></td>";

      print "<td style=\"text-align: right\" class=\"nowrap\" id=\"${id}-items\">";

      if($unread)
      {
         print "<a class=\"unread\" title=\"new items\" href=\"$u\">$unread</a>/";
      }

      print "<a href=\"$u2\" title=\"all items, $starred starred, $tagged tagged\">$items</a>";

      print "</td>";

      print "<td align='center'>";
      if($image && $fof_prefs_obj->get('favicons'))
      {
         print "<a href=\"$url\" title=\"feed\"><img src='" . $image . "' width='16' height='16' border='0' /></a>";
      }
      else
      {
         print "<a href=\"$url\" title=\"feed\"><img src='image/feed-icon.png' width='16' height='16' border='0' /></a>";
      }
      print "</td>";

      print "<td>";
      print "<a href=\"$link\" title=\"home page\"><b>$title</b></a></td>";

      print "<td><nobr>";

      print "<a href=\"update.php?feed=$id\" title=\"update\">u</a>";
      $stitle = htmlspecialchars(addslashes($title));
      print " <a href=\"#\" title=\"mark all read\" onclick=\"if(confirm('Mark all [$stitle] items as read --are you SURE?')) { mark_feed_read($id); return false; }  else { return false; }\">m</a>";
      print " <a href=\"delete.php?feed=$id\" title=\"delete\" onclick=\"return confirm('Unsubscribe [$stitle] --are you SURE?')\">d</a>";
   }

   print "</nobr></td>";

   print "</tr>";
}

?>

</table>

</div>

</div>


<?php

$order = $_GET['order'];
$direction = $_GET['direction'];

if(!isset($order))
{
   $order = "title";
}

if(!isset($direction))
{
   $direction = "asc";
}

?>

