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

set_time_limit(60*10);

$url = $_POST['rss_url'];
if(!$url) $url = $_GET['rss_url'];
$opml = $_POST['opml_url'];
$file = $_POST['opml_file'];
$feeds = array();
?>

<div style="background: #eee; border: 1px solid black; padding: 1.5em; margin: 1.5em;">If your browser is cool, you can <a href='javascript:window.navigator.registerContentHandler("application/vnd.mozilla.maybe.feed", "http://<?php echo $_SERVER["HTTP_HOST"] . dirname($_SERVER["SCRIPT_NAME"]) ?>/add.php?rss_url=%s", "Feed on Feeds")'>register Feed on Feeds as a Feed Reader</a>.  If it is not cool, you can still use the <a href="javascript:void(location.href='http://<?php echo $_SERVER["HTTP_HOST"] . $_SERVER["SCRIPT_NAME"] ?>?rss_url='+escape(location))">FoF subscribe</a> bookmarklet to subscribe to any page with a feed.  Just add it as a bookmark and then click on it when you are at a page you'd like to subscribe to!</div>

<form method="post" action="add.php" enctype="multipart/form-data">
<input type="hidden" name="MAX_FILE_SIZE" value="100000">

RSS or weblog URL: <input type="text" name="rss_url" size="40" value="<?php echo $url ?>"><input type="Submit" value="Add a feed"><br><br>
</form>

<form method="post" action="add.php" enctype="multipart/form-data">
OPML URL: <input type="hidden" name="MAX_FILE_SIZE" value="100000">

<input type="text" name="opml_url" size="40" value="<?php echo $opml ?>"><input type="Submit" value="Add feeds from OPML file on the Internet"><br><br>
</form>

<form method="post" action="add.php" enctype="multipart/form-data">
<input type="hidden" name="MAX_FILE_SIZE" value="100000">
OPML filename: <input type="file" name="opml_file" size="40" value="<?php echo $file ?>"><input type="Submit" value="Upload an OPML file">

</form>

<?php
if($url) fof_subscribe(fof_current_user(), $url);

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

if($_FILES['opml_file']['tmp_name'])
{
	if(!$content_array = file($_FILES['opml_file']['tmp_name']))
	{
		echo "Cannot open uploaded file<br>";
		return false;
	}

	$content = implode("", $content_array);

	$feeds = fof_opml_to_array($content);
}

if(isset($feeds))
{
	foreach ($feeds as $feed)
	{
		fof_subscribe(fof_current_user(), $feed);
		echo "<hr size=1>";
		flush();
	}
}

include("footer.php");
?>
