<?php

fof_add_item_filter('strip_comments');

function strip_comments($text) {
	$text = preg_replace('/<!--.*-->/U', '', $text);

	return $text;
}

?>
