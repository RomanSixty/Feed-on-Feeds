<?php

/* pass images through our proxy, to address hotlinking and mixed-content security issues */

fof_add_domitem_filter('fof_images_by_proxy');

function wrap_image_in_proxy($url, $item) {
	return 'img.php?item=' . $item['item_id'] . '&url=' . urlencode($url);
}

function fof_images_by_proxy($dom, $item) {
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

	return $dom;
}
?>
