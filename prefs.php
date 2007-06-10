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

if(isset($_POST['prefs']))
{
	$fof_user_prefs['favicons'] = isset($_POST['favicons']);
	$fof_user_prefs['keyboard'] = isset($_POST['keyboard']);
	$fof_user_prefs['tzoffset'] = intval($_POST['tzoffset']);

	fof_db_save_prefs(fof_current_user(), $fof_user_prefs);
    
    if($_POST['password'] && ($_POST['password'] == $_POST['password2']))
    {
        fof_db_change_password($fof_user_name, $_POST['password']);
        setcookie ( "user_password_hash",  md5($_POST['password'] . $fof_user_name), time()+60*60*24*365*10, "/" );
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

$favicons = $_POST['favicons'];
?>

<?php if(isset($message)) { ?>

<br><font color="red"><?php echo $message ?></font><br>

<?php } ?>

<br><h1>Feed on Feeds - Preferences</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;">
Display custom feed favicons? <input type="checkbox" name="favicons" <?php if($fof_user_prefs['favicons']) echo "checked=true";?> ><br><br>
Use keyboard shortcuts? <input type="checkbox" name="keyboard" <?php if($fof_user_prefs['keyboard']) echo "checked=true";?> ><br><br>
Time offset in hours: <input size=3 type=string name=tzoffset value="<?php echo $fof_user_prefs['tzoffset']?>"> (UTC time: <?php echo gmdate("Y-n-d g:ia") ?>, local time: <?php echo gmdate("Y-n-d g:ia", time() + $fof_user_prefs["tzoffset"]*60*60) ?>)<br><br>
<table border=0 cellspacing=0 cellpadding=2><tr><td>New password:</td><td><input type=password name=password> (leave blank to not change)</td></tr>
<tr><td>Repeat new password:</td><td><input type=password name=password2></td></tr></table>
<br>
<input type=submit name=prefs value="Save Preferences">
</form>

<?php if(fof_is_admin()) { ?>

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

<!--
<br><h1>Feed on Feeds - Admin Options</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;">
No Admin options yet!<br><br>
<input type=submit name=options value="Save Options">
</form>
-->

<br>
<form method="get" action="uninstall.php" onsubmit="return confirm('Really?  This will delete all the database tables!')">
<center><input type=submit name=uninstall value="Uninstall Feed on Feeds" style="background-color: #ff9999"></center>
</form>

<?php } ?>

<?php include("footer.php") ?>
