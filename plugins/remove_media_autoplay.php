<?php

/* Make media playback more sensible */

fof_add_domitem_filter('remove_media_autoplay');

function remove_media_autoplay($dom, $item) {
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

	return $dom;
}
?>