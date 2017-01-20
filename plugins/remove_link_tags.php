<?php

/* Remove spurious <link> tags, which have no business belonging in a syndicated item */

fof_add_item_filter('remove_link_tags');

function remove_link_tags($content) {
	$old_xml_err = libxml_use_internal_errors(true);
	$dom = new DOMDocument();
	$dom->loadHtml(mb_convert_encoding($content, 'HTML-ENTITIES', "UTF-8"));

	foreach ($dom->getElementsByTagName('link') as $link) {
		$link->parentNode->removeChild($link);
	}

	$content_out = '';
	$node = $dom->firstChild;
	while ($node) {
		$content_out .= $dom->saveHTML($node);
		/* repeat for all nodes at this level */
		$node = $node->nextSibling;
	}

	foreach (libxml_get_errors() as $error) {
		/* just ignore warnings */
		if ($error->level === LIBXML_ERR_WARNING) {
			continue;
		}

		fof_log(__FUNCTION__ . ': ' . $error->message);
	}
	libxml_clear_errors();
	libxml_use_internal_errors($old_xml_err);

	return $content_out;
}