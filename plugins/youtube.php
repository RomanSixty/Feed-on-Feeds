<?php

fof_add_item_prefilter('fof_youtube');

/**
 * if an item is a youtube video, embed it via iframe
 */
function fof_youtube($item, $link, $title, $content)
{
	if ( strstr ( $link, 'youtube.com/watch' ) !== false )
	{
		$matches = array();
		$embed   = '';

		if ( preg_match ( '~watch\?v=(.*)$~i', $link, $matches ) )
			$embed = '<iframe width="560" height="315" src="https://www.youtube-nocookie.com/embed/' . $matches [ 1 ] . '?rel=0" frameborder="0" allowfullscreen></iframe>';
		$content .= $embed;
	}

	return array ( $link, $title, $content );
}
?>
