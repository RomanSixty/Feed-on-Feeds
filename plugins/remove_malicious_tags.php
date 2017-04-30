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

	// These attributes are never good news
	$xpath = new DOMXpath($dom);
	foreach (Array("style", "class") as $attr) {
		foreach ($xpath->query("//*[$attr]") as $elem) {
			$elem->removeAttribute($attr);
		}
	}

	return fof_dom_to_content($dom);
}