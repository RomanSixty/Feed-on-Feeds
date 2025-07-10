<?php

fof_add_item_prefilter('fof_youtube');

/**
 * if an item is a youtube video, embed its thumbnail and on click replace it with the embed iframe
 */
function fof_youtube($item, $link, $title, $content) {
    $matches = array();
    $embed = '';

    $permalink = $item->get_permalink();

    if (strstr($permalink, 'youtube.com/shorts/') !== false && strstr($title, '#shorts') === false) {
        $title .= ' #shorts';
    }

    if (strstr($permalink, 'youtube.com/v') !== false ||
        strstr($permalink, 'youtube.com/watch') !== false ||
        strstr($permalink, 'youtube.com/shorts') !== false) {

        if (preg_match('~(watch\?v=|shorts/|v/)([^?]*)~i', $permalink, $matches)) {
            $ytid = $matches[2];
            $embed = '<div class="youtube-video"
                data-ytid="' . $matches[1] . '"
                style="background-image: url(\'//i.ytimg.com/vi/'.$ytid.'/hqdefault.jpg\')"
                onclick="embed_youtube(this);"></div>';
        }

        // TODO: figure out why the content is blank
        $content = $embed . '<p>' . $item->get_content() . '</p>';
    }

    // replace default YouTube embeds...
    // but not if it's a playlist
    if (strstr($content, 'youtube.com/embed/videoseries') === false) {
        $content = preg_replace(
            '~<iframe[^>]+src="https?://(?:www\.)?youtube\.com/embed/([^?"]+)[^>]*>.*</iframe>~iu',
            '<div class="youtube-video"
                data-ytid="$1"
                style="background-image: url(\'//i.ytimg.com/vi/$1/hqdefault.jpg\')"
                onclick="embed_youtube(this);"></div>',
            $content);
    }

    return array($link, $title, $content);
}