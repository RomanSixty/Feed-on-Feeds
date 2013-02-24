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

if(fof_is_admin() && isset($_POST['adminprefs']))
{
	$prefs->set('purge', $_POST['purge']);
	$prefs->set('manualtimeout', $_POST['manualtimeout']);
	$prefs->set('autotimeout', $_POST['autotimeout']);
	$prefs->set('logging', $_POST['logging']);
	$prefs->set('blacklist', $_POST['blacklist']);

	$prefs->save();

	$message .= ' Saved admin prefs.';

    if($prefs->get('logging') && !@fopen("fof.log", 'a'))
    {
        $message .= ' Warning: could not write to log file!';
    }
}

if(isset($_POST['changed']))
{
    $feed_id = $_POST['changed'];
    $title = $_POST['title'];
    $alt_image = $_POST['alt_image'];

    fof_update_feed_prefs($feed_id, $title, $alt_image);
    $message .= " Updated feed settings of '$title'.";
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
	$prefs->set('simple_sidebar', isset($_POST['simple_sidebar']));
	$prefs->set('favicons', isset($_POST['favicons']));
	$prefs->set('keyboard', isset($_POST['keyboard']));
	$prefs->set('tzoffset', intval($_POST['tzoffset']));
	$prefs->set('howmany', intval($_POST['howmany']));
	$prefs->set('order', $_POST['order']);
	$prefs->set('sharing', $_POST['sharing']);
	$prefs->set('sharedname', $_POST['sharedname']);
	$prefs->set('sharedurl', $_POST['sharedurl']);

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

if(isset($_POST['plugins']))
{
    foreach(fof_get_plugin_prefs() as $plugin_pref)
    {
        $key = $plugin_pref[1];
        $prefs->set($key, $_POST[$key]);
    }

    $plugins = array();
    $dirlist = opendir(FOF_DIR . "/plugins");
    while($file=readdir($dirlist))
    {
        if(preg_match('/\.php$/',$file))
        {
           $plugins[] = substr($file, 0, -4);
        }
    }

    closedir();

    foreach($plugins as $plugin)
    {
        $prefs->set("plugin_" . $plugin, $_POST[$plugin] != "on");
    }

	$prefs->save(fof_current_user());

	$message .= ' Saved plugin prefs.';
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

if(fof_is_admin() && isset($_POST['adduser']) && $_POST['username'] && $_POST['password']) 
{
    $username = $_POST['username'];
    $password = $_POST['password'];

	fof_db_add_user($username, $password);
	$message = "User '$username' added.";
}


if(fof_is_admin() && isset($_POST['deleteuser']) && $_POST['username'])
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

<br><h1 id="basic">Feed on Feeds - Preferences</h1>
<form method="post" action="prefs.php#basic" style="border: 1px solid black; margin: 10px; padding: 10px;">

<fieldset>
<legend><b>Basic Settings</b></legend>
Default display order: <select name="order"><option value=desc>new to old</option><option value=asc <?php if($prefs->get('order') == "asc") echo "selected";?>>old to new</option></select><br><br>
Number of items in paged displays: <input type="string" size="3" name="howmany" value="<?php echo $prefs->get('howmany') ?>"><br><br>
Display custom feed favicons? <input type="checkbox" name="favicons" <?php if($prefs->get('favicons')) echo "checked=true";?> ><br><br>
Display simple sidebar? <input type="checkbox" name="simple_sidebar" <?php if($prefs->get('simple_sidebar')) echo "checked=true";?> ><br><br>
Use keyboard shortcuts? <input type="checkbox" name="keyboard" <?php if($prefs->get('keyboard')) echo "checked=true";?> ><br><br>
Time offset in hours: <input size="3" type="text" name=tzoffset value="<?php echo $prefs->get('tzoffset')?>"> (UTC time: <?php echo gmdate("Y-n-d g:ia") ?>, local time: <?php echo gmdate("Y-n-d g:ia", time() + $prefs->get("tzoffset")*60*60) ?>)
</fieldset>
<br>

<fieldset>
<legend><b>Sharing</b></legend>
Share
<select name="sharing">
<option value="no"">no</option>
<option value="all" <?php if($prefs->get('sharing') == "all") echo "selected";?>>all</option>
<option value="tagged" <?php if($prefs->get('sharing') == "tagged") echo "selected";?>>tagged as "shared"</option>
<option value="all_tagged" <?php if($prefs->get('sharing') == "all_tagged") echo "selected";?>>all tagged items</option>
</select>
items.
<?php if($prefs->get('sharing') != "no") echo " <small><i>(your default shared page is <a href='./shared.php?user=$fof_user_id'>here</a>)</i></small>";?><br><br>
Name to be shown on shared page: <input type="text" name="sharedname" value="<?php echo $prefs->get('sharedname')?>"><br><br>
URL to be linked on shared page: <input type="text" name="sharedurl" value="<?php echo $prefs->get('sharedurl')?>">
</fieldset>
<br>

<fieldset>
<legend><b>Password</b></legend>
<table border=0 cellspacing=0 cellpadding=2><tr><td>New password:</td><td><input type=password name=password> (leave blank to not change)</td></tr>
<tr><td>Repeat new password:</td><td><input type=password name=password2></td></tr></table>
</fieldset>
<br>

<input type=submit name=prefs value="Save Preferences">
</form>

<br><h1 id="plugins">Feed on Feeds - Plugin Preferences</h1>
<form method="post" action="prefs.php#plugins" style="border: 1px solid black; margin: 10px; padding: 10px;">

<?php
    $plugins = array();
    $dirlist = opendir(FOF_DIR . "/plugins");
    while($file=readdir($dirlist))
    {
    	fof_log("considering " . $file);
        if(preg_match('/\.php$/',$file))
        {
           $plugins[] = substr($file, 0, -4);
        }
    }

    closedir();
?>

<?php foreach($plugins as $plugin) { ?>
<input type=checkbox name=<?php echo $plugin ?> <?php if(!$prefs->get("plugin_" . $plugin)) echo "checked"; ?>> Enable plugin <tt><?php echo $plugin?></tt>?<br>
<?php } ?>

<br>
<?php foreach(fof_get_plugin_prefs() as $plugin_pref) { $name = $plugin_pref[0]; $key = $plugin_pref[1]; $type = $plugin_pref[2]; ?>
<?php echo $name ?>:

<?php if($type == "boolean") { ?>
<input name="<?php echo $key ?>" type="checkbox" <?php if($prefs->get($key)) echo "checked" ?>><br>
<?php } else { ?>
<input name="<?php echo $key ?>" value="<?php echo $prefs->get($key)?>"><br>
<?php } } ?>
<br>
<input type="submit" name="plugins" value="Save Plugin Preferences">
</form>

<?php
// advanced plugin preferences
foreach ( $plugins as $plugin )
{
	// save preferences
	$funcname = 'save_prefs_' . $plugin;

	if ( function_exists ( $funcname ) )
		$funcname ( $_POST );

	// view and edit preferences
	$funcname = 'edit_prefs_' . $plugin;

	if ( function_exists ( $funcname ) )
		$funcname ( $prefs );
}
?>

<br><h1 id="feedsntags">Feed on Feeds - Feeds and Tags</h1>
<div style="border: 1px solid black; margin: 10px; padding: 10px; font-size: 12px; font-family: verdana, arial;">
<table cellpadding="3" cellspacing="0">
<?php
foreach($feeds as $row)
{
   $id = $row['feed_id'];
   $url = $row['feed_url'];
   $title = $row['feed_title'];
   $alt_image = $row['alt_image'];
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

   print "<td>
            <form method=\"post\" action=\"prefs.php#feedsntags\">
            <input type=\"hidden\" name=\"changed\" value=\"$id\"/>
            <input type=\"text\" name=\"title\" value=\"$title\" size=\"50\"/>
            <input type=\"text\" name=\"alt_image\" value=\"$alt_image\" size=\"50\"/>
            <input type=\"submit\" value=\"submit\"/>
            </form>
          </td>";


   print "<td align=right>";

   if($tags)
   {
       foreach($tags as $tag)
       {
           $utag = urlencode($tag);
           $utitle = urlencode($title);
           print "$tag <a href='prefs.php?untagfeed=$id&tag=$utag&title=$utitle#feedsntags'>[x]</a> ";
       }
   }
   else
   {
   }

   print "</td>";
   $title = htmlspecialchars($title);
   print "<td><form method=\"post\" action=\"prefs.php#feedsntags\"><input type=\"hidden\" name=\"title\" value=\"$title\"><input type=\"hidden\" name=\"feed_id\" value=\"$id\"><input type=\"text\" name=\"tag\"> <input type=\"submit\" name=\"tagfeed\" value=\"Tag Feed\"> <small><i>(separate tags with spaces)</i></small></form></td></tr>";
}
?>
</table>
</div>


<?php if(fof_is_admin()) { ?>

<br><h1 id="adminprefs">Feed on Feeds - Admin Options</h1>
<form method="post" action="prefs.php#adminprefs" style="border: 1px solid black; margin: 10px; padding: 10px;">
Enable logging? <input type=checkbox name="logging" <?php if($prefs->get('logging')) echo "checked" ?>><br><br>
Purge read items after <input size="4" type="text" name=purge value="<?php echo $prefs->get('purge')?>"> days (leave blank to never purge)<br><br>
Allow automatic feed updates every <input size="4" type="text" name=autotimeout value="<?php echo $prefs->get('autotimeout')?>"> minutes<br><br>
Allow manual feed updates every <input size="4" type="text" name=manualtimeout value="<?php echo $prefs->get('manualtimeout')?>"> minutes<br><br>

<fieldset>
<legend><b>Feed Item Title Blacklist</b></legend>
Feed items with titles containing one of the following terms (one per line) will be discarded immediately. The matching is not case sensitive.<br>
<textarea name="blacklist" cols="100" rows="10"><?php echo $prefs->get('blacklist')?></textarea>
</fieldset>
<br>

<input type=submit name=adminprefs value="Save Admin Options">
</form>

<br><h1 id="adduser">Add User</h1>
<form method="post" action="prefs.php#adduser" style="border: 1px solid black; margin: 10px; padding: 10px;">
Username: <input type="text" name=username> Password: <input type="text" name=password> <input type=submit name=adduser value="Add user">
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

<br><h1 id="deluser">Delete User</h1>
<form method="post" action="prefs.php#deluser" style="border: 1px solid black; margin: 10px; padding: 10px;" onsubmit="return confirm('Delete User - Are you sure?')">
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
