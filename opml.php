<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * ompl.php - exports subscription list as OPML
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

header("Content-Type: text/xml; charset=utf-8");
include_once("fof-main.php");

echo '<?xml version="1.0"?>';
?>

<opml>
  <body>
<?php
$result = fof_db_get_subscriptions(fof_current_user());

while($row = fof_db_get_row($result))
{
	$url = $row['feed_url'];
	$title = $row['feed_title'];
	$link = $row['feed_link'];
	$description = $row['feed_description'];

	echo <<<HEYO
    <outline description="$description"
             htmlurl="$link"
             title="$title"
             xmlUrl="$url"
    />

HEYO;
}
?>
  </body>
</opml>
