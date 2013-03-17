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

$FOF_FEED_TABLE = FOF_FEED_TABLE;
$FOF_ITEM_TABLE = FOF_ITEM_TABLE;
$FOF_ITEM_TAG_TABLE = FOF_ITEM_TAG_TABLE;
$FOF_SUBSCRIPTION_TABLE = FOF_SUBSCRIPTION_TABLE;
$FOF_TAG_TABLE = FOF_TAG_TABLE;
$FOF_USER_TABLE = FOF_USER_TABLE;

if (defined('USE_SQLITE')) {
    /* sqlite uses an additional table */
    define('FOF_USER_LEVELS_TABLE', FOF_USER_TABLE . "_levels");
    $FOF_USER_LEVELS_TABLE = FOF_USER_LEVELS_TABLE;
}

/*
    Non-local function dependencies:
    fof_prefs();
    fof_log();
    fof_todays_date();
    fof_multi_sort();

    Non-local variable dependencies:
    $fof_connection;
    $fof_user_id;
    $fof_user_name;
    $fof_user_level;
*/

////////////////////////////////////////////////////////////////////////////////
// Utilities
////////////////////////////////////////////////////////////////////////////////

/* yeah, I dunno, just throwing some logging in here */
function fof_pdoexception_log_($f, $e, $msg) {
    fof_log($f . ': ' . $msg . ': ' . $e->GetMessage(), 'error');
    throw new Exception($msg, 0, $previous=$e);
}

/* set up a db connection, creating the db if it doesn't exist */
function fof_db_connect()
{
    global $FOF_DB_HOST, $FOF_DB_NAME, $FOF_DB_USER, $FOF_DB_PASS;
    global $fof_connection;

    $pdo_options = array( PDO::ATTR_EMULATE_PREPARES => false,
                          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION );
    try {
        if (defined('USE_MYSQL')) {
            $dsn = "mysql:host=$FOF_DB_HOST;charset=utf8";
        } else if (defined('USE_SQLITE')) {
            global $FOF_DB_SQLITE_PATH;

            $sqlite_db = FOF_DB_SQLITE_PATH . DIRECTORY_SEPARATOR . FOF_DB_DBNAME;
            $dsn = "sqlite:$sqlite_db";
        }
        else
        {
            die('missing implementation for this driver');
        }

        fof_log("Connecting to '$dsn'...");

        $fof_connection = new PDO($dsn, $FOF_DB_USER, $FOF_DB_PASS, $pdo_options);

        if (defined('USE_MYSQL'))
        {
            $fof_connection->exec("CREATE DATABASE IF NOT EXISTS $FOF_DB_DBNAME");
            $fof_connection->exec("USE $FOF_DB_DBNAME");
        }
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "could not establish database connection");
    }
}

function fof_db_get_row($statement, $key=NULL, $nomore=FALSE)
{
    if (($row = $statement->fetch(PDO::FETCH_ASSOC)) === FALSE)
        return FALSE;

    if ($nomore)
        $statement->closeCursor();

    if (isset($key)) {
        return (isset($row[$key]) ? $row[$key] : NULL);
    }

    return $row;
}

/* invoke callable first argument with remaining arguments, returns array of result and elapsed time */
function fof_db_wrap_elapsed_(/* callable, args... */)
{
    $arg_list = func_get_args();
    $fn = array_shift($arg_list);

    list($usec, $sec) = explode(" ", microtime());
    $t_start = (float)$sec + (float)$usec;

    $result = call_user_func_array($fn, $arg_list);

    list($usec, $sec) = explode(" ", microtime());
    $t_finish = (float)$sec + (float)$usec;
    $elapsed = $t_finish - $t_start;

    return array($result, $elapsed);
}

function fof_db_log_query_caller_($caller_frame, $query, $elapsed, $rows=null)
{
    $msg = sprintf("%s:%s [%s] %.3f", basename($caller_frame['file']), $caller_frame['function'], $query, $elapsed);
    if ($rows) {
        $msg .= sprintf(" (%d rows affected)", $rows);
    }

    fof_log($msg, "query");
}

/* Wraps a prepared statement execution to gather elapsed query time, and log such.
   It'd be nicer if we could get the query from the statement, rather
   than having to pass it along for logging here
 */
function fof_db_statement_execute($query, $statement, $input_parameters = NULL)
{
    $bt = debug_backtrace();

    $callable = array($statement, 'execute');
    list($result, $elapsed) = fof_db_wrap_elapsed_($callable, $input_parameters);

    fof_db_log_query_caller_($bt[1], $query, $elapsed, $statement->rowCount());

    return $result;
}

function fof_db_statement_prepare($query)
{
    global $fof_connection;

    $statement = $fof_connection->prepare($query);

    return $statement;
}

function fof_db_query($query)
{
    global $fof_connection;

    $bt = debug_backtrace();

    $callable = array($fof_connection, 'query');
    list($statement, $elapsed) = fof_db_wrap_elapsed_($callable, $query);

    fof_db_log_query_caller_($bt[1], $query, $elapsed, $statement->rowCount());

    return $statement;
}

function fof_db_exec($query)
{
    global $fof_connection;

    $bt = debug_backtrace();

    $callable = array($fof_connection, 'exec');
    list($rows_affected, $elapsed) = fof_db_wrap_elapsed_($callable, $query);

    fof_db_log_query_caller_($bt[1], $query, $elapsed, $rows_affected);

    return $rows_affected;
}

function fof_db_optimize()
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_TAG_TABLE, $FOF_USER_TABLE;
    global $fof_connection;

    if (defined('USE_MYSQL')) {
        $query = "OPTIMIZE TABLE $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_TAG_TABLE, $FOF_USER_TABLE";
    } else if (defined('USE_SQLITE')) {
        $query = "VACUUM";
    } else {
        throw new Exception("missing implementation");
    }

    try {
        $result = fof_db_exec($query);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "could not optimize database");
    }
}

////////////////////////////////////////////////////////////////////////////////
// Feed level stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_feed_update_prefs($feed_id, $title, $alt_image)
{
    global $FOF_FEED_TABLE;
    global $fof_connection;

    $query = "UPDATE $FOF_FEED_TABLE SET feed_title = :title, alt_image = :alt_image, feed_image_cache_date = :cache_date WHERE feed_id = :feed_id";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':title', $title);
        $statement->bindValue(':alt_image', $alt_image);
        $statement->bindValue(':cache_date', 1);
        $statement->bindValue(':feed_id', $feed_id);
        $result = fof_db_statement_execute($query, $statement);
        $statement->closeCursor();
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "could not update feed (feed_id='$feed_id'");
    }

    return $result;
}

function fof_db_feed_mark_cached($feed_id)
{
    global $FOF_FEED_TABLE;
    global $fof_connection;

    $query = "UPDATE $FOF_FEED_TABLE SET feed_cache_date = :cache_date WHERE feed_id = :feed_id";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':cache_date', time());
        $statement->bindValue(':feed_id', $feed_id);
        $result = fof_db_statement_execute($query, $statement);
        $statement->closeCursor();
    } catch(PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "could not mark feed as cached (feed_id='$feed_id')");
    }

    return $result;
}

function fof_db_feed_mark_attempted_cache($feed_id)
{
    global $FOF_FEED_TABLE;
    global $fof_connection;

    $query = "UPDATE $FOF_FEED_TABLE SET feed_cache_attempt_date = :cache_date WHERE feed_id = :feed_id";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':cache_date', time());
        $statement->bindValue(':feed_id', $feed_id);
        $result = fof_db_statement_execute($query, $statement);
        $statement->closeCursor();
    } catch(PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "could not mark feed as attempted-to-be-cached (feed_id='$feed_id')");
    }

    return $result;
}

function fof_db_feed_update_metadata($feed_id, $title, $link, $description, $image, $image_cache_date)
{
    global $FOF_FEED_TABLE;
    global $fof_connection;

    $query = "UPDATE $FOF_FEED_TABLE SET feed_link = :link, feed_description = :description, feed_image = :image, feed_image_cache_date = :image_cache_date WHERE feed_id = :id";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':link', $link);
        $statement->bindValue(':description', $description);
        if ( ! empty($image)) {
            $statement->bindValue(':image', $image);
        } else {
            $statement->bindValue(':image', null, PDO::PARAM_NULL);
        }
        $statement->bindValue(':image_cache_date', time());
        $statement->bindValue(':id', $feed_id);
        $result = fof_db_statement_execute($query, $statement);
        $statement->closeCursor();
    } catch(PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "could not set feed metadata (feed_id='$feed_id')");
    }
}

/* XXX: user_id is unused */
function fof_db_get_latest_item_age($user_id)
{
    global $FOF_ITEM_TABLE;
    global $fof_connection;

    $query = "SELECT max(item_cached) AS max_date, $FOF_ITEM_TABLE.feed_id as id FROM $FOF_ITEM_TABLE GROUP BY $FOF_ITEM_TABLE.feed_id";
    try {
        $statement = fof_db_query($query);
    } catch(PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "could not get latest item age");
    }

    return $statement;
}

function fof_db_get_subscriptions($user_id)
{
    global $FOF_FEED_TABLE, $FOF_SUBSCRIPTION_TABLE;
    global $fof_connection;

    $query = "SELECT * FROM $FOF_FEED_TABLE, $FOF_SUBSCRIPTION_TABLE WHERE $FOF_SUBSCRIPTION_TABLE.user_id = :user_id AND $FOF_FEED_TABLE.feed_id = $FOF_SUBSCRIPTION_TABLE.feed_id ORDER BY feed_title";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':user_id', $user_id);
        $result = fof_db_statement_execute($query, $statement);
    } catch(PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "could not get subscriptions (user_id='$user_id')");
    }

    return $statement;
}

function fof_db_get_feeds_needing_attempt()
{
    global $FOF_FEED_TABLE;
    global $fof_connection;

    $query = "SELECT * FROM $FOF_FEED_TABLE WHERE feed_cache_next_attempt < :now ORDER BY feed_title";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':now', time());
        $result = fof_db_statement_execute($query, $statement);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get feeds needing cache attempts");
    }

    return $statement;
}

function fof_db_get_item_count ( $user_id, $what = 'all', $feed = null, $search = null )
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
    global $fof_connection;

    $query = "SELECT COUNT(*) AS count," .
                   " $FOF_ITEM_TABLE.feed_id AS id" .
                   " FROM $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_FEED_TABLE";

    if ($what != 'all') {
        $query .= ", $FOF_ITEM_TAG_TABLE";
    }

    $query .= " WHERE $FOF_SUBSCRIPTION_TABLE.user_id = :user_id" .
                    " AND $FOF_ITEM_TABLE.feed_id = $FOF_SUBSCRIPTION_TABLE.feed_id" .
                    " AND $FOF_FEED_TABLE.feed_id = $FOF_ITEM_TABLE.feed_id";

    if ($what != 'all') {
        $query .= " AND $FOF_ITEM_TABLE.item_id = $FOF_ITEM_TAG_TABLE.item_id" .
                  " AND $FOF_ITEM_TAG_TABLE.user_id = :user_id" .
                  " AND $FOF_FEED_TABLE.feed_id = $FOF_SUBSCRIPTION_TABLE.feed_id";
    }

    switch ($what)
    {
        case 'all':
        break;

        case 'unread':
        $query .= " AND $FOF_ITEM_TAG_TABLE.tag_id = 1";
        break;

        case 'starred':
        $query .= " AND $FOF_ITEM_TAG_TABLE.tag_id = 2";
        break;

        case 'tagged':
        $query .= " AND $FOF_ITEM_TAG_TABLE.tag_id != 1" .
                  " AND $FOF_ITEM_TAG_TABLE.tag_id != 2" .
                  " AND $FOF_ITEM_TAG_TABLE.tag_id = :folded_id";
        break;

        default:
        $tag_ids_a = array();
        foreach(explode(',', fof_db_get_tag_by_name($user_id, $what)) as $t) {
            $tag_ids_a[] = $fof_connection->quote($t);
        }
        $query .= " AND $FOF_ITEM_TAG_TABLE.tag_id IN (" . implode(', ', $tag_ids_a) . ")";
    }

    if ( ! empty($search)) {
        $query .= " AND ($FOF_ITEM_TABLE.item_title LIKE :search OR $FOF_ITEM_TABLE.item_content LIKE :search )";
    }

    $query .= " GROUP BY id";

    if ( ! empty($feed)) {
        $query .= " HAVING id = :feed";
    }

    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':user_id', $user_id);
        if ( ! empty($search)) {
            $statement->bindValue(':search', '%' . $search . '%');
        }
        if ( ! empty($feed)) {
            $statement->bindValue(':feed', $feed);
        }
        switch ($what) {
            case 'all':
            case 'unread':
            case 'starred':
            break;

            case 'tagged':
            $statement->bindValue(':folded_id', fof_db_get_tag_by_name($user_id, 'folded'));
            break;

            default:
        }
        $result = fof_db_statement_execute($query, $statement);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get feeds (user_id='$user_id')");
    }

    return $statement;
}

function fof_db_get_subscribed_users($feed_id)
{
    global $FOF_SUBSCRIPTION_TABLE;
    global $fof_connection;

    $query = "SELECT user_id FROM $FOF_SUBSCRIPTION_TABLE WHERE $FOF_SUBSCRIPTION_TABLE.feed_id = :feed_id";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':feed_id', $feed_id);
        $result = fof_db_statement_execute($query, $statement);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get subscribed users for feed (feed_id='$feed_id')");
    }
    return $statement;
}

function fof_db_get_subscribed_users_count($feed_id)
{
    $subscribed_users = fof_db_get_subscribed_users($feed_id)->fetchAll();
    return count($subscribed_users);
}

function fof_db_is_subscribed($user_id, $feed_url)
{
    global $FOF_FEED_TABLE, $FOF_SUBSCRIPTION_TABLE;
    global $fof_connection;

    $query = "SELECT $FOF_SUBSCRIPTION_TABLE.feed_id FROM $FOF_FEED_TABLE, $FOF_SUBSCRIPTION_TABLE WHERE feed_url = :feed_url AND $FOF_SUBSCRIPTION_TABLE.feed_id = $FOF_FEED_TABLE.feed_id AND $FOF_SUBSCRIPTION_TABLE.user_id = :user_id;";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':feed_url', $feed_url);
        $statement->bindValue(':user_id', $user_id);
        $result = fof_db_statement_execute($query, $statement);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot correlate user-to-feed (user_id='$user_id' feed_url='$feed_url')");
    }
    $row = fof_db_get_row($statement, NULL, TRUE);
    if ( ! empty($row)) {
        return true;
    }

    return false;
}

function fof_db_get_feed_by_url($feed_url)
{
    global $FOF_FEED_TABLE;
    global $fof_connection;

    $query = "SELECT * FROM $FOF_FEED_TABLE WHERE feed_url = :feed_url";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':feed_url', $feed_url);
        $result = fof_db_statement_execute($query, $statement);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get feed (feed_url='$feed_url')");
    }

    return fof_db_get_row($statement, NULL, TRUE);
}

function fof_db_get_feed_by_id($feed_id)
{
    global $FOF_FEED_TABLE;
    global $fof_connection;

    $query = "SELECT * FROM $FOF_FEED_TABLE WHERE feed_id = :feed_id";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':feed_id', $feed_id);
        $result = fof_db_statement_execute($query, $statement);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get feed (feed_id='$feed_id')");
    }

    return fof_db_get_row($statement, NULL, TRUE);
}

function fof_db_add_feed($url, $title, $link, $description)
{
    global $FOF_FEED_TABLE;
    global $fof_connection;

    $query = "INSERT INTO $FOF_FEED_TABLE (feed_url, feed_title, feed_link, feed_description) values (:url, :title, :link, :description)";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':url', $url);
        $statement->bindValue(':title', $title);
        $statement->bindValue(':link', $link);
        $statement->bindValue(':description', $description);
        $result = fof_db_statement_execute($query, $statement);
        $statement->closeCursor();
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot add feed (feed_url='$url')");
    }
    $query = "SELECT feed_id FROM $FOF_FEED_TABLE WHERE feed_url = :url AND feed_title = :title AND feed_link = :link AND feed_description = :description";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':url', $url);
        $statement->bindValue(':title', $title);
        $statement->bindValue(':link', $link);
        $statement->bindValue(':description', $description);
        $result = fof_db_statement_execute($query, $statement);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get new feed id (feed_url='$url')");
    }

    return fof_db_get_row($statement, 'feed_id', TRUE);
}

function fof_db_add_subscription($user_id, $feed_id)
{
    global $FOF_SUBSCRIPTION_TABLE;
    global $fof_connection;

    $query = "INSERT INTO $FOF_SUBSCRIPTION_TABLE (feed_id, user_id) values (:feed_id, :user_id)";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':feed_id', $feed_id);
        $statement->bindValue(':user_id', $user_id);
        $result = fof_db_statement_execute($query, $statement);
        $statement->closeCursor();
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot add subscription (user_id='$user_id' feed_id='$feed_id')");
    }
}

function fof_db_delete_subscription($user_id, $feed_id)
{
    global $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
    global $fof_connection;

    $all_items = fof_db_get_items($user_id, $feed_id, "all");
    $items = array();
    foreach($all_items as $i) {
        $items[] = $fof_connection->quote($i['item_id']);
    }

    if (count($items) > 0) {
        $query = "DELETE FROM $FOF_ITEM_TAG_TABLE WHERE user_id = :user_id AND item_id IN (" . implode(', ', $items) . ")";
        try {
            $statement = $fof_connection->prepare($query);
            $statement->bindValue(':user_id', $user_id);
            $result = fof_db_statement_execute($query, $statement);
            $statement->closeCursor();
        } catch (PDOException $e) {
            fof_pdoexception_log_(__FUNCTION__, $e, "cannot delete items for feed (user_id='$user_id' feed_id='$feed_id')");
        }
    }

    $query = "DELETE FROM $FOF_SUBSCRIPTION_TABLE WHERE feed_id = :feed_id and user_id = :user_id";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':user_id', $user_id);
        $statement->bindValue(':feed_id', $feed_id);
        $result = fof_db_statement_execute($query, $statement);
        $statement->closeCursor();
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot delete subscriptions for feed (user_id='$user_id' feed_id='$feed_id')");
    }
}

function fof_db_delete_feed($feed_id)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE;
    global $fof_connection;

    $query = "DELETE FROM $FOF_FEED_TABLE WHERE feed_id = :feed_id";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':feed_id', $feed_id);
        $result = fof_db_statement_execute($query, $statement);
        $statement->closeCursor();
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot delete feed (feed_id='$feed_id')");
    }

    $query = "DELETE FROM $FOF_ITEM_TABLE WHERE feed_id = :feed_id";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':feed_id', $feed_id);
        $result = fof_db_statement_execute($query, $statement);
        $statement->closeCursor();
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot delete items for feed (feed_id='$feed_id')");
    }
}

function fof_db_feed_cache_set($feed_id, $next_attempt)
{
    global $FOF_FEED_TABLE;
    global $fof_connection;

    $query = "UPDATE $FOF_FEED_TABLE SET feed_cache_next_attempt = :next_attempt WHERE feed_id = :id";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(":feed_id", $feed_id);
        $statement->bindValue(":next_attempt", $next_attempt);
        $result = fof_db_statement_execute($query, $statement);
        $statement->closeCursor();
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot set feed cache next attempt (feed_id='$feed_id')");
    }
}

////////////////////////////////////////////////////////////////////////////////
// Item level stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_find_item($feed_id, $item_guid)
{
    global $FOF_ITEM_TABLE;
    global $fof_connection;

    $query = "SELECT item_id FROM $FOF_ITEM_TABLE WHERE feed_id = :feed_id and item_guid = :item_guid";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(":feed_id", $feed_id);
        $statement->bindValue(":item_guid", $item_guid);
        $result = fof_db_statement_execute($query, $statement);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get item id (feed_id='$feed_id' item_guid='$item_guid')");
    }

    return fof_db_get_row($statement, 'item_id', TRUE);
}

function fof_db_add_item($feed_id, $guid, $link, $title, $content, $cached, $published, $updated)
{
    global $FOF_ITEM_TABLE;
    global $fof_connection;

    $query = "INSERT INTO $FOF_ITEM_TABLE (feed_id, item_link, item_guid, item_title, item_content, item_cached, item_published, item_updated) VALUES (:feed_id, :link, :guid, :title, :content, :cached, :published, :updated)";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':feed_id', $feed_id);
        $statement->bindValue(':link', $link);
        $statement->bindValue(':guid', $guid);
        $statement->bindValue(':title', $title);
        $statement->bindValue(':content', $content);
        $statement->bindValue(':cached', $cached);
        $statement->bindValue(':published', $published);
        $statement->bindValue(':updated', $updated);
        $result = fof_db_statement_execute($query, $statement);
        $statement->closeCursor();
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot add item (feed_id='$feed_id' item_guid='$guid)");
    }

    $query = "SELECT item_id FROM $FOF_ITEM_TABLE WHERE feed_id = :feed_id AND item_link = :link AND item_guid = :guid AND item_title = :title AND item_content = :content AND item_cached = :cached AND item_published = :published AND item_updated = :updated";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':feed_id', $feed_id);
        $statement->bindValue(':link', $link);
        $statement->bindValue(':guid', $guid);
        $statement->bindValue(':title', $title);
        $statement->bindValue(':content', $content);
        $statement->bindValue(':cached', $cached);
        $statement->bindValue(':published', $published);
        $statement->bindValue(':updated', $updated);
        $result = fof_db_statement_execute($query, $statement);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get added item id (feed_id='$feed_id' item_guid='$guid')");
    }

    return fof_db_get_row($statement, 'item_id', TRUE);
}

function fof_db_get_items($user_id=1, $feed=NULL, $what="unread", $when=NULL, $start=NULL, $limit=NULL, $order="desc", $search=NULL)
{
    global $FOF_SUBSCRIPTION_TABLE, $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_TAG_TABLE;
    global $fof_connection;
    $all_items = array();

    $prefs = fof_prefs();
    $offset = isset($prefs['tzoffset']) ? $prefs['tzoffest'] : NULL;

    if ( ! empty($when))
    {
        $whendate = explode("/", ($when == 'today') ? fof_todays_date() : $when);
        $begin = gmmktime(0, 0, 0, $whendate[1], $whendate[2], $whendate[0]) - ($offset * 60 * 60);
        $end = $begin + (24 * 60 * 60);
    }

    $select = "SELECT i.* , f.* ";
    $from = "FROM $FOF_FEED_TABLE f, $FOF_ITEM_TABLE i, $FOF_SUBSCRIPTION_TABLE s ";
    $where = "WHERE s.user_id = :user_id AND s.feed_id = f.feed_id AND f.feed_id = i.feed_id ";
    $group = "";

    if ( ! empty($feed)) {
        $where .= "AND f.feed_id = :feed_id ";
    }

    if ( ! empty($when)) {
        $where .= "AND i.item_published > :begin AND i.item_published < :end ";
    }

    if ($what != 'all')
    {
        $tags_q = array();
        foreach(explode(' ', $what) as $t) {
            $tags_q[] = $fof_connection->quote($t);
        }

        $from .= ", $FOF_TAG_TABLE t, $FOF_ITEM_TAG_TABLE it ";
        $where .= "AND it.user_id = :user_id ";
        $where .= "AND it.tag_id = t.tag_id AND ( t.tag_name IN ( " . implode(', ', $tags_q) . " ) ) AND i.item_id = it.item_id ";
        $group = "GROUP BY i.item_id HAVING COUNT( i.item_id ) = :tag_count ";
    }

    if ( ! empty($search)) {
        $where .= "AND (i.item_title LIKE :search OR i.item_content LIKE :search )";
    }

    $order_by = "ORDER BY i.item_published DESC ";
    if (is_numeric($start)) {
        $order_by .= " LIMIT $start, " . (is_numeric($limit)) ? $limit : $prefs['howmany'];
    }

    $query = $select . $from . $where . $group . $order_by;

    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':user_id', $user_id);
        if ( ! empty($feed)) {
            $statement->bindValue(':feed_id', $feed);
        }
        if ( ! empty($when)) {
            $statement->bindValue(':begin', $begin);
            $statement->bindValue(':end', $end);
        }
        if ($what != 'all') {
            $statement->bindValue(':tag_count', count($tags_q));
        }
        if ( ! empty($search)) {
            $statement->bindValue(':search', $search);
        }
        $result = fof_db_statement_execute($query, $statement);
        $all_items = fof_multi_sort($statement->fetchAll(), 'item_published', $order != "asc");
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get items (user_id='$user_id' feed_id='$feed_id')");
    }

    $ids_q = array();
    $idx = 0;
    foreach($all_items as $item)
    {
        $ids_q[] = $fof_connection->quote($item['item_id']);
        $lookup[$item['item_id']] = $idx;
        $all_items[$idx]['tags'] = array();
        $idx += 1;
    }

    $query = "SELECT $FOF_TAG_TABLE.tag_name, $FOF_ITEM_TAG_TABLE.item_id" .
            " FROM $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE" .
            " WHERE $FOF_TAG_TABLE.tag_id = $FOF_ITEM_TAG_TABLE.tag_id " .
                " AND $FOF_ITEM_TAG_TABLE.item_id IN ( " . implode(', ', $ids_q) . " )" .
                " AND $FOF_ITEM_TAG_TABLE.user_id = :user_id";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':user_id', $user_id);
        $result = fof_db_statement_execute($query, $statement);
        while (($row = fof_db_get_row($statement)) !== false) {
            $all_items[$lookup[$row['item_id']]]['tags'][] = $row['tag_name'];
        }
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get item tags feed (user_id='$user_id' item_ids:" . implode(',', $ids_q). ")");
    }

    return $all_items;
}

function fof_db_get_item($user_id, $item_id)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_TAG_TABLE;
    global $fof_connection;
    $item = array();

    $query = "SELECT $FOF_FEED_TABLE.feed_image AS feed_image," .
                   " $FOF_FEED_TABLE.feed_title AS feed_title," .
                   " $FOF_FEED_TABLE.feed_link AS feed_link," .
                   " $FOF_FEED_TABLE.feed_description AS feed_description," .
                   " $FOF_ITEM_TABLE.item_id AS item_id," .
                   " $FOF_ITEM_TABLE.item_link AS item_link," .
                   " $FOF_ITEM_TABLE.item_title AS item_title," .
                   " $FOF_ITEM_TABLE.item_cached," .
                   " $FOF_ITEM_TABLE.item_published," .
                   " $FOF_ITEM_TABLE.item_updated," .
                   " $FOF_ITEM_TABLE.item_content AS item_content" .
            " FROM $FOF_FEED_TABLE, $FOF_ITEM_TABLE" .
            " WHERE $FOF_ITEM_TABLE.feed_id = $FOF_FEED_TABLE.feed_id" .
                " AND $FOF_ITEM_TABLE.item_id = :item_id";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':item_id', $item_id);
        $result = fof_db_statement_execute($query, $statement);
        $item = fof_db_get_row($statement, NULL, TRUE);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get item (item_id='$item_id')");
    }

    $item['tags'] = array();

    if ($user_id) {
        $query = "SELECT $FOF_TAG_TABLE.tag_name".
                " FROM $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE" .
                " WHERE $FOF_TAG_TABLE.tag_id = $FOF_ITEM_TAG_TABLE.tag_id" .
                    " AND $FOF_ITEM_TAG_TABLE.item_id = :item_id" .
                    " AND $FOF_ITEM_TAG_TABLE.user_id = :user_id";
        try {
            $statement = $fof_connection->prepare($query);
            $statement->bindValue(':item_id', $item_id);
            $statement->bindValue(':user_id', $user_id);
            $result = fof_db_statement_execute($query, $statement);
            while (($row = fof_db_get_row($statement)) !== false) {
                $item['tags'][] = $row['tag_name'];
            }
        } catch (PDOException $e) {
            fof_pdoexception_log_(__FUNCTION__, $e, "cannot get item tags for user (item_id='$item_id' user_id='$user_id)");
        }
    }

    return $item;
}

function fof_db_items_purge_list($feed_id, $purge_days)
{
    global $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE;
    global $fof_connection;

    $purge_secs = $purge_days * 24 * 60 * 60;
    $query = "SELECT i.item_id FROM $FOF_ITEM_TABLE i" .
            " LEFT JOIN $FOF_ITEM_TAG_TABLE t ON i.item_id=t.item_id" .
            " WHERE tag_id IS NULL" .
                " AND feed_id = :feed_id" .
                " AND i.item_cached <= :purge_time";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(":feed_id", $feed_id);
        $statement->bindValue(":purge_time", time() - $purge_secs);
        $result = fof_db_statement_execute($query, $statement);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get purge list (feed_id='$feed_id')");
    }
    return $statement;
}

function fof_db_items_delete($items)
{
    global $FOF_ITEM_TABLE;
    global $fof_connection;

    if ( ! $items)
        return;

    if ( ! is_array($items))
        $items = array($items);

    $items_q = array();
    foreach($items as $item) {
        $items_q[] = $fof_connection->quote($item);
    }

    $query = "DELETE FROM $FOF_ITEM_TABLE WHERE item_id IN (" . implode(', ', $items_q) . ")";
    try {
        $statement = $fof_connection->prepare($query);
        $result = fof_db_statement_execute($query, $statement);
        $statement->closeCursor();
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot delete items (items=" . implode(',', $items_q). ")");
    }
}

function fof_db_items_duplicate_list()
{
    global $FOF_ITEM_TABLE;
    global $fof_connection;

    $query = "SELECT i2.item_id, i1.item_content AS c1," .
                   " i2.item_content AS c2" .
                   " FROM $FOF_ITEM_TABLE i1" .
            " LEFT JOIN $FOF_ITEM_TABLE i2" .
            " ON i1.item_title=i2.item_title AND i1.feed_id=i2.feed_id" .
            " WHERE i1.item_id < i2.item_id";
    try {
        $statement = fof_db_query($query);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get duplicate items");
    }

    return $statement;
}

function fof_db_items_updated_list($feed_id)
{
    global $FOF_ITEM_TABLE;
    global $fof_connection;

    $query = "SELECT item_updated FROM $FOF_ITEM_TABLE WHERE feed_id = :id ORDER BY item_updated ASC";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':id', $feed_id);
        $result = fof_db_statement_execute($query, $statement);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get updated items (feed_id='$feed_id')");
    }

    return $statement;
}

////////////////////////////////////////////////////////////////////////////////
// Tag stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_tag_delete($items)
{
    global $FOF_ITEM_TAG_TABLE;
    global $fof_connection;

    if ( ! $items)
        return;

    if ( ! is_array($items))
        $items = array($items);

    foreach($items as $item) {
        $items_q[] = $fof_connection->quote($item);
    }

    $query = "DELETE FROM $FOF_ITEM_TAG_TABLE WHERE item_id IN (" . implode(', ', $items_q) . ")";
    try {
        fof_db_exec($query);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot delete tags (items=" . implode(',', $items_q). ")");
    }
}

function fof_db_get_subscription_to_tags()
{
    global $FOF_SUBSCRIPTION_TABLE;
    global $fof_connection;
    $r = array();

    $query = "SELECT * FROM $FOF_SUBSCRIPTION_TABLE";
    try {
        $statement = fof_db_query($query);
        while(($row = fof_db_get_row($statement)) !== false) {
            $feed_id = $row['feed_id'];
            $user_id = $row['user_id'];
            $prefs = unserialize($row['subscription_prefs']);
            if ( ! is_array($r[$feed_id])) {
                $r[$feed_id] = array();
            }
            $r[$feed_id][$user_id] = $prefs['tags'];
        }
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get subscriptions)");
    }

    return $r;
}

function fof_db_tag_feed($user_id, $feed_id, $tag_id)
{
    global $FOF_SUBSCRIPTION_TABLE;
    global $fof_connection;

    $query = "SELECT subscription_prefs FROM $FOF_SUBSCRIPTION_TABLE WHERE feed_id = :feed_id AND user_id = :user_id";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':feed_id', $feed_id);
        $statement->bindValue(':user_id', $user_id);
        $result = fof_db_statement_execute($query, $statement);
        $prefs = unserialize(fof_db_get_row($statement, 'subscription_prefs', TRUE));
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get subscription prefs (feed_id='$feed_id' user_id='$user_id')");
    }

    if ( ! is_array($prefs['tags']) || ! in_array($tag_id, $prefs['tags'])) {
        $prefs['tags'][] = $tag_id;
    }

    $query = "UPDATE $FOF_SUBSCRIPTION_TABLE SET subscription_prefs = :prefs WHERE feed_id = :feed_id AND user_id = :user_id";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':prefs', serialize($prefs));
        $statement->bindValue(':user_id', $user_id);
        $statement->bindValue(':feed_id', $feed_id);
        $result = fof_db_statement_execute($query, $statement);
        $statement->closeCursor();
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot set subscription prefs (feed_id='$feed_id' user_id='$user_id')");
    }
}

function fof_db_untag_feed($user_id, $feed_id, $tag_id)
{
    global $FOF_SUBSCRIPTION_TABLE;
    global $fof_connection;

    $query = "SELECT subscription_prefs FROM $FOF_SUBSCRIPTION_TABLE WHERE feed_id = :feed_id AND user_id = :user_id";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':feed_id', $feed_id);
        $statement->bindValue(':user_id', $user_id);
        $result = fof_db_statement_execute($query, $statement);
        $prefs = unserialize(fof_db_get_row($statement, 'subscription_prefs', TRUE));
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get subscription prefs (feed_id='$feed_id' user_id='$user_id')");
    }

    if (is_array($prefs['tags'])) {
        $prefs['tags'] = array_diff($prefs['tags'], array($tag_id));
    }

    $query = "UPDATE $FOF_SUBSCRIPTION_TABLE SET subscription_prefs = :prefs WHERE feed_id = :feed_id AND user_id = :user_id";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':prefs', serialize($prefs));
        $statement->bindValue(':feed_id', $feed_id);
        $statement->bindValue(':user_id', $user_id);
        $result = fof_db_statement_execute($query, $statement);
        $statement->closeCursor();
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot set subscription prefs (feed_id='$feed_id' user_id='$user_id)");
    }
}

function fof_db_get_item_tags($user_id, $item_id)
{
    global $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE;
    global $fof_connection;

    $query = "SELECT $FOF_TAG_TABLE.tag_name" .
            " FROM $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE" .
            " WHERE $FOF_TAG_TABLE.tag_id = $FOF_ITEM_TAG_TABLE.tag_id" .
                " AND $FOF_ITEM_TAG_TABLE.item_id = :item_id" .
                " AND $FOF_ITEM_TAG_TABLE.user_id = :user_id";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':item_id', $item_id);
        $statement->bindValue(':user_id', $user_id);
        $result = fof_db_statement_execute($query, $statement);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get tags for item (item_id='$item_id' user_id='$user_id')");
    }

    return $statement;
}

function fof_db_item_has_tags($item_id)
{
    global $FOF_ITEM_TAG_TABLE;
    global $fof_connection;

    $query = "SELECT count(*) AS tag_count" .
            " FROM $FOF_ITEM_TAG_TABLE" .
            " WHERE item_id = :item_id" .
                " AND tag_id <= 2";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':item_id', $item_id);
        $result = fof_db_statement_execute($query, $statement);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get tag count (item_id='$item_id')");
    }

    return fof_db_get_row($statement, 'tag_count', TRUE);
}

function fof_db_get_unread_count($user_id)
{
    global $FOF_ITEM_TAG_TABLE;
    global $fof_connection;

    $query = "SELECT count(*) AS tag_count" .
            " FROM $FOF_ITEM_TAG_TABLE" .
            " WHERE tag_id = 1" .
                " AND user_id = :user_id";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':user_id', $user_id);
        $result = fof_db_statement_execute($query, $statement);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get unread tag count (user_id='$user_id')");
    }

    return fof_db_get_row($statement, 'tag_count', TRUE);
}

function fof_db_get_tag_unread($user_id)
{
    global $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE;
    global $fof_connection;
    $counts = array();

    $query = "SELECT count(*) AS tag_count, it2.tag_id" .
            " FROM $FOF_ITEM_TABLE i, $FOF_ITEM_TAG_TABLE it , $FOF_ITEM_TAG_TABLE it2" .
            " WHERE it.item_id = it2.item_id" .
                " AND it.tag_id = 1" .
                " AND i.item_id = it.item_id" .
                " AND i.item_id = it2.item_id" .
                " AND it.user_id = :user_id" .
                " AND it2.user_id = :user_id" .
            " GROUP BY it2.tag_id";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':user_id', $user_id);
        $result = fof_db_statement_execute($query, $statement);
        while (($row = fof_db_get_row($statement)) !== false) {
            $counts[$row['tag_id']] = $row['tag_count'];
        }
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get unread tags (user_id='$user_id')");
    }

    return $counts;
}

function fof_db_get_tags($user_id)
{
    global $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE;
    global $fof_connection;

    $query = "SELECT $FOF_TAG_TABLE.tag_id, $FOF_TAG_TABLE.tag_name, count( $FOF_ITEM_TAG_TABLE.item_id ) AS count" .
            " FROM $FOF_TAG_TABLE" .
            " LEFT JOIN $FOF_ITEM_TAG_TABLE ON $FOF_TAG_TABLE.tag_id = $FOF_ITEM_TAG_TABLE.tag_id" .
            " WHERE $FOF_ITEM_TAG_TABLE.user_id = :user_id" .
            " GROUP BY $FOF_TAG_TABLE.tag_id ORDER BY $FOF_TAG_TABLE.tag_name";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':user_id', $user_id);
        $result = fof_db_statement_execute($query, $statement);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get tags (user_id='$user_id')");
    }

    return $statement;
}

function fof_db_get_tag_id_map()
{
    global $FOF_TAG_TABLE;
    global $fof_connection;
    $tags = array();

    $query = "SELECT * FROM $FOF_TAG_TABLE";
    try {
        $statement = fof_db_query($query);
        while (($row = fof_db_get_row($statement)) !== false) {
            $tags[$row['tag_id']] = $row['tag_name'];
        }
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get tags");
    }

    return $tags;
}

/* XXX: user_id unused */
function fof_db_create_tag($user_id, $tag)
{
    global $FOF_TAG_TABLE;
    global $fof_connection;

    $query = "INSERT INTO $FOF_TAG_TABLE (tag_name) VALUES (:tag_name)";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':tag_name', $tag);
        $result = fof_db_statement_execute($query, $statement);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot add tag (tag_name='$tag')");
    }

    $query = "SELECT tag_id FROM $FOF_TAG_TABLE WHERE tag_name = :tag_name";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':tag_name', $tag);
        $result = fof_db_statement_execute($query, $statement);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get new tag id (tag_name='$tag')");
    }

    return fof_db_get_row($statement, 'tag_id', TRUE);
}

/* XXX: user_id unused */
/* XXX: also why doesn't this just return an array */
function fof_db_get_tag_by_name($user_id, $tag)
{
    global $FOF_TAG_TABLE;
    global $fof_connection;
    $return = array();

    $tags_q = array();
    foreach(explode(' ', $tag) as $t) {
        $tags_q[] = $fof_connection->quote($t);
    }

    $query = "SELECT DISTINCT $FOF_TAG_TABLE.tag_id" .
            " FROM $FOF_TAG_TABLE" .
            " WHERE $FOF_TAG_TABLE.tag_name IN ( " . implode (', ', $tags_q) . " )";
    try {
        $statement = fof_db_query($query);
        while (($row = fof_db_get_row($statement)) !== false) {
            $return[] = $row['tag_id'];
        }
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get tags (user_id='$user_id' tags=" . implode(',', $tags_q) . ")");
    }

    if (count($return))
        return implode(',', $return);

    return NULL;
}

function fof_db_mark_unread($user_id, $items)
{
    fof_db_tag_items($user_id, 1, $items);
}

function fof_db_mark_read($user_id, $items)
{
    fof_db_untag_items($user_id, 1, $items);
}

function fof_db_fold($user_id, $items)
{
    $tag_id = fof_db_get_tag_by_name($user_id, "folded");

    fof_db_tag_items($user_id, $tag_id, $items);
}

function fof_db_unfold($user_id, $items)
{
    $tag_id = fof_db_get_tag_by_name($user_id, "folded");

    fof_db_untag_items($user_id, $tag_id, $items);
}

function fof_db_mark_feed_read($user_id, $feed_id)
{
    $result = fof_db_get_items($user_id, $feed_id, $what="all");

    foreach($result as $r) {
        $items[] = $r['item_id'];
    }

    fof_db_untag_items($user_id, 1, $items);
}

function fof_db_mark_feed_unread($user_id, $feed, $what)
{
    fof_log("fof_db_mark_feed_unread($user_id, $feed, $what)");

    if($what == "all")
    {
        $result = fof_db_get_items($user_id, $feed, "all");
    }
    if($what == "today")
    {
        $result = fof_db_get_items($user_id, $feed, "all", "today");
    }

    $items = array();
    foreach($result as $r)
    {
        $items[] = $r['item_id'];
    }

    fof_db_tag_items($user_id, 1, $items);
}

function fof_db_mark_item_unread($users, $id)
{
    global $FOF_ITEM_TAG_TABLE;
    global $fof_connection;

    if (count($users) == 0)
        return;

    $values = array();
    $idx = 0;
    foreach($users as $user)
    {
        $values[] = "(:user_id_$idx, :tag_id, :item_id)";
        $idx += 1;
    }

    $query = "INSERT INTO $FOF_ITEM_TAG_TABLE (user_id, tag_id, item_id) VALUES " . implode(', ', $values);
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(":item_id", $id);
        $statement->bindValue(":tag_id", 1);
        $idx = 0;
        foreach($users as $user)
        {
            $statement->bindValue(":user_id_$idx", $user);
            $idx += 1;
        }
        $result = fof_db_statement_execute($query, $statement);
        $statement->closeCursor();
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot mark item unread (item_id='$item_id' users='" . implode(',', $users) . "')");
    }
}

function fof_db_tag_items($user_id, $tag_id, $items)
{
    global $FOF_ITEM_TAG_TABLE;
    global $fof_connection;

    if (! $items)
        return;

    if (! is_array($items))
        $items = array($items);

    $values = array();
    $idx = 0;
    foreach($items as $item) {
        $values[] = "(:user_id, :tag_id, :item_id_$idx)";
        $idx += 1;
    }

    $query = "INSERT INTO $FOF_ITEM_TAG_TABLE (user_id, tag_id, item_id) VALUES " . implode(', ', $values);
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(":user_id", $user_id);
        $statement->bindValue(":tag_id", $tag_id);
        $idx = 0;
        foreach($items as $item) {
            $statement->bindValue(":item_id_$idx", $item);
            $idx += 1;
        }
        $result = fof_db_statement_execute($query, $statement);
        $statement->closeCursor();
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot tag items (user_id='$user_id' tag_id='$tag_id' items='" . implode(',', $items) . "')");
    }
}

function fof_db_untag_items($user_id, $tag_id, $items)
{
    global $FOF_ITEM_TAG_TABLE;
    global $fof_connection;

    if (! $items)
        return;

    if (! is_array($items))
        $items = array($items);

    $items_q = array();
    foreach($items as $item) {
        $items_q = $fof_connection->quote($item);
    }

    $query = "DELETE FROM $FOF_ITEM_TAG_TABLE WHERE user_id = :user_id AND tag_id = :tag_id AND item_id IN ( " . implode(', ', $items_q) . " )";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':user_id', $user_id);
        $statement->bindValue(':tag_id', $tag_id);
        $result = fof_db_statement_execute($query, $statement);
        $statement->closeCursor();
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot untag items (user_id='$user_id' tag_id='$tag_id' items='" . implode(',', $items) . "')");
    }
}


////////////////////////////////////////////////////////////////////////////////
// User stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_user_password_hash($password, $user)
{
    return md5($password . $user);
}

function fof_db_get_users()
{
    global $FOF_USER_TABLE;
    global $fof_connection;
    $users = array();

    $query = "SELECT user_name, user_id, user_prefs FROM $FOF_USER_TABLE";
    try {
        $statement = fof_db_query($query);
        while (($row = fof_db_get_row($statement)) !== false) {
            $users[$row['user_id']]['user_name'] = $row['user_name'];
            $users[$row['user_id']]['user_prefs'] = unserialize($row['user_prefs']);
        }
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get users");
    }

    return $users;
}

function fof_db_get_nonadmin_usernames()
{
    global $FOF_USER_TABLE;
    global $fof_connection;

    $query = "SELECT user_name FROM $FOF_USER_TABLE WHERE user_id > 1";
    try {
        $statement = fof_db_query($query);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get users");
    }

    return $statement;
}

/* only used during install */
function fof_db_add_user_all($user_id, $user_name, $user_password, $user_level)
{
    global $FOF_USER_TABLE;
    global $fof_connection;

    $query = "INSERT INTO $FOF_USER_TABLE (user_id, user_name, user_password_hash, user_level) VALUES (:id, :name, :password_hash, :level)";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':id', $user_id);
        $statement->bindValue(':name', $user_name);
        $statement->bindValue(':password_hash', fof_db_user_password_hash($user_password, $user_name));
        $statement->bindValue(':level', $user_level);
        $result = fof_db_statement_execute($query, $statement);
        $statement->closeCursor();
    } catch(PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot add user (user_id='$user_id')");
    }
}

function fof_db_add_user($username, $password)
{
    global $FOF_USER_TABLE;
    global $fof_connection;

    $query = "INSERT INTO $FOF_USER_TABLE (user_name, user_password_hash) VALUES (:name, :password_hash)";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':password_hash', fof_db_user_password_hash($password, $username));
        $statement->bindValue(':name', $username);
        $result = fof_db_statement_execute($query, $statement);
        $statement->closeCursor();
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot add user (user_name='$username')");
    }
}

function fof_db_change_password($username, $password)
{
    global $FOF_USER_TABLE;
    global $fof_connection;

    $query = "UPDATE $FOF_USER_TABLE SET user_password_hash = :password_hash WHERE user_name = :name";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':password_hash', fof_db_user_password_hash($password, $username));
        $statement->bindValue(':name', $username);
        $result = fof_db_statement_execute($query, $statement);
        $statement->closeCursor();
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot set password (user_name='$username')");
    }
}

function fof_db_get_user_id($username)
{
    global $FOF_USER_TABLE;
    global $fof_connection;

    $query = "SELECT user_id FROM $FOF_USER_TABLE WHERE user_name = :name";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':name', $username);
        $result = fof_db_statement_execute($query, $statement);
        $row = fof_db_get_row($statement, NULL, TRUE);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get user id (user_name='$username')");
    }

    return $row['user_id'];
}

function fof_db_delete_user($username)
{
    global $FOF_USER_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE;
    global $fof_connection;

    $user_id = fof_db_get_user_id($username);

    $tables = array($FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_USER_TABLE);
    foreach($tables as $table)
    {
        $query = "DELETE FROM $table WHERE user_id = :id";
        try {
            $statement = $fof_connection->prepare($query);
            $statement->bindValue(':id', $user_id);
            $result = fof_db_statement_execute($query, $statement);
            $statement->closeCursor();
        } catch (PDOException $e) {
            fof_pdoexception_log_(__FUNCTION__, $e, "cannot delete user from (table='$table' user_name='$username' user_id='$user_id')");
        }
    }
}

function fof_db_prefs_get($user_id)
{
    global $FOF_USER_TABLE;
    global $fof_connection;

    $query = "SELECT user_prefs FROM $FOF_USER_TABLE WHERE user_id = :user_id";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':user_id', $user_id);
        $result = fof_db_statement_execute($query, $statement);
        $prefs = fof_db_get_row($statement, 'user_prefs', TRUE);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot get user prefs (user_id='$user_id')");
    }

    return unserialize($prefs);
}

function fof_db_save_prefs($user_id, $prefs)
{
    global $FOF_USER_TABLE;
    global $fof_connection;

    $query = "UPDATE $FOF_USER_TABLE SET user_prefs = :prefs WHERE user_id = :id";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':id', $user_id);
        $statement->bindValue(':prefs', serialize($prefs));
        $result = fof_db_statement_execute($query, $statement);
        $statement->closeCursor();
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot set user prefs (user_id='$user_id')");
    }
}

/* check user and password hash, set global user info if matching record found */
function fof_db_authenticate_hash($user_name, $user_password_hash)
{
    global $FOF_USER_TABLE;
    global $fof_connection, $fof_user_id, $fof_user_name, $fof_user_level;

    $query = "SELECT * FROM $FOF_USER_TABLE WHERE user_name = :name AND user_password_hash = :password_hash";
    try {
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':name', $user_name);
        $statement->bindValue(':password_hash', $user_password_hash);
        $result = fof_db_statement_execute($query, $statement);
        $row = fof_db_get_row($statement, NULL, TRUE);
    } catch (PDOException $e) {
        fof_pdoexception_log_(__FUNCTION__, $e, "cannot check user authentication (user_name='$user_name')");
    }

    if ( ! $row) {
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
