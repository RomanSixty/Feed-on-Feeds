<?php

fof_add_item_prefilter('fof_youtube');

/**
 * if an item is a youtube video, embed its thumbnail and on click replace it with the embed iframe
 */
function fof_youtube($item, $link, $title, $content)
{
	static $js_embedded = false;

	if ( strstr ( $link, 'youtube.com/watch' ) !== false )
	{
		$matches = array();
		$embed   = '';

		if ( preg_match ( '~watch\?v=(.*)$~i', $link, $matches ) )
			$embed = '<div class="youtube-video"
				data-ytid="' . $matches [ 1 ] . '"
				style="background-image: url(\'//i.ytimg.com/vi/' . $matches [ 1 ] . '/hqdefault.jpg\')"
				onclick="embed_youtube(this);"></div>';

		if ( !$js_embedded )
		{
			$content .= <<<JS
<script>
function embed_youtube ( element ) {
	var iframe = document.createElement("iframe");

	iframe.setAttribute("src", "https://www.youtube.com/embed/" + element.dataset.ytid + "?autoplay=1&rel=0");
	iframe.setAttribute("frameborder", "0");
	iframe.setAttribute("width", 560);
	iframe.setAttribute("height", 315);
	iframe.setAttribute("allowfullscreen", "1");

	element.parentNode.replaceChild(iframe, element);
}
</script>
JS;

			$js_embedded = true;
		}

		$content .= $embed;
	}

	return array ( $link, $title, $content );
}