<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * prefs.php - display and change preferences
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once("fof-main.php");

$prefs =& FoF_Prefs::instance();

if(isset($_POST['adminprefs']))
{
	$prefs->set('purge', $_POST['purge']);
	$prefs->set('manualtimeout', $_POST['manualtimeout']);
	$prefs->set('autotimeout', $_POST['autotimeout']);

	$prefs->save();
    	
	$message .= ' Saved admin prefs.';
}

if(isset($_POST['tagfeed']))
{
    $tags = $_POST['tag'];
    $feed_id = $_POST['feed_id'];
    $title = $_POST['title'];
    
    foreach(explode(" ", $tags) as $tag)
    {
        fof_tag_feed(fof_current_user(), $feed_id, $tag);
        $message .= " Tagged '$title' as $tag.";
    }
}

if(isset($_GET['untagfeed']))
{
    $feed_id = $_GET['untagfeed'];
    $tags = $_GET['tag'];
    $title = $_GET['title'];
	
    foreach(explode(" ", $tags) as $tag)
    {
        fof_untag_feed(fof_current_user(), $feed_id, $tag);
        $message .= " Dropped $tag from '$title'.";
    }
}

if(isset($_POST['prefs']))
{
	$prefs->set('favicons', isset($_POST['favicons']));
	$prefs->set('keyboard', isset($_POST['keyboard']));
	$prefs->set('tzoffset', intval($_POST['tzoffset']));
	$prefs->set('howmany', intval($_POST['howmany']));
	$prefs->set('order', $_POST['order']);
	$prefs->set('sharing', $_POST['sharing']);
	$prefs->set('sharedname', $_POST['sharedname']);
	$prefs->set('sharedurl', $_POST['sharedurl']);

    foreach(fof_get_plugin_prefs() as $plugin_pref)
    {
        $key = $plugin_pref[1];
        
        $prefs->set($key, $_POST[$key]);
    }
     
	$prefs->save(fof_current_user());
    
    if($_POST['password'] && ($_POST['password'] == $_POST['password2']))
    {
        fof_db_change_password($fof_user_name, $_POST['password']);
        setcookie ( "user_password_hash",  md5($_POST['password'] . $fof_user_name), time()+60*60*24*365*10 );
        $message = "Updated password.";
    }
    else if($_POST['password'] || $_POST['password2'])
    {
        $message = "Passwords do not match!";
    }
	
	$message .= ' Saved prefs.';
}


if(isset($_POST['changepassword'])) 
{
    if($_POST['password'] != $_POST['password2'])
    {
        $message = "Passwords do not match!";
    }
    else
    {
        $username = $_POST['username'];
        $password = $_POST['password'];
        fof_db_change_password($username, $password);
        
        $message = "Changed password for $username.";
    }
}

if(isset($_POST['adduser']) && $_POST['username'] && $_POST['password']) 
{
    $username = $_POST['username'];
    $password = $_POST['password'];

	fof_db_add_user($username, $password);
	$message = "User '$username' added.";
}


if(isset($_POST['deleteuser']) && $_POST['username'])
{
	$username = $_POST['username'];
	
	fof_db_delete_user($username);
	$message = "User '$username' deleted.";
}

include("header.php");

?>

<?php if(isset($message)) { ?>

<br><font color="red"><?php echo $message ?></font><br>

<?php } ?>

<br><h1>Feed on Feeds - Preferences</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;">
Default display order: <select name="order"><option value=desc>new to old</option><option value=asc <?php if($prefs->get('order') == "asc") echo "selected";?>>old to new</option></select><br><br>
Number of items in paged displays: <input type="string" name="howmany" value="<?php echo $prefs->get('howmany') ?>"><br><br>
Display custom feed favicons? <input type="checkbox" name="favicons" <?php if($prefs->get('favicons')) echo "checked=true";?> ><br><br>
Use keyboard shortcuts? <input type="checkbox" name="keyboard" <?php if($prefs->get('keyboard')) echo "checked=true";?> ><br><br>
Time offset in hours: <input size=3 type=string name=tzoffset value="<?php echo $prefs->get('tzoffset')?>"> (UTC time: <?php echo gmdate("Y-n-d g:ia") ?>, local time: <?php echo gmdate("Y-n-d g:ia", time() + $prefs->get("tzoffset")*60*60) ?>)<br><br>
<table border=0 cellspacing=0 cellpadding=2><tr><td>New password:</td><td><input type=password name=password> (leave blank to not change)</td></tr>
<tr><td>Repeat new password:</td><td><input type=password name=password2></td></tr></table>
<br>

Share 
<select name="sharing">
<option value=no>no</option>
<option value=all <?php if($prefs->get('sharing') == "all") echo "selected";?>>all</option>
<option value=tagged <?php if($prefs->get('sharing') == "tagged") echo "selected";?>>tagged as "shared"</option>
</select>
items.
<?php if($prefs->get('sharing') != "no") echo " <small><i>(your shared page is <a href='./shared.php?user=$fof_user_id'>here</a>)</i></small>";?><br><br>
Name to be shown on shared page: <input type=string name=sharedname value="<?php echo $prefs->get('sharedname')?>"><br><br>
URL to be linked on shared page: <input type=string name=sharedurl value="<?php echo $prefs->get('sharedurl')?>">
<br><br>


<?php foreach(fof_get_plugin_prefs() as $plugin_pref) { $name = $plugin_pref[0]; $key = $plugin_pref[1]; ?>
<?php echo $name ?>: <input name="<?php echo $key ?>" value="<?php echo $prefs->get($key)?>"> <i><small>(this preference is from a plugin)</small></i><br><br>
<?php } ?>
<input type=submit name=prefs value="Save Preferences">
</form>

<br><h1>Feed on Feeds - Feeds and Tags</h1>
<div style="border: 1px solid black; margin: 10px; padding: 10px; font-size: 12px; font-family: verdana, arial;">
<table cellpadding=3 cellspacing=0>
<?php
foreach($feeds as $row)
{
   $id = $row['feed_id'];
   $url = $row['feed_url'];
   $title = $row['feed_title'];
   $link = $row['feed_link'];
   $description = $row['feed_description'];
   $age = $row['feed_age'];
   $unread = $row['feed_unread'];
   $starred = $row['feed_starred'];
   $items = $row['feed_items'];
   $agestr = $row['agestr'];
   $agestrabbr = $row['agestrabbr'];
   $lateststr = $row['lateststr'];
   $lateststrabbr = $row['lateststrabbr'];   
   $tags = $row['tags'];
   
   if(++$t % 2)
   {
      print "<tr class=\"odd-row\">";
   }
   else
   {
      print "<tr>";
   }

   if($row['feed_image'] && $prefs->get('favicons'))
   {
	   print "<td><a href=\"$url\" title=\"feed\"><img src='" . $row['feed_image'] . "' width='16' height='16' border='0' /></a></td>";
   }
   else
   {
	   print "<td><a href=\"$url\" title=\"feed\"><img src='image/feed-icon.png' width='16' height='16' border='0' /></a></td>";
   }
    
   print "<td><a href=\"$link\" title=\"home page\">$title</a></td>";
   
   print "<td align=right>";
   
   if($tags)
   {
       foreach($tags as $tag)
       {
           $utag = urlencode($tag);
           $utitle = urlencode($title);
           print "$tag <a href='prefs.php?untagfeed=$id&tag=$utag&title=$utitle'>[x]</a> ";
       }
   }
   else
   {
   }
   
   print "</td>";
   $title = htmlspecialchars($title);
   print "<td><form method=post action=prefs.php><input type=hidden name=title value=\"$title\"><input type=hidden name=feed_id value=$id><input type=string name=tag> <input type=submit name=tagfeed value='Tag Feed'> <small><i>(separate tags with spaces)</i></small></form></td></tr>";
}
?>
</table>
</div>


<?php if(fof_is_admin()) { ?>

<br><h1>Feed on Feeds - Admin Options</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;">
Purge read items after <input size=4 type=string name=purge value="<?php echo $prefs->get('purge')?>"> days (leave blank to never purge)<br><br>
Allow automatic feed updates every <input size=4 type=string name=autotimeout value="<?php echo $prefs->get('autotimeout')?>"> minutes<br><br>
Allow manual feed updates every <input size=4 type=string name=manualtimeout value="<?php echo $prefs->get('manualtimeout')?>"> minutes<br><br>
<input type=submit name=adminprefs value="Save Options">
</form>

<br><h1>Add User</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;">
Username: <input type=string name=username> Password: <input type=string name=password> <input type=submit name=adduser value="Add user">
</form>

<?php
	$result = fof_db_query("select user_name from $FOF_USER_TABLE where user_id > 1");
	
	while($row = fof_db_get_row($result))
	{
		$username = $row['user_name'];
		$delete_options .= "<option value=$username>$username</option>";
	}

    if(isset($delete_options))
    {
?>

<br><h1>Delete User</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;" onsubmit="return confirm('Delete User - Are you sure?')">
<select name=username><?php echo $delete_options ?></select>
<input type=submit name=deleteuser value="Delete user"><br>
</form>

<br><h1>Change User's Password</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;" onsubmit="return confirm('Change Password - Are you sure?')">
<table border=0 cellspacing=0 cellpadding=2>
<tr><td>Select user:</td><td><select name=username><?php echo $delete_options ?></select></td></tr>
<tr><td>New password:</td><td><input type=password name=password></td></tr>
<tr><td>Repeat new password:</td><td><input type=password name=password2></td></tr></table>
<input type=submit name=changepassword value="Change"><br>
</form>

<?php } ?>

<br>
<form method="get" action="uninstall.php" onsubmit="return confirm('Really?  This will delete all the database tables!')">
<center><input type=submit name=uninstall value="Uninstall Feed on Feeds" style="background-color: #ff9999"></center>
</form>

<?php } ?>

<?php include("footer.php") ?>
