<?php

fof_add_item_filter('remove_malicious_tags');

function remove_malicious_tags($content) {
	$dom = fof_content_to_dom($content);

	// These tags are never good news
	foreach (Array("iframe", "link", "script") as $tag) {
		foreach ($dom->getElementsByTagName($tag) as $elem) {
			$elem->parentNode->removeChild($elem);
		}
	}

	// Strip out problematic attributes
	foreach ($dom->getElementsByTagName("*") as $elem) {
		foreach ($elem->attributes as $attr) {
			if (preg_match("/style|class|on.*/", $attr->nodeName)) {
				$elem->removeAttribute($attr->nodeName);
			}
		}
	}

	return fof_dom_to_content($dom);
}