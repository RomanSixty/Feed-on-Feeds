<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * header.php - common header for all pages
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once "fof-main.php";

fof_set_content_type();

$width = isset($_COOKIE['fof_sidebar_width']) ? $_COOKIE['fof_sidebar_width'] : 250;

$unread_count = fof_db_tag_count(fof_current_user(), 'unread');

?>
<!DOCTYPE html>
<html>
  <head>
    <title>Feed on Feeds<?php if ($unread_count) {
	echo " ($unread_count)";
}
?></title>
    <link rel="stylesheet" href="fof.css" media="screen" />
<?php
if (is_readable('./fof-custom.css')) {
	?>
    <link rel="stylesheet" href="fof-custom.css" media="screen" />
<?php
}
?>
    <link rel="microsummary" href="microsummary.php" />
    <script src="prototype/prototype.js" type="text/javascript"></script>
    <script src="fof.js" type="text/javascript"></script>
    <script>
        document.onmousemove = dragResize;
        document.onmouseup = completeDrag;
<?php if ($fof_prefs_obj->get('keyboard')) {?>
        document.onkeypress = keyboard;
<?php }?>
        isIE = false;
    </script>

    <style>
        #sidebar {
            width: <?php echo $width ?>px;
        }
        #handle {
            left:<?php echo $width ?>px;
        }
        #items {
            margin-left: <?php echo $width + 10 ?>px;
        }
        #item-display-controls { left: <?php echo $width + 10 ?>px; }
        #welcome { width: <?php echo $width - 30 ?>px; }
    </style>

<!--[if IE]>
    <style>
        #sidebar table {
            width: <?php echo $width - 20 ?>px;
        }
    </style>
    <script>isIE = true;</script>
<![endif]-->
</head>

<body class="highlight-on"> <!--onkeypress="keyboard(event)"-->
<div id="sidebar">
<?php
include 'sidebar.php';
?>
</div>

<div id="handle" onmousedown="startResize(event)"></div>

<div id="items">
