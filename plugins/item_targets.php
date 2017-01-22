<?php

/* rewrite links in content to open in new windows */

fof_add_render_filter('fof_item_targets');

function fof_item_targets($content) {
	/* quiet warnings */
	$old_xml_err = libxml_use_internal_errors(true);
	$dom = new DOMDocument();

	/*
	Load content into DOM, within a div wrapper.  Wrapper div will be
	stripped before returning altered content.  Without doing this,
	any bare text content would get wrapped in p elements while being
	parsed in.
	 */
	$dom->loadHtml('<div>' . mb_convert_encoding($content, 'HTML-ENTITIES', "UTF-8") . '</div>');

	/* strip <!DOCTYPE> which DOMDocument adds */
	$dom->removeChild($dom->firstChild);
	/* strip <html><body> which DOMDocument adds */
	$dom->replaceChild($dom->firstChild->firstChild->firstChild, $dom->firstChild);

	/* replace or add link targets */
	$xpath = new DOMXpath($dom);
	foreach ($xpath->query('//a') as $node) {
		$node->setAttribute('target', '_blank');
	}

	$content_out = '';
	/* emit the updated contents inside our div */
	/* start at the first node inside first div.. */
	$node = $dom->firstChild->firstChild;
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