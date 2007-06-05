<?php
require_once('fof-main.php');
require_once('simplepie/simplepie.inc');
SimplePie_Misc::display_cached_file($_GET['i'], './cache', 'spi');
?>
