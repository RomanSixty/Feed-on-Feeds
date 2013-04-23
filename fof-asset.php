<?php
/*
 * This file is part of Feed On Feeds
 *
 * fof-asset.php - initializes an array of assets
 *
 * Copyright (C) 2013 Justin Wind <justin.wind@gmail.com>
 *
 * Distributed under the GPL - see LICENSE
 *
 */

global $fof_asset;
$fof_asset = array(
    'tag_icon' => 'image/tag-icon.svg',
    'feed_icon' => 'image/feed-icon.png',
    'throbber_image' => 'image/throbber.gif',
    'star_on_image' => 'image/star-on.gif',
    'star_off_image' => 'image/star-off.gif',
    'star_pend_image' => 'image/star-pending.gif',
    'busy_icon' => 'image/spinner.gif',
    'alert_icon' => 'image/warn.gif',
);

@include_once('fof-asset-custom.php');
