<?php

/* load images on-demand */

fof_add_render_filter('fof_images_by_proxy');

function wrap_image_in_proxy($url, $item) {
	 return 'img.php?item=' . $item['item_id'] . '&url=' . urlencode($url);
}

function fof_images_by_proxy($content, $item) {
	/* quiet warnings */
	$old_xml_err = libxml_use_internal_errors(true);
	$dom = new DOMDocument();

	// hack borrowed from http://beerpla.net/projects/smartdomdocument-a-smarter-php-domdocument-class/
	$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', "UTF-8"));

	foreach ($dom->getElementsByTagName('img') as $img) {
		if ($img->hasAttribute('src')) {
			$img->setAttribute('src', wrap_image_in_proxy($img->getAttribute('src'), $item));
		}
		if ($img->hasAttribute('srcset')) {
		   	$srcset = $img->getAttribute('srcset');
			$specs = preg_split('/,\s*/', $srcset);
			
			$outspec = [];
			foreach ($specs as $spec) {
				list($url, $selector) = preg_split('/\s+/', $spec, 2);
				$outspec[] = wrap_image_in_proxy($url, $item) . ' ' . $selector;
			}
			$img->setAttribute('srcset', implode(', ', $outspec));
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
