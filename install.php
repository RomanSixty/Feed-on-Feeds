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
$fof_installer = true;

include_once("fof-main.php");

fof_set_content_type();

// compatibility testing code lifted from SimplePie

function get_curl_version()
{
        if (is_array($curl = curl_version()))
        {
                $curl = $curl['version'];
        }
        else if (preg_match('/curl\/(\S+)(\s|$)/', $curl, $match))
        {
                $curl = $match[1];
        }
        else
        {
                $curl = 0;
        }
        return $curl;
}

$php_ok = (function_exists('version_compare') && version_compare(phpversion(), '4.3.2', '>='));
$xml_ok = extension_loaded('xml');
$pcre_ok = extension_loaded('pcre');
$mysql_ok = extension_loaded('mysql');

$curl_ok = (extension_loaded('curl') && version_compare(get_curl_version(), '7.10.5', '>='));
$zlib_ok = extension_loaded('zlib');
$mbstring_ok = extension_loaded('mbstring');
$iconv_ok = extension_loaded('iconv');

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head><title>feed on feeds - installation</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<link rel="stylesheet" href="fof.css" media="screen" />
		<script src="fof.js" type="text/javascript"></script>
		<meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
        <style>
        body
        {
            font-family: georgia;
            font-size: 16px;
        }
        
        div
        {
            background: #eee;
            border: 1px solid black;
            width: 75%;
            margin: 5em auto;
            padding: 1.5em;
        }
        
        hr
        {
            height:0;
            border:0;
            border-top:1px solid #999;
        }
        
        .fail { color: red; }
        
        .pass { color: green; }

        .warn { color: #a60; }
        
        </style>

	</head>

	<body><div>		<center style="font-size: 20px;"><a href="http://feedonfeeds.com/">Feed on Feeds</a> - Installation</center><br>


<?php
if($_GET['password'] && $_GET['password'] == $_GET['password2'] )
{
	$password_hash = md5($_GET['password'] . 'admin');
	fof_safe_query("insert into $FOF_USER_TABLE (user_id, user_name, user_password_hash, user_level) values (1, 'admin', '%s', 'admin')", $password_hash);
	
	echo '<center><b>OK!  Setup complete! <a href=".">Login as admin</a>, and start subscribing!</center></b></div></body></html>';
}
else
{
    if($_GET['password'] != $_GET['password2'] )
    {
        echo '<center><font color="red">Passwords do not match!</font></center><br><br>';
    }

?>

Checking compatibility...
<?php
if($php_ok) echo "<span class='pass'>PHP ok...</span> ";
else
{
    echo "<br><span class='fail'>Your PHP version is too old!</span>  Feed on Feeds requires at least PHP 4.3.2.  Sorry!";
    echo "</div></body></html>";
    exit;
}

if($xml_ok) echo "<span class='pass'>XML ok...</span> ";
else
{
    echo "<br><span class='fail'>Your PHP installation is missing the XML extension!</span>  This is required by Feed on Feeds.  Sorry!";
    echo "</div></body></html>";
    exit;
}

if($pcre_ok) echo "<span class='pass'>PCRE ok...</span> ";
else
{
    echo "<br><span class='fail'>Your PHP installation is missing the PCRE extension!</span>  This is required by Feed on Feeds.  Sorry!";
    echo "</div></body></html>";
    exit;
}

if($mysql_ok) echo "<span class='pass'>MySQL ok...</span> ";
else
{
    echo "<br><span class='fail'>Your PHP installation is missing the MySQL extension!</span>  This is required by Feed on Feeds.  Sorry!";
    echo "</div></body></html>";
    exit;
}

if($curl_ok) echo "<span class='pass'>cURL ok...</span> ";
else
{
    echo "<br><span class='warn'>Your PHP installation is either missing the cURL extension, or it is too old!</span>  cURL version 7.10.5 or later is required to be able to subscribe to https or digest authenticated feeds.<br>";
}

if($zlib_ok) echo "<span class='pass'>Zlib ok...</span> ";
else
{
    echo "<br><span class='warn'>Your PHP installation is missing the Zlib extension!</span>  Feed on Feeds will not be able to save bandwidth by requesting compressed feeds.<br>";
}

if($iconv_ok) echo "<span class='pass'>iconv ok...</span> ";
else
{
    echo "<br><span class='warn'>Your PHP installation is missing the iconv extension!</span>  The number of international languages that Feed on Feeds can handle will be reduced.<br>";
}

if($mbstring_ok) echo "<span class='pass'>mbstring ok...</span> ";
else
{
    echo "<br><span class='warn'>Your PHP installation is missing the mbstring extension!</span>  The number of international languages that Feed on Feeds can handle will be reduced.<br>";
}

?>
<br>Minimum requirements met!
<hr>

Creating tables...
<?php

$tables[] = <<<EOQ
CREATE TABLE IF NOT EXISTS `$FOF_FEED_TABLE` (
  `feed_id` int(11) NOT NULL auto_increment,
  `feed_url` text NOT NULL,
  `feed_title` text NOT NULL,
  `feed_link` text NOT NULL,
  `feed_description` text NOT NULL,
  `feed_image` text,
  `alt_image` text,
  `feed_image_cache_date` int(11) default '0',
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
  KEY `item_guid` (`item_guid`(255)),
  KEY `feed_id_item_cached` (`feed_id`,`item_cached`)
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
  PRIMARY KEY  (`tag_id`),
  UNIQUE KEY (`tag_name`)
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
Tables exist.<hr>

<?php
$result = fof_db_query("show columns from $FOF_FEED_TABLE like 'feed_image_cache_date'");

if(mysql_num_rows($result) == 0)
{

print "Upgrading schema...";

fof_db_query("ALTER TABLE $FOF_FEED_TABLE ADD `feed_image_cache_date` INT( 11 ) DEFAULT '0' AFTER `feed_image` ;");

print "Done.<hr>";
}
?>


<?php
$result = fof_db_query("show columns from $FOF_USER_TABLE like 'user_password_hash'");

if(mysql_num_rows($result) == 0)
{

print "Upgrading schema...";

fof_db_query("ALTER TABLE $FOF_USER_TABLE CHANGE `user_password` `user_password_hash` VARCHAR( 32 ) NOT NULL");
fof_db_query("update $FOF_USER_TABLE set user_password_hash = md5(concat(user_password_hash, user_name))");

print "Done.<hr>";
}
?>


<?php
$result = fof_db_query("show columns from $FOF_FEED_TABLE like 'feed_cache_attempt_date'");

if(mysql_num_rows($result) == 0)
{

print "Upgrading schema...";

fof_db_query("ALTER TABLE $FOF_FEED_TABLE ADD `feed_cache_attempt_date` INT( 11 ) DEFAULT '0' AFTER `feed_cache_date` ;");

print "Done.<hr>";
}
?>

Inserting initial data...

<?php
fof_db_query("insert into $FOF_TAG_TABLE (tag_id, tag_name) values (1, 'unread')", 1);
fof_db_query("insert into $FOF_TAG_TABLE (tag_id, tag_name) values (2, 'star')", 1);
fof_db_query("insert into $FOF_TAG_TABLE (tag_id, tag_name) values (3, 'folded')", 1);
?>

Done.<hr>

Checking cache directory...
<?php

if ( ! file_exists( "cache" ) )
{
	$status = @mkdir( "cache", 0755 );

	if ( ! $status )
	{
		echo "<font color='red'>Can't create directory <code>" . getcwd() . "/cache/</code>.<br>You will need to create it yourself, and make it writeable by your PHP process.<br>Then, reload this page.</font>";
		echo "</div></body></html>";
        exit;
	}
}

if(!is_writable( "cache" ))
{
		echo "<font color='red'>The directory <code>" . getcwd() . "/cache/</code> exists, but is not writable.<br>You will need to make it writeable by your PHP process.<br>Then, reload this page.</font>";
		echo "</div></body></html>";
		exit;
}

?>

Cache directory exists and is writable.<hr>

<?php
	$result = fof_db_query("select * from $FOF_USER_TABLE where user_name = 'admin'");
	if(mysql_num_rows($result) == 0) {
?>

You now need to choose an initial password for the 'admin' account:<br>

<form>
<table>
<tr><td>Password:</td><td><input type=password name=password></td></tr>
<tr><td>Password again:</td><td><input type=password name=password2></td></tr>
</table>
<input type=submit value="Set Password">
</form>

<?php } else { ?>

'admin' account already exists.<br>
<br><b><center>OK!  Setup complete! <a href=".">Login as admin</a>, and start subscribing!</center></b>

<?php } } ?>

</div></body></html>
