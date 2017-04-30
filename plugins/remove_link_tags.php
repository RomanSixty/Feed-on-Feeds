<?php

/* Remove spurious <link> tags, which have no business belonging in a syndicated item */

fof_add_item_filter('remove_link_tags');

function remove_link_tags($content) {
	$dom = fof_content_to_dom($content);

	foreach ($dom->getElementsByTagName('link') as $link) {
		$link->parentNode->removeChild($link);
	}

	return fof_dom_to_content($dom);
}