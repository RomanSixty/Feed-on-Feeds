<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * shared.php - display shared items for a user
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

$fof_no_login = true;
include_once("fof-main.php");
include_once("fof-render.php");

$user = $_GET['user'];
if(!isset($user)) die;

$prefs = new FoF_Prefs($user);
$sharing = $prefs->get("sharing");
if($sharing == "no") die;

$name = $prefs->get("sharedname");
$url = $prefs->get("sharedurl");

header("Content-Type: text/html; charset=utf-8");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

   <head>
      <title>Feed on Feeds - Shared Items<?php if($name) echo " from $name" ?></title>
      <link rel="stylesheet" href="fof.css" media="screen" />
      <style>
      .box
      {
          font-family: georgia;
          background: #eee;
          border: 1px solid black;
          width: 30em;
          margin: 10px auto 20px;
          padding: 1em;
          text-align: center;
      }
      </style>

   </head>
      
  <body>

  <h1 class="box"><a href="http://feedonfeeds.com/">Feed on Feeds</a> - Shared Items
  <?php if($name) echo " from" ?>
  <?php if($url) echo "<a href='$url'>" ?>
  <?php if($name) echo "$name" ?>
  <?php if($url) echo "</a>" ?>
 </h1>
<div id="items">

<?php
$which = ($sharing == "all") ? "all" : "shared";
$result = fof_get_items($user, NULL, $which, NULL, 0, 100);

$first = true;

foreach($result as $item)
{
	$item_id = $item['item_id'];
	print '<div class="item shown" id="i' . $item_id . '">';
    
    $feed_link = $item['feed_link'];
	$feed_title = $item['feed_title'];
	$feed_image = $item['feed_image'];
	$feed_description = $item['feed_description'];

	$item_link = $item['item_link'];
	$item_id = $item['item_id'];
	$item_title = $item['item_title'];
	$item_content = $item['item_content'];
	$item_read = $item['item_read'];

	$item_published = gmdate("Y-n-d g:ia", $item['item_published'] + $offset*60*60);
	$item_cached = gmdate("Y-n-d g:ia", $item['item_cached'] + $offset*60*60);
	$item_updated = gmdate("Y-n-d g:ia", $item['item_updated'] + $offset*60*60);

	if(!$item_title) $item_title = "[no title]";
	
?>

<div class="header">

    <h1>
        <a href="<?php echo $item_link ?>">
            <?php echo $item_title ?>
		</a>
	</h1>
	
    
    <span class='dash'> - </span>
    
    <h2>

    <a href="<?php echo $feed_link ?>" title='<?php echo $feed_description ?>'><img src="<?php echo $feed_image ?>" height="16" width="16" border="0" /></a>
    <a href="<?php echo $feed_link ?>" title='<?php echo $feed_description ?>'><?php echo $feed_title ?></a>

    </h2>

	<span class="meta">on <?php echo $item_published ?> GMT</span>

</div>


<div class="body"><?php echo $item_content ?></div>

<div class="clearer"></div>
</div>

<?php
}

if(count($result) == 0)
{
	echo "<p><i>No shared items.</i></p>";
}

?>

</div></body></html>
