<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * init.php - (nearly) all of the DB specific code
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

////////////////////////////////////////////////////////////////////////////////
// Utilities
////////////////////////////////////////////////////////////////////////////////

function fof_db_connect()
{
    global $fof_connection;
    
    $fof_connection = mysql_connect(FOF_DB_HOST, FOF_DB_USER, FOF_DB_PASS) or die("<br><br>Cannot connect to database.  Please update configuration in <b>fof-config.php</b>.  Mysql says: <i>" . mysql_error() . "</i>");
    mysql_select_db(FOF_DB_DBNAME, $fof_connection) or die("<br><br>Cannot select database.  Please update configuration in <b>fof-config.php</b>.  Mysql says: <i>" . mysql_error() . "</i>");
}

function fof_db_optimize()
{
	global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_TAG_TABLE, $FOF_USER_TABLE;
    
	fof_db_query("optimize table $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_TAG_TABLE, $FOF_USER_TABLE");
}

function fof_safe_query(/* $query, [$args...]*/)
{
    $args  = func_get_args();
    $query = array_shift($args);
    if(is_array($args[0])) $args = $args[0];
    $args  = array_map('mysql_real_escape_string', $args);
    $query = vsprintf($query, $args);
    
    return fof_db_query($query);
}

function fof_db_query($sql, $live=0)
{   
    global $fof_connection;
    
    list($usec, $sec) = explode(" ", microtime()); 
    $t1 = (float)$sec + (float)$usec;
    
    $result = mysql_query($sql, $fof_connection);
    
    if(is_resource($result)) $num = mysql_num_rows($result);
    if($result) $affected = mysql_affected_rows();
    
    list($usec, $sec) = explode(" ", microtime()); 
    $t2 = (float)$sec + (float)$usec;
    $elapsed = $t2 - $t1;
    $logmessage = sprintf("%.3f: [%s] (%d / %d)", $elapsed, $sql, $num, $affected);
    fof_log($logmessage);
    
    if($live)
    {
        return $result;
    }
    else
    {
        if(mysql_errno()) 
        {
            //echo "<pre>";
            //print_r(debug_backtrace());
            //echo "</pre>";
            die("Cannot query database.  Have you run <a href=\"install.php\"><code>install.php</code></a> to create or upgrade your installation? MySQL says: <b>". mysql_error() . "</b>");
        }
        return $result;
    }
}

function fof_db_get_row($result)
{
    return mysql_fetch_array($result);
}


////////////////////////////////////////////////////////////////////////////////
// Feed level stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_feed_mark_cached($feed_id)
{
    global $FOF_FEED_TABLE;
    
	$result = fof_safe_query("update $FOF_FEED_TABLE set feed_cache_date = %d where feed_id = %d", time(), $feed_id);
}

function fof_db_feed_mark_attempted_cache($feed_id)
{
    global $FOF_FEED_TABLE;
    
	$result = fof_safe_query("update $FOF_FEED_TABLE set feed_cache_attempt_date = %d where feed_id = %d", time(), $feed_id);
}

function fof_db_feed_update_metadata($feed_id, $url, $title, $link, $description, $image)
{
    global $FOF_FEED_TABLE;
    
    $sql = "update $FOF_FEED_TABLE set feed_url = '%s', feed_title = '%s', feed_link = '%s', feed_description = '%s'";
    $args = array($url, $title, $link, $description);
    
	if($image)
	{
		$sql .= ", feed_image = '%s' ";
        $args[] = $image;
	}
	else
	{
		$sql .= ", feed_image = NULL ";
	}
	
	$sql .= "where feed_id = %d";
    $args[] = $feed_id;
    
	$result = fof_safe_query($sql, $args);
}

function fof_db_get_latest_item_age($user_id)
{
    global $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TABLE;
    
	$result = fof_db_query("SELECT max( item_cached ) AS \"max_date\", $FOF_ITEM_TABLE.feed_id as \"id\" FROM $FOF_ITEM_TABLE GROUP BY $FOF_ITEM_TABLE.feed_id");
	return $result;	
}

function fof_db_get_subscriptions($user_id)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
    
    return(fof_safe_query("select * from $FOF_FEED_TABLE, $FOF_SUBSCRIPTION_TABLE where $FOF_SUBSCRIPTION_TABLE.user_id = %d and $FOF_FEED_TABLE.feed_id = $FOF_SUBSCRIPTION_TABLE.feed_id order by feed_title", $user_id));
}

function fof_db_get_feeds()
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
    
    return(fof_db_query("select * from $FOF_FEED_TABLE order by feed_title"));
}

function fof_db_get_item_count($user_id)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
    
    return(fof_safe_query("select count(*) as count, $FOF_ITEM_TABLE.feed_id as id from $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE where $FOF_SUBSCRIPTION_TABLE.user_id = %d and $FOF_ITEM_TABLE.feed_id = $FOF_SUBSCRIPTION_TABLE.feed_id group by id", $user_id));
}

function fof_db_get_unread_item_count($user_id)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
    
    return(fof_safe_query("select count(*) as count, $FOF_ITEM_TABLE.feed_id as id from $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_FEED_TABLE where $FOF_ITEM_TABLE.item_id = $FOF_ITEM_TAG_TABLE.item_id and $FOF_SUBSCRIPTION_TABLE.user_id = $user_id and  $FOF_ITEM_TAG_TABLE.tag_id = 1 and $FOF_ITEM_TAG_TABLE.user_id = %d and $FOF_FEED_TABLE.feed_id = $FOF_SUBSCRIPTION_TABLE.feed_id and $FOF_ITEM_TABLE.feed_id = $FOF_FEED_TABLE.feed_id group by id", $user_id));
}

function fof_db_get_starred_item_count($user_id)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
    
    return(fof_safe_query("select count(*) as count, $FOF_ITEM_TABLE.feed_id as id from $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_FEED_TABLE where $FOF_ITEM_TABLE.item_id = $FOF_ITEM_TAG_TABLE.item_id and $FOF_SUBSCRIPTION_TABLE.user_id = $user_id and  $FOF_ITEM_TAG_TABLE.tag_id = 2 and $FOF_ITEM_TAG_TABLE.user_id = %d and $FOF_FEED_TABLE.feed_id = $FOF_SUBSCRIPTION_TABLE.feed_id and $FOF_ITEM_TABLE.feed_id = $FOF_FEED_TABLE.feed_id group by id", $user_id));
}

function fof_db_get_subscribed_users($feed_id)
{
    global $FOF_SUBSCRIPTION_TABLE;
    
    return(fof_safe_query("select user_id from $FOF_SUBSCRIPTION_TABLE where $FOF_SUBSCRIPTION_TABLE.feed_id = %d", $feed_id));
}

function fof_db_is_subscribed($user_id, $feed_url)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
    
    $result = fof_safe_query("select $FOF_SUBSCRIPTION_TABLE.feed_id from $FOF_FEED_TABLE, $FOF_SUBSCRIPTION_TABLE where feed_url='%s' and $FOF_SUBSCRIPTION_TABLE.feed_id = $FOF_FEED_TABLE.feed_id and $FOF_SUBSCRIPTION_TABLE.user_id = %d", $feed_url, $user_id);
    
    if(mysql_num_rows($result) == 0)
    {
        return false;
    }
    
    return true;
}

function fof_db_get_feed_by_url($feed_url)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
    
    $result = fof_safe_query("select * from $FOF_FEED_TABLE where feed_url='%s'", $feed_url);
    
    if(mysql_num_rows($result) == 0)
    {
        return NULL;
    }
    
    $row = mysql_fetch_array($result);
    
    return $row;
}

function fof_db_get_feed_by_id($feed_id)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
    
    $result = fof_safe_query("select * from $FOF_FEED_TABLE where feed_id=%d", $feed_id);
    
    $row = mysql_fetch_array($result);
    
    return $row;
}

function fof_db_add_feed($url, $title, $link, $description)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $fof_connection;
    
    fof_safe_query("insert into $FOF_FEED_TABLE (feed_url,feed_title,feed_link,feed_description) values ('%s', '%s', '%s', '%s')", $url, $title, $link, $description);
    
    return(mysql_insert_id($fof_connection));
}

function fof_db_add_subscription($user_id, $feed_id)
{
    global $FOF_SUBSCRIPTION_TABLE;
    
    fof_safe_query("insert into $FOF_SUBSCRIPTION_TABLE (feed_id, user_id) values (%d, %d)", $feed_id, $user_id);
}

function fof_db_delete_subscription($user_id, $feed_id)
{
    global $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
        
	$result = fof_db_get_items($user_id, $feed_id, $what="all", NULL, NULL);
    
    foreach($result as $r)
    {
        $items[] = $r['item_id'];
    }
    
    $itemclause = join(", ", $items);

    fof_safe_query("delete from $FOF_SUBSCRIPTION_TABLE where feed_id = %d and user_id = %d", $feed_id, $user_id);

    fof_safe_query("delete from $FOF_ITEM_TAG_TABLE where user_id = %d and item_id in ($itemclause)", $user_id);
}

function fof_db_delete_feed($feed_id)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE;
    
    fof_safe_query("delete from $FOF_FEED_TABLE where feed_id = %d", $feed_id);
    fof_safe_query("delete from $FOF_ITEM_TABLE where feed_id = %d", $feed_id);
}


////////////////////////////////////////////////////////////////////////////////
// Item level stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_find_item($feed_id, $item_guid)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $fof_connection;
    
    $result = fof_safe_query("select item_id from $FOF_ITEM_TABLE where feed_id=%d and item_guid='%s'", $feed_id, $item_guid);
    $row = mysql_fetch_array($result);
    
    if(mysql_num_rows($result) == 0)
    {
        return NULL;
    }
    else
    {
        return($row['item_id']);
    }
}

function fof_db_add_item($feed_id, $guid, $link, $title, $content, $cached, $published, $updated)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $fof_connection;
    
    fof_safe_query("insert into $FOF_ITEM_TABLE (feed_id, item_link, item_guid, item_title, item_content, item_cached, item_published, item_updated) values (%d, '%s', '%s' ,'%s', '%s', %d, %d, %d)",
    $feed_id, $link, $guid, $title, $content, $cached, $published, $updated);
    
    return(mysql_insert_id($fof_connection));
}

function fof_db_get_items($user_id=1, $feed=NULL, $what="unread", $when=NULL, $start=NULL, $limit=NULL, $order="desc", $search=NULL)
{
    global $FOF_SUBSCRIPTION_TABLE, $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_TAG_TABLE;
    
    $prefs = fof_prefs();
    $offset = $prefs['tzoffset'];
    
    if(!is_null($when) && $when != "")
    {
        if($when == "today")
        {
            $whendate = fof_todays_date();
        }
        else
        {
            $whendate = $when;
        }
        
        $whendate = explode("/", $whendate);
        $begin = gmmktime(0, 0, 0, $whendate[1], $whendate[2], $whendate[0]) - ($offset * 60 * 60);
        $end = $begin + (24 * 60 * 60);
    }
    
    if(is_numeric($start))
    {
        if(!is_numeric($limit))
        {
            $limit = $prefs["howmany"];
        }
        
        $limit_clause = " limit $start, $limit ";
    }
    
    $args = array();
    $select = "SELECT i.* , f.* ";
    $from = "FROM $FOF_FEED_TABLE f, $FOF_ITEM_TABLE i, $FOF_SUBSCRIPTION_TABLE s ";
    $where = sprintf("WHERE s.user_id = %d AND s.feed_id = f.feed_id AND f.feed_id = i.feed_id ", $user_id);
 
    if(!is_null($feed) && $feed != "")
    {
        $where .= sprintf("AND f.feed_id = %d ", $feed);
    }
    
    if(!is_null($when) && $when != "")
    {
        $where .= sprintf("AND i.item_published > %d and i.item_published < %d ", $begin, $end);
    }
    
    if($what != "all")
    {
        $tags = split(" ", $what);
        $in = implode(", ", array_fill(0, count($tags), "'%s'"));
        $from .= ", $FOF_TAG_TABLE t, $FOF_ITEM_TAG_TABLE it ";
        $where .= sprintf("AND it.user_id = %d ", $user_id);
        $where .= "AND it.tag_id = t.tag_id AND ( t.tag_name IN ( $in ) ) AND i.item_id = it.item_id ";
        $group = sprintf("GROUP BY i.item_id HAVING COUNT( i.item_id ) = %d ", count($tags));
        $args = array_merge($args, $tags);
    }
    
    if(!is_null($search) && $search != "")
    {
        $where .= "AND (i.item_title like '%%%s%%'  or i.item_content like '%%%s%%' )";
        $args[] = $search;
        $args[] = $search;
    }
    
    $order = "order by i.item_published desc $limit_clause ";
    
    $query = $select . $from . $where . $group . $order;
    
    $result = fof_safe_query($query, $args);
    
    if(mysql_num_rows($result) == 0)
    {
        return array();
    }
    	
    while($row = mysql_fetch_assoc($result))
    {
        $array[] = $row;
    }
    
    $array = fof_multi_sort($array, 'item_published', $order != "asc");
    
    $i = 0;
    foreach($array as $item)
    {
        $ids[] = $item['item_id'];
        $lookup[$item['item_id']] = $i;
        $array[$i]['tags'] = array();
        
        $i++;
    }
    
    $items = join($ids, ", ");
    
    $result = fof_safe_query("select $FOF_TAG_TABLE.tag_name, $FOF_ITEM_TAG_TABLE.item_id from $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE where $FOF_TAG_TABLE.tag_id = $FOF_ITEM_TAG_TABLE.tag_id and $FOF_ITEM_TAG_TABLE.item_id in (%s) and $FOF_ITEM_TAG_TABLE.user_id = %d", $items, $user_id);
    
    while($row = fof_db_get_row($result))
    {
        $item_id = $row['item_id'];
        $tag = $row['tag_name'];
        
        $array[$lookup[$item_id]]['tags'][] = $tag;
    }

    return $array;
}

function fof_db_get_item($user_id, $item_id)
{
    global $FOF_SUBSCRIPTION_TABLE, $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_TAG_TABLE;
    
    $query = "select distinct $FOF_FEED_TABLE.feed_image as feed_image, $FOF_FEED_TABLE.feed_title as feed_title, $FOF_FEED_TABLE.feed_link as feed_link, $FOF_FEED_TABLE.feed_description as feed_description, $FOF_ITEM_TABLE.item_id as item_id, $FOF_ITEM_TABLE.item_link as item_link, $FOF_ITEM_TABLE.item_title as item_title, $FOF_ITEM_TABLE.item_cached, $FOF_ITEM_TABLE.item_published, $FOF_ITEM_TABLE.item_updated, $FOF_ITEM_TABLE.item_content as item_content from $FOF_FEED_TABLE, $FOF_ITEM_TABLE where $FOF_ITEM_TABLE.feed_id=$FOF_FEED_TABLE.feed_id and $FOF_ITEM_TABLE.item_id = %d";
    
    $result = fof_safe_query($query, $item_id);
    
    $item = mysql_fetch_assoc($result);
    
    $result = fof_safe_query("select $FOF_TAG_TABLE.tag_name from $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE where $FOF_TAG_TABLE.tag_id = $FOF_ITEM_TAG_TABLE.tag_id and $FOF_ITEM_TAG_TABLE.item_id = %d and $FOF_ITEM_TAG_TABLE.user_id = %d", $item_id, $user_id);

    $item['tags'] = array();
    
    while($row = fof_db_get_row($result))
    {
        $item['tags'][] = $row['tag_name'];
    }
    
    return $item;
}


////////////////////////////////////////////////////////////////////////////////
// Tag stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_get_subscription_to_tags()
{
    $r = array();
    global $FOF_SUBSCRIPTION_TABLE;
    $result = fof_safe_query("select * from $FOF_SUBSCRIPTION_TABLE");
    while($row = fof_db_get_row($result))
    {
        $prefs = unserialize($row['subscription_prefs']);
        $tags = $prefs['tags'];
        if(!is_array($r[$row['feed_id']])) $r[$row['feed_id']] = array();
        $r[$row['feed_id']][$row['user_id']] = $tags;
    }
    
    return $r;    
}

function fof_db_tag_feed($user_id, $feed_id, $tag_id)
{
    global $FOF_SUBSCRIPTION_TABLE;
    
    $result = fof_safe_query("select subscription_prefs from $FOF_SUBSCRIPTION_TABLE where feed_id = %d and user_id = %d", $feed_id, $user_id);
    $row = fof_db_get_row($result);
    $prefs = unserialize($row['subscription_prefs']);
    
    if(!is_array($prefs['tags']) || !in_array($tag_id, $prefs['tags'])) $prefs['tags'][] = $tag_id;
    
    fof_safe_query("update $FOF_SUBSCRIPTION_TABLE set subscription_prefs = '%s' where feed_id = %d and user_id = %d", serialize($prefs), $feed_id, $user_id);
}

function fof_db_untag_feed($user_id, $feed_id, $tag_id)
{
    global $FOF_SUBSCRIPTION_TABLE;
    
    $result = fof_safe_query("select subscription_prefs from $FOF_SUBSCRIPTION_TABLE where feed_id = %d and user_id = %d", $feed_id, $user_id);
    $row = fof_db_get_row($result);
    $prefs = unserialize($row['subscription_prefs']);
    
    if(is_array($prefs['tags']))
    {
        $prefs['tags'] = array_diff($prefs['tags'], array($tag_id));
    }
    
    fof_safe_query("update $FOF_SUBSCRIPTION_TABLE set subscription_prefs = '%s' where feed_id = %d and user_id = %d", serialize($prefs), $feed_id, $user_id);
}

function fof_db_get_item_tags($user_id, $item_id)
{
    global $FOF_TAG_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $fof_connection;
    
    $result = fof_safe_query("select $FOF_TAG_TABLE.tag_name from $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE where $FOF_TAG_TABLE.tag_id = $FOF_ITEM_TAG_TABLE.tag_id and $FOF_ITEM_TAG_TABLE.item_id = %d and $FOF_ITEM_TAG_TABLE.user_id = %d", $item_id, $user_id);
    
    return $result;   
}

function fof_db_item_has_tags($item_id)
{
    global $FOF_TAG_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $fof_connection;
    
    $result = fof_safe_query("select count(*) as \"count\" from $FOF_ITEM_TAG_TABLE where item_id=%d and tag_id <= 2", $item_id);
    $row = mysql_fetch_array($result);
    
    return $row["count"];
}

function fof_db_get_unread_count($user_id)
{
    global $FOF_ITEM_TAG_TABLE;
    
    $result = fof_safe_query("select count(*) as \"count\" from $FOF_ITEM_TAG_TABLE where tag_id = 1 and user_id = %d", $user_id); 
    $row = mysql_fetch_array($result);
    
    return $row["count"];
}

function fof_db_get_tag_unread($user_id)
{
    global $FOF_TAG_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE;

    $result = fof_safe_query("SELECT count(*) as count, it2.tag_id FROM $FOF_ITEM_TABLE i, $FOF_ITEM_TAG_TABLE it , $FOF_ITEM_TAG_TABLE it2 where it.item_id = it2.item_id and it.tag_id = 1 and i.item_id = it.item_id and i.item_id = it2.item_id and it.user_id = %d and it2.user_id = %d group by it2.tag_id", $user_id, $user_id);
    
    $counts = array();
    while($row = fof_db_get_row($result))
    {
        $counts[$row['tag_id']] = $row['count'];
    }
    
    return $counts;
}

function fof_db_get_tags($user_id)
{
    global $FOF_TAG_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $fof_connection;
    
    $sql = "SELECT $FOF_TAG_TABLE.tag_id, $FOF_TAG_TABLE.tag_name, count( $FOF_ITEM_TAG_TABLE.item_id ) as count
        FROM $FOF_TAG_TABLE
        LEFT JOIN $FOF_ITEM_TAG_TABLE ON $FOF_TAG_TABLE.tag_id = $FOF_ITEM_TAG_TABLE.tag_id
        WHERE $FOF_ITEM_TAG_TABLE.user_id = %d
        GROUP BY $FOF_TAG_TABLE.tag_id order by $FOF_TAG_TABLE.tag_name";
    
    $result = fof_safe_query($sql, $user_id);
    
    return $result;   
}

function fof_db_get_tag_id_map()
{
    global $FOF_TAG_TABLE;
    
    $sql = "select * from $FOF_TAG_TABLE";
    
    $result = fof_safe_query($sql);
    
    $tags = array();
    
    while($row = fof_db_get_row($result))
    {
        $tags[$row['tag_id']] = $row['tag_name'];
    }
    
    return $tags;   
}

function fof_db_create_tag($user_id, $tag)
{
    global $FOF_TAG_TABLE, $fof_connection;
    
    fof_safe_query("insert into $FOF_TAG_TABLE (tag_name) values ('%s')", $tag);
    
    return(mysql_insert_id($fof_connection));
}

function fof_db_get_tag_by_name($user_id, $tag)
{
    global $FOF_TAG_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $fof_connection;
    
    $result = fof_safe_query("select $FOF_TAG_TABLE.tag_id from $FOF_TAG_TABLE where $FOF_TAG_TABLE.tag_name = '%s'", $tag);
    
    if(mysql_num_rows($result) == 0)
    {
        return NULL;
    }
    
    $row = mysql_fetch_array($result);
    
    return $row['tag_id'];
}

function fof_db_mark_unread($user_id, $items)
{
    fof_db_tag_items($user_id, 1, $items);
}

function fof_db_mark_read($user_id, $items)
{
    fof_db_untag_items($user_id, 1, $items);
}

function fof_db_mark_feed_read($user_id, $feed_id)
{
    global $FOF_ITEM_TAG_TABLE;
    
    $result = fof_db_get_items($user_id, $feed_id, $what="all", NULL, NULL);
    
    foreach($result as $r)
    {
        $items[] = $r['item_id'];
    }
    
    fof_db_untag_items($user_id, 1, $items);
}

function fof_db_mark_feed_unread($user_id, $feed)
{
    global $FOF_ITEM_TAG_TABLE;
    
    $result = fof_db_get_items($user_id, $feed, $what="all", NULL, NULL, 10);
    
    foreach($result as $r)
    {
        $items[] = $r['item_id'];
    }
    
    fof_db_tag_items($user_id, 1, $items);
}

function fof_db_mark_item_unread($users, $id)
{
    global $FOF_ITEM_TAG_TABLE;
    
    foreach($users as $user)
    {
        $sql[] = sprintf("(%d, 1, %d)", $user, $id);
    }
    
    $values = implode ( ",", $sql );
    
	$sql = "insert into $FOF_ITEM_TAG_TABLE (user_id, tag_id, item_id) values " . $values;
	
	$result = fof_db_query($sql, 1);

    if(!$result && (mysql_errno() != 1062))
    {
        die("Cannot query database.  Have you run <a href=\"install.php\"><code>install.php</code></a> to create or upgrade your installation? MySQL says: <b>". mysql_error() . "</b>");
    }
}

function fof_db_tag_items($user_id, $tag_id, $items)
{
    global $FOF_ITEM_TAG_TABLE;

    if(!$items) return;
    
    if(!is_array($items)) $items = array($items);

    foreach($items as $item)
    {
        $sql[] = sprintf("(%d, %d, %d)", $user_id, $tag_id, $item);
    }
    
    $values = implode ( ",", $sql );
    
	$sql = "insert into $FOF_ITEM_TAG_TABLE (user_id, tag_id, item_id) values " . $values;
	
	$result = fof_db_query($sql, 1);
    
    if(!$result && (mysql_errno() != 1062))
    {
        die("Cannot query database.  Have you run <a href=\"install.php\"><code>install.php</code></a> to create or upgrade your installation? MySQL says: <b>". mysql_error() . "</b>");
    }
}

function fof_db_untag_items($user_id, $tag_id, $items)
{
    global $FOF_ITEM_TAG_TABLE;
    
    if(!$items) return;
    
    if(!is_array($items)) $items = array($items);
    
    foreach($items as $item)
    {
        $sql[] = " item_id = %d ";
        $args[] = $item;
    }
    
    $values = implode ( " or ", $sql );
    
    $sql = "delete from $FOF_ITEM_TAG_TABLE where user_id = %d and tag_id = %d and ( $values )";
    
    array_unshift($args, $tag_id);
    array_unshift($args, $user_id);

    fof_safe_query($sql, $args);
}


////////////////////////////////////////////////////////////////////////////////
// User stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_add_user($username, $password)
{
    global $FOF_USER_TABLE;
    
	$password_hash = md5($password . $username);
    
	fof_safe_query("insert into $FOF_USER_TABLE (user_name, user_password_hash) values ('%s', '%s')", $username, $password_hash);
}

function fof_db_change_password($username, $password)
{
    global $FOF_USER_TABLE;
    
	$password_hash = md5($password . $username);
    
	fof_safe_query("update $FOF_USER_TABLE set user_password_hash = '%s' where user_name = '%s'", $password_hash, $username);
}

function fof_db_get_user_id($username)
{
    global $FOF_USER_TABLE;
    $result = fof_safe_query("select user_id from $FOF_USER_TABLE where user_name = '%s'", $username);
    $row = mysql_fetch_array($result);
    
    return $row['user_id'];
}

function fof_db_delete_user($username)
{
    global $FOF_USER_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE;
    $user_id = fof_db_get_user_id($username);
    
    fof_safe_query("delete from $FOF_SUBSCRIPTION_TABLE where user_id = %d", $user_id);
    fof_safe_query("delete from $FOF_ITEM_TAG_TABLE where user_id = %d", $user_id);
    fof_safe_query("delete from $FOF_USER_TABLE where user_id = %d", $user_id);
}

function fof_db_save_prefs($user_id, $prefs)
{
    global $FOF_USER_TABLE, $fof_user_prefs;
    
    $prefs = serialize($fof_user_prefs);
    
    fof_safe_query("update $FOF_USER_TABLE set user_prefs = '%s' where user_id = %d", $prefs, $user_id);
}

function fof_db_authenticate($user_name, $user_password_hash)
{
    global $FOF_USER_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $fof_connection, $fof_user_id, $fof_user_name, $fof_user_level, $fof_user_prefs, $fof_admin_prefs;
    
    $result = fof_safe_query("select * from $FOF_USER_TABLE where user_name = '%s' and user_password_hash = '%s'", $user_name, $user_password_hash);
    
    if(mysql_num_rows($result) == 0)
    {
        return false;
    }
    
    $row = mysql_fetch_array($result);
    
    $fof_user_name = $row['user_name'];
    $fof_user_id = $row['user_id'];
    $fof_user_level = $row['user_level'];
    $fof_user_prefs = unserialize($row['user_prefs']);
    
    if(!is_array($fof_user_prefs)) $fof_user_prefs = array();
    if(!isset($fof_user_prefs['favicons'])) $fof_user_prefs['favicons'] = true;
    if(!isset($fof_user_prefs['keyboard'])) $fof_user_prefs['keyboard'] = false;
    if(!isset($fof_user_prefs['direction'])) $fof_user_prefs['direction'] = "desc";
    if(!isset($fof_user_prefs['howmany'])) $fof_user_prefs['howmany'] = 50;
    
    if($fof_user_id != 1)
    {
        $result = fof_safe_query("select user_prefs from $FOF_USER_TABLE where user_id = 1");
                
        $row = mysql_fetch_array($result);
        
        $fof_admin_prefs = unserialize($row['user_prefs']);
    }
    else
    {
        $fof_admin_prefs = $fof_user_prefs;
    }
    
    if(!isset($fof_admin_prefs['purge'])) $fof_admin_prefs['purge'] = 30;
    if(!isset($fof_admin_prefs['autotimeout'])) $fof_admin_prefs['autotimeout'] = 30;
    if(!isset($fof_admin_prefs['manualtimeout'])) $fof_admin_prefs['manualtimeout'] = 15;
    
    return true;
}

?>
