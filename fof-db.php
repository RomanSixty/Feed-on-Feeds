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

function fof_db_connect()
{
    global $fof_connection;
    
    $fof_connection = mysql_connect(FOF_DB_HOST, FOF_DB_USER, FOF_DB_PASS) or die("<br><br>Cannot connect to database.  Please update configuration in <b>fof-config.php</b>.  Mysql says: <i>" . mysql_error() . "</i>");
    mysql_select_db(FOF_DB_DBNAME, $fof_connection) or die("<br><br>Cannot select database.  Please update configuration in <b>fof-config.php</b>.  Mysql says: <i>" . mysql_error() . "</i>");
}

function fof_db_query($sql, $live=0)
{
   //echo "[$sql]<br>\n";
   
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
      die("Cannot query database.  Have you run <a href=\"install.php\"><code>install.php</code></a>? MySQL says: <b>". mysql_error() . "</b>");
      }
      return $result;
   }
}

function fof_db_get_row($result)
{
    return mysql_fetch_array($result);
}

function fof_db_feed_mark_cached($feed_id)
{
    global $FOF_FEED_TABLE;

	$sql = "update $FOF_FEED_TABLE set feed_cache_date = " . time() . " where feed_id = '$feed_id'";
	$result = fof_db_query($sql);
}


function fof_db_feed_update_metadata($feed_id, $url, $title, $link, $description, $image)
{
    global $FOF_FEED_TABLE;
    
   $url = mysql_escape_string($url);
   $title = mysql_escape_string($title);
   $link = mysql_escape_string($link);
   $description = mysql_escape_string($description);
   $image = mysql_escape_string($image);

	$sql = "update $FOF_FEED_TABLE set feed_url = '$url', feed_title = '$title', feed_link = '$link', feed_description = '$description'";

	if($image)
	{
		$sql .= ", feed_image = '$image' ";
	}
	else
	{
		$sql .= ", feed_image = NULL ";
	}
	
	$sql .= "where feed_id = '$feed_id'";
	$result = fof_db_query($sql);
}

function fof_db_get_latest_item_age($user_id)
{
    global $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TABLE;

	$sql = "SELECT max( item_cached ) AS  \"max_date\", $FOF_ITEM_TABLE.feed_id as \"id\" FROM $FOF_ITEM_TABLE GROUP BY $FOF_ITEM_TABLE.feed_id";
	$result = fof_db_query($sql);
	return $result;	
}

function fof_db_get_subscriptions($user_id)
{
   global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;

   return(fof_db_query("select * from $FOF_FEED_TABLE, $FOF_SUBSCRIPTION_TABLE where $FOF_SUBSCRIPTION_TABLE.user_id = $user_id and $FOF_FEED_TABLE.feed_id = $FOF_SUBSCRIPTION_TABLE.feed_id order by feed_title"));
}

function fof_db_get_feeds()
{
   global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;

   return(fof_db_query("select * from $FOF_FEED_TABLE order by feed_title"));
}

function fof_db_get_item_count($user_id)
{
   global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;

    return(fof_db_query("select count(*) as count, $FOF_ITEM_TABLE.feed_id as id from $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE where $FOF_SUBSCRIPTION_TABLE.user_id = $user_id and $FOF_ITEM_TABLE.feed_id = $FOF_SUBSCRIPTION_TABLE.feed_id group by id"));
}

function fof_db_get_unread_item_count($user_id)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;

    return(fof_db_query("select count(*) as count, $FOF_ITEM_TABLE.feed_id as id from $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_FEED_TABLE where $FOF_ITEM_TABLE.item_id = $FOF_ITEM_TAG_TABLE.item_id and $FOF_SUBSCRIPTION_TABLE.user_id = $user_id and  $FOF_ITEM_TAG_TABLE.tag_id = 1 and $FOF_ITEM_TAG_TABLE.user_id = $user_id and $FOF_FEED_TABLE.feed_id = $FOF_SUBSCRIPTION_TABLE.feed_id and $FOF_ITEM_TABLE.feed_id = $FOF_FEED_TABLE.feed_id group by id"));
}

function fof_db_get_starred_item_count($user_id)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;

    return(fof_db_query("select count(*) as count, $FOF_ITEM_TABLE.feed_id as id from $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_FEED_TABLE where $FOF_ITEM_TABLE.item_id = $FOF_ITEM_TAG_TABLE.item_id and $FOF_SUBSCRIPTION_TABLE.user_id = $user_id and  $FOF_ITEM_TAG_TABLE.tag_id = 2 and $FOF_ITEM_TAG_TABLE.user_id = $user_id and $FOF_FEED_TABLE.feed_id = $FOF_SUBSCRIPTION_TABLE.feed_id and $FOF_ITEM_TABLE.feed_id = $FOF_FEED_TABLE.feed_id group by id"));
}

function fof_db_get_subscribed_users($feed_id)
{
    global $FOF_SUBSCRIPTION_TABLE;
    
    return(fof_db_query("select user_id from $FOF_SUBSCRIPTION_TABLE where $FOF_SUBSCRIPTION_TABLE.feed_id = $feed_id"));
}

function fof_db_mark_item_unread($users, $id)
{
    foreach($users as $user)
    {
        $sql[] = "($user, 1, $id)";
    }
    
    $values = implode ( ",", $sql );

	$sql = "insert into  " . FOF_ITEM_TAG_TABLE . "(user_id, tag_id, item_id) values " . $values;
	
	fof_db_query($sql, 1);
}

function fof_db_is_subscribed($user_id, $feed_url)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;

    $safeurl = mysql_escape_string( $feed_url );
    $result = fof_db_query("select $FOF_SUBSCRIPTION_TABLE.feed_id from $FOF_FEED_TABLE, $FOF_SUBSCRIPTION_TABLE where feed_url='$safeurl' and $FOF_SUBSCRIPTION_TABLE.feed_id = $FOF_FEED_TABLE.feed_id and $FOF_SUBSCRIPTION_TABLE.user_id = $user_id");
        
    if(mysql_num_rows($result) == 0)
    {
        return false;
    }
    
    return true;
}

function fof_db_get_feed_by_url($feed_url)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;

    $safeurl = mysql_escape_string( $feed_url );
    $result = fof_db_query("select * from $FOF_FEED_TABLE where feed_url='$safeurl'");
        
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

    $result = fof_db_query("select * from $FOF_FEED_TABLE where feed_id='$feed_id'");
    
    $row = mysql_fetch_array($result);

    return $row;
}

function fof_db_find_item($feed_id, $item_guid)
{
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $fof_connection;

    $guid = mysql_escape_string($item_guid);

    $result = fof_db_query("select item_id from $FOF_ITEM_TABLE where feed_id=$feed_id and item_guid='$guid'");
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

    $guid = mysql_escape_string($guid);
    $link = mysql_escape_string($link);
    $title = mysql_escape_string($title);
    $content = mysql_escape_string($content);

    $sql = "insert into $FOF_ITEM_TABLE (feed_id,item_link,item_guid,item_title,item_content, item_cached, item_published, item_updated) values ('$feed_id','$link','$guid','$title','$content', $cached, $published, $updated)";

    fof_db_query($sql);
    
    return(mysql_insert_id($fof_connection));
}

function fof_db_add_feed($url, $title, $link, $description)
{
   global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $fof_connection;

   $url = mysql_escape_string($url);
   $title = mysql_escape_string($title);
   $link = mysql_escape_string($link);
   $description = mysql_escape_string($description);

   $sql = "insert into $FOF_FEED_TABLE (feed_url,feed_title,feed_link,feed_description) values ('$url','$title','$link','$description')";
   
   fof_db_query($sql);
   
   return(mysql_insert_id($fof_connection));
}

function fof_db_add_subscription($user_id, $feed_id)
{
   global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $fof_connection;

   $sql = "insert into $FOF_SUBSCRIPTION_TABLE (feed_id, user_id) values ($feed_id, $user_id)";
   
   fof_db_query($sql);
}

function fof_db_delete_subscription($user_id, $feed_id)
{
   global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $fof_connection;

   $sql = "delete from $FOF_SUBSCRIPTION_TABLE where feed_id = $feed_id and user_id = $user_id";
   
   fof_db_query($sql);
}

function fof_db_delete_feed($feed_id)
{
   global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $fof_connection;

   $sql = "delete from $FOF_FEED_TABLE where feed_id = $feed_id";
   fof_db_query($sql);

   $sql = "delete from $FOF_ITEM_TABLE where feed_id = $feed_id";
   fof_db_query($sql);
}

function fof_db_save_prefs($user_id, $prefs)
{
   global $FOF_USER_TABLE, $fof_connection, $fof_user_id, $fof_user_name, $fof_user_level, $fof_user_prefs;
   
   $prefs = mysql_escape_string(serialize($prefs));
   
   $sql = "update $FOF_USER_TABLE set user_prefs = '$prefs' where user_id = $user_id";
   
   fof_db_query($sql);
}

function fof_db_authenticate($user_name, $user_password_hash)
{
   global $FOF_USER_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $fof_connection, $fof_user_id, $fof_user_name, $fof_user_level, $fof_user_prefs;
   
   $sql = "select * from $FOF_USER_TABLE where user_name = '$user_name' and md5(user_password) = '" . mysql_escape_string($user_password_hash) . "'";
   
   $result = fof_db_query($sql);
   
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
    if(!isset($fof_user_prefs['favicons'])) $fof_user_prefs['favicons'] = false;
    if(!isset($fof_user_prefs['keyboard'])) $fof_user_prefs['keyboard'] = false;
   
   return true;
}

function fof_db_get_item_tags($user_id, $item_id)
{
   global $FOF_TAG_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $fof_connection;

   $sql = "select $FOF_TAG_TABLE.tag_name from $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE where $FOF_TAG_TABLE.tag_id = $FOF_ITEM_TAG_TABLE.tag_id and $FOF_ITEM_TAG_TABLE.item_id = $item_id and $FOF_ITEM_TAG_TABLE.user_id = $user_id";
   
   $result = fof_db_query($sql);

    return $result;   
}

function fof_db_item_has_tags($item_id)
{
   global $FOF_TAG_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $fof_connection;

   $sql = "select count(*) as \"count\" from $FOF_ITEM_TAG_TABLE where item_id=$item_id";
   $result = fof_db_query($sql);
   $row = mysql_fetch_array($result);
   
   return $row["count"];
}

function fof_db_get_tags($user_id)
{
   global $FOF_TAG_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $fof_connection;

   $sql = "SELECT $FOF_TAG_TABLE.tag_id, $FOF_TAG_TABLE.tag_name, count( $FOF_ITEM_TAG_TABLE.item_id ) as count
FROM $FOF_TAG_TABLE
LEFT JOIN $FOF_ITEM_TAG_TABLE ON $FOF_TAG_TABLE.tag_id = $FOF_ITEM_TAG_TABLE.tag_id
WHERE $FOF_ITEM_TAG_TABLE.user_id = $user_id
GROUP BY $FOF_TAG_TABLE.tag_id order by $FOF_TAG_TABLE.tag_name";
   
   $result = fof_db_query($sql);

    return $result;   
}

function fof_db_create_tag($user_id, $tag)
{
    global $fof_connection;
    
    $sql = "insert into " .FOF_TAG_TABLE. " (tag_name) values ('$tag')";
    
    fof_db_query($sql);
    
    return(mysql_insert_id($fof_connection));
}

function fof_db_tag_item($user_id, $item_id, $tag_id)
{
    $sql = "insert into " .FOF_ITEM_TAG_TABLE. " (user_id, item_id, tag_id) values ($user_id, $item_id, $tag_id)";

    fof_db_query($sql);
}

function fof_db_untag_item($user_id, $item_id, $tag_id)
{
    $sql = "delete from " .FOF_ITEM_TAG_TABLE. " where user_id = $user_id and item_id = $item_id and tag_id = $tag_id";

    fof_db_query($sql);
}


function fof_db_get_tag_by_name($user_id, $tag)
{
   global $FOF_TAG_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $fof_connection;

   $sql = "select $FOF_TAG_TABLE.tag_id from $FOF_TAG_TABLE where $FOF_TAG_TABLE.tag_name = '$tag'";
   
   $result = fof_db_query($sql);

    if(mysql_num_rows($result) == 0)
    {
        return NULL;
    }
    
    $row = mysql_fetch_array($result);
    
    return $row['tag_id'];
}

function fof_db_get_items($user_id=1, $feed=NULL, $what="unread", $when=NULL, $start=NULL, $limit=NULL, $order="desc", $search=NULL)
{
   global $FOF_SUBSCRIPTION_TABLE, $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_TAG_TABLE;

   if(!is_null($when) && $when != "")
   {
     if($when == "today")
     {
      $whendate = date( "Y/m/d", time() - (FOF_TIME_OFFSET * 60 * 60) );
     }
     else
     {
      $whendate = $when;
     }

     $begin = strtotime($whendate);
     $begin = $begin + (FOF_TIME_OFFSET * 60 * 60);
     $end = $begin + (24 * 60 * 60);

     $tomorrow = date( "Y/m/d", $begin + (24 * 60 * 60) );
     $yesterday = date( "Y/m/d", $begin - (24 * 60 * 60) );
   }

   if(is_numeric($start))
   {
      if(!is_numeric($limit))
      {
         $limit = FOF_HOWMANY;
      }

      $limit_clause = " limit $start, $limit ";
   }

   $query = "select distinct $FOF_FEED_TABLE.feed_title as feed_title, $FOF_FEED_TABLE.feed_link as feed_link, $FOF_FEED_TABLE.feed_description as feed_description, $FOF_ITEM_TABLE.item_id as item_id, $FOF_ITEM_TABLE.item_link as item_link, $FOF_ITEM_TABLE.item_title as item_title, $FOF_ITEM_TABLE.item_cached, $FOF_ITEM_TABLE.item_published, $FOF_ITEM_TABLE.item_updated, $FOF_ITEM_TABLE.item_content as item_content from $FOF_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_FEED_TABLE, $FOF_ITEM_TABLE left outer join $FOF_ITEM_TAG_TABLE on $FOF_ITEM_TABLE.item_id = $FOF_ITEM_TAG_TABLE.item_id where $FOF_ITEM_TABLE.feed_id=$FOF_FEED_TABLE.feed_id and $FOF_SUBSCRIPTION_TABLE.user_id = $user_id and $FOF_FEED_TABLE.feed_id = $FOF_SUBSCRIPTION_TABLE.feed_id";

   if(!is_null($feed) && $feed != "")
   {
     $query .= " and $FOF_FEED_TABLE.feed_id = $feed";
   }

   if(!is_null($when) && $when != "")
   {
     $query .= " and $FOF_ITEM_TABLE.item_cached > $begin and $FOF_ITEM_TABLE.item_cached < $end";
   }

   if($what != "all")
   {
     $query .= " and $FOF_ITEM_TABLE.item_id = $FOF_ITEM_TAG_TABLE.item_id and $FOF_ITEM_TAG_TABLE.tag_id = $FOF_TAG_TABLE.tag_id and $FOF_TAG_TABLE.tag_name = '$what' and $FOF_ITEM_TAG_TABLE.user_id = $user_id";
   }

   if(!is_null($search) && $search != "")
   {
     $query .= " and ($FOF_ITEM_TABLE.item_title like '%$search%'  or $FOF_ITEM_TABLE.item_content like '%$search%' )";
   }

   $query .= " order by $FOF_ITEM_TABLE.item_published desc $limit_clause";

   $result = fof_db_query($query);

   $array = array();
	
   while($row = mysql_fetch_assoc($result))
   {
      $array[] = $row;
   }

   $array = fof_multi_sort($array, 'item_published', $order != "asc");

   return $array;
}

function fof_db_get_item($user_id, $item_id)
{
   global $FOF_SUBSCRIPTION_TABLE, $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_TAG_TABLE;

   $query = "select distinct $FOF_FEED_TABLE.feed_title as feed_title, $FOF_FEED_TABLE.feed_link as feed_link, $FOF_FEED_TABLE.feed_description as feed_description, $FOF_ITEM_TABLE.item_id as item_id, $FOF_ITEM_TABLE.item_link as item_link, $FOF_ITEM_TABLE.item_title as item_title, $FOF_ITEM_TABLE.item_cached, $FOF_ITEM_TABLE.item_published, $FOF_ITEM_TABLE.item_updated, $FOF_ITEM_TABLE.item_content as item_content from $FOF_FEED_TABLE, $FOF_ITEM_TABLE where $FOF_ITEM_TABLE.feed_id=$FOF_FEED_TABLE.feed_id and $FOF_ITEM_TABLE.item_id = $item_id";

   $result = fof_db_query($query);

   $row = mysql_fetch_assoc($result);

   return $row;
}

function fof_db_mark_unread($user_id, $items)
{
    foreach($items as $item)
    {
        $sql[] = "($user_id, 1, $item)";
    }
    
    $values = implode ( ",", $sql );

	$sql = "insert into  " . FOF_ITEM_TAG_TABLE . "(user_id, tag_id, item_id) values " . $values;
	
	fof_db_query($sql, 1);
}

function fof_db_mark_feed_unread($user_id, $feed)
{
    $result = fof_db_get_items($user_id, $feed, $what="all", NULL, NULL, 10);

   foreach($result as $r)
      {
      $items[] = $r['item_id'];
   }

    foreach($items as $item)
    {
        $sql[] = "($user_id, 1, $item)";
    }
    
    $values = implode ( ",", $sql );

	$sql = "insert into  " . FOF_ITEM_TAG_TABLE . "(user_id, tag_id, item_id) values " . $values;
	
	fof_db_query($sql, 1);
}

function fof_db_mark_read($user_id, $items)
{
    if(!$items) return;

    foreach($items as $item)
    {
        $sql[] = " item_id = $item ";
    }
    
    $values = implode ( " or ", $sql );
    
    $sql = "delete from " . FOF_ITEM_TAG_TABLE . " where user_id = $user_id and tag_id = 1 
and ( $values )";

    fof_db_query($sql);
}

function fof_db_optimize()
{
	global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_TAG_TABLE, $FOF_USER_TABLE;

	fof_db_query("optimize table $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_TAG_TABLE, $FOF_USER_TABLE");
}
?>
