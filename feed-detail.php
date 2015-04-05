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
 */

require_once 'fof-main.php';

include 'header.php';

function pretty_time($t) {
	return strftime('%c', $t);
}

$p = &FoF_Prefs::instance();
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

if (isset($_POST['new_tag'])) {
	if (fof_tag_feed(fof_current_user(), $feed_id, $_POST['new_tag'])) {
		$messages[] = 'Feed will now be tagged with "' . $_POST['new_tag'] . '"';
	} else {
		$messages[] = '<span class="error">Failed to set tag on feed!</span>';
	}
}

if (!empty($messages)) {
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
		echo '<li>' . $users[$subscriber]['user_name'] . ' (' . $subscriber . ')</li>' . "\n";
	}
	echo '</ul>' . "\n";
	echo '</div>' . "\n";
} elseif (fof_db_is_subscribed_id(fof_current_user(), $feed_id)) {
	echo '<h1>Feed Details</h1>' . "\n";
} else {
	echo '<div class="error"><h1>Not Subscribed</h1>You don\'t know anything about that feed.</div>' . "\n";
	die();
}

if (fof_is_admin() && !fof_db_is_subscribed_id(fof_current_user(), $feed_id)) {
	/* fof_get_feed expects a subscription, so shirk that and just populate overall stats */
	$feed_row = fof_db_get_feed_by_id($feed_id);
	fof_db_subscription_feed_fix($feed_row);
	list($feed_row['feed_items'], $feed_row['feed_tagged'], $counts) = fof_db_feed_counts(fof_current_user(), $feed_id);
	$feed_row = array_merge(array('tags' => array(), 'feed_unread' => 0, 'feed_read' => 0, 'feed_starred' => 0, 'feed_age' => $feed_row['feed_cache_date']), $feed_row);
	list($feed_row['agestr'], $feed_row['agestrabbr']) = fof_nice_time_stamp($feed_row['feed_cache_date']);
	$max_stmt = fof_db_get_latest_item_age(fof_current_user(), $feed_id);
	$feed_row['max_date'] = fof_db_get_row($max_stmt, 'max_date', TRUE);
	list($feed_row['lateststr'], $feed_row['lateststrabbr']) = fof_nice_time_stamp($feed_row['max_date']);

	/* not subscribed, so no subscription preferences to change.. */
	$admin_view = true;
} else {
	$feed_row = fof_get_feed(fof_current_user(), $feed_id);
	$admin_view = false;
}

/* only include the update scripts if subscribed */
if (!$admin_view || fof_db_is_subscribed_id(fof_current_user(), $feed_id)) {
	$feed_id_js = json_encode($feed_id);
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
	subscription_tags_refresh(<?php echo $feed_id_js;?>);
});
document.observe("dom:loaded", function() {
	$('new_tag').observe("keypress", function(event) {
		if (event.keyCode == Event.KEY_RETURN) {
			subscription_tag_modify(<?php echo $feed_id_js;?>, this.value, "add");
			this.clear();
			return false;
		}
	});
});
document.observe("dom:loaded", function() {
	$('new_tag').next('input[type="button"]').observe("click", function(event) {
		subscription_tag_modify(<?php echo $feed_id_js;?>, $('new_tag').value, "add");
		$('new_tag').clear();
		return false;
	});
});
</script>
<?php
}
?>

<form method="post" action="">

<div id="general">
	<h2>General</h2>
	<ul>
		<li>
			Source URL: '<?php echo $feed_row['feed_url'];?>'
		</li>
		<li>
			Site URL: '<?php echo $feed_row['feed_link'];?>'
		</li>
		<li>
			Description: '<?php echo $feed_row['feed_description'];?>'
		</li>
		<li>
			Title: '<?php echo $feed_row['feed_title'];?>'
		</li>
<?php
if (!$admin_view) {
	?>
		<li>
			Custom Title:
			<input type="text" name="alt_title" value="<?php echo htmlentities($feed_row['alt_title'], ENT_QUOTES);?>" size="50" />
		</li>
<?php
}
?>
		<li>
			Image:
			<img class="feed-icon" src="<?php echo htmlentities($feed_row['feed_image'], ENT_QUOTES);?>" />
		</li>
<?php
if (!$admin_view) {
	?>
		<li>
			Custom Image:
			<input type="text" name="alt_image" value="<?php echo htmlentities($feed_row['alt_image'], ENT_QUOTES);?>" size="50" />
			&nbsp;
			<img class="feed-icon" src="<?php echo htmlentities($feed_row['alt_image'], ENT_QUOTES);?>" />
		</li>
<?php
}
?>
	</ul>
	<span>
<?php
if (!$admin_view) {
	?>
		<input type="submit" value="Update" />
<?php
}
?>
	</span>
</div>

</form>

<div id="counts">
	<h2>Item Counts</h2>
	<ul>
		<li>Items: <?php echo $feed_row['feed_items'];?></li>
		<li>Read: <?php echo $feed_row['feed_read'];?></li>
		<li>Unread: <?php echo $feed_row['feed_unread'];?></li>
		<li>Starred: <?php echo $feed_row['feed_starred'];?></li>
		<li>Tagged: <?php echo $feed_row['feed_tagged'];?></li>
	</ul>
</div>

<div id="history">
	<h2>Item History</h2>
	<div>New items per day (today at left)</div>
	<div>
	    <img src="feed-action.php?feed_history=<?php echo $feed_id;?>" />
	</div>
</div>
<?php
if (!empty($fof_admin_prefs['purge'])) {
	echo '<div id="purge">' . "\n";
	echo '<h2>Purge Potential</h2>' . "\n";
	/* NOTE: not accurate, don't know how many items were listed in latest feed fetch */
	$purge_statement = fof_db_items_purge_list($feed_id, $fof_admin_prefs['purge'], $fof_admin_prefs['purge_grace'], array('folded'));
	$purge_items = $purge_statement->fetchAll();
	$purge_count = count($purge_items);
	echo '<div>' . ($purge_count ? $purge_count : 'No') . ' item' . ($purge_count == 1 ? '' : 's') . ' likely to be purged on next update.</div>' . "\n";
	echo '</div>' . "\n";
}
?>

<div id="times">
	<h2>Dates</h2>
	<ul>
		<li>Last updated: <?php echo pretty_time($feed_row['feed_age']) . '&nbsp;(' . $feed_row['agestr'] . ')';?></li>
		<li>Most recent item: <?php echo pretty_time($feed_row['max_date']) . '&nbsp;(' . $feed_row['lateststr'] . ')';?></li>
		<li>Image cached:  <?php echo pretty_time($feed_row['feed_image_cache_date']);?>
</li>
		<li>Last update attempt: <?php echo pretty_time($feed_row['feed_cache_attempt_date']);?></li>
<?php
if (!empty($feed_row['feed_cache_last_attempt_status'])) {
	echo '<li><img class="feed-icon" src="' . $fof_asset['alert_icon'] . '" />&nbsp;Last update attempt was not successful: <span>' . $feed_row['feed_cache_last_attempt_status'] . '</span></li>' . "\n";
}
?>
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

<?php
if (!$admin_view) {
	?>
<form method="post" action="">

<div id="feedtags">
	<h2>Tags Automatically Applied to Items from this Feed</h2>
	<ul>
		<li><img class="feed-icon" src="<?php echo $fof_asset['busy_icon'];?>" /> Tags loading...</li>
	</ul>
	<span>
<?php
/*
	 * As far as I can tell, the observe event attached on domload (above) ought to handle this, but without the inline keyCode check here, it seems to still try to submit the form on carriage-return.
	 * Perhaps someone better-versed in clientside js can iron this out.
	 */
	?>
		<input type="text" size="10" id="new_tag" onkeypress="if (event.keyCode == Event.KEY_RETURN) return false;" /><input type="button" value="Tag Feed" />
	</span>
</div>

</form>

<?php
}
?>

<?php
include 'footer.php';
?>
