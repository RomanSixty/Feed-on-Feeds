<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * fof-main.php - initializes FoF, and contains functions used from other scripts
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

//$LOG = fopen("fof.log", 'a');

require_once("fof-config.php");
require_once("fof-db.php");

fof_db_connect();

fof_init_plugins();

if(!$fof_no_login)
{
    require_user();
}

require_once('simplepie/simplepie.inc');

function fof_log($message)
{
	global $LOG;
	//fwrite($LOG, "$message\n");
}

function require_user()
{
    if(!isset($_COOKIE["user_name"]) || !isset($_COOKIE["user_password_hash"]))
    {
        Header("Location: login.php");
    }
    
    $user_name = $_COOKIE["user_name"];
    $user_password_hash = $_COOKIE["user_password_hash"];
    
    if(!fof_authenticate($user_name, $user_password_hash))
    {
        Header("Location: login.php");
    }
}

function fof_authenticate($user_name, $user_password_hash)
{
    global $fof_user_name;
        
    if(fof_db_authenticate($user_name, $user_password_hash))
    {
        setcookie ( "user_name", $fof_user_name, time()+60*60*24*365*10, "/" );
        setcookie ( "user_password_hash",  $user_password_hash, time()+60*60*24*365*10, "/" );
        return true;
    }
}

function fof_logout()
{
    setcookie ( "user_name", "", time(), "/" );
    setcookie ( "user_password_hash", "", time(), "/" );
}

function fof_current_user()
{
    global $fof_user_id;
    
    return $fof_user_id;
}

function fof_username()
{
    global $fof_user_name;
    
    return $fof_user_name;
}

function fof_prefs()
{
    global $fof_user_prefs;
        
    return $fof_user_prefs;
}

function fof_is_admin()
{
    global $fof_user_level;
    
    return $fof_user_level == "admin";
}

function fof_get_tags($user_id)
{
   $tags = array();
   
   $result = fof_db_get_tags($user_id);
   
   while($row = fof_db_get_row($result))
   {
      $tags[] = $row;
    }
    
    return $tags;
}

function fof_get_item_tags($user_id, $item_id)
{
	$result = fof_db_get_item_tags($user_id, $item_id);
   
	$tags = array();
   
	while($row = fof_db_get_row($result))
	{
    	$tags[] = $row['tag_name'];
    }
    
	return $tags;
}

function fof_tag_item($user_id, $item_id, $tag)
{
   $tag_id = fof_db_get_tag_by_name($user_id, $tag);
   if($tag_id == NULL)
   {
    $tag_id = fof_db_create_tag($user_id, $tag);
   }

   fof_db_tag_item($user_id, $item_id, $tag_id);   
}

function fof_untag_item($user_id, $item_id, $tag)
{
   $tag_id = fof_db_get_tag_by_name($user_id, $tag);
   fof_db_untag_item($user_id, $item_id, $tag_id);   
}

function fof_nice_time_stamp($age)
{
      $age = time() - $age;

      if($age == 0)
      {
         $agestr = "never";
         $agestrabbr = "&infin;";
      }
      else
      {
         $seconds = $age % 60;
         $minutes = $age / 60 % 60;
         $hours = $age / 60 / 60 % 24;
         $days = floor($age / 60 / 60 / 24);

         if($seconds)
         {
            $agestr = "$seconds second";
            if($seconds != 1) $agestr .= "s";
            $agestr .= " ago";

            $agestrabbr = $seconds . "s";
         }

         if($minutes)
         {
            $agestr = "$minutes minute";
            if($minutes != 1) $agestr .= "s";
            $agestr .= " ago";

            $agestrabbr = $minutes . "m";
         }

         if($hours)
         {
            $agestr = "$hours hour";
            if($hours != 1) $agestr .= "s";
            $agestr .= " ago";

            $agestrabbr = $hours . "h";
         }

         if($days)
         {
            $agestr = "$days day";
            if($days != 1) $agestr .= "s";
            $agestr .= " ago";

            $agestrabbr = $days . "d";
         }
      }
      
      return array($agestr, $agestrabbr);
}

function fof_get_feeds($user_id, $order = 'feed_title', $direction = 'asc')
{
   $feeds = array();
   
   $result = fof_db_get_subscriptions($user_id);
   
   $i = 0;

   while($row = fof_db_get_row($result))
   {
      $id = $row['feed_id'];
      $age = $row['feed_cache_date'];

      $feeds[$i]['feed_id'] = $id;
      $feeds[$i]['feed_url'] = $row['feed_url'];
      $feeds[$i]['feed_title'] = $row['feed_title'];
      $feeds[$i]['feed_link'] = $row['feed_link'];
      $feeds[$i]['feed_description'] = $row['feed_description'];
      $feeds[$i]['feed_image'] = $row['feed_image'];
      $feeds[$i]['feed_age'] = $age;

	  list($agestr, $agestrabbr) = fof_nice_time_stamp($age);
	  
      $feeds[$i]['agestr'] = $agestr;
      $feeds[$i]['agestrabbr'] = $agestrabbr;

      $i++;

   }

   $result = fof_db_get_item_count($user_id);

   while($row = fof_db_get_row($result))
   {
     for($i=0; $i<count($feeds); $i++)
     {
      if($feeds[$i]['feed_id'] == $row['id'])
      {
         $feeds[$i]['feed_items'] = $row['count'];
         $feeds[$i]['feed_read'] = $row['count'];
         $feeds[$i]['feed_unread'] = 0;
      }
     }
   }

   $result = fof_db_get_unread_item_count($user_id);

   while($row = fof_db_get_row($result))
   {
     for($i=0; $i<count($feeds); $i++)
     {
      if($feeds[$i]['feed_id'] == $row['id'])
      {
         $feeds[$i]['feed_unread'] = $row['count'];
      }
     }
   }

   foreach($feeds as $feed)
   {
      $feed['feed_starred'] = 0;
   }
   
   $result = fof_db_get_starred_item_count($user_id);

   while($row = fof_db_get_row($result))
   {
     for($i=0; $i<count($feeds); $i++)
     {
      if($feeds[$i]['feed_id'] == $row['id'])
      {
         $feeds[$i]['feed_starred'] = $row['count'];
      }
     }
   }
   
   $result = fof_db_get_latest_item_age($user_id);

   while($row = fof_db_get_row($result))
   {
     for($i=0; $i<count($feeds); $i++)
     {
      if($feeds[$i]['feed_id'] == $row['id'])
      {
         $feeds[$i]['max_date'] = $row['max_date'];
		  list($agestr, $agestrabbr) = fof_nice_time_stamp($row['max_date']);
	  
    	  $feeds[$i]['lateststr'] = $agestr;
      	$feeds[$i]['lateststrabbr'] = $agestrabbr;

      }
     }
   }


   $feeds = fof_multi_sort($feeds, $order, $direction != "asc");

   return $feeds;
}

function fof_view_title($feed=NULL, $what="new", $when=NULL, $start=NULL, $limit=NULL, $search=NULL)
{
   $title = "feed on feeds";

   if(!is_null($when) && $when != "")
   {
      $title .= ' - ' . $when ;
   }
   if(!is_null($feed) && $feed != "")
   {
      $r = fof_db_get_feed_by_id($feed);
      $title .=' - ' . $r['feed_title'];
   }
   if(is_numeric($start))
   {
      if(!is_numeric($limit)) $limit = FOF_HOWMANY;
      $title .= " - items $start to " . ($start + $limit);
   }
   if($what != "all")
   {
      $title .=' - new items';
   }
   else
   {
      $title .= ' - all items';
   }
   
   if(isset($search))
   {
      $title .= " - <a href='javascript:toggle_highlight()'>matching <i class='highlight'>$search</i></a>";
   }

   return $title;
}

function fof_get_items($user_id, $feed=NULL, $what="unread", $when=NULL, $start=NULL, $limit=NULL, $order="desc", $search=NULL)
{
   global $fof_item_filters;
   
   $items = fof_db_get_items($user_id, $feed, $what, $when, $start, $limit, $order, $search);
   
   for($i=0; $i<count($items); $i++)
   {
   	  foreach($fof_item_filters as $filter)
   	  {
		  $items[$i]['item_content'] = $filter($items[$i]['item_content']);
      }
   }
   
   return $items;
}

function fof_get_item($user_id, $item_id)
{   
   global $fof_item_filters;

   $item = fof_db_get_item($user_id, $item_id);
   
   foreach($fof_item_filters as $filter)
   {
      $item['item_content'] = $filter($item['item_content']);
   }
   
   return $item;
}

function fof_mark_read($user_id, $items)
{
    fof_db_mark_read($user_id, $items);
}

function fof_mark_unread($user_id, $items)
{
    fof_db_mark_unread($user_id, $items);
}

function fof_delete_subscription($user_id, $feed_id)
{
    fof_db_delete_subscription($user_id, $feed_id);
    
    if(mysql_num_rows(fof_get_subscribed_users($feed_id)) == 0)
    {
    	fof_db_delete_feed($feed_id);
    }
}

function fof_get_nav_links($feed=NULL, $what="new", $when=NULL, $start=NULL, $limit=NULL)
{
   $string = "";

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

      $string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=$yesterday&amp;how=$how&amp;howmany=$howmany\">[&laquo; $yesterday]</a> ";
      if($when != "today") $string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=today&amp;how=$how&amp;howmany=$howmany\">[today]</a> ";
      if($when != "today") $string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=$tomorrow&amp;how=$how&amp;howmany=$howmany\">[$tomorrow &raquo;]</a> ";
   }

   if(is_numeric($start))
   {
      if(!is_numeric($limit)) $limit = FOF_HOWMANY;

      $earlier = $start + $limit;
      $later = $start - $limit;

      $string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=$when&amp;how=paged&amp;which=$earlier&amp;howmany=$limit\">[&laquo; previous $limit]</a> ";
      if($later >= 0) $string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=$when&amp;how=paged&amp;howmany=$limit\">[current items]</a> ";
      if($later >= 0) $string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=$when&amp;how=paged&amp;which=$later&amp;howmany=$limit\">[next $limit &raquo;]</a> ";
   }

   return $string;
}

function fof_render_feed_link($row)
{
   $link = $row['feed_link'];
   $description = $row['feed_description'];
   $title = $row['feed_title'];
   $url = $row['feed_url'];

   $s = "<b><a href=\"$link\" title=\"$description\">$title</a></b> ";
   $s .= "<a href=\"$url\">(rss)</a>";

   return $s;
}

function fof_opml_to_array($opml)
{
   $rx = "/xmlurl=\"(.*?)\"/mi";

   if (preg_match_all($rx, $opml, $m))
   {
      for($i = 0; $i < count($m[0]) ; $i++)
      {
         $r[] = $m[1][$i];
      }
  }

  return $r;
}

function fof_prepare_url($url)
{
   $url = trim($url);

   if(substr($url, 0, 7) != 'http://' && substr($url, 0, 8) != 'https://')
   {
     $url = 'http://' . $url;
   }

    return $url;
}

function fof_subscribe($user_id, $url, $autodiscovery=true)
{
   if(!$url) return false;

    $url = fof_prepare_url($url);

   print "Attempting to subscribe to <a href=\"$url\">$url</a>...<br>";

    $feed = fof_db_get_feed_by_url($url);

   if(fof_is_subscribed($user_id, $url))
   {
      print "You are already subscribed to " . fof_render_feed_link($feed) . "<br><br>";
      return true;
   }

   if(fof_feed_exists($url))
   {
    fof_db_add_subscription($user_id, $feed['feed_id']);
   fof_update_feed($feed['feed_id']);
   
   fof_db_mark_feed_unread($user_id, $feed['feed_id']);
      echo "<font color=\"green\"><b>Subscribed.</b></font><br>";
      
      return true;
   }

    $rss = new SimplePie();
    $rss->set_cache_duration(30 * 60);
	$rss->set_feed_url($url);
	$rss->init();

			if (isset($rss->error)) {

      print "Error: <B>" . $rss->error . "</b> ";
      print "<a href=\"http://feedvalidator.org/check?url=$url\">try to validate it?</a> ";
      return 0;
   }
	else
   {
   
      if(fof_feed_exists($rss->subscribe_url()))
   {
       $feed = fof_db_get_feed_by_url($rss->subscribe_url());

      if(fof_is_subscribed($user_id, $rss->subscribe_url()))
   {
      print "You are already subscribed to " . fof_render_feed_link($feed) . "<br><br>";
      return true;
   }


    fof_db_add_subscription($user_id, $feed['feed_id']);
   fof_update_feed($feed['feed_id']);
   
   fof_db_mark_feed_unread($user_id, $feed['feed_id']);
      echo "<font color=\"green\"><b>Subscribed.</b></font><br>";
      
      return true;
   }

   //   echo "<pre>";
   //print_r($rss);
   //echo "</pre>";

      $id = fof_add_feed($rss->subscribe_url(), $rss->get_feed_title(), $rss->get_feed_link(), $rss->get_feed_description() );
		echo "added with id $id<br>";
		
      fof_db_add_subscription($user_id, $id);
		echo "added subscription<br>";
   fof_update_feed($id);

      fof_db_mark_feed_unread($user_id, $id);
      echo "<font color=\"green\"><b>Subscribed.</b></font><br>";
      
      return true;
   }
}

function fof_add_feed($url, $title, $link, $description)
{
   $id = fof_db_add_feed($url, $title, $link, $description);
   
   return $id;
}

function fof_is_subscribed($user_id, $url)
{
   return(fof_db_is_subscribed($user_id, $url));
}

function fof_feed_exists($url)
{
   $feed = fof_db_get_feed_by_url($url);

   return $feed;
}

function fof_get_subscribed_users($feed_id)
{
   return(fof_db_get_subscribed_users($feed_id));
}

function fof_mark_item_unread($feed_id, $id)
{
   $result = fof_get_subscribed_users($feed_id);
   
   while($row = fof_db_get_row($result))
   {
      $users[] = $row['user_id'];
   }
   
   fof_db_mark_item_unread($users, $id);
}

function fof_update_feed($id)
{
   if(!$id) return 0;
   
   $feed = fof_db_get_feed_by_id($id);
   $url = $feed['feed_url'];
   fof_log("Updating $url");

    $rss = new SimplePie();
    $rss->set_cache_duration(30 * 60);
	$rss->set_feed_url($feed['feed_url']);
	$rss->init();

			if (isset($rss->error)) {

      print "Error: <B>" . $rss->error . "</b> ";
      print "<a href=\"http://feedvalidator.org/check?url=$url\">try to validate it?</a> ";
      return 0;
   }

   
   //echo "<pre>";
   //print_r($rss);
   //echo "</pre>";

	$sub = $rss->subscribe_url();
   fof_log("subscription url is $sub");
   
   fof_db_feed_update_metadata($id, $rss->subscribe_url(), $rss->get_feed_title(), $rss->get_feed_link(), $rss->get_feed_description(), $rss->get_favicon(true, $rss->get_image_url() ) );

   $feed_id = $feed['feed_id'];
   
   if($rss->get_items())
   {
	   foreach($rss->get_items() as $item)
	   {
		  $link = $item->get_permalink();
		  $title = $item->get_title();
		  $content = $item->get_description();
		  $date = $item->get_date();
		  $item_id = $item->get_id();
	
		  if(!$item_id)
		  {
			 $item_id = $link;
		  }
	
	
		  $id = fof_db_find_item($feed_id, $item_id);
	
		  if($id == NULL)
		  {
			 $n++;
			 $id = fof_db_add_item($feed_id, $item_id, $link, $title, $content, time(), time(), time());
			 fof_mark_item_unread($feed_id, $id);
		  }
	
		  $ids[] = $id;
	   }
   }

   // delete items that are not tagged, over a month old, and not currently in the feed
   $count = count($ids);
   if(count($ids) != 0)
   {
	  $in = implode ( ", ", $ids );
	  
	  global $FOF_ITEM_TABLE;
   	  $sql = "select item_id, item_cached from $FOF_ITEM_TABLE where feed_id = $feed_id and item_id not in ($in) order by item_cached asc limit $count, 1000000000";
      $result = fof_db_query($sql);

	   while($row = fof_db_get_row($result))
	   {
		  if($row['item_cached'] < time() - 30 * 24 * 60 * 60)
		  {
		    if(!fof_item_has_tags($row['item_id']))
		    {		      
		      $delete[] = $row['item_id'];

		    }
		  }
	   }
	   
	   if(count($delete) != 0)
	   {
	     $in = implode(", ", $delete); 
	     fof_db_query( "delete from $FOF_ITEM_TABLE where item_id in ($in)" );
	   }
   }
   
   $rss = null;
   
   fof_db_feed_mark_cached($feed_id);
   
   return $n;
}

function fof_item_has_tags($item_id)
{
	return fof_db_item_has_tags($item_id);
}

function fof_init_plugins()
{
    global $fof_item_filters;
    
    $fof_item_filters = array();
    
    $dirlist = opendir(FOF_DIR . "/plugins");
    while($file=readdir($dirlist))
    {
    	fof_log("considering " . $file);
        if(ereg('\.php$',$file))
        {
        	fof_log("including " . $file);

            include(FOF_DIR . "/plugins/" . $file);
        }
    }

    closedir();
}

function fof_add_item_filter($function)
{
    global $fof_item_filters;
    
    $fof_item_filters[] = $function;
}

function fof_multi_sort($tab,$key,$rev){
   if($rev)
   {
   $compare = create_function('$a,$b','if (strtolower($a["'.$key.'"]) == strtolower($b["'.$key.'"])) {return 0;}else {return (strtolower($a["'.$key.'"]) > strtolower($b["'.$key.'"])) ? -1 : 1;}');
   }
   else
   {
   $compare = create_function('$a,$b','if (strtolower($a["'.$key.'"]) == strtolower($b["'.$key.'"])) {return 0;}else {return (strtolower($a["'.$key.'"]) < strtolower($b["'.$key.'"])) ? -1 : 1;}');
   }

   usort($tab,$compare) ;
   return $tab ;
}

?>
