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
    fof_prefs()
    fof_log()
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

/* set up a db connection, creating the db if it doesn't exist */
function fof_db_connect()
{
    global $fof_connection;

    /* It would be nice to actually use prepared statements by setting
       PDO::ATTR_EMULATE_PREPARES => false
       but it seems that however PDO translates its named parameters to the
       MySQL bindings doesn't work when a parameter is repeated in a query..
       Leaving the emulation on is easier than changing the sql.
     */
    $pdo_options = array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION );
    if (defined('USE_MYSQL')) {
        $dsn = "mysql:host=" . FOF_DB_HOST . ";charset=utf8";
    } else if (defined('USE_SQLITE')) {
        if (! defined('FOF_DATA_PATH') || ! defined('FOF_DB_DBNAME') ) {
            throw new Exception('vital configuration is not set');
        }
        $sqlite_db = FOF_DATA_PATH . DIRECTORY_SEPARATOR . FOF_DB_DBNAME;
        $dsn = "sqlite:$sqlite_db";
    } else {
        throw new Exception('missing implementation for pdo driver');
    }

    fof_log("Connecting to '$dsn'...");

    $fof_connection = new PDO($dsn, FOF_DB_USER, FOF_DB_PASS, $pdo_options);

    if (defined('USE_MYSQL')) {
        if ($fof_connection) {
            $fof_connection->exec("CREATE DATABASE IF NOT EXISTS " . FOF_DB_DBNAME);
            $fof_connection->exec("USE " . FOF_DB_DBNAME);
        }
    }

    return $fof_connection;
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

    fof_log($msg, 'query');
}

/*
    Wraps a prepared statement execution to gather elapsed query time, and log such.
*/
function fof_db_statement_execute($statement, $input_parameters = NULL)
{
    $bt = debug_backtrace();

    $callable = array($statement, 'execute');
    list($result, $elapsed) = fof_db_wrap_elapsed_($callable, $input_parameters);

    fof_db_log_query_caller_($bt[1], $statement->queryString, $elapsed, $statement->rowCount());

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

    $result = fof_db_exec($query);

    return $result;
}

////////////////////////////////////////////////////////////////////////////////
// Feed level stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_feed_update_prefs($feed_id, $title, $alt_image)
{
    global $FOF_FEED_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "UPDATE $FOF_FEED_TABLE SET feed_title = :title, alt_image = :alt_image, feed_image_cache_date = :cache_date WHERE feed_id = :feed_id";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':title', empty($title) ? "[no title]" : $title);
    $statement->bindValue(':alt_image', $alt_image);
    $statement->bindValue(':cache_date', 1);
    $statement->bindValue(':feed_id', $feed_id);
    $result = fof_db_statement_execute($statement);
    $statement->closeCursor();

    return $result;
}

function fof_db_feed_mark_cached($feed_id)
{
    global $FOF_FEED_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "UPDATE $FOF_FEED_TABLE SET feed_cache_date = :cache_date WHERE feed_id = :feed_id";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':cache_date', time());
    $statement->bindValue(':feed_id', $feed_id);
    $result = fof_db_statement_execute($statement);
    $statement->closeCursor();

    return $result;
}

function fof_db_feed_mark_attempted_cache($feed_id)
{
    global $FOF_FEED_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "UPDATE $FOF_FEED_TABLE SET feed_cache_attempt_date = :cache_date WHERE feed_id = :feed_id";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':cache_date', time());
    $statement->bindValue(':feed_id', $feed_id);
    $result = fof_db_statement_execute($statement);
    $statement->closeCursor();

    return $result;
}

function fof_db_feed_update_metadata($feed_id, $title, $link, $description, $image, $image_cache_date)
{
    global $FOF_FEED_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "UPDATE $FOF_FEED_TABLE SET feed_title = :title, feed_link = :link, feed_description = :description, feed_image = :image, feed_image_cache_date = :image_cache_date WHERE feed_id = :id";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':title', empty($title) ? "[no title]" : $title);
    $statement->bindValue(':link', empty($link) ? "[no link]" : $link);
    $statement->bindValue(':description', empty($description) ? "[no description]" : $description);
    if ( ! empty($image)) {
        $statement->bindValue(':image', $image);
    } else {
        $statement->bindValue(':image', null, PDO::PARAM_NULL);
    }
    $statement->bindValue(':image_cache_date', empty($image_cache_date) ? time() : $image_cache_date);
    $statement->bindValue(':id', $feed_id);
    $result = fof_db_statement_execute($statement);
    $statement->closeCursor();

    return $result;
}

/* XXX: user_id is unused */
function fof_db_get_latest_item_age($user_id)
{
    global $FOF_ITEM_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "SELECT max(item_cached) AS max_date, i.feed_id AS id FROM $FOF_ITEM_TABLE i GROUP BY i.feed_id";
    $statement = fof_db_query($query);

    return $statement;
}

function fof_db_get_subscriptions($user_id, $dueOnly=false)
{
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
    $result = fof_db_statement_execute($statement);

    return $statement;
}

function fof_db_get_feeds_needing_attempt()
{
    global $FOF_FEED_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "SELECT * FROM $FOF_FEED_TABLE WHERE feed_cache_next_attempt < :now ORDER BY feed_title";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':now', time());
    $result = fof_db_statement_execute($statement);

    return $statement;
}

/* N.B. confusing: 'starred' selects 'star' tag */
function fof_db_get_item_count ( $user_id, $what = 'all', $feed = null, $search = null )
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "SELECT COUNT(*) AS count," .
                   " i.feed_id AS id" .
                   " FROM $FOF_ITEM_TABLE i, $FOF_SUBSCRIPTION_TABLE s, $FOF_FEED_TABLE f";

    if ($what != 'all') {
        $query .= ", $FOF_ITEM_TAG_TABLE it";
    }

    $query .= " WHERE s.user_id = :user_id" .
                    " AND s.feed_id = f.feed_id" .
                    " AND f.feed_id = i.feed_id";

    if ($what != 'all') {
        $query .= " AND it.user_id = s.user_id" .
                  " AND i.item_id = it.item_id" .
                  " AND f.feed_id = s.feed_id";
    }

    switch ($what) {
    case 'all':
        break;

    case 'unread':
        $query .= " AND it.tag_id = :unread_id";
        break;

    case 'starred':
        $query .= " AND it.tag_id = :star_id";
        break;

    case 'tagged':
        $query .= " AND it.tag_id != :unread_id" .
                  " AND it.tag_id != :star_id" .
                  " AND it.tag_id != :folded_id";
        break;

    default:
        $tag_ids_q = array();
        foreach (explode(',', fof_db_get_tag_by_name($what)) as $t) {
            $tag_ids_q[] = $fof_connection->quote($t);
         }
        $query .= " AND it.tag_id IN ( " . (count($tag_ids_q) ? implode(', ', $tag_ids_q) : "''") . " )";
    }

    if ( ! empty($search)) {
        $query .= " AND (i.item_title LIKE :search OR i.item_content LIKE :search )";
    }

    $query .= " GROUP BY id";

    if ( ! empty($feed)) {
        $query .= " HAVING id = :feed";
    }

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
        break;

    case 'unread':
        $statement->bindValue(':unread_id', fof_db_get_tag_by_name('unread'));
        break;

    case 'starred':
        $statement->bindValue(':star_id', fof_db_get_tag_by_name('star'));
        break;

    case 'tagged':
        $statement->bindValue(':unread_id', fof_db_get_tag_by_name('unread'));
        $statement->bindValue(':star_id', fof_db_get_tag_by_name('star'));
        $statement->bindValue(':folded_id', fof_db_get_tag_by_name('folded'));
        break;

    default:
    }
    $result = fof_db_statement_execute($statement);

    return $statement;
}


/* FIXME: tried to fix this, but it's still not quite right */
function fof_db_get_item_count_XXX($user_id=1, $what='all', $feed_id=NULL, $search=NULL) {
    global $FOF_SUBSCRIPTION_TABLE, $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_TAG_TABLE;
    global $fof_connection;

    fof_trace();

    /* build a query which will return rows containing just feed_id,item_id matching the requested parameters */

    $items_select = "SELECT i.feed_id, i.item_id FROM $FOF_ITEM_TABLE i, $FOF_SUBSCRIPTION_TABLE s";
    $items_where = " WHERE s.user_id = " . $fof_connection->quote($user_id) . " AND s.feed_id = i.feed_id";
    if ( ! empty($feed_id)) {
        $items_where .= " AND i.feed_id = " . $fof_connection->quote($feed_id);
    }
    if ( ! empty($search)) {
        $search = $fof_connection->quote('%' . $search . '%');
        $items_where .= " AND (i.item_title LIKE $search OR i.item_content LIKE $search)";
    }

    $tag_invert = '';
    /* special-case some whats */
    if ($what == 'all') {
        $what = '';
    } else if ($what == 'starred') {
        $what = 'star';
    } else if ($what == 'tagged') {
        $what = 'unread star folded';
        $tag_invert = ' NOT';
    }

    $what_q = array_map(array($fof_connection, 'quote'), array_filter(explode(' ', $what)));

    $items_having = '';
    if ( ! empty($what_q)) {
        $items_select .= " JOIN $FOF_ITEM_TAG_TABLE it ON it.item_id = i.item_id";
        $items_select .= " JOIN $FOF_TAG_TABLE t ON t.tag_id = it.tag_id AND t.tag_name" . $tag_invert . " IN (" . (count($what_q) ? implode(',', $what_q) : "''") . ")";
        $items_having = " HAVING COUNT(i.item_id) = " . count($what_q);
    }

    $items_query = $items_select . $items_where . " GROUP BY i.item_id" . $items_having;

    /* wrap that item query, to correlate counts by feed_id, because SQL is hard */
    $count_query = "SELECT feed_id AS id, COUNT(*) AS count FROM ( $items_query ) GROUP BY feed_id;";

    $statement = $fof_connection->prepare($count_query);
    $result = fof_db_statement_execute($statement);
    return $statement;
}


function fof_db_get_subscribed_users($feed_id)
{
    global $FOF_SUBSCRIPTION_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "SELECT user_id FROM $FOF_SUBSCRIPTION_TABLE WHERE feed_id = :feed_id";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':feed_id', $feed_id);
    $result = fof_db_statement_execute($statement);

    return $statement;
}

function fof_db_get_subscribed_users_count($feed_id)
{
    fof_trace();

    $sub_statement = fof_db_get_subscribed_users($feed_id);
    $subscribed_users = $sub_statement->fetchAll();

    return count($subscribed_users);
}

function fof_db_is_subscribed($user_id, $feed_url)
{
    global $FOF_FEED_TABLE, $FOF_SUBSCRIPTION_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "SELECT s.feed_id" .
                " FROM $FOF_FEED_TABLE f, $FOF_SUBSCRIPTION_TABLE s" .
                " WHERE feed_url = :feed_url" .
                    " AND s.feed_id = f.feed_id" .
                    " AND s.user_id = :user_id;";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':feed_url', $feed_url);
    $statement->bindValue(':user_id', $user_id);
    $result = fof_db_statement_execute($statement);
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

    fof_trace();

    $query = "SELECT * FROM $FOF_FEED_TABLE WHERE feed_url = :feed_url";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':feed_url', $feed_url);
    $result = fof_db_statement_execute($statement);

    return fof_db_get_row($statement, NULL, TRUE);
}

function fof_db_get_feed_by_id($feed_id)
{
    global $FOF_FEED_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "SELECT * FROM $FOF_FEED_TABLE WHERE feed_id = :feed_id";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':feed_id', $feed_id);
    $result = fof_db_statement_execute($statement);

    return fof_db_get_row($statement, NULL, TRUE);
}

function fof_db_add_feed($url, $title, $link, $description)
{
    global $FOF_FEED_TABLE;
    global $fof_connection;

    fof_trace();

    /* XXX: not sure why these weren't implemented as default values in feed table */
    if (empty($title)) $title = "[no title]";
    if (empty($link)) $link = "[no link]";
    if (empty($description)) $description = "[no description]";

    $query = "INSERT INTO $FOF_FEED_TABLE (feed_url, feed_title, feed_link, feed_description) VALUES (:url, :title, :link, :description)";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':url', $url);
    $statement->bindValue(':title', $title);
    $statement->bindValue(':link', $link);
    $statement->bindValue(':description', $description);
    $result = fof_db_statement_execute($statement);
    $statement->closeCursor();

    /*
        It would be nice to use:
            return $fof_connection->lastInsertId();
        But it has problems:
            not reliable between pdo drivers
            is a race condition
        So just grab the id of what we put in..
        FIXME: I bet there's a sane way of doing this in one transaction.
    */

    $query = "SELECT feed_id FROM $FOF_FEED_TABLE WHERE feed_url = :url AND feed_title = :title AND feed_link = :link AND feed_description = :description";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':url', $url);
    $statement->bindValue(':title', $title);
    $statement->bindValue(':link', $link);
    $statement->bindValue(':description', $description);
    $result = fof_db_statement_execute($statement);

    return fof_db_get_row($statement, 'feed_id', TRUE);
}

function fof_db_add_subscription($user_id, $feed_id)
{
    global $FOF_SUBSCRIPTION_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "INSERT INTO $FOF_SUBSCRIPTION_TABLE ( feed_id, user_id ) VALUES ( :feed_id, :user_id )";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':feed_id', $feed_id);
    $statement->bindValue(':user_id', $user_id);
    $result = fof_db_statement_execute($statement);
    $statement->closeCursor();
}

function fof_db_delete_subscription($user_id, $feed_id)
{
    global $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
    global $fof_connection;

    fof_trace();

    $all_items = fof_db_get_items($user_id, $feed_id, "all");
    $items_q = array();
    foreach($all_items as $i) {
        $items_q[] = $fof_connection->quote($i['item_id']);
    }

    if (count($items_q) > 0) {
        $query = "DELETE FROM $FOF_ITEM_TAG_TABLE WHERE user_id = :user_id AND item_id IN ( " . (count($items_q) ? implode(', ', $items_q) : "''") . " )";
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':user_id', $user_id);
        $result = fof_db_statement_execute($statement);
        $statement->closeCursor();
    }

    $query = "DELETE FROM $FOF_SUBSCRIPTION_TABLE WHERE feed_id = :feed_id and user_id = :user_id";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':user_id', $user_id);
    $statement->bindValue(':feed_id', $feed_id);
    $result = fof_db_statement_execute($statement);
    $statement->closeCursor();
}

function fof_db_delete_feed($feed_id)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "DELETE FROM $FOF_FEED_TABLE WHERE feed_id = :feed_id";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':feed_id', $feed_id);
    $result = fof_db_statement_execute($statement);
    $statement->closeCursor();

    $query = "DELETE FROM $FOF_ITEM_TABLE WHERE feed_id = :feed_id";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':feed_id', $feed_id);
    $result = fof_db_statement_execute($statement);
    $statement->closeCursor();
}

function fof_db_feed_cache_set($feed_id, $next_attempt)
{
    global $FOF_FEED_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "UPDATE $FOF_FEED_TABLE SET feed_cache_next_attempt = :next_attempt WHERE feed_id = :feed_id";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(":feed_id", $feed_id);
    $statement->bindValue(":next_attempt", $next_attempt);
    $result = fof_db_statement_execute($statement);
    $statement->closeCursor();
}

////////////////////////////////////////////////////////////////////////////////
// Item level stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_find_item($feed_id, $item_guid)
{
    global $FOF_ITEM_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "SELECT item_id FROM $FOF_ITEM_TABLE WHERE feed_id = :feed_id and item_guid = :item_guid";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(":feed_id", $feed_id);
    $statement->bindValue(":item_guid", $item_guid);
    $result = fof_db_statement_execute($statement);

    return fof_db_get_row($statement, 'item_id', TRUE);
}

function fof_db_add_item($feed_id, $guid, $link, $title, $content, $cached, $published, $updated)
{
    global $FOF_ITEM_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "INSERT INTO $FOF_ITEM_TABLE (feed_id, item_link, item_guid, item_title, item_content, item_cached, item_published, item_updated) VALUES (:feed_id, :link, :guid, :title, :content, :cached, :published, :updated)";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':feed_id', $feed_id);
    $statement->bindValue(':link', $link);
    $statement->bindValue(':guid', $guid);
    $statement->bindValue(':title', $title);
    $statement->bindValue(':content', $content);
    $statement->bindValue(':cached', $cached);
    $statement->bindValue(':published', $published);
    $statement->bindValue(':updated', $updated);
    $result = fof_db_statement_execute($statement);
    $statement->closeCursor();

    /* FIXME: see comments elsewhere about lastInsertId */

    $query = "SELECT item_id FROM $FOF_ITEM_TABLE WHERE feed_id = :feed_id AND item_link = :link AND item_guid = :guid AND item_title = :title AND item_content = :content AND item_cached = :cached AND item_published = :published AND item_updated = :updated";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':feed_id', $feed_id);
    $statement->bindValue(':link', $link);
    $statement->bindValue(':guid', $guid);
    $statement->bindValue(':title', $title);
    $statement->bindValue(':content', $content);
    $statement->bindValue(':cached', $cached);
    $statement->bindValue(':published', $published);
    $statement->bindValue(':updated', $updated);
    $result = fof_db_statement_execute($statement);

    return fof_db_get_row($statement, 'item_id', TRUE);
}


/* when: Y/m/d or 'today' */
function fof_db_get_items($user_id=1, $feed=NULL, $what='unread', $when=NULL, $start=NULL, $limit=NULL, $order='desc', $search=NULL) {
    global $FOF_SUBSCRIPTION_TABLE, $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_TAG_TABLE;
    global $fof_connection;
    $all_items = array();

    fof_trace();

    $prefs = fof_prefs();

    $select = "SELECT i.*, f.*";

    $from = " FROM $FOF_FEED_TABLE f, $FOF_ITEM_TABLE i, $FOF_SUBSCRIPTION_TABLE s";
    if ($what != 'all') {
        $from .= ", $FOF_TAG_TABLE t, $FOF_ITEM_TAG_TABLE it";
    }

    $where = " WHERE s.user_id = " . $fof_connection->quote($user_id) . " AND s.feed_id = f.feed_id AND f.feed_id = i.feed_id";
    if (! empty($feed)) {
        $where .= " AND f.feed_id = " . $fof_connection->quote($feed);
    }
    if (! empty($when)) {
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

    if ( ! empty($search)) {
        $search_q = $fof_connection->quote('%' . $search . '%');
        $where .= " AND (i.item_title LIKE $search_q OR i.item_content LIKE $search_q )";
    }

    $order_by = " ORDER BY i.item_published DESC";
    if (is_numeric($start)) {
        $order_by .= " LIMIT " . $start . ", " . ((is_numeric($limit)) ? $limit : $prefs['howmany']);
    }

    $query = $select . $from . $where . $group . $order_by;

    fof_log(__FUNCTION__ . " first query: " . $query);

    $statement = fof_db_statement_prepare($query);
    $result = fof_db_statement_execute($statement);

    $item_ids_q = array();
    $lookup = array(); /* remember item_id->all_rows mapping, for populating tags */
    $idx = 0;
    while ( ($row = fof_db_get_row($statement)) !== FALSE ) {
        fof_log(__FUNCTION__ . " collecting item_id:" . $row['item_id'] . " idx:$idx");
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
                " AND it.item_id IN ( " . (count($item_ids_q) ? implode(', ', $item_ids_q) : "''") . " )" .
                " AND it.user_id = " . $fof_connection->quote($user_id);

    fof_log(__FUNCTION__ . " second query: " . $query);
    fof_log('item_ids_q' . var_export($item_ids_q, true));

    $statement = fof_db_statement_prepare($query);
    $result = fof_db_statement_execute($statement);
    while ( ($row = fof_db_get_row($statement)) !== FALSE ) {
        $idx = $lookup[$row['item_id']];
        $all_items[$idx]['tags'][] = $row['tag_name'];
    }

    fof_log(__FUNCTION__ . " all_items:" . var_export($all_items,true));

    return $all_items;
}


function fof_db_get_item($user_id, $item_id)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_TAG_TABLE;
    global $fof_connection;
    $item = array();

    fof_trace();

    $query = "SELECT f.feed_image AS feed_image," .
                   " f.feed_title AS feed_title," .
                   " f.feed_link AS feed_link," .
                   " f.feed_description AS feed_description," .
                   " i.item_id AS item_id," .
                   " i.item_link AS item_link," .
                   " i.item_title AS item_title," .
                   " i.item_cached," .
                   " i.item_published," .
                   " i.item_updated," .
                   " i.item_content AS item_content" .
            " FROM $FOF_FEED_TABLE f, $FOF_ITEM_TABLE i" .
            " WHERE i.feed_id = f.feed_id" .
                " AND i.item_id = :item_id";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':item_id', $item_id);
    $result = fof_db_statement_execute($statement);
    $item = fof_db_get_row($statement, NULL, TRUE);

    $item['tags'] = array();

    if ($user_id) {
        $query = "SELECT t.tag_name".
                " FROM $FOF_TAG_TABLE t, $FOF_ITEM_TAG_TABLE it" .
                " WHERE t.tag_id = it.tag_id" .
                    " AND it.item_id = :item_id" .
                    " AND it.user_id = :user_id";
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':item_id', $item_id);
        $statement->bindValue(':user_id', $user_id);
        $result = fof_db_statement_execute($statement);
        while (($row = fof_db_get_row($statement)) !== false) {
            $item['tags'][] = $row['tag_name'];
        }
    }

    return $item;
}

function fof_db_items_purge_list($feed_id, $purge_days)
{
    global $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE;
    global $fof_connection;

    fof_trace();

    $purge_secs = $purge_days * 24 * 60 * 60;
    $query = "SELECT i.item_id FROM $FOF_ITEM_TABLE i" .
            " LEFT JOIN $FOF_ITEM_TAG_TABLE t ON i.item_id = t.item_id" .
            " WHERE tag_id IS NULL" .
                " AND feed_id = :feed_id" .
                " AND i.item_cached <= :purge_time";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(":feed_id", $feed_id);
    $statement->bindValue(":purge_time", time() - $purge_secs);
    $result = fof_db_statement_execute($statement);

    return $statement;
}

function fof_db_items_delete($items)
{
    global $FOF_ITEM_TABLE;
    global $fof_connection;

    fof_trace();

    if ( ! $items)
        return;

    if ( ! is_array($items))
        $items = array($items);

    $items_q = array();
    foreach($items as $item) {
        $items_q[] = $fof_connection->quote($item);
    }

    $query = "DELETE FROM $FOF_ITEM_TABLE WHERE item_id IN (" . (count($items_q) ? implode(', ', $items_q) : "''") . ")";
    $statement = $fof_connection->prepare($query);
    $result = fof_db_statement_execute($statement);
    $statement->closeCursor();
}

function fof_db_items_duplicate_list()
{
    global $FOF_ITEM_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "SELECT i2.item_id, i1.item_content AS c1," .
                   " i2.item_content AS c2" .
                   " FROM $FOF_ITEM_TABLE i1" .
            " LEFT JOIN $FOF_ITEM_TABLE i2" .
            " ON i1.item_title=i2.item_title AND i1.feed_id=i2.feed_id" .
            " WHERE i1.item_id < i2.item_id";
    $statement = fof_db_query($query);

    return $statement;
}

function fof_db_items_updated_list($feed_id)
{
    global $FOF_ITEM_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "SELECT item_updated FROM $FOF_ITEM_TABLE WHERE feed_id = :id ORDER BY item_updated ASC";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':id', $feed_id);
    $result = fof_db_statement_execute($statement);

    return $statement;
}

////////////////////////////////////////////////////////////////////////////////
// Tag stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_tag_delete($items)
{
    global $FOF_ITEM_TAG_TABLE;
    global $fof_connection;

    fof_trace();

    if ( ! $items)
        return;

    if ( ! is_array($items))
        $items = array($items);

    $items_q = array();
    foreach($items as $item) {
        $items_q[] = $fof_connection->quote($item);
    }

    $query = "DELETE FROM $FOF_ITEM_TAG_TABLE WHERE item_id IN (" . (count($items_q) ? implode(', ', $items_q) : "''") . ")";
    $result = fof_db_exec($query);
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
    $result = fof_db_statement_execute($statement);

    while (($sub = fof_db_get_row($statement)) !== false) {
        $sub['subscription_prefs'] = empty($sub['subscription_prefs']) ? array('tags'=>array()) : unserialize($sub['subscription_prefs']);
        if ( ! empty($sub['subscription_prefs']['tags'])) {
            foreach ($sub['subscription_prefs']['tags'] as $tagid) {
                if (empty($r[$tagid]))
                    $r[$tagid] = array();
                $r[$tagid][] = $sub['feed_id'];
            }
        } else {
            $tagid = 0; /* untagged feeds get lumped into tagid '0' */
            if (empty($r[$tagid]))
                $r[$tagid] = array();
            $r[$tagid][] = $sub['feed_id'];
        }
    }

    return $r;
}

function fof_db_get_subscription_to_tags()
{
    global $FOF_SUBSCRIPTION_TABLE;
    global $fof_connection;
    $r = array();

    fof_trace();

    $query = "SELECT * FROM $FOF_SUBSCRIPTION_TABLE";
    $statement = fof_db_query($query);
    while(($row = fof_db_get_row($statement)) !== false) {
        $feed_id = $row['feed_id'];
        $user_id = $row['user_id'];
        $prefs = unserialize($row['subscription_prefs']);
        if ( ! isset($r[$feed_id])) {
            $r[$feed_id] = array();
        }
        $r[$feed_id][$user_id] = $prefs['tags'];
    }

    return $r;
}

function fof_db_tag_feed($user_id, $feed_id, $tag_id)
{
    global $FOF_SUBSCRIPTION_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "SELECT subscription_prefs FROM $FOF_SUBSCRIPTION_TABLE WHERE feed_id = :feed_id AND user_id = :user_id";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':feed_id', $feed_id);
    $statement->bindValue(':user_id', $user_id);
    $result = fof_db_statement_execute($statement);
    $prefs = unserialize(fof_db_get_row($statement, 'subscription_prefs', TRUE));

    if ( ! is_array($prefs['tags']) || ! in_array($tag_id, $prefs['tags'])) {
        $prefs['tags'][] = $tag_id;
    }

    $query = "UPDATE $FOF_SUBSCRIPTION_TABLE SET subscription_prefs = :prefs WHERE feed_id = :feed_id AND user_id = :user_id";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':prefs', serialize($prefs));
    $statement->bindValue(':user_id', $user_id);
    $statement->bindValue(':feed_id', $feed_id);
    $result = fof_db_statement_execute($statement);
    $statement->closeCursor();
}

function fof_db_untag_feed($user_id, $feed_id, $tag_id)
{
    global $FOF_SUBSCRIPTION_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "SELECT subscription_prefs FROM $FOF_SUBSCRIPTION_TABLE WHERE feed_id = :feed_id AND user_id = :user_id";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':feed_id', $feed_id);
    $statement->bindValue(':user_id', $user_id);
    $result = fof_db_statement_execute($statement);
    $prefs = unserialize(fof_db_get_row($statement, 'subscription_prefs', TRUE));

    if (is_array($prefs['tags'])) {
        $prefs['tags'] = array_diff($prefs['tags'], array($tag_id));
    }

    $query = "UPDATE $FOF_SUBSCRIPTION_TABLE SET subscription_prefs = :prefs WHERE feed_id = :feed_id AND user_id = :user_id";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':prefs', serialize($prefs));
    $statement->bindValue(':feed_id', $feed_id);
    $statement->bindValue(':user_id', $user_id);
    $result = fof_db_statement_execute($statement);
    $statement->closeCursor();
}

function fof_db_get_item_tags($user_id, $item_id)
{
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
    $result = fof_db_statement_execute($statement);

    return $statement;
}

/* returns count of a single tag_name for user_id */
function fof_db_tag_count($user_id, $tag_name) {
    global $FOF_ITEM_TAG_TABLE, $FOF_TAG_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "SELECT COUNT(*) AS tag_count FROM $FOF_ITEM_TAG_TABLE it, $FOF_TAG_TABLE t"
        . " WHERE it.tag_id = t.tag_id AND it.user_id = :user_id AND t.tag_name = :tag_name";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':user_id', $user_id);
    $statement->bindValue(':tag_name', $tag_name);
    $result = fof_db_statement_execute($statement);

    return fof_db_get_row($statement, 'tag_count', TRUE);
}

/* returns rows containing tag_id and count of items with both tag_id and 'unread' tag */
/* FIXME: this seems to return incorrect count for otherwise-untagged 'unread' items */
function fof_db_get_tag_unread($user_id)
{
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
    $result = fof_db_statement_execute($statement);
    while (($row = fof_db_get_row($statement)) !== false) {
        $counts[$row['tag_id']] = $row['tag_count'];
    }

    return $counts;
}

function fof_db_get_tags($user_id)
{
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
    $result = fof_db_statement_execute($statement);

    return $statement;
}

function fof_db_get_tag_id_map()
{
    global $FOF_TAG_TABLE;
    global $fof_connection;
    $tags = array();

    fof_trace();

    $query = "SELECT * FROM $FOF_TAG_TABLE";
    $statement = fof_db_query($query);
    while (($row = fof_db_get_row($statement)) !== false) {
        $tags[$row['tag_id']] = $row['tag_name'];
    }

    return $tags;
}

function fof_db_create_tag($tag_name)
{
    global $FOF_TAG_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "INSERT INTO $FOF_TAG_TABLE (tag_name) VALUES (:tag_name)";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':tag_name', $tag_name);
    $result = fof_db_statement_execute($statement);

    /* FIXME: see comments elsewhere about lastInsertId */

    $query = "SELECT tag_id FROM $FOF_TAG_TABLE WHERE tag_name = :tag_name";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':tag_name', $tag_name);
    $result = fof_db_statement_execute($statement);

    return fof_db_get_row($statement, 'tag_id', TRUE);
}

/* XXX: also why doesn't this just return (or take) an array */
function fof_db_get_tag_by_name($tags)
{
    global $FOF_TAG_TABLE;
    global $fof_connection;
    $return = array();

    fof_trace();

    $tags_q = array();
    foreach(explode(' ', $tags) as $t) {
        $tags_q[] = $fof_connection->quote($t);
    }

    $query = "SELECT DISTINCT tag_id" .
            " FROM $FOF_TAG_TABLE" .
            " WHERE tag_name IN ( " . (count($tags_q) ? implode (', ', $tags_q) : "''") . " )";
    $statement = fof_db_query($query);
    while (($row = fof_db_get_row($statement)) !== false) {
        $return[] = $row['tag_id'];
    }

    if (count($return))
        return implode(',', $return);

    return NULL;
}

/* removes unread tag from all items with tagname for user_id */
function fof_db_mark_tag_read($user_id, $tagname) {
    global $FOF_ITEM_TAG_TABLE;
    global $fof_connection;

    fof_trace();

    $user_id = $fof_connection->quote($user_id);
    $tag_id = fof_db_get_tag_by_name($tagname);
    $unread_id = fof_db_get_tag_by_name('unread');
    if (empty($tag_id) || empty($unread_id) || $tag_id == $unread_id)
        return false;

    /* items with all these tags */
    $matching_tag_ids = array($tag_id, $unread_id);

    /* all items with both unread and tagname */
    $items_query = "SELECT item_id FROM $FOF_ITEM_TAG_TABLE it WHERE it.user_id = $user_id AND it.tag_id IN (" . (count($matching_tag_ids) ? implode(', ', $matching_tag_ids) : "''") . ") GROUP BY it.item_id HAVING COUNT(DISTINCT it.tag_id) = " . count($matching_tag_ids);

    /* get rid of the unread ones */
    $untag_query = "DELETE FROM $FOF_ITEM_TAG_TABLE WHERE user_id = $user_id AND tag_id = $unread_id AND item_id IN ($items_query)";

    $result = fof_db_exec($untag_query);

    return $result;
}

function fof_db_mark_unread($user_id, $items)
{
    fof_trace();

    $tag_id = fof_db_get_tag_by_name('unread');
    return fof_db_tag_items($user_id, $tag_id, $items);
}

function fof_db_mark_read($user_id, $items)
{
    fof_trace();

    $tag_id = fof_db_get_tag_by_name('unread');
    return fof_db_untag_items($user_id, $tag_id, $items);
}

function fof_db_fold($user_id, $items)
{
    fof_trace();

    $tag_id = fof_db_get_tag_by_name('folded');
    return fof_db_tag_items($user_id, $tag_id, $items);
}

function fof_db_unfold($user_id, $items)
{
    fof_trace();

    $tag_id = fof_db_get_tag_by_name('folded');
    return fof_db_untag_items($user_id, $tag_id, $items);
}

function fof_db_mark_feed_read($user_id, $feed_id)
{
    fof_trace();

    $result = fof_db_get_items($user_id, $feed_id, $what="all");

    foreach($result as $r) {
        $items[] = $r['item_id'];
    }

    $tag_id = fof_db_get_tag_by_name('unread');
    fof_db_untag_items($user_id, $tag_id, $items);
}

function fof_db_mark_feed_unread($user_id, $feed_id, $what)
{
    fof_trace();

    if($what == 'all')
    {
        $result = fof_db_get_items($user_id, $feed_id, 'all');
    }
    if($what == 'today')
    {
        $result = fof_db_get_items($user_id, $feed_id, 'all', 'today');
    }

    $items = array();
    foreach($result as $r)
    {
        $items[] = $r['item_id'];
    }

    $tag_id = fof_db_get_tag_by_name('unread');

    fof_db_tag_items($user_id, $tag_id, $items);
}

/* sets unread tag on item_id for each user_id in array of users */
function fof_db_mark_item_unread($users, $item_id)
{
    global $FOF_ITEM_TAG_TABLE;
    global $fof_connection;

    fof_trace();

    if (count($users) == 0)
        return;

    $tag_id = fof_db_get_tag_by_name('unread');

    /*
        This query will need to be changed to work with a driver other than
        MySQL or SQLite.  It simply needs to be able to insert, and ignore
        existing row conflicts.
        Perhaps someone versed in SQL has suggestions.
    */

    if (defined('USE_SQLITE')) {

        $query = "INSERT OR IGNORE INFO $FOF_ITEM_TAG_TABLE (user_id, tag_id, item_id) VALUES (:user_id, :tag_id, :item_id)";
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':tag_id', $tag_id);
        $statement->bindValue(':item_id', $item_id);

        fof_db_exec('BEGIN TRANSACTION');
        foreach ($users as $user_id) {
            $statement->bindValue(':user_id', $user_id);
            $result = fof_db_statement_execute($statement);
            $statement->closeCursor();
        }
        fof_db_exec('COMMIT');
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
        $result = fof_db_exec($query);
        return;
    }
}

/* sets (user_id, tag_id, item_id) for each item_id in items array */
function fof_db_tag_items($user_id, $tag_id, $items)
{
    global $FOF_ITEM_TAG_TABLE;
    global $fof_connection;

    fof_trace();

    if (! $items)
        return;

    if (! is_array($items))
        $items = array($items);

    if (empty($items))
        return;

    /* This query will need to be changed to work with a driver other than MySQL or SQLite. */
    /* (also see fof_db_mark_item_unread) */

    if (defined('USE_SQLITE')) {
        $query = "INSERT OR IGNORE INTO $FOF_ITEM_TAG_TABLE (user_id, tag_id, item_id) VALUES (:user_id, :tag_id, :item_id)";
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':user_id', $user_id);
        $statement->bindValue(':tag_id', $tag_id);

        fof_db_exec('BEGIN TRANSACTION');
        foreach ($items as $item_id) {
            $statement->bindValue(':item_id', $item_id);
            $result = fof_db_statement_execute($statement);
            $statement->closeCursor();
        }
        fof_db_exec('COMMIT');
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
        $result = fof_db_exec($query);
        return;
    }
}

function fof_db_untag_items($user_id, $tag_id, $items)
{
    global $FOF_ITEM_TAG_TABLE;
    global $fof_connection;

    fof_trace();

    if (! $items)
        return;

    if (! is_array($items))
        $items = array($items);

    $items_q = array();
    foreach($items as $item) {
        $items_q[] = $fof_connection->quote($item);
    }

    $query = "DELETE FROM $FOF_ITEM_TAG_TABLE WHERE user_id = :user_id AND tag_id = :tag_id AND item_id IN ( " . (count($items_q) ? implode(', ', $items_q) : "''") . " )";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':user_id', $user_id);
    $statement->bindValue(':tag_id', $tag_id);
    $result = fof_db_statement_execute($statement);
    $statement->closeCursor();
}


////////////////////////////////////////////////////////////////////////////////
// User stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_user_password_hash($password, $user)
{
    return md5($password . $user);
}

/* returns array of user info, keyed by user_id */
function fof_db_get_users()
{
    global $FOF_USER_TABLE;
    global $fof_connection;
    $users = array();

    fof_trace();

    $query = "SELECT user_name, user_id, user_prefs FROM $FOF_USER_TABLE";
    $statement = fof_db_query($query);
    while (($row = fof_db_get_row($statement)) !== false) {
        $users[$row['user_id']]['user_name'] = $row['user_name'];
        $users[$row['user_id']]['user_prefs'] = unserialize($row['user_prefs']);
    }

    return $users;
}

function fof_db_get_nonadmin_usernames()
{
    global $FOF_USER_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "SELECT user_name FROM $FOF_USER_TABLE WHERE user_id > 1";
    $statement = fof_db_query($query);

    return $statement;
}

/* only used during install */
function fof_db_add_user_all($user_id, $user_name, $user_password, $user_level)
{
    global $FOF_USER_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "INSERT INTO $FOF_USER_TABLE (user_id, user_name, user_password_hash, user_level) VALUES (:id, :name, :password_hash, :level)";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':id', $user_id);
    $statement->bindValue(':name', $user_name);
    $statement->bindValue(':password_hash', fof_db_user_password_hash($user_password, $user_name));
    $statement->bindValue(':level', $user_level);
    $result = fof_db_statement_execute($statement);
    $statement->closeCursor();

    return $result;
}

function fof_db_add_user($username, $password)
{
    global $FOF_USER_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "INSERT INTO $FOF_USER_TABLE (user_name, user_password_hash) VALUES (:name, :password_hash)";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':password_hash', fof_db_user_password_hash($password, $username));
    $statement->bindValue(':name', $username);
    $result = fof_db_statement_execute($statement);
    $statement->closeCursor();

    return $result;
}

function fof_db_change_password($username, $password)
{
    global $FOF_USER_TABLE;
    global $fof_connection;

    $query = "UPDATE $FOF_USER_TABLE SET user_password_hash = :password_hash WHERE user_name = :name";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':password_hash', fof_db_user_password_hash($password, $username));
    $statement->bindValue(':name', $username);
    $result = fof_db_statement_execute($statement);
    $statement->closeCursor();

    return $result;
}

function fof_db_get_user($username) {
    global $FOF_USER_TABLE;
    global $fof_connection;

    $query = "SELECT * FROM $FOF_USER_TABLE WHERE user_name = :name";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':name', $username);
    $result = fof_db_statement_execute($statement);
    return fof_db_get_row($statement, NULL, TRUE);
}

function fof_db_get_user_id($username)
{
    global $FOF_USER_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "SELECT user_id FROM $FOF_USER_TABLE WHERE user_name = :name";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':name', $username);
    $result = fof_db_statement_execute($statement);

    return fof_db_get_row($statement, 'user_id', TRUE);
}

function fof_db_delete_user($username)
{
    global $FOF_USER_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE;
    global $fof_connection;

    fof_trace();

    $user_id = fof_db_get_user_id($username);

    $tables = array($FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_USER_TABLE);
    foreach($tables as $table)
    {
        $query = "DELETE FROM $table WHERE user_id = :id";
        $statement = $fof_connection->prepare($query);
        $statement->bindValue(':id', $user_id);
        $result = fof_db_statement_execute($statement);
        $statement->closeCursor();
    }
}

function fof_db_prefs_get($user_id)
{
    global $FOF_USER_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "SELECT user_prefs FROM $FOF_USER_TABLE WHERE user_id = :user_id";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':user_id', $user_id);
    $result = fof_db_statement_execute($statement);
    $prefs = fof_db_get_row($statement, 'user_prefs', TRUE);

    return unserialize($prefs);
}

function fof_db_save_prefs($user_id, $prefs)
{
    global $FOF_USER_TABLE;
    global $fof_connection;

    fof_trace();

    $query = "UPDATE $FOF_USER_TABLE SET user_prefs = :prefs WHERE user_id = :id";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':id', $user_id);
    $statement->bindValue(':prefs', serialize($prefs));
    $result = fof_db_statement_execute($statement);
    $statement->closeCursor();
}

/* check user and password hash, set global user info if matching record found */
function fof_db_authenticate_hash($user_name, $user_password_hash)
{
    global $FOF_USER_TABLE;
    global $fof_connection, $fof_user_id, $fof_user_name, $fof_user_level;

    fof_trace();

    $query = "SELECT * FROM $FOF_USER_TABLE WHERE user_name = :name AND user_password_hash = :password_hash";
    $statement = $fof_connection->prepare($query);
    $statement->bindValue(':name', $user_name);
    $statement->bindValue(':password_hash', $user_password_hash);
    $result = fof_db_statement_execute($statement);
    $row = fof_db_get_row($statement, NULL, TRUE);

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
