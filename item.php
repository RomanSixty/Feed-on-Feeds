<?php

include_once("fof-main.php");
include_once("fof-render.php");

header("Content-Type: text/html; charset=utf-8");

$row = fof_get_item(fof_current_user(), $_GET['id']);

fof_render_item($row);

?>