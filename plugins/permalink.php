<?php

fof_add_item_widget('fof_permalink');

function fof_permalink($item) {
	$url = html_entity_decode($item['item_link']);

	return '<a href="' . $url . '" target="_blank">permalink</a>';
}
?>
