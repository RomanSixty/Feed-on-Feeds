<?php
$fof_no_login = 1;
require("fof-main.php");

if (defined('FOF_AUTH_EXTERNAL') && ! empty($_SERVER['REMOTE_USER'])) {
    $unread = fof_db_tag_count(fof_db_get_user_id($_SERVER['REMOTE_USER']), 'unread');
} else if (isset($_COOKIE['user_name']) && isset($_COOKIE['user_password_hash'])) {
    if (fof_authenticate($_COOKIE['user_name'], $_COOKIE['user_password_hash'])) {
        $unread = fof_db_tag_count(fof_current_user(), 'unread');
    }
}

echo 'Feed on Feeds' . ($unread ? " ($unread)" : '');
?>

