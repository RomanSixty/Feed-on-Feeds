<?php 

fof_add_item_prefilter('fof_enclosures');

function fof_enclosures($item, $link, $title, $content)
{
    if ($enclosure = $item->get_enclosure(0))
    {
        $html = '<br><br><a href="#" onclick="show_enclosure(event); return false;">show enclosure</a><div style="display: none" align="center" width="auto">';
        $html .= '<p>' . $enclosure->embed(array(
            'audio' => 'plugins/place_audio.png',
            'video' => 'plugins/place_video.png',
            'mediaplayer' => 'plugins/mediaplayer.swf',
            'alt' => '<img src="plugins/mini_podcast.png" class="download" border="0" title="Download the Podcast (' . $enclosure->get_extension() . '; ' . $enclosure->get_size() . ' MB)" />',
            'altclass' => 'download'
            )) . '</p>';
        $html .= '<i align="center">(' . $enclosure->get_type();
            if ($enclosure->get_size())
            {
                $html .= '; ' . $enclosure->get_size() . ' MB';                             
                
            }
            $html .= ')</i>';
        $html .= '</div>';
    }

   return array($link, $title, $content . $html);
}
?>
