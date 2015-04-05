<?php

/* load images on-demand */

fof_add_render_filter('fof_images_on_demand');

function fof_images_on_demand($content) {
	/* quiet warnings */
	$old_xml_err = libxml_use_internal_errors(true);
	$dom = new DOMDocument();

	// hack borrowed from http://beerpla.net/projects/smartdomdocument-a-smarter-php-domdocument-class/
	$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', "UTF-8"));

	foreach ($dom->getElementsByTagName('img') as $img) {
		if ($img->hasAttribute('src')) {
			$img->setAttribute('data-fof-ondemand-src', $img->getAttribute('src'));
			$img->removeAttribute('src');
		}
		if ($img->hasAttribute('srcset')) {
			$img->setAttribute('data-fof-ondemand-srcset', $img->getAttribute('srcset'));
			$img->removeAttribute('srcset');
		}
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
?>
