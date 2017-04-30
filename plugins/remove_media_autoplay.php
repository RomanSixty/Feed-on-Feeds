<?php

/* Make media playback more sensible */

fof_add_item_filter('remove_media_autoplay');

function remove_media_autoplay($content) {
	$dom = fof_content_to_dom($content);

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

	return fof_dom_to_content($dom);
}