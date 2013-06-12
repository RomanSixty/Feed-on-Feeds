<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * config.php - modify this file with your database settings
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */


// Database connection information.  Host, username, password, database name.

define('FOF_DB_HOST', "host.example.com");
define('FOF_DB_USER', "username");
define('FOF_DB_PASS', "password");
define('FOF_DB_DBNAME', "database");


// The rest you should not need to change

// DB table names

define('FOF_DB_PREFIX', "fof_");

define('FOF_FEED_TABLE', FOF_DB_PREFIX . "feed");
define('FOF_ITEM_TABLE', FOF_DB_PREFIX . "item");
define('FOF_ITEM_TAG_TABLE', FOF_DB_PREFIX . "item_tag");
define('FOF_SUBSCRIPTION_TABLE', FOF_DB_PREFIX . "subscription");
define('FOF_TAG_TABLE', FOF_DB_PREFIX . "tag");
define('FOF_USER_TABLE', FOF_DB_PREFIX . "user");

define('FOF_SMALL_FEED_AMOUNT',5);

// Find ourselves and the cache dir

if (!defined('DIR_SEP')) {
	define('DIR_SEP', DIRECTORY_SEPARATOR);
}

if (!defined('FOF_DIR')) {
    define('FOF_DIR', dirname(__FILE__) . DIR_SEP);
}

?>
