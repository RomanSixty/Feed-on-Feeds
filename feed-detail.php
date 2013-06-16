<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * feed-detail.php - display and control all information about a feed
 *
 *
 * Copyright (C) 2013 Justin Wind <justin.wind@gmail.com>
 *
 * Distributed under the GPL - see LICENSE
 *
 */

/*
	FIXME:
		add form fallbacks for ajax actions
		add keyup handlers for ajax field submissions
*/

require_once('fof-main.php');

include('header.php');

function pretty_time($t) {
	return strftime('%c', $t);
}

$p =& FoF_Prefs::instance();
$fof_admin_prefs = $p->admin_prefs;

if (empty($_GET['feed'])) {
	echo '<div class="error"><h1>No Feed</h1>Nothing to detail.</div>' . "\n";
	die();
}

$feed_id = $_GET['feed'];

/* handle updates */

$messages = array();

if (isset($_POST['alt_title'])) {
	if (fof_db_subscription_title_set(fof_current_user(), $feed_id, $_POST['alt_title'])) {
		if (empty($_POST['alt_title'])) {
			$messages[] = 'Cleared custom title.';
		} else {
			$messages[] = 'Set custom title to "' . $_POST['alt_title'] . '"';
		}
	} else {
		$messages[] = '<span class="error">Failed to set custom title!</span>';
	}
}

if (isset($_POST['alt_image'])) {
	if (fof_db_subscription_image_set(fof_current_user(), $feed_id, $_POST['alt_image'])) {
		if (empty($_POST['alt_image'])) {
			$messages[] = 'Cleared custom image.';
		} else {
			$messages[] = 'Set custom image to "' . $_POST['alt_image'] . '"';
		}
	} else {
		$messages[] = '<span class="error">Failed to set custom image!</span>';
	}
}

if ( ! empty($messages)) {
	echo '<div class="notice">';
	foreach ($messages as $msg) {
		echo '<div>' . $msg . '</div>' . "\n";
	}
	echo '</div>' . "\n";
	/* sidebar will be wrong until refreshed */
	echo '<script>document.observe("dom:loaded", function() { refreshlist(); });</script>';
}

/* let admin see any feed */
if (fof_is_admin()) {
	echo '<h1>Admin Feed Details</h1>' . "\n";

	echo '<div class="subscribers">' . "\n";
	echo '<h2>Subscribers</h2>' . "\n";
	echo '<ul>' . "\n";
	$sub_statement = fof_db_get_subscribed_users($feed_id);
	$users = fof_db_get_users();
	while (($subscriber = fof_db_get_row($sub_statement, 'user_id')) !== false) {
		echo '<li>' . $users[$subscriber] . ' (' . $subscriber . ')</li>' . "\n";
	}
	echo '</ul>' . "\n";
	echo '</div>' . "\n";
} elseif (fof_db_is_subscribed_id(fof_current_user(), $feed_id)) {
	echo '<h1>Feed Details</h1>' . "\n";
} else {
	echo '<div class="error"><h1>Not Subscribed</h1>You don\'t know anything about that feed.</div>' . "\n";
	die();
}

$feed_row = fof_get_feed(fof_current_user(), $feed_id);

?>
<script>
function subscription_tags_refresh(feed) {
	var params = { subscription_tag_list: feed };
	new Ajax.Updater($("feedtags").down("ul"), "feed-action.php", { method: "post", parameters: params });
}
function subscription_tag_modify(feed, tag, action) {
	var params = { feed: feed, subscription_tag: tag };
	if (action == "delete") {
		params[action] = true;
	}
	var complete = function () {
		subscription_tags_refresh(feed);
		refreshlist(); // update sidebar
	};
	new Ajax.Request("feed-action.php", {method: "post", parameters: params, onComplete: complete});
}
document.observe("dom:loaded", function() {
	subscription_tags_refresh(<?php echo json_encode($feed_id); ?>);
});
</script>

<form method="post" action="">

<div id="general">
	<h2>General</h2>
	<ul>
		<li>
			Source URL: '<?php echo $feed_row['feed_url']; ?>'
		</li>
		<li>
			Site URL: '<?php echo $feed_row['feed_link']; ?>'
		</li
		<li>
			Description: '<?php echo $feed_row['feed_description']; ?>'
		</li>
		<li>
			Title: '<?php echo $feed_row['feed_title']; ?>'
		</li>
		<li>
			Custom Title: 
			<input type="text" name="alt_title" value="<?php echo htmlentities($feed_row['alt_title'], ENT_QUOTES); ?>" size="50" />
		</li>
		<li>
			Image:
			<img class="feed-icon" src="<?php echo htmlentities($feed_row['feed_image'], ENT_QUOTES); ?>" />
		</li>
		<li>
			Custom Image: 
			<input type="text" name="alt_image" value="<?php echo htmlentities($feed_row['alt_image'], ENT_QUOTES); ?>" size="50" />
			&nbsp;
			<img class="feed-icon" src="<?php echo htmlentities($feed_row['alt_image'], ENT_QUOTES); ?>" />
		</li>
	</ul>
	<span>
		<input type="submit" value="Update" />
	</span>
</div>

<div id="counts">
	<h2>Item Counts</h2>
	<ul id="counts">
		<li>Items: <?php echo $feed_row['feed_items']; ?></li>
		<li>Read: <?php echo $feed_row['feed_read']; ?></li>
		<li>Unread: <?php echo $feed_row['feed_unread']; ?></li>
		<li>Starred: <?php echo $feed_row['feed_starred']; ?></li>
		<li>Tagged: <?php echo $feed_row['feed_tagged']; ?></li>
	</ul>
</div>

<div id="times">
	<h2>Dates</h2>
	<ul>
		<li>Last updated: <?php echo pretty_time($feed_row['feed_age']) . '&nbsp;(' . $feed_row['agestr'] . ')'; ?></li>
		<li>Most recent item: <?php echo pretty_time($feed_row['max_date']) . '&nbsp;(' . $feed_row['lateststr'] . ')'; ?></li>
		<li>Image cached:  <?php echo pretty_time($feed_row['feed_image_cache_date']); ?>
</li>
		<li>Last update attempt: <?php echo pretty_time($feed_row['feed_cache_attempt_date']); ?></li>
		<li>Next attempt: 
<?php
$now = time();
if ($now >= $feed_row['feed_cache_next_attempt']) {
	echo "due";
	if ($now - $feed_row['feed_cache_date'] <= $fof_admin_prefs['autotimeout'] * 60) {
		echo ", but is delayed by the system rate-limit until " . pretty_time($feed_row['feed_cache_date'] + ($fof_admin_prefs['autotimeout'] * 60));
	}
} else {
	echo pretty_time($feed_row['feed_cache_next_attempt']);
}
?>
</li>
	</ul>
</div>

<div id="feedtags">
	<h2>Tags Automatically Applied to Items from this Feed</h2>
	<ul>
		<li><img class="feed-icon" src="<?php echo $fof_asset['busy_icon']; ?>" /> Tags loading...</li>
	</ul>
	<span>
		<input type="text" size="10" id="new_tag" /><input type="button" value="Tag Feed" onclick="subscription_tag_modify(<?php echo htmlentities(implode(',', array(json_encode($feed_id), '$("new_tag").value', json_encode('add'))), ENT_QUOTES); ?>); $(&quot;new_tag&quot;).value = &quot;&quot;; return false;"/>
	</span>
</div>

</form>

<?php
include('footer.php');
?>
