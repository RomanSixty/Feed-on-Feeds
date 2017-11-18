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

include 'header.php';

$url = isset($_POST['rss_url']) ? $_POST['rss_url'] : NULL;
if (!isset($url)) {
	$url = isset($_GET['rss_url']) ? $_GET['rss_url'] : NULL;
}

$youtube = isset($_POST['youtube_channel']) ? $_POST['youtube_channel'] : NULL;
$opml = isset($_POST['opml_url']) ? $_POST['opml_url'] : NULL;
$file = isset($_POST['opml_file']) ? $_POST['opml_file'] : NULL;
$unread = isset($_POST['unread']) ? $_POST['unread'] : NULL;

$feeds = array();

if ($youtube) {
	// okay, we may have different kinds of URLs here, depending if the channel owner got a vanity user name
	//
	// https://www.youtube.com/user/<vanity_title>...
	// https://www.youtube.com/channel/<channel_id>...
	//
	// what we need however is the channel's id, so let's try to find that out

	$channel_id = null;
	$matches = array();

	if (preg_match('~youtube\.com/channel/([^/]+)~', $youtube, $matches)) {
		$channel_id = $matches[1];
	} elseif (preg_match('~youtube\.com/user/([^/]+)~', $youtube, $matches)) {
		$file = file_get_contents($youtube);

		if (preg_match('~data-style-type="branded"[^>]+data-channel-external-id="([^"]+)"~m', $file, $matches)) {
			$channel_id = $matches[1];
		}

	}

	if (!empty($channel_id)) {
		$feeds[] = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $channel_id;
	}

}

if ($url) {
	$feeds[] = $url;
}

if ($opml) {
	$sfile = new SimplePie_File($opml);
	if (!$sfile->success) {
		echo "Cannot open " . htmlentities($opml) . "<br>";
		return false;
	}

	$content = $sfile->body;
	$feeds = fof_opml_to_array($content);
}

if (!empty($_FILES['opml_file']) && !empty($_FILES['opml_file']['tmp_name'])) {
	if (($content = file_get_contents($_FILES['opml_file']['tmp_name'])) === false) {
		echo "Cannot open uploaded file '" . htmlentities($_FILES['opml_file']['name']) . "'<br>";
	} else {
		$feeds = fof_opml_to_array($content);
	}
}

$add_feed_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
$add_feed_url .= "://" . $_SERVER["HTTP_HOST"] . $_SERVER["SCRIPT_NAME"];
?>

<div style="background: #eee; border: 1px solid black; padding: 1.5em; margin: 1.5em;">
<span id="register_feed_reader">
  If your browser is cool, you can <a href="#" onclick="window.navigator.registerContentHandler('application/vnd.mozilla.maybe.feed', '<?php echo $add_feed_url ?>?rss_url=%s', 'Feed on Feeds');return false;">register Feed on Feeds as a Feed Reader</a>.
</span>
<span id="add_bookmarklet">
  You can also use the <a href="javascript:void(location.href='<?php echo $add_feed_url; ?>?rss_url='+escape(location))">FoF subscribe</a> bookmarklet to subscribe to any page with a feed.  Just add it as a bookmark and then click on it when you are at a page you'd like to subscribe to!
</span>
</div>
<?php
if (strpos($_SERVER['HTTP_USER_AGENT'], 'Firefox') !== FALSE) {
	?>
<script>
document.observe("dom:loaded", function() {
    if (window.navigator.isContentHandlerRegistered('application/vnd.mozilla.maybe.feed', '<?php echo $add_feed_url; ?>?rss_url=%s')) {
        $('register_feed_reader').update('Feed on Feeds is already registered as a feed reader in this browser!');
    }
});
</script>
<?php
}
?>

<form method="post" action="opml.php">

<input type="submit" value="Export subscriptions as OPML">

</form>
<br>

<form method="post" name="addform" action="add.php" enctype="multipart/form-data">

When adding feeds, mark <select name="unread">
    <option value=all<?php if ($unread == "all") {
	echo " selected";
}
?>>all</option>
    <option value=today<?php if ($unread == "today") {
	echo " selected";
}
?>>today's</option>
    <option value=no<?php if ($unread == "no") {
	echo " selected";
}
?>>no</option>
</select> items as unread<br><br>

RSS or weblog URL: <input type="text" name="rss_url" size="40" value="<?php echo htmlentities($url) ?>"><input type="Submit" value="Add a feed"><br><br>

YouTube channel page: <input type="text" name="youtube_channel" size="40" value="<?php echo htmlentities($youtube) ?>"><input type="Submit" value="Subscribe to channel"><br><br>

OPML URL: <input type="hidden" name="MAX_FILE_SIZE" value="100000">

<input type="text" name="opml_url" size="40" value="<?php echo htmlentities($opml) ?>"><input type="Submit" value="Add feeds from OPML file on the Internet"><br><br>

<input type="hidden" name="MAX_FILE_SIZE" value="100000">
OPML filename: <input type="file" name="opml_file" size="40" value="<?php echo htmlentities($file) ?>"><input type="Submit" value="Upload an OPML file">

</form>
<hr>

<?php
if (count($feeds)) {
	$idx = 0;
	$feedjson = array();
	foreach ($feeds as $feed) {
		$feedjson[] = json_encode(array('url' => $feed, 'idx' => $idx));
		echo '<div id="feed_index_' . $idx . '">'
			. $feed . ' is waiting to add...'
			. "</div>\n";
		$idx++;
	}

	echo "<script>\nwindow.onload = ajaxadd;\nfeedslist = [" . implode(', ', $feedjson) . "];\n</script>\n";
}
echo '<br>';

include 'footer.php';
?>
