<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * uninstall.php - if confirmed, drops FoF's tables
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once("fof-main.php");

fof_set_content_type();

?>
<!DOCTYPE html>
<html>

    <head>
        <title>feed on feeds - uninstallation</title>
        <link rel="stylesheet" href="fof.css" media="screen" />
        <script src="fof.js" type="text/javascript"></script>
        <meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
    </head>

    <body id="panel-page">


<?php
if ( ! empty($_GET['really']))
{
    $queries = array(
        "DROP TABLE $FOF_FEED_TABLE",
        "DROP TABLE $FOF_ITEM_TABLE",
        "DROP TABLE $FOF_TAG_TABLE",
        "DROP TABLE $FOF_ITEM_TAG_TABLE",
        "DROP TABLE $FOF_SUBSCRIPTION_TABLE",
        "DROP TABLE $FOF_USER_TABLE"
    );
    try {
        foreach($queries as $query) {
            $fof_connection->exec($query);
        }
    } catch (PDOException $e) {
        die('Cannot drop table: <pre>' . $e->GetMessage() . '</pre>');
    }

echo 'Done.  Now just delete this entire directory and we\'ll forget this ever happened.';
}
else
{
?>
<script>
if(confirm('This is your last chance.  Do you really want to uninstall Feed on Feeds?'))
{
	document.location = './uninstall.php?really=really';
}
</script>
<a href="."><b>phew!</b></a>
</body></html>
<?php } ?>
