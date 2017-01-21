<?php

fof_add_item_prefilter('fof_youtube');

/**
 * if an item is a youtube video, embed its thumbnail and on click replace it with the embed iframe
 */
function fof_youtube($item, $link, $title, $content)
{
	if ( strstr ( $link, 'youtube.com/watch' ) !== false )
	{
		$matches = array();
		$embed   = '';

		if ( preg_match ( '~watch\?v=(.*)$~i', $link, $matches ) )
			$embed = '<div class="youtube-video"
				data-ytid="' . $matches [ 1 ] . '"
				style="background-image: url(\'//i.ytimg.com/vi/' . $matches [ 1 ] . '/hqdefault.jpg\')"
				onclick="embed_youtube(this);"></div>';

		$content .= $embed;
	}

	return array ( $link, $title, $content );
}