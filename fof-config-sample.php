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


/* FOF_DATA_PATH
    Controls where various data will be written.
    Currently this includes log files and the SQLite database (if being used)
*/
define('FOF_DATA_PATH', '/var/fof-data/');

// Database connection information.  Host, username, password, database name.

define('FOF_DB_HOST', "host.example.com");
define('FOF_DB_USER', "username");
define('FOF_DB_PASS', "password");
define('FOF_DB_DBNAME', "database");

// choose a backend
// define('USE_MYSQL', 1);
// define('USE_SQLITE', 1);

// define('FOF_AUTH_EXTERNAL',1); // trust server auth to manage users
// define('FOF_AUTH_EXTERNAL_ONLY', 1); // never fall back to internal user/passwords
// define('FOF_AUTH_EXTERNAL_ADD', 1); // create user->id mappings automatically

// maximum number of seconds background update script can run, 0 for indefinitely
define('FOF_UPDATE_TIME_LIMIT', 60 * 10);

// The rest you should not need to change

// DB table names
//define('FOF_DB_PREFIX', "fof_");

// Find ourselves and the cache dir

if (!defined('DIR_SEP')) {
	define('DIR_SEP', DIRECTORY_SEPARATOR);
}

if (!defined('FOF_DIR')) {
    define('FOF_DIR', dirname(__FILE__) . DIR_SEP);
}

?>
