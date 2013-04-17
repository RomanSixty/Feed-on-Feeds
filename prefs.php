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

include_once('fof-main.php');

/* quell warnings */
function fof_prefs_get_key_($array, $key, $default=NULL) {
    if ( ! empty($array[$key]))
        return $array[$key];
    return $default;
}

$prefs =& FoF_Prefs::instance();

$message = "";

if(fof_is_admin() && isset($_POST['adminprefs']))
{
    $prefs->set('purge', $_POST['purge']);
    $prefs->set('manualtimeout', $_POST['manualtimeout']);
    $prefs->set('autotimeout', $_POST['autotimeout']);
    $prefs->set('logging', $_POST['logging']);
    $prefs->set('blacklist', $_POST['blacklist']);
    $prefs->set('dynupdates', $_POST['dynupdates']);
    $prefs->set('match_similarity', $_POST['match_similarity']);

    $prefs->save();

    $message .= ' Saved admin prefs.';

    if ($prefs->get('logging')) {
        $log_path = (defined('FOF_DATA_PATH') ? FOF_DATA_PATH : '.');
        $log_file = (empty($fof_installer) ? 'fof.log' : 'fof-install.log');
        $log = @fopen(implode(DIRECTORY_SEPARATOR, array($log_path, $log_file)), 'a');

        if ($log) {
            $message .= ' Warning: could not write to log file!';
        }
    }
}

if(isset($_POST['changed']))
{
    $feed_id = $_POST['changed'];
    $title = $_POST['title'];
    $alt_image = $_POST['alt_image'];

    fof_db_feed_update_prefs($feed_id, $title, $alt_image);
    $feed = fof_db_get_feed_by_id($feed_id);
    $message .= " Updated feed settings of '" . $feed['feed_title'] . "'.";
}

if(isset($_POST['tagfeed']))
{
    $tags = $_POST['tag'];
    $feed_id = $_POST['feed_id'];

    foreach(explode(" ", $tags) as $tag)
    {
        fof_tag_feed(fof_current_user(), $feed_id, $tag);
        $feed = fof_db_get_feed_by_id($feed_id);
        $message .= " Tagged '" . $feed['feed_title'] . "' as $tag.";
    }
}

if(isset($_GET['untagfeed']))
{
    $feed_id = $_GET['untagfeed'];
    $tags = $_GET['tag'];

    foreach(explode(" ", $tags) as $tag)
    {
        fof_untag_feed(fof_current_user(), $feed_id, $tag);
        $feed = fof_db_get_feed_by_id($feed_id);
        $message .= " Dropped $tag from '" . $feed['feed_title'] . "'.";
    }
}

if(isset($_POST['prefs']))
{
    $prefs->set('sidebar_style', $_POST['sidebar_style']);
    $prefs->set('simple_sidebar', isset($_POST['simple_sidebar']));
    $prefs->set('favicons', isset($_POST['favicons']));
    $prefs->set('keyboard', isset($_POST['keyboard']));
    $prefs->set('item_target', isset($_POST['item_target']));
    $prefs->set('tzoffset', intval($_POST['tzoffset']));
    $prefs->set('howmany', intval($_POST['howmany']));
    $prefs->set('order', $_POST['order']);
    $prefs->set('sharing', $_POST['sharing']);
    $prefs->set('sharedname', $_POST['sharedname']);
    $prefs->set('sharedurl', $_POST['sharedurl']);

    $prefs->save(fof_current_user());

    if ( ! defined('FOF_AUTH_EXTERNAL_ONLY')) {
        if($_POST['password'] && ($_POST['password'] == $_POST['password2']))
        {
            fof_db_change_password($fof_user_name, $_POST['password']);
            $user_password_hash = fof_db_user_password_hash($_POST['password'], $fof_user_name);
            setcookie ( "user_password_hash",  $user_password_hash, time()+60*60*24*365*10 );
            $message = "Updated password.";
        }
        else if($_POST['password'] || $_POST['password2'])
        {
            $message = "Passwords do not match!";
        }
    }

    $message .= ' Saved prefs.';
}

if(isset($_POST['plugins']))
{
    foreach(fof_get_plugin_prefs() as $plugin_pref)
    {
        $key = $plugin_pref[1];
        $prefs->set($key, fof_prefs_get_key_($_POST, $key));
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
        $prefs->set("plugin_" . $plugin, fof_prefs_get_key_($_POST, $plugin) != "on");
    }

    $prefs->save(fof_current_user());

    $message .= ' Saved plugin prefs.';
}

if ( ! defined('FOF_AUTH_EXTERNAL_ONLY')) {
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
}

include("header.php");

if ( ! empty($message)) {
    echo "<br><span style=\"color:red\">$message</span><br>\n";
}
?>
<br>
<h1 id="basic">Feed on Feeds - Preferences</h1>
<form method="post" action="prefs.php#basic" style="border: 1px solid black; margin: 10px; padding: 10px;">
  <fieldset>
    <legend><b>Basic Settings</b></legend>
    Sidebar style: <select name="sidebar_style">
<?php
    $sidebar_style = $prefs->get('sidebar_style');
    /* migrate old settings */
    if (empty($sidebar_style) && $prefs->get('simple_sidebar'))
        $sidebar_style = 'simple';
?>
        <option value=""<?php if (empty($sidebar_style)) echo ' selected'; ?>>Default</option>
        <option value="simple"<?php if ($sidebar_style === 'simple') echo ' selected'; ?>>Simple</option>
        <option value="fancy"<?php if ($sidebar_style === 'fancy') echo ' selected'; ?>>Fancy</option>
    </select>
    <br>
    Default display order: <select name="order"><option value=desc>new to old</option><option value=asc <?php if($prefs->get('order') == "asc") echo "selected";?>>old to new</option></select>
    <br>
    Number of items in paged displays: <input type="string" size="3" name="howmany" value="<?php echo $prefs->get('howmany') ?>">
    <br>
    Display custom feed favicons? <input type="checkbox" name="favicons"<?php if($prefs->get('favicons')) echo " checked=true";?>>
    <br>
    Use keyboard shortcuts? <input type="checkbox" name="keyboard"<?php if($prefs->get('keyboard')) echo " checked=true";?>>
    <br>
    Open articles in new window? <input type="checkbox" name="item_target"<?php if($prefs->get('item_target')) echo " checked=true";?>>
    <br>
    Time offset in hours: <input size="3" type="text" name=tzoffset value="<?php echo $prefs->get('tzoffset')?>"> (UTC time: <?php echo gmdate("Y-n-d g:ia") ?>, local time: <?php echo gmdate("Y-n-d g:ia", time() + $prefs->get("tzoffset")*60*60) ?>)
  </fieldset>
  <br>
  <fieldset>
    <legend><b>Sharing</b></legend>
    Share <select name="sharing">
      <option value="no">no</option>
      <option value="all"<?php if ($prefs->get('sharing') == 'all') echo ' selected';?>>all</option>
      <option value="tagged"<?php if ($prefs->get('sharing') == 'tagged') echo ' selected';?>>tagged as "shared"</option>
      <option value="all_tagged"<?php if ($prefs->get('sharing') == 'all_tagged') echo ' selected';?>>all tagged items</option>
    </select> items.<?php if($prefs->get('sharing') != "no") echo " <small><i>(your default shared page is <a href='./shared.php?user=" . urlencode($fof_user_id) . "'>here</a>)</i></small>";?>
    <br>
    Name to be shown on shared page: <input type="text" name="sharedname" value="<?php echo $prefs->get('sharedname')?>">
    <br>
    URL to be linked on shared page: <input type="text" name="sharedurl" value="<?php echo $prefs->get('sharedurl')?>">
  </fieldset>
  <br>
<?php
if ( ! defined('FOF_AUTH_EXTERNAL_ONLY')) {
?>
  <fieldset>
    <legend><b>Password</b></legend>
    <table border=0 cellspacing=0 cellpadding=2>
      <tr>
        <td>New password:</td>
        <td><input type=password name=password> (leave blank to not change)</td>
      </tr>
      <tr>
        <td>Repeat new password:</td>
        <td><input type=password name=password2></td>
      </tr>
    </table>
  </fieldset>
  <br>
<?php
}
?>
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

    foreach ($plugins as $plugin) {
        echo '<input type=checkbox name=\'' . htmlentities($plugin) . '\'';
        if ( ! $prefs->get('plugin_' . $plugin))
            echo ' checked';
        echo '> Enable plugin <tt>' . $plugin . "</tt>?<br>\n";
    }
?>

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
    $anchor = 'feed_row_' . $id;

    $url = $row['feed_url'];
    $title = htmlentities(fof_prefs_get_key_($row, 'feed_title'), ENT_QUOTES);
    $alt_image = htmlentities(fof_prefs_get_key_($row, 'alt_image'), ENT_QUOTES);
    $description = fof_prefs_get_key_($row, 'feed_description');
    $tags = fof_prefs_get_key_($row, 'tags', array());

    echo '<tr' . (++$t % 2 ? ' class="odd-row"' : '') . ">\n";

    if ($prefs->get('favicons')) {
        $feed_image = fof_prefs_get_key_($row, 'feed_image', 'image/feed-icon.png');
    } else {
        $feed_image = 'image/feed-icon.png';
    }
    echo "  <td><a href=\"$url\" title=\"feed\" name=\"$anchor\"><img src='$feed_image' width='16' height='16' border='0' /></a></td>";

    echo "  <td>
    <form method=\"post\" action=\"prefs.php#$anchor\">
      <input type=\"hidden\" name=\"changed\" value=\"$id\"/>
      <input type=\"text\" name=\"title\" value=\"$title\" size=\"50\"/>
      <input type=\"text\" name=\"alt_image\" value=\"$alt_image\" size=\"50\"/>
      <input type=\"submit\" value=\"submit\"/>
    </form>
  </td>";

    echo "  <td align=right>";
    foreach ($tags as $tag) {
        echo $tag . ' <a href="' . fof_url('prefs.php', array('untagfeed' => $id, 'tag' => $tag), $anchor) . '">[x]</a> ';
    }
    echo "</td>\n";

    $title = htmlspecialchars($title);
    echo "  <td>
    <form method=\"post\" action=\"prefs.php#$anchor\">
      <input type=\"hidden\" name=\"feed_id\" value=\"$id\">
      <input type=\"text\" name=\"tag\">
      <input type=\"submit\" name=\"tagfeed\" value=\"Tag Feed\"> <small><i>(separate tags with spaces)</i></small>
    </form>
  </td>\n";

    echo "</tr>\n";
}
?>
</table>
</div>


<?php if(fof_is_admin()) { ?>

<br><h1 id="adminprefs">Feed on Feeds - Admin Options</h1>
<form method="post" action="prefs.php#adminprefs" style="border: 1px solid black; margin: 10px; padding: 10px;">
Enable logging? <input type=checkbox name="logging" <?=$prefs->get('logging')?"checked":'' ?>><br><br>
Purge read items after <input size="4" type="text" name=purge value="<?=$prefs->get('purge')?>"> days (leave blank to never purge)<br><br>
Allow automatic feed updates every <input size="4" type="text" name=autotimeout value="<?=$prefs->get('autotimeout')?>"> minutes<br><br>
Allow manual feed updates every <input size="4" type="text" name=manualtimeout value="<?=$prefs->get('manualtimeout')?>"> minutes<br><br>
Use dynamic update intervals based on each feed's own update rate <input type="checkbox" name="dynupdates" value="1" <?=$prefs->get('dynupdates')?'checked':''?>><br><br>
Remove duplicate entries based on similarity <input size="4" type="text" name="match_similarity" value="<?=$prefs->get('match_similarity')?>">% (leave blank to disable)<br><br>

<fieldset>
<legend><b>Feed Item Title Blacklist</b></legend>
Feed items with titles containing one of the following terms (one per line) will be discarded immediately. The matching is not case sensitive.<br>
<textarea name="blacklist" cols="100" rows="10"><?php echo $prefs->get('blacklist')?></textarea>
</fieldset>
<br>

<input type=submit name=adminprefs value="Save Admin Options">
</form>

<?php
if ( ! defined('FOF_AUTH_EXTERNAL_ONLY')) {
?>
<br><h1 id="adduser">Add User</h1>
<form method="post" action="prefs.php#adduser" style="border: 1px solid black; margin: 10px; padding: 10px;">
Username: <input type="text" name=username> Password: <input type="text" name=password> <input type=submit name=adduser value="Add user">
</form>

<?php
    $result = fof_db_get_nonadmin_usernames();

    $user_options = array();
    while ( ($row = fof_db_get_row($result)) !== false ) {
        $username = $row['user_name'];
        $user_options[] = "<option value=$username>$username</option>";
    }

    if ( ! empty($user_options))
    {
?>

<br><h1 id="deluser">Delete User</h1>
<form method="post" action="prefs.php#deluser" style="border: 1px solid black; margin: 10px; padding: 10px;" onsubmit="return confirm('Delete User - Are you sure?')">
<select name=username><?php echo implode('', $user_options); ?></select>
<input type=submit name=deleteuser value="Delete user"><br>
</form>

<br><h1>Change User's Password</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;" onsubmit="return confirm('Change Password - Are you sure?')">
<table border=0 cellspacing=0 cellpadding=2>
<tr><td>Select user:</td><td><select name=username><?php echo implode('', $user_options); ?></select></td></tr>
<tr><td>New password:</td><td><input type=password name=password></td></tr>
<tr><td>Repeat new password:</td><td><input type=password name=password2></td></tr></table>
<input type=submit name=changepassword value="Change"><br>
</form>

<?php } ?>

<?php
} /* FOF_AUTH_EXTERNAL_ONLY */
?>
<br>
<form method="get" action="uninstall.php" onsubmit="return confirm('Really?  This will delete all the database tables!')">
<center><input type=submit name=uninstall value="Uninstall Feed on Feeds" style="background-color: #ff9999"></center>
</form>

<?php } ?>

<?php include("footer.php") ?>
