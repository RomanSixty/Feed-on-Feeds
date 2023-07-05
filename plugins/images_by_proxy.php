<?php

/* pass images through our proxy, to address hotlinking and mixed-content security issues */

fof_add_domitem_filter('fof_images_by_proxy');

function wrap_image_in_proxy($url, $item) {
	return 'img.php?item=' . $item['item_id'] . '&url=' . urlencode($url);
}

function fof_images_by_proxy($dom, $item) {
	foreach ($dom->getElementsByTagName('img') as $img) {
		foreach (['src', 'data-fof-ondemand-src'] as $attr) {
			if ($img->hasAttribute($attr)) {
				$img->setAttribute($attr, wrap_image_in_proxy($img->getAttribute($attr), $item));
			}
		}
		foreach (['srcset', 'data-fof-ondemand-srcset'] as $attr) {
			if ($img->hasAttribute($attr)) {
				$srcset = $img->getAttribute($attr);
				$specs = preg_split('/,\s+/', $srcset);

				$outspec = [];
				foreach ($specs as $spec) {
					list($url, $selector) = preg_split('/\s+/', $spec, 2);
					$outspec[] = wrap_image_in_proxy($url, $item) . ' ' . $selector;
				}
				$img->setAttribute($attr, implode(', ', $outspec));
			}
		}
	}

	return $dom;
}
?>
