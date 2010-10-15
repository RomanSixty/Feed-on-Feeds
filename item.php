<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * item.php - renders a single item (useful for Ajax)
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once("fof-main.php");
include_once("fof-render.php");

fof_set_content_type();

$row = fof_get_item(fof_current_user(), $_GET['id']);

fof_render_item($row);

?>