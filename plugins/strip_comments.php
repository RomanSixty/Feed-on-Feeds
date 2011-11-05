<?php

fof_add_item_filter('strip_comments',0);

function strip_comments($text) {
	$text = preg_replace('/<!--.+-->/Us', '', $text);

	return $text;
}

?>
