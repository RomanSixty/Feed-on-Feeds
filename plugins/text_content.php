<?php

fof_add_item_prefilter('fof_text_content');

/**
 * if an item has <summary type="text"> (or unspecified type) wrap it in a "text-post" div
 */
function fof_text_content($item, $link, $title, $content) {
	$content_tag = null;

	foreach (Array(SIMPLEPIE_NAMESPACE_ATOM_10, SIMPLEPIE_NAMESPACE_ATOM_03) as $ns) {
		if ($tags = $item->get_item_tags($ns, "content")) {
			foreach ($tags as $tag) {
				fof_log("found $ns:content");
				$content_tag = $tag;
			}
		} else if ($tags = $item->get_item_tags($ns, "summary")) {
			foreach ($tags as $tag) {
				fof_log("found $ns:summary");
				$content_tag = $tag;
			}
		}
	}

	if ($content_tag) {
		// We are an atom item with a best-match content element
		if (!isset($tag['attribs']['']['type'])
			|| $tag['attribs']['']['type'] == 'text') {
			// type is either unset, or explicitly set to text
			$content = '<div class="text-post">' . $content . '</div>';
		}
	}

	return array($link, $title, $content);
}