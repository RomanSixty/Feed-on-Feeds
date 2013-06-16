<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * favicon.php - displays an image cached by SimplePie
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */
require_once('fof-asset.php');
require_once('autoloader.php');
require_once('simplepie/SimplePie.php');

if(file_exists($filename = "./cache/" . md5($_GET[i]) . ".spi"))
{
    $file = unserialize(file_get_contents($filename));
    header('Content-type:' . $file['headers']['content-type']);
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 604800) . ' GMT'); // 7 days
    echo $file['body'];
}
else
{
    header("Location: " . $fof_asset['feed_icon']);
}
?>
