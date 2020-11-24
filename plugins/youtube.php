<?php

fof_add_item_prefilter('fof_youtube');

/**
 * if an item is a youtube video, embed its thumbnail and on click replace it with the embed iframe
 */
function fof_youtube($item, $link, $title, $content) {
    $matches = array();
    $embed = '';

    if (strstr($link, 'youtube.com/watch') !== false) {
        if (preg_match('~watch\?v=(.*)$~i', $link, $matches)) {
            $embed = '<div class="youtube-video"
                data-ytid="' . $matches[1] . '"
                style="background-image: url(\'//i.ytimg.com/vi/' . $matches[1] . '/hqdefault.jpg\')"
                onclick="embed_youtube(this);"></div>';
        }

        $content .= $embed;
    }

    if (strstr($content, 'youtube.com/embed/videoseries') !== false) {
        // YouTube playlists
        $content = preg_replace(
            '~<iframe[^>]+src="https?://(?:www\.)?youtube\.com/embed/videoseries\?list=([^&"]+)[^>]*>.*</iframe>~iu',
            '<div class="youtube-video"
                data-ytplaylist="$1"
                onclick="embed_youtube(this);"></div>',
            $content);
    }
    else {
        // replace default YouTube embeds...
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