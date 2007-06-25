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

?>
<img id="throbber" src="image/throbber.gif" align="left" style="position: fixed; left: 0; top: 0; display: none">

<center>Welcome <b><?php echo $fof_user_name ?></b>! <a href="prefs.php">prefs</a> | <a href="logout.php">log out</a></center>

<ul>
<li><a href="javascript:flag_all();mark_read()"><b>Mark all read</b></a> -
<a href="add.php">Add Feeds</a> - 
<a href="update.php">Update Feeds</a></li><br />
<li><a href="javascript:flag_all()">Flag all</a> |
<a href="javascript:unflag_all()">Unflag all</a> |
<a href="javascript:toggle_all()">Toggle all</a> |
<a href="javascript:show_all()">Show all</a> |
<a href="javascript:hide_all()">Hide all</a>
</li><br />
<li>Flagged items: <a href="javascript:mark_read()">Mark as read</a> | <a href="javascript:mark_unread()">Mark as unread</a></li>
</ul>
<hr>
<ul id="nav">

<?php

$order = $_GET['order'];
$direction = $_GET['direction'];

if(!isset($order))
{
   $order = "feed_title";
}

if(!isset($direction))
{
   $direction = "asc";
}

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

?>

<li <?php if($what == "unread") echo "style='background: #ddd'" ?> ><a href=".?what=unread"><font color=red><b>Unread <?php if($unread) echo "($unread)" ?></b></font></a></li>
<li <?php if($what == "star") echo "style='background: #ddd'" ?> ><a href=".?what=star"><img src="image/star-on.gif" border="0" height="10" width="10"> Starred <?php if($starred) echo "($starred)" ?></a></li>
<li <?php if($what == "all" && isset($when)) echo "style='background: #ddd'" ?> ><a href=".?what=all&when=today">&lt; Today</a></li>
<li <?php if($what == "all" && !isset($when)) echo "style='background: #ddd'" ?> ><a href=".?what=all&how=paged">All Items <?php if($total) echo "($total)" ?></a></li>
<li <?php if(isset($search)) echo "style='background: #ddd'" ?> ><a href="javascript:Element.toggle('search'); Field.focus('searchfield');void(0);">Search</a>
<form action="." id="search" <?php if(!isset($search)) echo 'style="display: none"' ?>>
<input id="searchfield" name="search" value="<?php echo $search?>">
<?php
	if($what == "unread")
		echo "<input type='hidden' name='what' value='all'>";
	else
		echo "<input type='hidden' name='what' value='$what'>";
?>
<?php if(isset($_GET['when'])) echo "<input type='hidden' name='what' value='${_GET['when']}'>" ?>
</form>
</li>
</ul>
<hr>
<form></form>
<br>

<div id="tags">

<table cellspacing="0" cellpadding="1" border="0" id="taglist">

<tr class="heading">
<td><span class="unread">#</span></td><td>tag name</td><td>untag all items</td>
</tr>

<?php

$tags = fof_get_tags(fof_current_user());

$n = 0;

foreach($tags as $tag)
{
    $n++;
}

foreach($tags as $tag)
{   
   $tag_name = $tag['tag_name'];
   $tag_id = $tag['tag_id'];
   $count = $tag['count'];
   $unread = $tag['unread'];
 
   if($tag_id == 1 || $tag_id == 2) continue;

   if(++$t % 2)
   {
      print "<tr class=\"odd-row\">";
   }
   else
   {
      print "<tr>";
   }

   print "<td>";
   if($unread) print "<a class='unread' href='.?what=$tag_name+unread'>$unread</a>/";
   print "<a href='.?what=$tag_name'>$count</a></td>";
   print "<td><b><a href='.?what=$tag_name'>$tag_name</a></b></td>";
   print "<td><a href=\"#\" title=\"untag all items\" onclick=\"if(confirm('Untag all [$tag_name] items --are you SURE?')) { delete_tag('$tag_name'); return false; }  else { return false; }\">[x]</a></td>";

   print "</tr>";
}


?>

</table>

</div>
<br><hr><br>
<div id="feeds">

<div id="feedlist">

<table cellspacing="0" cellpadding="1" border="0">

<tr class="heading">

<?php

$title["age"] = "sort by last update time";
$title["unread"] = "sort by number of unread items";
$title["title"] = "sort by feed title";

foreach (array("age", "latest", "unread", "feed", "title") as $col)
{
   echo "<td><nobr>";


   if($col == "unread")
   {
      echo "<span class=\"unread\">#</span>";
   }
   else
   {
      echo $col;
   }

   if($col == $order)
   {
      echo ($direction == "asc") ? "&darr;" : "&uarr;";
   }

   echo "</nobr></td>";
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
   $link = $row['feed_link'];
   $description = $row['feed_description'];
   $age = $row['feed_age'];
   $unread = $row['feed_unread'];
   $starred = $row['feed_starred'];
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

   $u = ".?feed=$id";
   $u2 = ".?feed=$id&amp;what=all&amp;how=paged";

   print "<td><span title=\"$agestr\" id=\"${id}-agestr\">$agestrabbr</span></td>";

   print "<td><span title=\"$lateststr\" id=\"${id}-lateststr\">$lateststrabbr</span></td>";

   print "<td class=\"nowrap\" id=\"${id}-items\">";

   if($unread)
   {
      print "<a class=\"unread\" title=\"new items\" href=\"$u\">$unread</a>/";
   }

   print "<a href=\"$u2\" title=\"all items\">$items</a>";

   print "</td>";

	print "<td align='center'>";
	if($row['feed_image'] && $fof_prefs_obj->get('favicons'))
	{
	   print "<a href=\"$url\" title=\"feed\"><img src='" . $row['feed_image'] . "' width='16' height='16' border='0' /></a>";
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





