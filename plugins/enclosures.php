<?php

fof_add_item_prefilter('fof_enclosures');

/** Appends item enclosures to end of incoming item content.
*/
function fof_enclosures($item, $link, $title, $content) {
	$enc_html = array();
	foreach ($item->get_enclosures() as $enclosure) {
		$medium = $enclosure->get_medium();
		@list($mimetype, $encoding) = explode(';', $enclosure->get_type());
		@list($type, $subtype) = explode(',', $mimetype);

		$show_handle = 'show enclosure';
		if ( ! empty($medium))
			$show_handle .= " ($medium)";
		else if ( ! empty($mimetype))
			$show_handle .= " ($mimetype)";

		/* skip empty enclosures */
		$enclosure_link = $enclosure->get_link();
		if (empty($enclosure_link))
			continue;

		/* Inline images. */
		if ($medium === 'image'
		||  $type === 'image') {

			$description = $enclosure->get_description();
			$enclosure_title = htmlentities($enclosure->get_title(), ENT_QUOTES);
			$enc_html[] = '<div>' .
			                '<a href="#" onclick="show_enclosure(event); return false;" title="' . $enclosure_title . '">' .
			                  $show_handle .
			                '</a>' .
			                '<div class="enclosure" style="display:none;">' .
			                '<a href="' . $enclosure_link . '">' .
			                  '<img src="' . $enclosure_link . '" title="' . $enclosure_title . '" alt="" />' .
			                '</a>' .
			                (empty($description) ? '' : ('<p>' . $description . '</p>')) .
			              '</div>' .
			            '</div>' . "\n";
			continue;
		}

		/* Anything else, let SimplePie deal embed. */
		$embed_opts = array(
			'audio' => 'plugins/place_audio.png',
			'video' => 'plugins/place_video.png',
			'mediaplayer' => 'plugins/mediaplayer.swf',
			'alt' => $enclosure_link,
		);

		$enc_html[] = '<a href="#" onclick="show_enclosure(event); return false;" title="' . htmlentities($enclosure->get_title(), ENT_QUOTES) . '">' .
		                $show_handle .
		              '</a>' .
		              '<div class="enclosure" style="display:none;">' .
		                '<div>' .
		                  $enclosure->embed($embed_opts) .
		                '</div>' .
		              '</div>';
	}

	return array($link, $title, $content . implode($enc_html));
}