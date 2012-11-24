<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * add.php - displays form to add a feed
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include("header.php");

$url = empty($_POST['rss_url']) ? '' : $_POST['rss_url'];
if(!$url) $url = empty( $_GET['rss_url'] ) ? '' : $_GET['rss_url'];
$opml = empty($_POST['opml_url']) ? '': $_POST['opml_url'];
$file = empty($_POST['opml_file']) ? '' : $_POST['opml_file'];
$unread = empty($_POST['unread']) ? '' : $_POST['unread'];

$feeds = array();

if($url) $feeds[] = $url;

if($opml)
{
	$sfile = new SimplePie_File($opml);

	if(!$sfile->success)
	{
		echo "Cannot open $opml<br>";
		return false;
	}

	$content = $sfile->body;

	$feeds = fof_opml_to_array($content);
}

if(!empty($_FILES['opml_file']['tmp_name']))
{
	if(!$content_array = file($_FILES['opml_file']['tmp_name']))
	{
		echo "Cannot open uploaded file<br>";
	}
    else
    {
        $content = implode("", $content_array);
        $feeds = fof_opml_to_array($content);
    }
}

$add_feed_url = "http";
if(!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on")
{
  $add_feed_url = "https";
}
$add_feed_url .= "://" . $_SERVER["HTTP_HOST"] . $_SERVER["SCRIPT_NAME"];
?>

<div style="background: #eee; border: 1px solid black; padding: 1.5em; margin: 1.5em;">If your browser is cool, you can <a href='javascript:window.navigator.registerContentHandler("application/vnd.mozilla.maybe.feed", "<?php echo $add_feed_url ?>?rss_url=%s", "Feed on Feeds")'>register Feed on Feeds as a Feed Reader</a>.  If it is not cool, you can still use the <a href="javascript:void(location.href='<?php echo $add_feed_url ?>?rss_url='+escape(location))">FoF subscribe</a> bookmarklet to subscribe to any page with a feed.  Just add it as a bookmark and then click on it when you are at a page you'd like to subscribe to!</div>

<form method="post" action="opml.php">

<input type="submit" value="Export subscriptions as OPML">

</form>
<br>

<form method="post" name="addform" action="add.php" enctype="multipart/form-data">

When adding feeds, mark <select name="unread"><option value=today <?php if($unread == "today") echo "selected" ?> >today's</option><option value=all <?php if($unread == "all") echo "selected" ?> >all</option><option value=no <?php if($unread == "no") echo "selected" ?> >no</option></select> items as unread<br><br>

RSS or weblog URL: <input type="text" name="rss_url" size="40" value="<?php echo $url ?>"><input type="Submit" value="Add a feed"><br><br>

OPML URL: <input type="hidden" name="MAX_FILE_SIZE" value="100000">

<input type="text" name="opml_url" size="40" value="<?php echo $opml ?>"><input type="Submit" value="Add feeds from OPML file on the Internet"><br><br>

<input type="hidden" name="MAX_FILE_SIZE" value="100000">
OPML filename: <input type="file" name="opml_file" size="40" value="<?php echo $file ?>"><input type="Submit" value="Upload an OPML file">

</form>

<?php
if(count($feeds))
{
print("<script>\nwindow.onload = ajaxadd;\nfeedslist = [");

foreach($feeds as $feed)
{
    $feedjson[] = "{'url': '" . addslashes($feed) . "'}";
}

print(join($feedjson, ", "));
print("];\n</script>");
}
print("<br>");

include("footer.php");
?>
