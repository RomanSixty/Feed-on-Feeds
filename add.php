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

$url = $_POST['rss_url'];
if(!$url) $url = $_GET['rss_url'];
$opml = $_POST['opml_url'];
$file = $_POST['opml_file'];
$feeds = array();
?>

<BR><br>
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
	if(!$content_array = file($opml))
	{
		echo "Cannot open $opml<br>";
		return false;
	}

	$content = implode("", $content_array);

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
