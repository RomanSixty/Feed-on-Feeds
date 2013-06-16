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

include_once("fof-main.php");
include_once("fof-render.php");

$result = fof_get_items($fof_user_id, NULL, "unread", NULL, 0, 10);

$prefs = fof_prefs();
$offset = isset($prefs['tzoffset']) ? $prefs['tzoffset'] : 0;

fof_set_content_type();
?>
<!DOCTYPE html>
<html>

   <head>
      <title>Feed on Feeds</title>
      <meta name = "viewport" content = "width=700">

      <link rel="stylesheet" href="fof.css" media="screen" />
      <?php if (is_readable('./fof-custom.css')) { ?><link rel="stylesheet" href="fof-custom.css" media="screen" /><?php } ?>
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
      <script src="prototype/prototype.js" type="text/javascript"></script>
      <script src="fof.js" type="text/javascript"></script>
<script>
function toggle_favorite(id)
{
    var image = $('fav' + id);

    var url = "add-tag.php";
    var params = { "tag": "star", "item": id };
    image.src = '<?php echo $fof_asset['star_pend_image']; ?>';

    if(image.star)
    {
        params["remove"] = "true";
        var complete = function()
        {
            image.src='<?php echo $fof_asset['star_off_image']; ?>';
            image.star = false;
        };
    }
    else
    {
        var complete = function()
        {
            image.src='<?php echo $fof_asset['star_on_image']; ?>';
            image.star = true;
        };
    }

    var options = { method: 'post', parameters: params, onComplete: complete };
    new Ajax.Request(url, options);

    return false;
}

function newWindowIfy()
{
    a=document.getElementsByTagName('a');

    for(var i=0,j=a.length;i<j;i++){a[i].setAttribute('target','_blank')};
}
</script>
   </head>

   <body onload="newWindowIfy()">
       <form id="itemform" name="items" action="view-action.php" method="post" onSubmit="return false;">
           <input type="hidden" name="action" value="read" />
           <input type="hidden" name="return" />

<div id="items">

<?php

$first = true;

foreach($result as $item)
{
    $item_id = $item['item_id'];
    echo '<div class="item shown" id="i' . $item_id . '">';

    $feed_link = $item['feed_link'];
    $feed_title = $item['feed_title'];
    $feed_image = $item['feed_image'];
    $feed_description = $item['feed_description'];

    $item_link = $item['item_link'];
    $item_id = $item['item_id'];
    $item_title = $item['item_title'];
    $item_content = $item['item_content'];

    $item_published = gmdate("Y-n-d g:ia", $item['item_published'] + $offset*60*60);
    $item_cached = gmdate("Y-n-d g:ia", $item['item_cached'] + $offset*60*60);
    $item_updated = gmdate("Y-n-d g:ia", $item['item_updated'] + $offset*60*60);

    if(!$item_title) $item_title = "[no title]";
    $tags = $item['tags'];
    $star = in_array("star", $tags) ? true : false;
    $star_image = $star ? $fof_asset['star_on_image'] : $fof_asset['star_off_image'];

?>

<div class="header">

    <h1>
        <img
            height="16"
            width="16"
            src="<?php echo $star_image ?>"
            id="fav<?php echo $item_id ?>"
            onclick="return toggle_favorite('<?php echo $item_id ?>')"
        />
        <script>
        document.getElementById('fav<?php echo $item_id ?>').star = <?php if($star) echo 'true'; else echo 'false'; ?>;
        </script>
        <a href="<?php echo $item_link ?>">
            <?php echo $item_title ?>
        </a>
    </h1>


    <span class='dash'> - </span>

    <h2>

        <a href="<?php echo $feed_link ?>" title='<?php echo htmlentities($feed_description); ?>'><img src="<?php echo $feed_image ?>" height="16" width="16" border="0" /></a>
        <a href="<?php echo $feed_link ?>" title='<?php echo htmlentities($feed_description); ?>'><?php echo $feed_title ?></a>

    </h2>

    <span class="meta">on <?php echo $item_published ?> GMT</span>

</div>


<div class="body"><?php echo $item_content ?></div>

<div class="clearer"></div>
</div>
<input
    type="hidden"
    name="c<?php echo $item_id ?>"
    id="c<?php echo $item_id ?>"
    value="checked"
/>

<?php
}

if(count($result) == 0)
{
    echo "<p><i>No new items.</i></p>";
}
else
{
    echo "<center><a href='#' onclick='mark_read(); return false;'><b>Mark All Read</b></a></center>";
}

?>

</div>
</form></body></html>

