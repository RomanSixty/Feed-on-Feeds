<?php

/* rewrite links in content to open in new windows */

fof_add_render_filter('fof_item_targets');

function fof_item_targets($content) {
	$dom = fof_content_to_dom($content);

	/* replace or add link targets */
	$xpath = new DOMXpath($dom);
	foreach ($xpath->query('//a') as $node) {
		$node->setAttribute('target', '_blank');
	}

	return fof_dom_to_content($dom);
}