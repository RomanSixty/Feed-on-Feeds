<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * websub.php - WebSub endpoint
 *
 * Copyright (C) 2018 j. shagam
 * fluffy@beesbuzz.biz - http://beesbuzz.biz/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

$fof_no_login = true;

require_once 'fof-main.php';

list($pre, $feed_id, $secret) = explode('/', $_SERVER['PATH_INFO']);

if (!$feed_id) {
    // No feed ID was specified
    http_response_code(400);
    die("Malformed request");
}

$feed = fof_db_get_feed_by_id($feed_id);
if (!$feed || !$feed['feed_websub_hub']) {
    // The feed doesn't exist, or doesn't have a known hub
    http_response_code(404);
    fof_log("Got push to unknown feed: id=$feed_id", 'warning');
    die("No such feed $feed_id");
}

if (!$feed['feed_websub_hub'] || $secret != $feed['feed_websub_secret']) {
    // A bad actor was trying to push an update
    http_response_code(403);
    fof_log("Hub attempted bad push: id=$feed_id secret=$secret");
    die("Bad push: id=$feed_id secret=$secret");
}

if ($_GET['hub_mode'] == 'subscribe') {
    // We are responding to a subscription verification
    $topic = $_GET['hub_topic'];
    $challenge = $_GET['hub_challenge'];
    $lease_time = $_GET['hub_lease_seconds'];
    fof_log("Got subscription verification request: id=$feed_id topic=$topic lease_time=$lease_time");

    // Set the lease to renew when they're down to 10% of their lifetime
    fof_db_feed_update_websub($feed_id, $feed['feed_websub_hub'], $secret, now() + $lease_time*9/10);

    // Respond with the challenge and exit
    echo $challenge;
    exit();
}

// We're responding to a push response.
fof_update_feed($feed_id, file_get_contents('php://input'));
?>

Updated feed <?=$feed_id?>.
