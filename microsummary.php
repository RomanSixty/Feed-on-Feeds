<?php
$fof_no_login = 1;
require("fof-main.php");

if(isset($_COOKIE["user_name"]) && isset($_COOKIE["user_password_hash"]))
{
  $user_name = $_COOKIE["user_name"];
  $user_password_hash = $_COOKIE["user_password_hash"];

  if(fof_authenticate($user_name, $user_password_hash))
  {
    $unread = fof_db_tag_count(fof_current_user(), 'unread');
  }
}

echo "Feed on Feeds";
if($unread) echo " ($unread)";
?>

