<?php

/* Make media playback more sensible */

fof_add_item_filter('remove_media_autoplay');

function remove_media_autoplay($content) {
	if (!$content) {
		return $content;
	}

	$old_xml_err = libxml_use_internal_errors(true);
	$dom = new DOMDocument();
	$dom->loadHtml(mb_convert_encoding($content, 'HTML-ENTITIES', "UTF-8"));

	// IFRAME is usually only used for horrible autoplaying videos and for ads.
	foreach ($dom->getElementsByTagName('iframe') as $link) {
		$link->parentNode->removeChild($link);
	}

	// Make sure video doesn't autoplay and does have contorls
	foreach ($dom->getElementsByTagName('video') as $video) {
		$video->removeAttribute("autoplay");
		$video->setAttribute("controls", true);
	}

	// Make sure audio doesn't autoplay and does have contorls
	foreach ($dom->getElementsByTagName('audio') as $audio) {
		$audio->removeAttribute("autoplay");
		$audio->setAttribute("controls", true);
	}

	return preg_replace('~<(?:!DOCTYPE|/?(?:html|body))[^>]*>\s*~i', '', $dom->saveHTML());
}