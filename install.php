<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * install.php - creates tables and cache directory, if they don't exist
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

$fof_no_login = true;

include_once("fof-main.php");
header("Content-Type: text/html; charset=utf-8");

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head><title>feed on feeds - installation</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<link rel="stylesheet" href="fof.css" media="screen" />
		<script src="fof.js" type="text/javascript"></script>
		<meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
	</head>

	<body id="items" style="margin: 5px;">

<?php
if($_GET['password'])
{
	$password_hash = md5($_GET['password'] . 'admin');
	fof_safe_query("insert into $FOF_USER_TABLE (user_id, user_name, user_password_hash, user_level) values (1, 'admin', '%s', 'admin')", $password_hash);
	
	echo 'OK!  Setup complete! <a href=".">Login as admin</a>, and start subscribing!';
}
else
{
?>

Creating tables...<br>
<?php

$tables[] = <<<EOQ
CREATE TABLE IF NOT EXISTS `$FOF_FEED_TABLE` (
  `feed_id` int(11) NOT NULL auto_increment,
  `feed_url` text NOT NULL,
  `feed_title` text NOT NULL,
  `feed_link` text NOT NULL,
  `feed_description` text NOT NULL,
  `feed_image` text,
  `feed_cache_date` int(11) default '0',
  `feed_cache_attempt_date` int(11) default '0',
  `feed_cache` text,
  PRIMARY KEY  (`feed_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
EOQ;

$tables[] = <<<EOQ
CREATE TABLE IF NOT EXISTS `$FOF_ITEM_TABLE` (
  `item_id` int(11) NOT NULL auto_increment,
  `feed_id` int(11) NOT NULL default '0',
  `item_guid` text NOT NULL,
  `item_link` text NOT NULL,
  `item_cached` int(11) NOT NULL default '0',
  `item_published` int(11) NOT NULL default '0',
  `item_updated` int(11) NOT NULL default '0',
  `item_title` text NOT NULL,
  `item_content` text NOT NULL,
  PRIMARY KEY  (`item_id`),
  KEY `feed_id` (`feed_id`),
  KEY `item_guid` (`item_guid`(255))
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
EOQ;

$tables[] = <<<EOQ
CREATE TABLE IF NOT EXISTS `$FOF_ITEM_TAG_TABLE` (
  `user_id` int(11) NOT NULL default '0',
  `item_id` int(11) NOT NULL default '0',
  `tag_id` int(11) NOT NULL default '0',
  PRIMARY KEY  (`user_id`,`item_id`,`tag_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
EOQ;

$tables[] = <<<EOQ
CREATE TABLE IF NOT EXISTS `$FOF_SUBSCRIPTION_TABLE` (
  `feed_id` int(11) NOT NULL default '0',
  `user_id` int(11) NOT NULL default '0',
  `subscription_prefs` text,
  PRIMARY KEY  (`feed_id`,`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
EOQ;

$tables[] = <<<EOQ
CREATE TABLE IF NOT EXISTS `$FOF_TAG_TABLE` (
  `tag_id` int(11) NOT NULL auto_increment,
  `tag_name` char(100) NOT NULL default '',
  PRIMARY KEY  (`tag_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
EOQ;

$tables[] = <<<EOQ
CREATE TABLE IF NOT EXISTS `$FOF_USER_TABLE` (
  `user_id` int(11) NOT NULL auto_increment,
  `user_name` varchar(100) NOT NULL default '',
  `user_password_hash` varchar(32) NOT NULL default '',
  `user_level` enum('user','admin') NOT NULL default 'user',
  `user_prefs` text,
  PRIMARY KEY  (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
EOQ;

foreach($tables as $table)
{
	if(!fof_db_query($table, 1))
	{
		exit ("Can't create table.  MySQL says: <b>" . mysql_error() . "</b><br>" );
	}
}

?>
Tables exist.<br><br>

<?php
$result = fof_db_query("show columns from $FOF_USER_TABLE like 'user_password_hash'");

if(mysql_num_rows($result) == 0)
{

print "Upgrading schema...";

fof_db_query("ALTER TABLE $FOF_USER_TABLE CHANGE `user_password` `user_password_hash` VARCHAR( 32 ) NOT NULL");
fof_db_query("update $FOF_USER_TABLE set user_password_hash = md5(concat(user_password_hash, user_name))");

print "Done.<BR><BR>";
}
?>


<?php
$result = fof_db_query("show columns from $FOF_FEED_TABLE like 'feed_cache_attempt_date'");

if(mysql_num_rows($result) == 0)
{

print "Upgrading schema...";

fof_db_query("ALTER TABLE $FOF_FEED_TABLE ADD `feed_cache_attempt_date` INT( 11 ) DEFAULT '0' AFTER `feed_cache_date` ;");

print "Done.<BR><BR>";
}
?>

Inserting initial data...

<?php
fof_db_query("insert into $FOF_TAG_TABLE (tag_id, tag_name) values (1, 'unread')", 1);
fof_db_query("insert into $FOF_TAG_TABLE (tag_id, tag_name) values (2, 'star')", 1);
?>

Done.<br><br>

Checking cache directory...<br>
<?php

if ( ! file_exists( "cache" ) )
{
	$status = @mkdir( "cache", 0755 );

	if ( ! $status )
	{
		echo "Can't create directory <code>" . getcwd() . "/cache/</code>.<br>You will need to create it yourself, and make it writeable by your PHP process.<br>Then, reload this page.";
		exit;
	}
}

if(!is_writable( "cache" ))
{
		echo "The directory <code>" . getcwd() . "/cache/</code> exists, but is not writable.<br>You will need to make it writeable by your PHP process.<br>Then, reload this page.";
		exit;
}

?>

Cache directory exists and is writable.<br><br>

<?php
	$result = fof_db_query("select * from $FOF_USER_TABLE where user_name = 'admin'");
	if(mysql_num_rows($result) == 0) {
?>

You now need to chose a password for the 'admin' account.<br><br>

Password: <form><input type=string name=password><input type=submit></form>

<?php } else { ?>

'admin' account already exists.<br><br>
OK!  Setup complete! <a href=".">Login as admin</a>, and start subscribing!

<?php } } ?>

</body></html>
