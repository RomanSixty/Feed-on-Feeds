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
	
	$message = 'Saved prefs.';
}

if(isset($_POST['adduser']) && isset($_POST['username']) && isset($_POST['password']) )
{
	$username = $_POST['username'];
	$password = $_POST['password'];
	$password_hash = md5($password . $username);

	fof_db_query("insert into $FOF_USER_TABLE (user_name, user_password_hash) values ('$username', '$password_hash')");
	
	$message = "User '$username' added.";
}

if(isset($_POST['deleteuser']) && isset($_POST['username']))
{
	$username = $_POST['username'];
	
	fof_db_query("delete from $FOF_USER_TABLE where user_name = '$username'");
	
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
Time offset in hours: <input size=3 type=string name=tzoffset value="<?php echo $fof_user_prefs['tzoffset']?>"> (UTC time: <?php echo gmdate("Y-n-d g:ia") ?>, local time: <?php echo gmdate("Y-n-d g:ia", time() + $fof_user_prefs["tzoffset"]*60*60) ?>)
<br><br>
<input type=submit name=prefs value="Save Preferences">
</form>

<?php if(fof_is_admin()) { ?>

<br><h1>Add User</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;">
Username: <input type=string name=username> Password: <input type=string name=password> <input type=submit name=adduser value="Add user">
</form>

<br><h1>Delete user</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;" onsubmit="return confirm('Are you sure?')">
<select name=username>
<?php
	$result = fof_db_query("select user_name from $FOF_USER_TABLE where user_id > 1");
	
	while($row = fof_db_get_row($result))
	{
		$username = $row['user_name'];
		echo "<option value=$username>$username</option>";
	}
?>

</select> <input type=submit name=deleteuser value="Delete user"><br>
</form>

<br><h1>Feed on Feeds - Admin Options</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;">
No Admin options yet!<br><br>
<input type=submit name=options value="Save Options">
</form>


<?php } ?>

<?php include("footer.php") ?>
