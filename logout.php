<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * logout.php - kills user cookie, redirects to login page
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

ob_start();

$fof_no_login = true;

include_once("fof-main.php");
header("Content-Type: text/html; charset=utf-8");

fof_logout();

header("Location: login.php");

?>
