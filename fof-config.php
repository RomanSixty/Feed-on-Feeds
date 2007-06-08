<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * config.php - modify this file with your database settings
 *
 *
 * Copyright (C) 2004-2006 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */


// Database connection information.  Host, username, password, database name.

define('FOF_DB_HOST', "feedb.feedonfeeds.com");
define('FOF_DB_USER', "feedb");
define('FOF_DB_PASS', "password");
define('FOF_DB_DBNAME', "feedb");


// The rest you should not need to change


// How many posts to show by default in paged mode

define('FOF_HOWMANY', 50);


// DB table names

define('FOF_DB_PREFIX', "fof_test_");

define('FOF_FEED_TABLE', FOF_DB_PREFIX . "feed");
define('FOF_ITEM_TABLE', FOF_DB_PREFIX . "item");
define('FOF_ITEM_TAG_TABLE', FOF_DB_PREFIX . "item_tag");
define('FOF_SUBSCRIPTION_TABLE', FOF_DB_PREFIX . "subscription");
define('FOF_TAG_TABLE', FOF_DB_PREFIX . "tag");
define('FOF_USER_TABLE', FOF_DB_PREFIX . "user");


// Find ourselves and the cache dir

if (!defined('DIR_SEP')) {
	define('DIR_SEP', DIRECTORY_SEPARATOR);
}

if (!defined('FOF_DIR')) {
    define('FOF_DIR', dirname(__FILE__) . DIR_SEP);
}

?>
