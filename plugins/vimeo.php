<?php

fof_add_item_prefilter('fof_vimeo');

/**
 * if an item is a vimeo video, embed its thumbnail and on click replace it with the embed iframe
 */
function fof_vimeo($item, $link, $title, $content)
{
	if ( strstr ( $link, '//vimeo.com/' ) !== false )
	{
		$matches1 = array();
		$matches2 = array();
		$embed    = '';

		$enclosure = $item -> get_enclosure ( 0 );

		$thumb_source = $enclosure -> get_thumbnail ( 0 );

		if ( preg_match ( '~/([0-9]+)_960\.jpg?~', $thumb_source, $matches1 ) && preg_match ( '~/([0-9]+)~', $link, $matches2 ) )
			$embed = '<div class="vimeo-video"
				data-vmid="' . $matches2 [ 1 ] . '"
				style="background-image: url(\'//i.vimeocdn.com/video/' . $matches1 [ 1 ] . '.jpg?mw=560\')"
				onclick="embed_vimeo(this);"></div>';

		$content = $embed . $content;
	}

	return array ( $link, $title, $content );
}