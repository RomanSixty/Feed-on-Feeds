<?php

/* rewrite links in content to open in new windows */

fof_add_domitem_filter('fof_item_targets');

function fof_item_targets($dom, $item) {
	foreach ($dom->getElementsByTagName('a') as $a) {
		$a->setAttribute('target', '_blank');
	}

	return $dom;
}
?>