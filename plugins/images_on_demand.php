<?php

/* load images on-demand */

fof_add_render_filter('fof_images_on_demand');

function fof_images_on_demand($content, $item) {
	// AJAX refresh: we don't apply this filter, because images are
	// probably already loaded
	if (isset($_REQUEST['no_img_filter'])) {
		return $content;
	}

	$dom = fof_content_to_dom($content);

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

	return fof_dom_to_content($dom);
}