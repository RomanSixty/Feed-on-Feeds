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

fof_repair_drain_bamage();

if ( !file_exists( dirname(__FILE__) . '/fof-config.php') )
{
    echo "You will first need to create a fof-config.php file.  Please copy fof-config-sample.php to fof-config.php and then update the values to match your database settings.";
    die();
}

require_once("fof-config.php");
require_once("fof-db.php");
require_once("classes/fof-prefs.php");

fof_db_connect();

if(!$fof_installer)
{
    if(!$fof_no_login)
    {
        require_user();
        $fof_prefs_obj =& FoF_Prefs::instance();
    }
    else
    {
        $fof_user_id = 1;
        $fof_prefs_obj =& FoF_Prefs::instance();
    }

    ob_start();
    fof_init_plugins();
    ob_end_clean();
}

require_once('autoloader.php');
require_once('simplepie/SimplePie.php');

function fof_set_content_type()
{
    static $set;
    if(!$set)
    {
        header("Content-Type: text/html; charset=utf-8");
        $set = true;
    }
}

function fof_log($message, $topic="debug")
{
    global $fof_prefs_obj;

    if(!$fof_prefs_obj) return;

    $p = $fof_prefs_obj->admin_prefs;
    if(!$p['logging']) return;

    static $log;
    if(!isset($log)) $log = @fopen("fof.log", 'a');

    if(!$log) return;

    $message = str_replace ("\n", "\\n", $message);
    $message = str_replace ("\r", "\\r", $message);

    fwrite($log, date('r') . " [$topic] $message\n");
}

function require_user()
{
    if(!isset($_COOKIE["user_name"]) || !isset($_COOKIE["user_password_hash"]))
    {
        Header("Location: login.php");
        exit();
    }

    $user_name = $_COOKIE["user_name"];
    $user_password_hash = $_COOKIE["user_password_hash"];

    if(!fof_authenticate($user_name, $user_password_hash))
    {
        Header("Location: login.php");
        exit();
    }
}

function fof_authenticate($user_name, $user_password_hash)
{
    global $fof_user_name;

    if(fof_db_authenticate($user_name, $user_password_hash))
    {
        setcookie ( "user_name", $fof_user_name, time()+60*60*24*365*10 );
        setcookie ( "user_password_hash",  $user_password_hash, time()+60*60*24*365*10 );
        return true;
    }
}

function fof_logout()
{
    setcookie ( "user_name", "", time() );
    setcookie ( "user_password_hash", "", time() );
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

function fof_get_users()
{
    return fof_db_get_users();
}

function fof_prefs()
{
    $p =& FoF_Prefs::instance();
    return $p->prefs;
}

function fof_is_admin()
{
    global $fof_user_level;

    return $fof_user_level == "admin";
}

function fof_get_unread_count($user_id)
{
    return fof_db_get_unread_count($user_id);
}

function fof_get_tags($user_id)
{
    $tags = array();

    $result = fof_db_get_tags($user_id);

    $counts = fof_db_get_tag_unread($user_id);

    while($row = fof_db_get_row($result))
    {
        if(isset($counts[$row['tag_id']]))
          $row['unread'] = $counts[$row['tag_id']];
        else
          $row['unread'] = 0;

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

function fof_update_feed_prefs($feed_id, $title, $alt_image)
{
    global $FOF_FEED_TABLE;

    $title = mysql_real_escape_string ( $title );

    $sql = "UPDATE $FOF_FEED_TABLE SET feed_title='$title', alt_image='$alt_image', feed_image_cache_date=1 WHERE feed_id=$feed_id";
    fof_db_query($sql);

    return true;
}

function fof_tag_feed($user_id, $feed_id, $tag)
{
    $tag_id = fof_db_get_tag_by_name($user_id, $tag);
    if($tag_id == NULL)
    {
        $tag_id = fof_db_create_tag($user_id, $tag);
    }

    $result = fof_db_get_items($user_id, $feed_id, $what="all", NULL, NULL);

    foreach($result as $r)
    {
        $items[] = $r['item_id'];
    }

    fof_db_tag_items($user_id, $tag_id, $items);

    fof_db_tag_feed($user_id, $feed_id, $tag_id);
}

function fof_untag_feed($user_id, $feed_id, $tag)
{
    $tag_id = fof_db_get_tag_by_name($user_id, $tag);
    if($tag_id == NULL)
    {
        $tag_id = fof_db_create_tag($user_id, $tag);
    }

    $result = fof_db_get_items($user_id, $feed_id, $what="all", NULL, NULL);

    foreach($result as $r)
    {
        $items[] = $r['item_id'];
    }

    fof_db_untag_items($user_id, $tag_id, $items);

    fof_db_untag_feed($user_id, $feed_id, $tag_id);
}

function fof_tag_item($user_id, $item_id, $tag)
{
    if(is_array($tag)) $tags = $tag; else $tags[] = $tag;

    foreach($tags as $tag)
    {
        // remove tag, if it starts with '-'
        if ( $tag{0} == '-' )
        {
            fof_untag_item($user_id, $item_id, substr($tag, 1));
            continue;
        }

        $tag_id = fof_db_get_tag_by_name($user_id, $tag);
        if($tag_id == NULL)
        {
            $tag_id = fof_db_create_tag($user_id, $tag);
        }

        fof_db_tag_items($user_id, $tag_id, $item_id);
    }
}

function fof_untag_item($user_id, $item_id, $tag)
{
    $tag_id = fof_db_get_tag_by_name($user_id, $tag);
    fof_db_untag_items($user_id, $tag_id, $item_id);
}

function fof_untag($user_id, $tag)
{
    $tag_id = fof_db_get_tag_by_name($user_id, $tag);

    $result = fof_db_get_items($user_id, $feed_id, $tag, NULL, NULL);

    foreach($result as $r)
    {
        $items[] = $r['item_id'];
    }

    fof_db_untag_items($user_id, $tag_id, $items);
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
        $feeds[$i]['feed_image'] = empty($row['alt_image']) ? $row['feed_image'] : $row['alt_image'];
        $feeds[$i]['alt_image'] = $row['alt_image'];
        $feeds[$i]['prefs'] = unserialize($row['subscription_prefs']);
        $feeds[$i]['feed_age'] = $age;

        list($agestr, $agestrabbr) = fof_nice_time_stamp($age);

        $feeds[$i]['agestr'] = $agestr;
        $feeds[$i]['agestrabbr'] = $agestrabbr;

        $i++;
    }

    $tags = fof_db_get_tag_id_map();

    for($i=0; $i<count($feeds); $i++)
    {
        $feeds[$i]['tags'] = array();
        if(is_array($feeds[$i]['prefs']['tags']))
        {
            foreach($feeds[$i]['prefs']['tags'] as $tag)
            {
                $feeds[$i]['tags'][] = $tags[$tag];
            }
        }
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

    $result = fof_db_get_item_count($user_id, 'unread');

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

    foreach($feeds as &$feed)
    {
        $feed['feed_starred'] = 0;
        $feed['feed_tagged']  = 0;
    }

    $result = fof_db_get_item_count($user_id, 'starred');

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

    $result = fof_db_get_item_count($user_id, 'tagged');

    while($row = fof_db_get_row($result))
    {
        for($i=0; $i<count($feeds); $i++)
        {
            if($feeds[$i]['feed_id'] == $row['id'])
            {
                $feeds[$i]['feed_tagged'] = $row['count'];
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

function fof_view_title($feed=NULL, $what="new", $when=NULL, $start=NULL, $limit=NULL, $search=NULL, $itemcount = 0)
{
    $prefs = fof_prefs();
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
        if(!is_numeric($limit)) $limit = $prefs["howmany"];
        $title .= " - items $start to " . ($start + $limit);
    }

    $title .= " of $itemcount items";

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

function fof_get_nav_links($feed=NULL, $what="new", $when=NULL, $start=NULL, $limit=NULL, $search=NULL, $itemcount=9999)
{
    $prefs = fof_prefs();
    $string = "";

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

        $begin = strtotime($whendate);

        $tomorrow = date( "Y/m/d", $begin + (24 * 60 * 60) );
        $yesterday = date( "Y/m/d", $begin - (24 * 60 * 60) );

        $string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=$yesterday&amp;how=$how&amp;howmany=$howmany\">[&laquo; $yesterday]</a> ";
        if($when != "today") $string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=today&amp;how=$how&amp;howmany=$howmany\">[today]</a> ";
        if($when != "today") $string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=$tomorrow&amp;how=$how&amp;howmany=$howmany\">[$tomorrow &raquo;]</a> ";
    }

    if(is_numeric($start))
    {
        if(!is_numeric($limit)) $limit = $prefs["howmany"];

        if ($itemcount <= $limit) return '';

        $earlier = $start + $limit;
        $later = $start - $limit;

        if($itemcount > $earlier) $string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=$when&amp;how=paged&amp;which=$earlier&amp;howmany=$limit&amp;search=$search\">[&laquo; previous $limit]</a> ";
        if($later >= 0) $string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=$when&amp;how=paged&amp;howmany=$limit&amp;search=$search\">[current items]</a> ";
        if($later >= 0) $string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=$when&amp;how=paged&amp;which=$later&amp;howmany=$limit&amp;search=$search\">[next $limit &raquo;]</a> ";
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

    if(substr($url, 0, 7) == "feed://") $url = substr($url, 7);

    if(substr($url, 0, 7) != 'http://' && substr($url, 0, 8) != 'https://')
    {
        $url = 'http://' . $url;
    }

    return $url;
}

function fof_subscribe($user_id, $url, $unread="today")
{
    if(!$url) return false;

    $url = fof_prepare_url($url);
    $feed = fof_db_get_feed_by_url($url);

    if(fof_is_subscribed($user_id, $url))
    {
        return "You are already subscribed to " . fof_render_feed_link($feed) . "<br>";
    }

    if(fof_feed_exists($url))
    {
        fof_db_add_subscription($user_id, $feed['feed_id']);
        fof_apply_plugin_tags($id, NULL, $user_id);
        fof_update_feed($feed['feed_id']);

        if($unread != "no") fof_db_mark_feed_unread($user_id, $feed['feed_id'], $unread);
        return '<font color="green"><b>Subscribed.</b></font><br>';
    }

    $rss = fof_parse($url);

    if (isset($rss->error))
    {
        return "Error: <B>" . $rss->error . "</b> <a href=\"http://feedvalidator.org/check?url=$url\">try to validate it?</a><br>";
    }
    else
    {
        $url = html_entity_decode($rss->subscribe_url(), ENT_QUOTES);
        $self = $rss->get_link(0, 'self');
        if($self) $url = html_entity_decode($self, ENT_QUOTES);

        if(fof_feed_exists($url))
        {
            $feed = fof_db_get_feed_by_url($url);

            if(fof_is_subscribed($user_id, $url))
            {
                return "You are already subscribed to " . fof_render_feed_link($feed) . "<br>";
            }

            fof_db_add_subscription($user_id, $feed['feed_id']);
            if($unread != "no") fof_db_mark_feed_unread($user_id, $feed['feed_id'], $unread);

            return '<font color="green"><b>Subscribed.</b></font><br>';
        }

        $id = fof_add_feed($url, $rss->get_title(), $rss->get_link(), $rss->get_description() );

        fof_update_feed($id);
        fof_db_add_subscription($user_id, $id);
        if($unread != "no") fof_db_mark_feed_unread($user_id, $id, $unread);

        fof_apply_plugin_tags($id, NULL, $user_id);

        return '<font color="green"><b>Subscribed.</b></font><br>';
    }
}

function fof_add_feed($url, $title, $link, $description)
{
    if($title == "") $title = "[no title]";

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

function fof_parse($url)
{
    $p =& FoF_Prefs::instance();
    $admin_prefs = $p->admin_prefs;

    $pie = new SimplePie();
    $pie->set_cache_location(dirname(__FILE__).'/cache');
    $pie->set_cache_duration($admin_prefs["manualtimeout"] * 60);
    $pie->set_feed_url($url);
    $pie->remove_div(true);
    $pie->init();

    if ( $pie -> error() )
    {
        $data = file_get_contents ( $url );

        $data = preg_replace ( '~.*<\?xml~sim', '<?xml', $data );

        #file_put_contents ('/tmp/text.xml',$data);

        unset ( $pie );

        $pie = new SimplePie();
        $pie->set_cache_location(dirname(__FILE__).'/cache');
        $pie->set_cache_duration($admin_prefs["manualtimeout"] * 60);
        $pie->remove_div(true);

        $pie -> set_raw_data ( $data );
        $pie -> init();
    }

    return $pie;
}

function fof_apply_tags($feed_id, $item_id)
{
    global $fof_subscription_to_tags;

    if(!isset($fof_subscription_to_tags))
    {
        $fof_subscription_to_tags = fof_db_get_subscription_to_tags();
    }

    foreach((array)$fof_subscription_to_tags[$feed_id] as $user_id => $tags)
    {
        if(is_array($tags))
        {
            foreach($tags as $tag)
            {
                fof_db_tag_items($user_id, $tag, $item_id);
            }
        }
    }
}

function fof_update_feed($id)
{
    global $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_FEED_TABLE;

    if(!$id) return 0;

    static $blacklist = null;
    static $admin_prefs = null;

    if($blacklist === null)
    {
        $p =& FoF_Prefs::instance();
        $admin_prefs = $p->admin_prefs;

        $blacklist = preg_split('/(\r\n|\r|\n)/', $admin_prefs['blacklist'], -1, PREG_SPLIT_NO_EMPTY);
    }

    $feed = fof_db_get_feed_by_id($id);
    $url = $feed['feed_url'];
    fof_log("Updating $url");

    fof_db_feed_mark_attempted_cache($id);

    $rss = fof_parse($feed['feed_url']);

    if ($rss->error())
    {
        fof_log("feed update failed: " . $rss->error(), "update");
        return array(0, "Error: <b>" . $rss->error() . "</b> <a href=\"http://feedvalidator.org/check?url=$url\">try to validate it?</a>");
    }

    if ( !empty ( $feed [ 'alt_image' ] ) )
    {
        $image = $feed [ 'alt_image' ];
    }
    else
    {
        $image = $feed [ 'feed_image' ];
        $image_cache_date = $feed['feed_image_cache_date'];

        // cached favicon older than a week?
        if ( $feed [ 'feed_image_cache_date' ] < time() - ( 7*24*60*60 ) )
        {
            $image = fof_get_favicon ( $feed [ 'feed_link' ] );
            $image_cache_date = time();
        }
    }

    $title = $rss->get_title();
    if($title == "") $title = "[no title]";

    fof_db_feed_update_metadata($id, $title, $rss->get_link(), $rss->get_description(), $image, $image_cache_date );

    $feed_id = $feed['feed_id'];
    $n = 0;

    // Set up the dynamic updates here, so we can include would-be-purged items
    $purgedUpdTimes = array();
    $count_Added = 0;

    if($rss->get_items())
    {
        foreach($rss->get_items() as $item)
        {
            $title = $item->get_title();

            foreach($blacklist as $bl)
              if(stristr($title, $bl) !== false)
                continue 2;

            $link = $item->get_permalink();
            $content = $item->get_content();
            $date = $item->get_date('U');

            // don't fetch entries older than the purge limit
            if ( !$date )
              $date = time();
            elseif ( !empty ( $admin_prefs [ 'purge' ] ) && $date <= ( time() - $admin_prefs [ 'purge' ] * 24 * 3600 ) ) {
                $purgedUpdTimes[] = $date;
                continue;
            }

            $item_id = $item->get_id();

            if(!$item_id)
            {
                $item_id = $link;
            }

            $id = fof_db_find_item($feed_id, $item_id);

            if($id == NULL)
            {
                $n++;

                global $fof_item_prefilters;
                foreach($fof_item_prefilters as $filter)
                {
                    list($link, $title, $content) = $filter($item, $link, $title, $content);
                }

                $id = fof_db_add_item($feed_id, $item_id, $link, $title, $content, time(), $date, $date);
                fof_apply_tags($feed_id, $id);
                $count_Added++;

                $republished = false;

                if(!$republished)
                {
                    fof_mark_item_unread($feed_id, $id);
                }

                fof_apply_plugin_tags($feed_id, $id, NULL);
            }

            $ids[] = $id;
        }
    }

    unset($rss);

    if ( $admin_prefs [ 'dynupdates' ] )
    {
        // Determine the average time between items, to determine the next update time

        $count = 0;
        $lastTime = 0;
        $totalDelta = 0.0;
        $totalDeltaSquare = 0.0;

        // Accumulate the times for the pre-purged items
        sort ($purgedUpdTimes, SORT_NUMERIC);
        foreach ($purgedUpdTimes as $time) {
            if ($count > 0) {
                $delta = $time - $lastTime;
                $totalDelta += $delta;
                $totalDeltaSquare += $delta*$delta;
            }
            $lastTime = $time;
            $count++;
        }

        // Accumulate the times for the stored items
        $result = fof_safe_query("SELECT item_updated FROM $FOF_ITEM_TABLE WHERE feed_id = %d ORDER BY item_updated ASC", $feed_id);
        while ($row = fof_db_get_row($result)) {
            if ($count > 0) {
                $delta = (float)($row['item_updated'] - $lastTime);
                $totalDelta += $delta;
                $totalDeltaSquare += $delta*$delta;
            }
            $count++;
            $lastTime = $row['item_updated'];
        }

        // Accumulate the time for 'now' (to give the window something to grow on)
        $delta = time() - $lastTime;
        if ($delta > 0 && $count > 0) {
            $totalDelta += $delta;
            $totalDeltaSquare += $delta*$delta;
            $count++;
        }

        $mean = 0;
        if ($count > 0) {
            $mean = $totalDelta/$count;
        }
        $stdev = 0;
        if ($count > 1) {
            $stdev = sqrt(($count*$totalDeltaSquare - $totalDelta*$totalDelta)
                          /($count * ($count - 1)));
        }
       
        // This algorithm is rife with fiddling, and I really need to generate metrics to test the efficacy
        $now = time();
        $nextInterval = $mean + $stdev*2/($count + 1);
        $nextTime = min(max($lastTime + $nextInterval, $now + $stdev),
                        $now + 86400*2);
       
	$lastInterval = $now - $lastTime; 
        fof_log($feed['feed_title'] . ": Next feed update in "
                . ($nextTime - $now) . " seconds;"
                . " count=$count t=$totalDelta t2=$totalDeltaSquare"
                . " mean=$mean stdev=$stdev');
        if ($count_Added > 0) {
                // In a perfect world, we want both of these numbers to be low
                fof_log("DYNUPDATE_PROFILE count $count_Added overstep $lastInterval");
        }
        fof_safe_query("UPDATE $FOF_FEED_TABLE SET feed_cache_next_attempt=%d"
                       . " WHERE feed_id = %d",
                       (int)round($nextTime), $feed_id);
    }

    // optionally purge old items -  if 'purge' is set we delete items that are not
    // unread or starred, not currently in the feed or within sizeof(feed) items
    // of being in the feed, and are over 'purge' many days old

    $ndelete = 0;

    if ( !empty ( $admin_prefs [ 'purge' ] ) )
    {
        $purge = $admin_prefs [ 'purge' ];

        fof_log('purge is ' . $purge);

        $sql = "SELECT i.item_id FROM $FOF_ITEM_TABLE i
            LEFT JOIN $FOF_ITEM_TAG_TABLE t ON i.item_id=t.item_id
            WHERE tag_id IS NULL
                AND feed_id = $feed_id
                AND i.item_cached <= (UNIX_TIMESTAMP() - $purge*24*60*60)";

        $result = fof_db_query($sql);

        $delete = array();

        while($row = fof_db_get_row($result)) {
            $delete[] = $row['item_id'];
        }

        if ( count ( $delete ) ) {
            fof_db_query( "DELETE FROM $FOF_ITEM_TABLE WHERE item_id IN (" . implode ( ',', $delete ) . ")" );
        }

        $ndelete += count ( $delete );

	// also purge duplicate items (based on title and content comparison)
	if ( !empty ( $admin_prefs['match_similarity'] ) ) {
	    $threshold = $admin_prefs['match_similarity'];
	    $sql = "SELECT i2.item_id, i1.item_content AS c1, i2.item_content AS c2 FROM $FOF_ITEM_TABLE i1
                LEFT JOIN $FOF_ITEM_TABLE i2
                    ON i1.item_title = i2.item_title AND i1.feed_id = i2.feed_id
                WHERE i1.item_id < i2.item_id";
	    
	    $result = fof_db_query ( $sql );
	    
	    while ( $row = fof_db_get_row ( $result ) )
	    {
		$similarity = 0;
		
		similar_text ( $row [ 'c1' ], $row [ 'c2' ], $similarity );
		
		if ( $similarity > $threshold )
			$delete[] = $row [ 'item_id' ];
	    }
	}

	if ( count ( $delete ) )
	{
	    fof_db_query( "DELETE FROM $FOF_ITEM_TABLE     WHERE item_id IN (" . implode ( ',', $delete ) . ")" );
	    fof_db_query( "DELETE FROM $FOF_ITEM_TAG_TABLE WHERE item_id IN (" . implode ( ',', $delete ) . ")" );
	}

	$ndelete += count ( $delete );
    }

    fof_db_feed_mark_cached($feed_id);

    $log = "feed update complete, $n new items, $ndelete items purged";
    if($admin_prefs['purge'] == "")
    {
        $log .= " (purging disabled)";
    }
    fof_log($log, "update");

    return array($n, "");
}

function fof_apply_plugin_tags($feed_id, $item_id = NULL, $user_id = NULL)
{
    $users = array();

    if($user_id)
    {
        $users[] = $user_id;
    }
    else
    {
        $result = fof_get_subscribed_users($feed_id);

        while($row = fof_db_get_row($result))
        {
            $users[] = $row['user_id'];
        }
    }

    $items = array();
    if($item_id)
    {
        $items[] = fof_db_get_item($user_id, $item_id);
    }
    else
    {
        $result = fof_db_get_items($user_id, $feed_id, $what="all", NULL, NULL);

        foreach($result as $r)
        {
            $items[] = $r;
        }
    }

    $userdata = fof_get_users();

    foreach($users as $user)
    {
        fof_log("tagging for $user");

        global $fof_tag_prefilters;
        foreach($fof_tag_prefilters as $plugin => $filter)
        {
            fof_log("considering $plugin $filter");

            if(!$userdata[$user]['prefs']['plugin_' . $plugin])
            {
                foreach($items as $item)
                {
                    $tags = $filter($item['item_link'], $item['item_title'], $item['item_content']);
                    fof_tag_item($user, $item['item_id'], $tags);
                }
            }
        }
    }
}

function fof_item_has_tags($item_id)
{
    return fof_db_item_has_tags($item_id);
}

function fof_init_plugins()
{
    global $fof_item_filters, $fof_item_prefilters, $fof_tag_prefilters, $fof_plugin_prefs;

    $fof_item_filters = array();
    $fof_item_prefilters = array();
    $fof_plugin_prefs = array();
    $fof_tag_prefilters = array();

    $p =& FoF_Prefs::instance();

    $dirlist = opendir(FOF_DIR . "/plugins");
    while($file=readdir($dirlist))
    {
        if(preg_match('/\.php$/',$file) && !$p->get('plugin_' . substr($file, 0, -4)))
        {
            fof_log("including " . $file);
            include(FOF_DIR . "/plugins/" . $file);
        }
    }

    closedir();
}

function fof_add_tag_prefilter($plugin, $function)
{
    global $fof_tag_prefilters;

    $fof_tag_prefilters[$plugin] = $function;
}

function fof_add_item_filter($function, $order=null)
{
    global $fof_item_filters;

    if(is_int($order))
      $fof_item_filters[$order] = $function;
    else
      $fof_item_filters[] = $function;

    ksort($fof_item_filters);
}

function fof_add_item_prefilter($function)
{
    global $fof_item_prefilters;

    $fof_item_prefilters[] = $function;
}

function fof_add_pref($name, $key, $type="string")
{
    global $fof_plugin_prefs;

    $fof_plugin_prefs[] = array($name, $key, $type);
}

function fof_add_item_widget($function)
{
    global $fof_item_widgets;

    $fof_item_widgets[] = $function;
}

function fof_get_widgets($item)
{
    global $fof_item_widgets;

    if (!is_array($fof_item_widgets))
    {
        return false;
    }

    foreach($fof_item_widgets as $widget)
    {
        $w = $widget($item);
        if($w) $widgets[] = $w;
    }

    return $widgets;
}

function fof_get_plugin_prefs()
{
    global $fof_plugin_prefs;

    return $fof_plugin_prefs;
}

function fof_multi_sort($tab,$key,$rev)
{
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

function fof_todays_date()
{
    $prefs = fof_prefs();
    $offset = $prefs['tzoffset'];

    return gmdate( "Y/m/d", time() + ($offset * 60 * 60) );
}

function fof_repair_drain_bamage()
{
    if (ini_get('register_globals')) foreach($_REQUEST as $k=>$v) { unset($GLOBALS[$k]); }

    // thanks to submitter of http://bugs.php.net/bug.php?id=39859
    if (get_magic_quotes_gpc()) {
        function undoMagicQuotes($array, $topLevel=true) {
            $newArray = array();
            foreach($array as $key => $value) {
                if (!$topLevel) {
                    $key = stripslashes($key);
                }
                if (is_array($value)) {
                    $newArray[$key] = undoMagicQuotes($value, false);
                }
                else {
                    $newArray[$key] = stripslashes($value);
                }
            }
            return $newArray;
        }
        $_GET = undoMagicQuotes($_GET);
        $_POST = undoMagicQuotes($_POST);
        $_COOKIE = undoMagicQuotes($_COOKIE);
        $_REQUEST = undoMagicQuotes($_REQUEST);
    }
}

// grab a favicon from $url and cache it
function fof_get_favicon ( $url )
{
    $request = 'http://fvicon.com/' . $url . '?format=gif&width=16&height=16&canAudit=false';

    $reflector = new ReflectionClass('SimplePie_File');
    $sp = $reflector->newInstanceArgs(array($request));

    if ( $sp -> success )
    {
        $data = $sp -> body;

        $path = 'cache/' . md5 ( $data ) . '.png';

        file_put_contents ( dirname(__FILE__) . '/' . $path, $data );

        return $path;
    }
    else
        return 'image/feed-icon.png';
}
?>
