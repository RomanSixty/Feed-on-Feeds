<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * set-prefs.php - interface for changing prefs from javascript
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

foreach($_POST as $k => $v)
{
    $prefs->set($k, $v);
}

$prefs->save();

?>
