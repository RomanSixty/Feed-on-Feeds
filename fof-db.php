<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * fof-db.php - all of the DB specific code
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

require_once 'classes/pdolog.php';

/* these may be overridden in fof-config.php, but generally oughtn't be */
defined('FOF_DB_PREFIX') || define('FOF_DB_PREFIX', 'fof_');
defined('FOF_FEED_TABLE') || define('FOF_FEED_TABLE', FOF_DB_PREFIX . 'feed');
defined('FOF_ITEM_TABLE') || define('FOF_ITEM_TABLE', FOF_DB_PREFIX . 'item');
defined('FOF_ITEM_TAG_TABLE') || define('FOF_ITEM_TAG_TABLE', FOF_DB_PREFIX . 'item_tag');
defined('FOF_SUBSCRIPTION_TABLE') || define('FOF_SUBSCRIPTION_TABLE', FOF_DB_PREFIX . 'subscription');
defined('FOF_TAG_TABLE') || define('FOF_TAG_TABLE', FOF_DB_PREFIX . 'tag');
defined('FOF_USER_TABLE') || define('FOF_USER_TABLE', FOF_DB_PREFIX . 'user');
defined('FOF_VIEW_TABLE') || define('FOF_VIEW_TABLE', FOF_DB_PREFIX . 'view');
defined('FOF_VIEW_STATE_TABLE') || define('FOF_VIEW_STATE_TABLE', FOF_DB_PREFIX . 'view_state');
if (defined('USE_MYSQL')) {
	defined('MYSQL_ENGINE') || define('MYSQL_ENGINE', 'InnoDB');
}

$FOF_FEED_TABLE = FOF_FEED_TABLE;
$FOF_ITEM_TABLE = FOF_ITEM_TABLE;
$FOF_ITEM_TAG_TABLE = FOF_ITEM_TAG_TABLE;
$FOF_SUBSCRIPTION_TABLE = FOF_SUBSCRIPTION_TABLE;
$FOF_TAG_TABLE = FOF_TAG_TABLE;
$FOF_USER_TABLE = FOF_USER_TABLE;
$FOF_VIEW_TABLE = FOF_VIEW_TABLE;
$FOF_VIEW_STATE_TABLE = FOF_VIEW_STATE_TABLE;

if (defined('USE_SQLITE')) {
	/* sqlite uses an additional table */
	defined('FOF_USER_LEVELS_TABLE') || define('FOF_USER_LEVELS_TABLE', FOF_USER_TABLE . "_levels");
	$FOF_USER_LEVELS_TABLE = FOF_USER_LEVELS_TABLE;
}

/*
Non-local function dependencies:
fof_prefs()
fof_log()
fof_stacktrace()
fof_todays_date()
fof_multi_sort()

Non-local variable dependencies:
$fof_connection
$fof_user_id
$fof_user_name
$fof_user_level
 */

////////////////////////////////////////////////////////////////////////////////
// Utilities
////////////////////////////////////////////////////////////////////////////////

/** Callback for logging database queries via PDOLog class.
 */
function fof_db_query_log_cb($query_string, $elapsed_time, $result, $rows_affected = null, $parameters = null) {
	$msg = '';

	if (defined('FOF_QUERY_LOG_TRACE')) {
		$msg .= fof_stacktrace(2, false);
		$msg .= ' result:' . $result . ' ';
	} /* FOF_QUERY_LOG_TRACE */

	$msg .= $query_string;

	if (!empty($parameters)) {
		$msg .= ' {';
		foreach ($parameters as $k => $v) {
			$msg .= " '$k':'$v'";
		}

		$msg .= ' }';
	}

	if (!is_null($rows_affected)) {
		$msg .= sprintf(' (%d rows affected)', $rows_affected);
	}

	$msg .= sprintf(' %.3f', $elapsed_time);

	fof_log($msg, 'query');
}

/* set up a db connection, creating the db if it doesn't exist */
function fof_db_connect($create = false) {
	global $fof_connection;

	/* It would be nice to actually use prepared statements by setting
		PDO::ATTR_EMULATE_PREPARES => false
		but it seems that however PDO translates its named parameters to the
		MySQL bindings doesn't work when a parameter is repeated in a query..
		Leaving the emulation on is easier than changing the sql.
	*/
	$pdo_options = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);
	if (defined('USE_MYSQL')) {
		if (!defined('FOF_DB_HOST') || !defined('FOF_DB_DBNAME')) {
			throw new Exception('Missing MySQL configuration; make sure FOF_DB_HOST and FOF_DB_DBNAME are set');
		}
		$dsn = "mysql:host=" . FOF_DB_HOST . ($create ? '' : (';dbname=' . FOF_DB_DBNAME)) . ";charset=utf8mb4";
	} else if (defined('USE_SQLITE')) {
		if (!defined('FOF_DATA_PATH') || !defined('FOF_DB_FILENAME')) {
			throw new Exception('Missing SQLite configuration; make sure FOF_DATA_PATH and FOF_DB_FILENAME are set');
		}
		$sqlite_db = FOF_DATA_PATH . DIRECTORY_SEPARATOR . FOF_DB_FILENAME;
		$dsn = "sqlite:$sqlite_db";
	} else {
		throw new Exception('Missing database configuration; make sure USE_SQLITE or USE_MYSQL is set');
	}

	fof_log("Connecting to '$dsn'...");

	if (defined('USE_SQLITE')) {
		// sqlite doesn't use these variables
		defined('FOF_DB_USER') || define('FOF_DB_USER', '');
		defined('FOF_DB_PASS') || define('FOF_DB_PASS', '');
	}

	$fof_connection = new PDOLog($dsn, FOF_DB_USER, FOF_DB_PASS, $pdo_options);
	PDOLog::$logfn = 'fof_db_query_log_cb';

	if (defined('USE_MYSQL') && $create) {
		if ($fof_connection) {
			$fof_connection->exec("CREATE DATABASE IF NOT EXISTS " . FOF_DB_DBNAME);
			$fof_connection->exec("USE " . FOF_DB_DBNAME);
		}
	}

	return $fof_connection;
}

function fof_db_get_row($statement, $key = NULL, $nomore = FALSE) {
	if (($row = $statement->fetch(PDO::FETCH_ASSOC)) === FALSE) {
		return FALSE;
	}

	if ($nomore) {
		$statement->closeCursor();
	}

	if (isset($key)) {
		return (isset($row[$key]) ? $row[$key] : NULL);
	}

	return $row;
}

function fof_db_optimize() {
	global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_TAG_TABLE, $FOF_USER_TABLE;
	global $fof_connection;

	if (defined('USE_MYSQL')) {
		$query = "OPTIMIZE TABLE $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_TAG_TABLE, $FOF_USER_TABLE";
	} else if (defined('USE_SQLITE')) {
		$query = "VACUUM";
	} else {
		throw new Exception("missing implementation");
	}

	$result = $fof_connection->exec($query);

	return $result;
}

////////////////////////////////////////////////////////////////////////////////
// Feed level stuff
////////////////////////////////////////////////////////////////////////////////

/** Store the current timestamp as when a feed was last successfully fetched and parsed.
 */
function fof_db_feed_mark_cached($feed_id) {
	global $FOF_FEED_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "UPDATE $FOF_FEED_TABLE SET feed_cache_date = :cache_date WHERE feed_id = :feed_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':cache_date', time());
	$statement->bindValue(':feed_id', $feed_id);
	$result = $statement->execute();
	$statement->closeCursor();

	return $result;
}

/** Store the current timestamp as when a feed was last attempted to be fetched.
 */
function fof_db_feed_mark_attempted_cache($feed_id) {
	global $FOF_FEED_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "UPDATE $FOF_FEED_TABLE SET feed_cache_attempt_date = :cache_date WHERE feed_id = :feed_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':cache_date', time());
	$statement->bindValue(':feed_id', $feed_id);
	$result = $statement->execute();
	$statement->closeCursor();

	return $result;
}

/** Store the status of the most recent update of a feed.
 */
function fof_db_feed_update_attempt_status($feed_id, $status) {
	global $FOF_FEED_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "UPDATE $FOF_FEED_TABLE SET feed_cache_last_attempt_status = :status WHERE feed_id = :feed_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':feed_id', $feed_id);
	if (empty($status)) {
		$statement->bindValue(':status', NULL, PDO::PARAM_NULL);
	} else {
		$statement->bindValue(':status', $status);
	}
	$result = $statement->execute();
	$statement->closeCursor();

	return $result;
}

/** Store the various data which describes a feed.
 */
function fof_db_feed_update_metadata($feed_id, $title, $link, $description, $image, $image_cache_date) {
	global $FOF_FEED_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "UPDATE $FOF_FEED_TABLE SET feed_title = :title, feed_link = :link, feed_description = :description, feed_image = :image, feed_image_cache_date = :image_cache_date WHERE feed_id = :feed_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':title', empty($title) ? "[no title]" : $title);
	$statement->bindValue(':link', empty($link) ? "[no link]" : $link);
	$statement->bindValue(':description', empty($description) ? "[no description]" : $description);
	if (!empty($image)) {
		$statement->bindValue(':image', $image);
	} else {
		$statement->bindValue(':image', null, PDO::PARAM_NULL);
	}
	$statement->bindValue(':image_cache_date', empty($image_cache_date) ? time() : $image_cache_date);
	$statement->bindValue(':feed_id', $feed_id);
	$result = $statement->execute();
	$statement->closeCursor();

	return $result;
}

/** Return a row iterator of the most-recent items, per feed_id.
 */
function fof_db_get_latest_item_age($user_id = null, $feed_id = null) {
	global $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE;
	global $fof_connection;

	fof_trace();

	if ($user_id || $feed_id) {
		$where = 'WHERE ';
		if ($user_id) {
			$where .= 's.user_id = :user_id ';
		}

		if ($user_id && $feed_id) {
			$where .= 'AND ';
		}

		if ($feed_id) {
			$where .= 'i.feed_id = :feed_id ';
		}

	} else {
		$where = '';
	}

	$query = "SELECT max(i.item_cached) AS max_date, i.feed_id " .
		"FROM $FOF_ITEM_TABLE i " .
		($user_id ? "JOIN $FOF_SUBSCRIPTION_TABLE s ON i.feed_id = s.feed_id " : '') .
		$where .
		"GROUP BY i.feed_id";
	$statement = $fof_connection->prepare($query);
	if ($user_id) {
		$statement->bindValue(':user_id', $user_id);
	}

	if ($feed_id) {
		$statement->bindValue(':feed_id', $feed_id);
	}

	$result = $statement->execute();

	return $statement;
}

/** Return a summary of the total number of items in a feed, the number of
items with tags, and the count of items per tag.
 */
function fof_db_feed_counts($user_id, $feed_id) {
	global $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_TAG_TABLE;
	global $fof_connection;
	static $system_tags = array('unread', 'star', 'folded'); /* won't tally these under 'tagged' */
	$counts = array();
	$tagged = 0;

	/* always want system tags to have a count */
	foreach ($system_tags as $t) {
		$counts[$t] = 0;
	}

	/* get total items */
	$query = "SELECT count(DISTINCT i.item_id) AS total " .
		"FROM $FOF_ITEM_TABLE i " .
		"LEFT JOIN $FOF_SUBSCRIPTION_TABLE s ON s.feed_id = i.feed_id " .
		"WHERE i.feed_id = :feed_id AND s.user_id = :user_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':user_id', $user_id);
	$statement->bindValue(':feed_id', $feed_id);
	$result = $statement->execute();
	$total = fof_db_get_row($statement, 'total', TRUE);

	/* get counts per tag */
	$query = "SELECT t.tag_id, t.tag_name, COUNT(t.tag_name) AS tag_count " .
		"FROM $FOF_ITEM_TABLE i " .
		"LEFT JOIN $FOF_ITEM_TAG_TABLE it ON it.item_id = i.item_id " .
		"LEFT JOIN $FOF_TAG_TABLE t ON t.tag_id = it.tag_id " .
		"WHERE i.feed_id = :feed_id AND it.user_id = :user_id " .
		"GROUP BY t.tag_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':user_id', $user_id);
	$statement->bindValue(':feed_id', $feed_id);
	$result = $statement->execute();
	while (($row = fof_db_get_row($statement)) !== false) {
		$counts[$row['tag_name']] = $row['tag_count'];
		if (!in_array($row['tag_name'], $system_tags)) {
			$tagged += $row['tag_count'];
		}
	}

	return array($total, $tagged, $counts);
}

/** Return a row iterator of either all subscribed feeds for the user, or only
those subscribed feeds which are due for updating.
NOTE: Caller must invoke fof_db_subscription_feed_fix() on rows, to unpack
subscription preferences into feed data.
 */
function fof_db_get_subscriptions($user_id, $dueOnly = false) {
	global $FOF_FEED_TABLE, $FOF_SUBSCRIPTION_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "SELECT * FROM $FOF_FEED_TABLE f, $FOF_SUBSCRIPTION_TABLE s WHERE s.user_id = :user_id AND f.feed_id = s.feed_id";
	if ($dueOnly) {
		$query .= " AND feed_cache_next_attempt < :now";
	}
	$query .= " ORDER BY feed_title";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':user_id', $user_id);
	if ($dueOnly) {
		$statement->bindValue(':now', time());
	}
	$result = $statement->execute();

	return $statement;
}

/** Fix subscription preferences of any array containing feed data, in-place,
to convert the user's feed preferences, if present, into usable entries.
Array should contain result of querying
FOF_FEED_TABLE.*,FOF_SUBSCRIPTION_TABLE.subscription_prefs
but will initialize entries to null if not present.
 */
function fof_db_subscription_feed_fix(&$f) {
	if (!empty($f['subscription_prefs'])) {
		$f['subscription_prefs'] = unserialize($f['subscription_prefs']);
	}

	if (empty($f['subscription_prefs'])) {
		$f['subscription_prefs'] = array();
	}

	if (empty($f['subscription_prefs']['tags'])) {
		$f['subscription_prefs']['tags'] = array();
	}

	$f['alt_title'] = empty($f['subscription_prefs']['alt_title']) ? null : $f['subscription_prefs']['alt_title'];
	$f['alt_image'] = empty($f['subscription_prefs']['alt_image']) ? null : $f['subscription_prefs']['alt_image'];
	$f['display_title'] = (!empty($f['alt_title'])) ? $f['alt_title'] : $f['feed_title'];
	$f['display_image'] = (!empty($f['alt_image'])) ? $f['alt_image'] : $f['feed_image'];
}

/** Return an array of all the data which defines a feed.
NOTE: This calls fof_db_subscription_feed_fix, so caller doesn't have to.
 */
function fof_db_subscription_feed_get($user_id, $feed_id) {
	global $FOF_FEED_TABLE, $FOF_SUBSCRIPTION_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "SELECT f.*, s.subscription_prefs FROM $FOF_FEED_TABLE f, $FOF_SUBSCRIPTION_TABLE s WHERE s.user_id = :user_id AND s.feed_id = :feed_id AND s.feed_id = f.feed_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':user_id', $user_id);
	$statement->bindValue(':feed_id', $feed_id);
	$result = $statement->execute();

	$r = fof_db_get_row($statement, NULL, TRUE);

	fof_db_subscription_feed_fix($r);

	return $r;
}

/** Return a row iterator of feeds due for updating.
 */
function fof_db_get_feeds_needing_attempt() {
	global $FOF_FEED_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "SELECT * FROM $FOF_FEED_TABLE WHERE feed_cache_next_attempt < :now";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':now', time());
	$result = $statement->execute();

	return $statement;
}

/** Return a row iterator which contains a list of feed_ids and the count of
items in each feed which match the specified parameters.
 */
function fof_db_get_item_count($user_id, $what = 'all', $feed_id = NULL, $search = NULL) {
	global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE;
	global $fof_connection;

	$what_q = array();

	fof_trace();

	/* FIXME: confusing */
	if ($what == 'starred') {
		$what = 'star';
	}

	/* TODO: global $system_tags or something */
	foreach (explode(' ', ($what == 'tagged') ? 'unread star folded' : $what) as $w) {
		$what_q[] = $fof_connection->quote($w);
	}

	/*
		First, generate a query which will return rows of feed_id,item_id for
		every item matching specifications.

		NOTE: would have used more bound parameters in here, but for some
		reason, queries were not working with them, so they've been stripped
		out and replaced with inline values.
	*/
	if ($what == 'all') {
		$query = "SELECT i.feed_id, i.item_id FROM $FOF_ITEM_TABLE i" .
			" JOIN $FOF_SUBSCRIPTION_TABLE s ON s.feed_id = i.feed_id" .
			" WHERE s.user_id = " . $user_id;
	} else {
		$query = "SELECT DISTINCT s.feed_id, i.item_id" .
			" FROM $FOF_SUBSCRIPTION_TABLE s" .
			" JOIN $FOF_ITEM_TABLE i ON i.feed_id = s.feed_id" .
			" JOIN $FOF_ITEM_TAG_TABLE it ON it.item_id = i.item_id AND it.user_id = " . $user_id .
			" JOIN $FOF_TAG_TABLE t ON t.tag_id = it.tag_id" .
			" WHERE s.user_id = " . $user_id;
	}

	if (!empty($feed_id)) {
		$query .= " AND i.feed_id = :feed_id";
	}

	if (!empty($search)) {
		$query .= " AND (i.item_title LIKE :search OR i.item_content LIKE :search)";
	}

	switch ($what) {
		case 'all':
			/* Every item. */
			break;

		case 'tagged':
			/* Item must be tagged, but not by any system tag. */
			$query .= " AND t.tag_name NOT IN (" . implode(',', $what_q) . ") GROUP BY it.item_id";
			break;

		default:
			/* Item must have all tags. */
			if (!empty($what_q)) {
				$query .= " AND t.tag_name IN (" . implode(',', $what_q) . ")";
				$query .= " GROUP BY it.item_id HAVING count(it.item_id) = " . count($what_q);
			}
	}

	/* Now tally the item results by feed_id. */
	$count_query = 'SELECT feed_id, count(*) AS count FROM (' . $query . ') AS matched_items GROUP BY feed_id';

	$statement = $fof_connection->prepare($count_query);

	if (!empty($feed_id)) {
		$statement->bindValue(':feed_id', $feed_id);
	}

	if (!empty($search)) {
		$statement->bindValue(':search', '%' . $search . '%');
	}

	$result = $statement->execute();

	return $statement;
}

function fof_db_get_subscribed_users($feed_id) {
	global $FOF_SUBSCRIPTION_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "SELECT user_id FROM $FOF_SUBSCRIPTION_TABLE WHERE feed_id = :feed_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':feed_id', $feed_id);
	$result = $statement->execute();

	return $statement;
}

function fof_db_get_subscribed_users_count($feed_id) {
	fof_trace();

	$sub_statement = fof_db_get_subscribed_users($feed_id);
	$subscribed_users = $sub_statement->fetchAll();

	return count($subscribed_users);
}

function fof_db_is_subscribed($user_id, $feed_url) {
	global $FOF_FEED_TABLE, $FOF_SUBSCRIPTION_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "SELECT s.feed_id FROM $FOF_SUBSCRIPTION_TABLE s, $FOF_FEED_TABLE f" .
		" WHERE f.feed_url = :feed_url" .
		" AND f.feed_id = s.feed_id" .
		" AND s.user_id = :user_id;";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':feed_url', $feed_url);
	$statement->bindValue(':user_id', $user_id);
	$result = $statement->execute();
	$row = fof_db_get_row($statement, NULL, TRUE);
	if (!empty($row)) {
		return true;
	}

	return false;
}

function fof_db_is_subscribed_id($user_id, $feed_id) {
	global $FOF_SUBSCRIPTION_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "SELECT feed_id FROM $FOF_SUBSCRIPTION_TABLE WHERE feed_id = :feed_id AND user_id = :user_id;";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':feed_id', $feed_id);
	$statement->bindValue(':user_id', $user_id);
	$result = $statement->execute();
	$row = fof_db_get_row($statement, NULL, TRUE);
	if (!empty($row)) {
		return true;
	}

	return false;
}

function fof_db_get_feed_by_url($feed_url) {
	global $FOF_FEED_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "SELECT * FROM $FOF_FEED_TABLE WHERE feed_url = :feed_url";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':feed_url', $feed_url);
	$result = $statement->execute();

	return fof_db_get_row($statement, NULL, TRUE);
}

function fof_db_get_feed_by_id($feed_id) {
	global $FOF_FEED_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "SELECT * FROM $FOF_FEED_TABLE WHERE feed_id = :feed_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':feed_id', $feed_id);
	$result = $statement->execute();

	return fof_db_get_row($statement, NULL, TRUE);
}

function fof_db_add_feed($url, $title, $link, $description) {
	global $FOF_FEED_TABLE;
	global $fof_connection;

	fof_trace();

	/* FIXME: just store these as empty here */
	if (empty($title)) {
		$title = "[no title]";
	}

	if (empty($link)) {
		$link = "[no link]";
	}

	if (empty($description)) {
		$description = "[no description]";
	}

	$query = "INSERT INTO $FOF_FEED_TABLE (feed_url, feed_title, feed_link, feed_description) VALUES (:url, :title, :link, :description)";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':url', $url);
	$statement->bindValue(':title', $title);
	$statement->bindValue(':link', $link);
	$statement->bindValue(':description', $description);
	$result = $statement->execute();
	$statement->closeCursor();

	$feed_id = $fof_connection->lastInsertId();

	return $feed_id;
}

function fof_db_add_subscription($user_id, $feed_id) {
	global $FOF_SUBSCRIPTION_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "INSERT INTO $FOF_SUBSCRIPTION_TABLE ( feed_id, user_id, subscription_added ) VALUES ( :feed_id, :user_id, :now )";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':feed_id', $feed_id);
	$statement->bindValue(':user_id', $user_id);
	$statement->bindValue(':now', time());
	$result = $statement->execute();
	$statement->closeCursor();
}

function fof_db_delete_subscription($user_id, $feed_id) {
	global $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
	global $fof_connection;

	fof_trace();

	$all_items = fof_db_get_items($user_id, $feed_id, "all");
	$items_q = array();
	foreach ($all_items as $i) {
		$items_q[] = $fof_connection->quote($i['item_id']);
	}

	if (count($items_q) > 0) {
		$query = "DELETE FROM $FOF_ITEM_TAG_TABLE WHERE user_id = :user_id AND item_id IN ( " . (count($items_q) ? implode(', ', $items_q) : "''") . " )";
		$statement = $fof_connection->prepare($query);
		$statement->bindValue(':user_id', $user_id);
		$result = $statement->execute();
		$statement->closeCursor();
	}

	$query = "DELETE FROM $FOF_SUBSCRIPTION_TABLE WHERE feed_id = :feed_id and user_id = :user_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':user_id', $user_id);
	$statement->bindValue(':feed_id', $feed_id);
	$result = $statement->execute();
	$statement->closeCursor();
}

function fof_db_delete_feed($feed_id) {
	global $FOF_FEED_TABLE, $FOF_ITEM_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "DELETE FROM $FOF_FEED_TABLE WHERE feed_id = :feed_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':feed_id', $feed_id);
	$result = $statement->execute();
	$statement->closeCursor();

	$query = "DELETE FROM $FOF_ITEM_TABLE WHERE feed_id = :feed_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':feed_id', $feed_id);
	$result = $statement->execute();
	$statement->closeCursor();
}

function fof_db_feed_cache_set($feed_id, $next_attempt) {
	global $FOF_FEED_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "UPDATE $FOF_FEED_TABLE SET feed_cache_next_attempt = :next_attempt WHERE feed_id = :feed_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(":feed_id", $feed_id);
	$statement->bindValue(":next_attempt", $next_attempt);
	$result = $statement->execute();
	$statement->closeCursor();
}

/** Return an array representing a feed's activity.
Array contains the number of items added to the feed, indexed by days-ago.

FIXME: there probably ought to be an offset to midnight or something in
here somewhere, but it's just a rough overview
 */
function fof_db_feed_history($feed_id) {
	global $FOF_ITEM_TABLE;
	global $fof_connection;

	$history = array();
	$today_day = floor(time() / (60 * 60 * 24));

	$query = "SELECT item_updated FROM $FOF_ITEM_TABLE WHERE feed_id = :feed_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':feed_id', $feed_id);
	$statement->execute();
	while (($item_updated = fof_db_get_row($statement, 'item_updated')) !== false) {
		$item_day = floor($item_updated / (60 * 60 * 24));
		$days_ago = $today_day - $item_day;
		if (empty($history[$days_ago])) {
			$history[$days_ago] = 1;
		} else {
			$history[$days_ago]++;
		}

	}
	end($history);
	$last = key($history);
	reset($history);
	if (!empty($last)) {
		$history = array_merge(array_fill(0, $last, 0), $history);
	}

	return $history;
}

////////////////////////////////////////////////////////////////////////////////
// Item level stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_find_item($feed_id, $item_guid) {
	global $FOF_ITEM_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "SELECT item_id FROM $FOF_ITEM_TABLE WHERE feed_id = :feed_id and item_guid = :item_guid";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(":feed_id", $feed_id);
	$statement->bindValue(":item_guid", $item_guid);
	$result = $statement->execute();

	return fof_db_get_row($statement, 'item_id', TRUE);
}

function fof_db_add_item($item_id, $feed_id, $guid, $link, $title, $content, $cached, $itemdate, $author) {
	global $FOF_ITEM_TABLE;
	global $fof_connection;

	fof_trace();

	/* last-ditch enforce constraints */
	if (is_null($link)) {
		$link = '';
	}

	if (is_null($guid)) {
		$guid = '';
	}

	if (is_null($title)) {
		$title = '';
	}

	if (is_null($content)) {
		$content = '';
	}

	if (is_null($cached)) {
		$cached = 0;
	}

	if (is_null($itemdate)) {
		$itemdate = 0;
	}

	if ($item_id == NULL) {
		$query = "INSERT INTO $FOF_ITEM_TABLE
				(feed_id, item_link, item_guid, item_title, item_content, item_cached, item_published, item_updated, item_author)
			VALUES
				(:feed_id, :link, :guid, :title, :content, :cached, :itemdate, :itemdate, :author)";
		$statement = $fof_connection->prepare($query);
		$statement->bindValue(':feed_id', $feed_id);
		$statement->bindValue(':link', $link);
		$statement->bindValue(':guid', $guid);
		$statement->bindValue(':title', $title);
		$statement->bindValue(':content', $content);
		$statement->bindValue(':cached', $cached);
		$statement->bindValue(':itemdate', $itemdate);
		$statement->bindValue(':author', $author);
	} else {
		$query = "UPDATE $FOF_ITEM_TABLE SET
				item_link = :link,
				item_guid = :guid,
				item_title = :title,
				item_content = :content,
				item_cached = :cached,
				item_updated = :itemdate,
				item_author = :author
			WHERE feed_id = :feed_id and item_id = :item_id";
		$statement = $fof_connection->prepare($query);
		$statement->bindValue(':link', $link);
		$statement->bindValue(':guid', $guid);
		$statement->bindValue(':title', $title);
		$statement->bindValue(':content', $content);
		$statement->bindValue(':cached', $cached);
		$statement->bindValue(':itemdate', $itemdate);
		$statement->bindValue(':author', $author);
		$statement->bindValue(':feed_id', $feed_id);
		$statement->bindValue(':item_id', $item_id);
	}
	$result = $statement->execute();
	$statement->closeCursor();

	if ($item_id == NULL) {
		$item_id = $fof_connection->lastInsertId();
	}
	return $item_id;
}

/* when: Y/m/d or 'today' */
function fof_db_get_items($user_id = 1, $feed = NULL, $what = 'unread', $when = NULL, $start = NULL, $limit = NULL, $order = 'desc', $search = NULL) {
	global $FOF_SUBSCRIPTION_TABLE, $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_TAG_TABLE;
	global $fof_connection;
	$all_items = array();

	if ($order != 'asc' && $order != 'desc') {
		$order = 'desc';
	}

	fof_trace();

	$prefs = fof_prefs();

	$select = "SELECT i.*, f.*, s.subscription_prefs";

	$from = " FROM $FOF_FEED_TABLE f, $FOF_ITEM_TABLE i, $FOF_SUBSCRIPTION_TABLE s";
	if ($what != 'all') {
		$from .= ", $FOF_TAG_TABLE t, $FOF_ITEM_TAG_TABLE it";
	}

	$where = " WHERE s.user_id = " . $fof_connection->quote($user_id) . " AND s.feed_id = f.feed_id AND f.feed_id = i.feed_id";
	if (!empty($feed)) {
		$where .= " AND f.feed_id = " . $fof_connection->quote($feed);
	}
	if (!empty($when)) {
		$tzoffset = isset($prefs['tzoffset']) ? $prefs['tzoffset'] : 0;
		$whendate = explode('/', ($when == 'today') ? fof_todays_date() : $when);
		$when_begin = gmmktime(0, 0, 0, $whendate[1], $whendate[2], $whendate[0]) - ($tzoffset * 60 * 60);
		$when_end = $when_begin + (24 * 60 * 60);
		$where .= " AND i.item_published > " . $fof_connection->quote($when_begin) . " AND i.item_published < " . $fof_connection->quote($when_end);
	}
	if ($what != 'all') {
		$tags_q = array();
		foreach (explode(' ', $what) as $tag) {
			$tags_q[] = $fof_connection->quote($tag);
		}
		$where .= " AND it.user_id = s.user_id AND it.tag_id = t.tag_id AND i.item_id = it.item_id AND t.tag_name IN (" . (count($tags_q) ? implode(', ', $tags_q) : "''") . ")";
	}

	if ($what == 'all') {
		$group = "";
	} else {
		$group = " GROUP BY i.item_id HAVING COUNT( i.item_id ) = " . count($tags_q);
	}

	if (!empty($search)) {
		$search_q = $fof_connection->quote('%' . $search . '%');
		$where .= " AND (i.item_title LIKE $search_q OR i.item_content LIKE $search_q )";
	}

	$order_by = " ORDER BY i.item_published " . strtoupper($order);
	if (is_numeric($start)) {
		$order_by .= " LIMIT " . $start . ", " . ((is_numeric($limit)) ? $limit : $prefs['howmany']);
	}

	$query = $select . $from . $where . $group . $order_by;

	// fof_log(__FUNCTION__ . " first query: " . $query);

	$statement = $fof_connection->prepare($query);
	$result = $statement->execute();

	$item_ids_q = array();
	$lookup = array(); /* remember item_id->all_rows mapping, for populating tags */
	$idx = 0;
	while (($row = fof_db_get_row($statement)) !== FALSE) {
		fof_trace("collecting item_id:" . $row['item_id'] . " idx:$idx");
		fof_db_subscription_feed_fix($row); /* feed prefs are included, so decode them */
		$item_ids_q[] = $fof_connection->quote($row['item_id']);
		$lookup[$row['item_id']] = $idx;
		$all_items[$idx] = $row;
		$all_items[$idx]['tags'] = array();
		$idx += 1;
	}

	$all_items = fof_multi_sort($all_items, 'item_published', $order != "asc");

	$query = "SELECT t.tag_name, it.item_id" .
	" FROM $FOF_TAG_TABLE t, $FOF_ITEM_TAG_TABLE it" .
	" WHERE t.tag_id = it.tag_id" .
	" AND it.item_id IN (" . (count($item_ids_q) ? implode(',', $item_ids_q) : "''") . ")" .
	" AND it.user_id = " . $fof_connection->quote($user_id);

	// fof_log(__FUNCTION__ . " second query: " . $query);
	fof_trace('item_ids_q:' . implode(',', $item_ids_q));

	$statement = $fof_connection->prepare($query);
	$result = $statement->execute();
	while (($row = fof_db_get_row($statement)) !== FALSE) {
		$idx = $lookup[$row['item_id']];
		$all_items[$idx]['tags'][] = $row['tag_name'];
	}

	fof_trace("all_items:" . var_export($all_items, true));

	return $all_items;
}

function fof_db_get_item($user_id, $item_id) {
	global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_TAG_TABLE;
	global $fof_connection;
	$item = array();

	fof_trace();

	$query = "SELECT i.*, f.*" .
		($user_id ? ", s.subscription_prefs " : '') .
		" FROM $FOF_ITEM_TABLE i " .
		" JOIN $FOF_FEED_TABLE f ON i.feed_id = f.feed_id " .
		($user_id ? "JOIN $FOF_SUBSCRIPTION_TABLE s ON i.feed_id = s.feed_id AND s.user_id = :user_id " : '') .
		" WHERE i.item_id = :item_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':item_id', $item_id);
	if ($user_id) {
		$statement->bindValue(':user_id', $user_id);
	}
	$result = $statement->execute();
	$item = fof_db_get_row($statement, NULL, TRUE);

	$item['tags'] = array();

	if ($user_id) {
		fof_db_subscription_feed_fix($item);

		$query = "SELECT t.tag_name" .
			" FROM $FOF_TAG_TABLE t, $FOF_ITEM_TAG_TABLE it" .
			" WHERE t.tag_id = it.tag_id" .
			" AND it.item_id = :item_id" .
			" AND it.user_id = :user_id";
		$statement = $fof_connection->prepare($query);
		$statement->bindValue(':item_id', $item_id);
		$statement->bindValue(':user_id', $user_id);
		$result = $statement->execute();
		while (($row = fof_db_get_row($statement)) !== false) {
			$item['tags'][] = $row['tag_name'];
		}
	}

	return $item;
}

/** Return a row iterator over any items from $feed_id which are older than
$purge_days and are not tagged (excepting $ignore_tag_names) by any users.
Does not include any items which would be in the remaining $purge_grace
items of feed, regardless of age.
 */
function fof_db_items_purge_list($feed_id, $purge_days, $purge_grace = 0, $ignore_tag_names = array()) {
	global $FOF_ITEM_TABLE, $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE;
	global $fof_connection;

	fof_trace();

	$purge_secs = $purge_days * 24 * 60 * 60;

	$ignore_tag_names_q = array();
	foreach ($ignore_tag_names as $tagname) {
		$ignore_tag_names_q[] = $fof_connection->quote($tagname);
	}

	/*  We're interested in items (from a specific feed, and last updated before
		the given timestamp) which either lack tags entirely, or which are
		tagged /solely/ with the tags we've specified.

		We begin by getting all of the item_ids from the item table which have
		either no tag_id in the item-tag table, or have one of the specified
		tag_names.  Then, from that set, we remove any item_ids which exist in
		the item-tag table which are tagged with anything other than our allowed
		tag_names.

		This doesn't use set-difference operations because MySQL doesn't know
		what those are.

		This could probably be optimized.
	*/

	$query = "SELECT DISTINCT i.item_id FROM $FOF_ITEM_TABLE i" .
		" LEFT JOIN $FOF_ITEM_TAG_TABLE it USING (item_id)" .
		" LEFT JOIN $FOF_TAG_TABLE t USING (tag_id)" .
		" WHERE i.feed_id = :feed_id" .
		" AND i.item_updated < :purge_before" .
		" AND (tag_id IS NULL" .
		(empty($ignore_tag_names_q) ? '' : (" OR t.tag_name IN (" . implode(',', $ignore_tag_names_q) . ")")) .
		")";
	if (!empty($ignore_tag_names_q)) {
		$query .= " AND i.item_id NOT IN (" .
		" SELECT DISTINCT it.item_id FROM $FOF_ITEM_TAG_TABLE it" .
		" LEFT JOIN $FOF_ITEM_TABLE i USING (item_id)" .
		" LEFT JOIN $FOF_TAG_TABLE t USING (tag_id)" .
		" WHERE feed_id = :feed_id" .
		" AND i.item_updated < :purge_before" .
		" AND t.tag_name NOT IN (" . implode(',', $ignore_tag_names_q) . ")" .
			" )";
	}

	$query .= " ORDER BY i.item_updated DESC";

	if (!empty($purge_grace)) {
		/*  We need to include a LIMIT of as many as the driver can return,
			because some drivers don't understand OFFSET without a LIMIT.
		*/
		if (defined('USE_MYSQL')) {
			$query .= ' LIMIT 18446744073709551610';
		}
		/* unsigned bigint max */
		else if (defined('USE_SQLITE')) {
			$query .= ' LIMIT -1';
		} else {
			throw new Exception('missing implementation');
		}

		/*  Leave some items. */
		$query .= ' OFFSET ' . $purge_grace;
	}

	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':feed_id', $feed_id);
	$statement->bindValue(':purge_before', time() - $purge_secs);
	$result = $statement->execute();

	return $statement;
}

function fof_db_items_delete($items) {
	global $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE;
	global $fof_connection;

	fof_trace();

	if (!$items) {
		return;
	}

	if (!is_array($items)) {
		$items = array($items);
	}

	$items = array_filter($items);

	if (empty($items)) {
		return;
	}

	$items_q = array();
	foreach ($items as $item) {
		$items_q[] = $fof_connection->quote($item);
	}

	$fof_connection->beginTransaction();

	$query = "DELETE FROM $FOF_ITEM_TABLE WHERE item_id IN (" . implode(',', $items_q) . ")";
	$result = $fof_connection->exec($query);

	/* ON DELETE CASCADE */
	$query = "DELETE FROM $FOF_ITEM_TAG_TABLE WHERE item_id IN (" . implode(',', $items_q) . ")";
	$result = $fof_connection->exec($query);

	$fof_connection->commit();
}

/* Used for similarity matching. */
function fof_db_items_duplicate_list() {
	global $FOF_ITEM_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "SELECT i2.item_id, i1.item_content AS c1," .
		" i2.item_content AS c2" .
		" FROM $FOF_ITEM_TABLE i1" .
		" LEFT JOIN $FOF_ITEM_TABLE i2" .
		" ON i1.item_title=i2.item_title AND i1.feed_id=i2.feed_id" .
		" WHERE i1.item_id < i2.item_id";
	$statement = $fof_connection->query($query);

	return $statement;
}

/* Used for dynamic update statistics. */
function fof_db_items_updated_list($feed_id) {
	global $FOF_ITEM_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "SELECT item_updated FROM $FOF_ITEM_TABLE WHERE feed_id = :feed_id ORDER BY item_updated ASC";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':feed_id', $feed_id);
	$result = $statement->execute();

	return $statement;
}

////////////////////////////////////////////////////////////////////////////////
// Tag stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_tag_delete($items) {
	global $FOF_ITEM_TAG_TABLE;
	global $fof_connection;

	fof_trace();

	if (!$items) {
		return;
	}

	if (!is_array($items)) {
		$items = array($items);
	}

	$items_q = array();
	foreach ($items as $item) {
		$items_q[] = $fof_connection->quote($item);
	}

	$query = "DELETE FROM $FOF_ITEM_TAG_TABLE WHERE item_id IN (" . (count($items_q) ? implode(', ', $items_q) : "''") . ")";
	$result = $fof_connection->exec($query);
}

/* fetches an array of tag_ids and the list of feed_ids which tag them for $user_id */
function fof_db_subscriptions_by_tags($user_id) {
	global $FOF_SUBSCRIPTION_TABLE;
	global $fof_connection;
	$r = array();

	fof_trace();

	$query = "SELECT feed_id, subscription_prefs FROM $FOF_SUBSCRIPTION_TABLE WHERE user_id = :user_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue('user_id', $user_id);
	$result = $statement->execute();

	while (($sub = fof_db_get_row($statement)) !== false) {
		$sub['subscription_prefs'] = empty($sub['subscription_prefs']) ? array('tags' => array()) : unserialize($sub['subscription_prefs']);
		if (!empty($sub['subscription_prefs']['tags'])) {
			foreach ($sub['subscription_prefs']['tags'] as $tagid) {
				if (empty($r[$tagid])) {
					$r[$tagid] = array();
				}

				$r[$tagid][] = $sub['feed_id'];
			}
		} else {
			$tagid = 0; /* untagged feeds get lumped into tagid '0' */
			if (empty($r[$tagid])) {
				$r[$tagid] = array();
			}

			$r[$tagid][] = $sub['feed_id'];
		}
	}

	return $r;
}

function fof_db_get_subscription_to_tags() {
	global $FOF_SUBSCRIPTION_TABLE;
	global $fof_connection;
	$r = array();

	fof_trace();

	$query = "SELECT * FROM $FOF_SUBSCRIPTION_TABLE";
	$statement = $fof_connection->query($query);
	while (($row = fof_db_get_row($statement)) !== false) {
		$feed_id = $row['feed_id'];
		$user_id = $row['user_id'];
		$prefs = unserialize($row['subscription_prefs']);
		if (!isset($r[$feed_id])) {
			$r[$feed_id] = array();
		}
		$r[$feed_id][$user_id] = $prefs['tags'];
	}

	return $r;
}

/* returns the per-user preferences for the feed */
function fof_db_subscription_prefs_get($user_id, $feed_id) {
	global $FOF_SUBSCRIPTION_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "SELECT subscription_prefs FROM $FOF_SUBSCRIPTION_TABLE WHERE feed_id = :feed_id AND user_id = :user_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':feed_id', $feed_id);
	$statement->bindValue(':user_id', $user_id);
	$result = $statement->execute();
	$prefs = unserialize(fof_db_get_row($statement, 'subscription_prefs', TRUE));
	if (empty($prefs)) {
		$prefs = array('tags' => array());
	}

	if (empty($prefs['tags'])) {
		$prefs['tags'] = array();
	}

	return $prefs;
}

/* store subscription prefs */
function fof_db_subscription_prefs_set($user_id, $feed_id, $prefs) {
	global $FOF_SUBSCRIPTION_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "UPDATE $FOF_SUBSCRIPTION_TABLE SET subscription_prefs = :prefs WHERE feed_id = :feed_id AND user_id = :user_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':user_id', $user_id);
	$statement->bindValue(':feed_id', $feed_id);
	$statement->bindValue(':prefs', serialize($prefs));
	$result = $statement->execute();
	$statement->closeCursor();

	return $result;
}

/* set a custom title for a subscription */
function fof_db_subscription_title_set($user_id, $feed_id, $alt_title) {
	fof_trace();

	$prefs = fof_db_subscription_prefs_get($user_id, $feed_id);

	if (empty($alt_title)) {
		unset($prefs['alt_title']);
	} else {
		$prefs['alt_title'] = $alt_title;
	}

	return fof_db_subscription_prefs_set($user_id, $feed_id, $prefs);
}

function fof_db_subscription_image_set($user_id, $feed_id, $alt_image) {
	fof_trace();

	$prefs = fof_db_subscription_prefs_get($user_id, $feed_id);

	if (empty($alt_image)) {
		unset($prefs['alt_image']);
	} else {
		$prefs['alt_image'] = $alt_image;
	}

	return fof_db_subscription_prefs_set($user_id, $feed_id, $prefs);
}

function fof_db_subscription_tag_add($user_id, $feed_id, $tag_id) {
	fof_trace();

	$prefs = fof_db_subscription_prefs_get($user_id, $feed_id);

	if (!in_array($tag_id, $prefs['tags'])) {
		$prefs['tags'][] = $tag_id;
	}

	return fof_db_subscription_prefs_set($user_id, $feed_id, $prefs);
}

function fof_db_subscription_tag_remove($user_id, $feed_id, $tag_id) {
	fof_trace();

	$prefs = fof_db_subscription_prefs_get($user_id, $feed_id);

	$prefs['tags'] = array_diff($prefs['tags'], array($tag_id));

	return fof_db_subscription_prefs_set($user_id, $feed_id, $prefs);
}

function fof_db_get_item_tags($user_id, $item_id) {
	global $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "SELECT t.tag_name" .
		" FROM $FOF_TAG_TABLE t, $FOF_ITEM_TAG_TABLE it" .
		" WHERE t.tag_id = it.tag_id" .
		" AND it.item_id = :item_id" .
		" AND it.user_id = :user_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':item_id', $item_id);
	$statement->bindValue(':user_id', $user_id);
	$result = $statement->execute();

	return $statement;
}

/** Return count of a items matching a single tag_name for user_id.
 */
function fof_db_tag_count($user_id, $tag_name) {
	global $FOF_ITEM_TAG_TABLE, $FOF_TAG_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "SELECT COUNT(*) AS tag_count FROM $FOF_ITEM_TAG_TABLE it, $FOF_TAG_TABLE t"
		. " WHERE it.tag_id = t.tag_id AND it.user_id = :user_id AND t.tag_name = :tag_name";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':user_id', $user_id);
	$statement->bindValue(':tag_name', $tag_name);
	$result = $statement->execute();

	return fof_db_get_row($statement, 'tag_count', TRUE);
}

/** Return row iterator, providing tag_id and count of items with both tag_id
and 'unread' tag.
FIXME: This produces incorrect count for otherwise-untagged 'unread' items.
Currently this is not a huge issue, as this is only used in the sidebar tag
list, and the unread tag (along with star and folded) are ignored from this.
 */
function fof_db_get_tag_unread($user_id) {
	global $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE;
	global $fof_connection;
	$counts = array();

	fof_trace();

	$query = "SELECT count(*) AS tag_count, it2.tag_id" .
		" FROM $FOF_ITEM_TABLE i, $FOF_ITEM_TAG_TABLE it , $FOF_ITEM_TAG_TABLE it2" .
		" WHERE it.item_id = it2.item_id" .
		" AND it.tag_id = 1" .
		" AND i.item_id = it.item_id" .
		" AND i.item_id = it2.item_id" .
		" AND it.user_id = :user_id" .
		" AND it2.user_id = :user_id" .
		" GROUP BY it2.tag_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':user_id', $user_id);
	$result = $statement->execute();
	while (($row = fof_db_get_row($statement)) !== false) {
		$counts[$row['tag_id']] = $row['tag_count'];
	}

	return $counts;
}

function fof_db_get_tags($user_id) {
	global $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "SELECT t.tag_id, t.tag_name, count( it.item_id ) AS count" .
		" FROM $FOF_TAG_TABLE t" .
		" LEFT JOIN $FOF_ITEM_TAG_TABLE it ON t.tag_id = it.tag_id" .
		" WHERE it.user_id = :user_id" .
		" GROUP BY t.tag_id ORDER BY t.tag_name";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':user_id', $user_id);
	$result = $statement->execute();

	return $statement;
}

/** Return a dictionary matching tag names to tag ids.
N.B. This caches result the first time it's called.  Cache may be
invalidated, but if invalidation variable is set, will do nothing else but
drop the current cache! (So as to defer reloading map until called again.)
 */
function fof_db_get_tag_name_map($tags = null, $invalidate = null) {
	global $FOF_TAG_TABLE;
	global $fof_connection;
	static $tag_name_to_id = null;

	fof_trace();

	if ($invalidate !== null) {
		$tag_name_to_id = null;
		return null;
	}

	if ($tag_name_to_id === null) {
		$tag_name_to_id = array();
		$query = "SELECT * FROM $FOF_TAG_TABLE";
		$statement = $fof_connection->query($query);
		while (($row = fof_db_get_row($statement)) !== false) {
			$tag_name_to_id[$row['tag_name']] = $row['tag_id'];
		}

	}

	if ($tags === null) {
		return $tag_names;
	}

	$r = array();
	foreach ($tags as $t) {
		if (!empty($tag_name_to_id[$t])) {
			$r[] = $tag_name_to_id[$t];
		}
	}

	return $r;
}

/** Return a dictionary matching tag ids to tag names.
N.B. This caches result the first time it's called.  Cache may be
invalidated, but if invalidation variable is set, will do nothing else but
drop the current cache! (So as to defer reloading map until called again.)
 */
function fof_db_get_tag_id_map($tags = null, $invalidate = null) {
	global $FOF_TAG_TABLE;
	global $fof_connection;
	static $tag_id_to_name = null;

	fof_trace();

	if ($invalidate !== null) {
		$tag_id_to_name = null;
		return null;
	}

	if ($tag_id_to_name === null) {
		$tag_id_to_name = array();
		$query = "SELECT * FROM $FOF_TAG_TABLE";
		$statement = $fof_connection->query($query);
		while (($row = fof_db_get_row($statement)) !== false) {
			$tag_id_to_name[$row['tag_id']] = $row['tag_name'];
		}

	}

	if ($tags === null) {
		return $tag_id_to_name;
	}

	$r = array();
	foreach ($tags as $t) {
		$r[] = $tag_id_to_name[$t];
	}

	return $r;
}

/** Store a new tag name, and return its id.
 */
function fof_db_create_tag($tag_name) {
	global $FOF_TAG_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "INSERT INTO $FOF_TAG_TABLE (tag_name) VALUES (:tag_name)";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':tag_name', $tag_name);
	$result = $statement->execute();

	$tag_id = $fof_connection->lastInsertId();

	/* Invalidate any currently-loaded tag maps. */
	fof_db_get_tag_id_map(null, TRUE);
	fof_db_get_tag_name_map(null, TRUE);

	return $tag_id;
}

/** Given a string of space-separated tags, return comma-delimited string of
ids.
NOTE: Order of tags/ids is not necessarily preserved!
TODO: Migrate callers to use fof_db_get_tag_name_map function instead.
 */
function fof_db_get_tag_by_name($tags) {
	global $FOF_TAG_TABLE;
	global $fof_connection;
	$return = array();

	fof_trace();

	$tags_q = array();
	foreach (explode(' ', $tags) as $t) {
		$tags_q[] = $fof_connection->quote($t);
	}

	$query = "SELECT DISTINCT tag_id" .
		" FROM $FOF_TAG_TABLE" .
		" WHERE tag_name IN ( " . (count($tags_q) ? implode(', ', $tags_q) : "''") . " )";
	$statement = $fof_connection->query($query);
	while (($row = fof_db_get_row($statement)) !== false) {
		$return[] = $row['tag_id'];
	}

	if (count($return)) {
		return implode(',', $return);
	}

	return NULL;
}

/** Remove 'unread' tag from all items for user_id which also have tagname.
 */
function fof_db_mark_tag_read($user_id, $tagname) {
	global $FOF_ITEM_TAG_TABLE;
	global $fof_connection;

	fof_trace();

	$user_id = $fof_connection->quote($user_id);
	$tag_id = fof_db_get_tag_by_name($tagname);
	$unread_id = fof_db_get_tag_by_name('unread');
	if (empty($tag_id) || empty($unread_id) || $tag_id == $unread_id) {
		return false;
	}

	/* items with all these tags */
	$matching_tag_ids = array($tag_id, $unread_id);

	/* all items with both unread and tagname */
	$items_query = "SELECT item_id FROM $FOF_ITEM_TAG_TABLE it WHERE it.user_id = $user_id AND it.tag_id IN (" . (count($matching_tag_ids) ? implode(', ', $matching_tag_ids) : "''") . ") GROUP BY it.item_id HAVING COUNT(DISTINCT it.tag_id) = " . count($matching_tag_ids);

	/* get rid of the unread ones */
	$untag_query = "DELETE FROM $FOF_ITEM_TAG_TABLE WHERE user_id = $user_id AND tag_id = $unread_id AND item_id IN ($items_query)";

	$result = $fof_connection->exec($untag_query);

	return $result;
}

function fof_db_mark_unread($user_id, $items) {
	fof_trace();

	$tag_id = fof_db_get_tag_by_name('unread');
	return fof_db_tag_items($user_id, $tag_id, $items);
}

function fof_db_mark_read($user_id, $items) {
	fof_trace();

	$tag_id = fof_db_get_tag_by_name('unread');
	return fof_db_untag_items($user_id, $tag_id, $items);
}

function fof_db_fold($user_id, $items) {
	fof_trace();

	$tag_id = fof_db_get_tag_by_name('folded');
	return fof_db_tag_items($user_id, $tag_id, $items);
}

function fof_db_unfold($user_id, $items) {
	fof_trace();

	$tag_id = fof_db_get_tag_by_name('folded');
	return fof_db_untag_items($user_id, $tag_id, $items);
}

function fof_db_mark_feed_read($user_id, $feed_id) {
	fof_trace();

	$result = fof_db_get_items($user_id, $feed_id, $what = "all");

	foreach ($result as $r) {
		$items[] = $r['item_id'];
	}

	$tag_id = fof_db_get_tag_by_name('unread');
	fof_db_untag_items($user_id, $tag_id, $items);
}

function fof_db_mark_feed_unread($user_id, $feed_id, $what) {
	fof_trace();

	if ($what == 'all') {
		$result = fof_db_get_items($user_id, $feed_id, 'all');
	}
	if ($what == 'today') {
		$result = fof_db_get_items($user_id, $feed_id, 'all', 'today');
	}

	$items = array();
	if ($result) {
		foreach ($result as $r) {
			$items[] = $r['item_id'];
		}
	}

	$tag_id = fof_db_get_tag_by_name('unread');

	fof_db_tag_items($user_id, $tag_id, $items);
}

/* sets unread tag on item_id for each user_id in array of users */
function fof_db_mark_item_unread($users, $item_id) {
	global $FOF_ITEM_TAG_TABLE;
	global $fof_connection;

	fof_trace();

	if (count($users) == 0) {
		return;
	}

	$tag_id = fof_db_get_tag_by_name('unread');

	/*
		This query will need to be changed to work with a driver other than
		MySQL or SQLite.  It simply needs to be able to insert, and ignore
		existing row conflicts.
		Perhaps someone versed in SQL has suggestions.
	*/

	if (defined('USE_SQLITE')) {

		$query = "INSERT OR IGNORE INTO $FOF_ITEM_TAG_TABLE (user_id, tag_id, item_id) VALUES (:user_id, :tag_id, :item_id)";
		$statement = $fof_connection->prepare($query);
		$statement->bindValue(':tag_id', $tag_id);
		$statement->bindValue(':item_id', $item_id);

		$fof_connection->beginTransaction();
		foreach ($users as $user_id) {
			$statement->bindValue(':user_id', $user_id);
			$result = $statement->execute();
			$statement->closeCursor();
		}
		$fof_connection->commit();
		return;
	}

	if (defined('USE_MYSQL')) {
		$values = array();
		$item_id_q = $fof_connection->quote($item_id);
		foreach ($users as $user) {
			$user_id_q = $fof_connection->quote($user);
			$values[] = "( $user_id_q, $tag_id, $item_id_q )";
		}
		$query = "INSERT IGNORE INTO $FOF_ITEM_TAG_TABLE (user_id, tag_id, item_id) VALUES " . implode(', ', $values);
		$result = $fof_connection->exec($query);
		return;
	}
}

/* sets (user_id, tag_id, item_id) for each item_id in items array */
function fof_db_tag_items($user_id, $tag_id, $items) {
	global $FOF_ITEM_TAG_TABLE;
	global $fof_connection;

	fof_trace();

	if (!$items) {
		return;
	}

	if (!is_array($items)) {
		$items = array($items);
	}

	if (empty($items)) {
		return;
	}

	/* This query will need to be changed to work with a driver other than MySQL or SQLite. */
	/* (also see fof_db_mark_item_unread) */

	if (defined('USE_SQLITE')) {
		$query = "INSERT OR IGNORE INTO $FOF_ITEM_TAG_TABLE (user_id, tag_id, item_id) VALUES (:user_id, :tag_id, :item_id)";
		$statement = $fof_connection->prepare($query);
		$statement->bindValue(':user_id', $user_id);
		$statement->bindValue(':tag_id', $tag_id);

		$fof_connection->beginTransaction();
		foreach ($items as $item_id) {
			$statement->bindValue(':item_id', $item_id);
			$result = $statement->execute();
			$statement->closeCursor();
		}
		$fof_connection->commit();
		return;
	}

	if (defined('USE_MYSQL')) {
		$items_q = array();
		$tag_q = $fof_connection->quote($tag_id);
		$user_q = $fof_connection->quote($user_id);
		foreach ($items as $item) {
			$i_q = $fof_connection->quote($item);
			$items_q[] = "($user_q, $tag_q, $i_q)";
		}

		$query = "INSERT IGNORE INTO $FOF_ITEM_TAG_TABLE (user_id, tag_id, item_id) VALUES " . implode(', ', $items_q);
		$result = $fof_connection->exec($query);
		return;
	}
}

function fof_db_untag_items($user_id, $tag_id, $items) {
	global $FOF_ITEM_TAG_TABLE;
	global $fof_connection;

	fof_trace();

	if (!$items) {
		return;
	}

	if (!is_array($items)) {
		$items = array($items);
	}

	$items_q = array();
	foreach ($items as $item) {
		$items_q[] = $fof_connection->quote($item);
	}

	$query = "DELETE FROM $FOF_ITEM_TAG_TABLE WHERE user_id = :user_id AND tag_id = :tag_id AND item_id IN ( " . (count($items_q) ? implode(', ', $items_q) : "''") . " )";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':user_id', $user_id);
	$statement->bindValue(':tag_id', $tag_id);
	$result = $statement->execute();
	$statement->closeCursor();
}

// Remove all occurences of tag_ids from all items for user_id.
function fof_db_untag_user_all($user_id, $tag_ids) {
	global $FOF_ITEM_TAG_TABLE;
	global $fof_connection;

	fof_trace();

	if (!is_array($tag_ids)) {
		$tag_ids = array($tag_ids);
	}

	if (empty($tag_ids)) {
		return false;
	}

	$query = "DELETE FROM $FOF_ITEM_TAG_TABLE WHERE user_id = :user_id AND tag_id IN ("
		. (count($tag_ids) ? implode(', ', $tag_ids) : "''")
		. ")";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':user_id', $user_id);
	$result = $statement->execute();
	$statement->closeCursor();

	return $result;
}

////////////////////////////////////////////////////////////////////////////////
// View stuff
// This facilitates persisting the presentation settings for a collection of
// items generated from a given set of tags.
////////////////////////////////////////////////////////////////////////////////

/** Return a view which matches the set of feeds and tags.
 */
function fof_db_view_get($user_id, $tag_ids, $feed_ids) {
	global $FOF_VIEW_STATE_TABLE, $FOF_VIEW_TABLE;
	global $fof_connection;

	fof_trace();

	if (empty($tag_ids)) {
		$tag_ids = array();
	}

	if (empty($feed_ids)) {
		$feed_ids = array();
	}

	if (!is_array($tag_ids)) {
		$tag_ids = array($tag_ids);
	}

	if (!is_array($feed_ids)) {
		$feed_ids = array($feed_ids);
	}

	/* scrub any unknowns */
	$tag_ids = array_filter($tag_ids);
	$feed_ids = array_filter($feed_ids);

	$constituent_count = count($tag_ids) + count($feed_ids);

	/*
		Well this is more awkward than I want it to be...
		$q1 gets a list of view_ids which match at least all of the criteria,
		but which might also have more set members.
		The main query then winnows through those view_ids for the one with the
		correct number of constituents.
	*/
	/*
		also I guess MySQL doesn't like empty IN clauses, so we have to ensure
		there's always something in there by adding these '-1's which ought
		never actually appear as ids (because autoincrement).
	*/
	$tag_ids[] = '-1';
	$feed_ids[] = '-1';

	$q1 = "SELECT view_id FROM $FOF_VIEW_STATE_TABLE vs WHERE vs.user_id = :user_id AND (vs.tag_id IN (" . implode(',', $tag_ids) . ") OR vs.feed_id IN (" . implode(',', $feed_ids) . ")) GROUP BY vs.view_id HAVING COUNT(vs.view_id) = " . $constituent_count;
	$query = "SELECT vs.view_id, v.view_settings FROM $FOF_VIEW_STATE_TABLE vs JOIN $FOF_VIEW_TABLE v ON v.view_id = vs.view_id WHERE vs.view_id IN (" . $q1 . ") GROUP BY vs.view_id HAVING COUNT(vs.view_id) = " . $constituent_count;

	fof_log(__FUNCTION__ . ' tag_ids: ' . var_export($tag_ids, true));

	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':user_id', $user_id);
	$result = $statement->execute();

	$view_row = fof_db_get_row($statement, NULL, TRUE);

	if (empty($view_row)) {
		$view_row = array('view_id' => null, 'view_settings' => array());
	}

	$view_row['view_settings'] = empty($view_row['view_settings']) ? array() : unserialize($view_row['view_settings']);
	return $view_row;
}

/** Return the view preferences for a given set of sources.
 */
function fof_db_view_settings_get($user_id, $tag_ids, $feed_ids) {
	$view_row = fof_db_view_get($user_id, $tag_ids, $feed_ids);
	return $view_row['view_settings'];
}

/** Return the view id for a given set of sources.
 */
function fof_db_view_id_get($user_id, $tag_ids, $feed_ids) {
	$view_row = fof_db_view_get($user_id, $tag_ids, $feed_ids);
	return $view_row['view_id'];
}

/** Create a new view and return its id.
 */
function fof_db_view_create($settings) {
	global $FOF_VIEW_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "INSERT INTO $FOF_VIEW_TABLE (view_settings) VALUES (:view_settings)";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':view_settings', serialize($settings));
	$result = $statement->execute();

	$view_id = $fof_connection->lastInsertId();

	return $view_id;
}

function fof_db_view_update($view_id, $settings) {
	global $FOF_VIEW_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "UPDATE $FOF_VIEW_TABLE SET view_settings = :view_settings WHERE view_id = :view_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':view_settings', serialize($settings));
	$statement->bindValue(':view_id', $view_id);
	$result = $statement->execute();

	return $result;
}

/*  set the view preferences for a given set of sources */
function fof_db_view_settings_set($user_id, $tag_ids, $feed_ids, $settings) {
	global $FOF_VIEW_STATE_TABLE;
	global $fof_connection;

	fof_trace();

	$fof_connection->beginTransaction();

	/* first check if view exists already */
	$view_id = fof_db_view_id_get($user_id, $tag_ids, $feed_ids);
	if (empty($view_id)) {
		fof_log('Creating new view set: tag_ids(' . implode(',', $tag_ids) . ') feed_ids(' . implode(',', $feed_ids) . ')');

		/* no existing view, so create a new one */
		$view_id = fof_db_view_create($settings);

		/* and map the sources-state to it */
		$query = "INSERT INTO $FOF_VIEW_STATE_TABLE (user_id, view_id, tag_id) VALUES (:user_id, :view_id, :tag_id)";
		$statement = $fof_connection->prepare($query);
		foreach ($tag_ids as $tag_id) {
			$statement->bindValue(':user_id', $user_id);
			$statement->bindValue(':view_id', $view_id);
			$statement->bindValue(':tag_id', $tag_id);
			$result = $statement->execute();
		}

		$query = "INSERT INTO $FOF_VIEW_STATE_TABLE (user_id, view_id, feed_id) VALUES (:user_id, :view_id, :feed_id)";
		$statement = $fof_connection->prepare($query);
		foreach ($feed_ids as $feed_id) {
			$statement->bindValue(':user_id', $user_id);
			$statement->bindValue(':view_id', $view_id);
			$statement->bindValue(':feed_id', $feed_id);
			$result = $statement->execute();
		}
	} else {
		fof_db_view_update($view_id, $settings);
	}

	$fof_connection->commit();
}

/* expunge a source-state view */
function fof_db_view_expunge($user_id, $tag_ids, $feed_ids) {
	global $FOF_VIEW_STATE_TABLE, $FOF_VIEW_TABLE;
	global $fof_connection;

	fof_trace();

	$view_id = fof_db_view_id_get($user_id, $tag_ids, $feed_ids);
	if (!empty($view_id)) {
		$fof_connection->beginTransaction();

		$query = "DELETE FROM $FOF_VIEW_STATE_TABLE WHERE view_id = :view_id";
		$statement = $fof_connection->prepare($query);
		$statement->bindValue(':view_id', $view_id);
		$result = $statement->execute();

		$query = "DELETE FROM $FOF_VIEW_TABLE WHERE view_id = :view_id";
		$statement = $fof_connection->prepare($query);
		$statement->bindValue(':view_id', $view_id);
		$result = $statement->execute();

		$fof_connection->commit();
	}
}

////////////////////////////////////////////////////////////////////////////////
// User stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_user_password_hash($password, $user) {
	return md5($password . $user);
}

/* returns array of user info, keyed by user_id */
function fof_db_get_users() {
	global $FOF_USER_TABLE;
	global $fof_connection;
	$users = array();

	fof_trace();

	$query = "SELECT user_name, user_id, user_prefs FROM $FOF_USER_TABLE";
	$statement = $fof_connection->query($query);
	while (($row = fof_db_get_row($statement)) !== false) {
		$users[$row['user_id']]['user_name'] = $row['user_name'];
		$users[$row['user_id']]['user_prefs'] = unserialize($row['user_prefs']);
	}

	return $users;
}

/* FIXME: check user_level for this */
function fof_db_get_nonadmin_usernames() {
	global $FOF_USER_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "SELECT user_name FROM $FOF_USER_TABLE WHERE user_id > 1";
	$statement = $fof_connection->query($query);

	return $statement;
}

/* only used during install */
function fof_db_add_user_all($user_id, $user_name, $user_password, $user_level) {
	global $FOF_USER_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "INSERT INTO $FOF_USER_TABLE (user_id, user_name, user_password_hash, user_level) VALUES (:user_id, :user_name, :user_password_hash, :user_level)";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':user_id', $user_id);
	$statement->bindValue(':user_name', $user_name);
	$statement->bindValue(':user_password_hash', fof_db_user_password_hash($user_password, $user_name));
	$statement->bindValue(':user_level', $user_level);
	$result = $statement->execute();
	$statement->closeCursor();

	return $result;
}

function fof_db_add_user($username, $password) {
	global $FOF_USER_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "INSERT INTO $FOF_USER_TABLE (user_name, user_password_hash) VALUES (:user_name, :user_password_hash)";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':user_password_hash', fof_db_user_password_hash($password, $username));
	$statement->bindValue(':user_name', $username);
	$result = $statement->execute();
	$statement->closeCursor();

	return $result;
}

function fof_db_change_password($username, $password) {
	global $FOF_USER_TABLE;
	global $fof_connection;

	$query = "UPDATE $FOF_USER_TABLE SET user_password_hash = :user_password_hash WHERE user_name = :user_name";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':user_password_hash', fof_db_user_password_hash($password, $username));
	$statement->bindValue(':user_name', $username);
	$result = $statement->execute();
	$statement->closeCursor();

	return $result;
}

function fof_db_get_user($username) {
	global $FOF_USER_TABLE;
	global $fof_connection;

	$query = "SELECT * FROM $FOF_USER_TABLE WHERE user_name = :user_name";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':user_name', $username);
	$result = $statement->execute();
	return fof_db_get_row($statement, NULL, TRUE);
}

function fof_db_get_user_id($username) {
	global $FOF_USER_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "SELECT user_id FROM $FOF_USER_TABLE WHERE user_name = :user_name";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':user_name', $username);
	$result = $statement->execute();

	return fof_db_get_row($statement, 'user_id', TRUE);
}

function fof_db_delete_user($username) {
	global $FOF_USER_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE;
	global $fof_connection;

	fof_trace();

	$user_id = fof_db_get_user_id($username);

	$tables = array($FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_USER_TABLE);
	foreach ($tables as $table) {
		$query = "DELETE FROM $table WHERE user_id = :user_id";
		$statement = $fof_connection->prepare($query);
		$statement->bindValue(':user_id', $user_id);
		$result = $statement->execute();
		$statement->closeCursor();
	}
}

function fof_db_prefs_get($user_id) {
	global $FOF_USER_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "SELECT user_prefs FROM $FOF_USER_TABLE WHERE user_id = :user_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':user_id', $user_id);
	$result = $statement->execute();
	$prefs = fof_db_get_row($statement, 'user_prefs', TRUE);

	return unserialize($prefs);
}

function fof_db_save_prefs($user_id, $prefs) {
	global $FOF_USER_TABLE;
	global $fof_connection;

	fof_trace();

	$query = "UPDATE $FOF_USER_TABLE SET user_prefs = :user_prefs WHERE user_id = :user_id";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':user_id', $user_id);
	$statement->bindValue(':user_prefs', serialize($prefs));
	$result = $statement->execute();
	$statement->closeCursor();
}

/* check user and password hash, set global user info if matching record found */
function fof_db_authenticate_hash($user_name, $user_password_hash) {
	global $FOF_USER_TABLE;
	global $fof_connection, $fof_user_id, $fof_user_name, $fof_user_level;

	fof_trace();

	$query = "SELECT * FROM $FOF_USER_TABLE WHERE user_name = :user_name AND user_password_hash = :user_password_hash";
	$statement = $fof_connection->prepare($query);
	$statement->bindValue(':user_name', $user_name);
	$statement->bindValue(':user_password_hash', $user_password_hash);
	$result = $statement->execute();
	$row = fof_db_get_row($statement, NULL, TRUE);

	if (!$row) {
		$fof_user_id = NULL;
		$fof_user_name = NULL;
		$fof_user_level = NULL;

		fof_log("u:'$user_name' uph:'$user_password_hash' FAIL", 'auth');

		return false;
	}

	$fof_user_id = $row['user_id'];
	$fof_user_name = $row['user_name'];
	$fof_user_level = $row['user_level'];

	fof_log("u:'$user_name' uph:'$user_password_hash' OK ui:'$fof_user_id' un:'$fof_user_name' ul:'$fof_user_level'", 'auth');

	return true;
}

?>
