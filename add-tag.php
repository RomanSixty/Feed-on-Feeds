<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * add-tag.php - adds (or removes) a tag to an item
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once 'fof-main.php';

if (empty($_POST['tag']) or empty($_POST['item'])) {
    header('Status: 400 Bad Request');
    echo 'Incomplete data.';
    exit();
}

$tags = $_POST['tag'];
$item = $_POST['item'];
$remove = (isset($_POST['remove']) ? $_POST['remove'] : null);

foreach (explode(' ', $tags) as $tag) {
	if ($remove == 'true') {
		fof_untag_item(fof_current_user(), $item, $tag);
	} else {
		fof_tag_item(fof_current_user(), $item, $tag);
	}
}
?>
