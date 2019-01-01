<?php

/* try and replace mini thumbnails with linked images */

fof_add_domitem_filter('reddit');

function reddit($dom, $item) {
    if (strncmp ('https://www.reddit.com', $item['item_link'], 22) == 0) {
        $url = '';

        foreach ($dom->getElementsByTagName('a') as $link) {
            if ($link->nodeValue != '[link]')
                continue;
            $url = $link->getAttribute('href');
        }

        $matches = array();

        // some manipulations on that because of different content shared on reddit
        if (preg_match ('~gfycat\.com/(.+)$~', $url, $matches)) {
            if ( preg_match ('~[A-Z]~', $matches[1]))
                $url = 'https://thumbs.gfycat.com/'.$matches[1].'-size_restricted.gif';
        }
        elseif (preg_match ('~^.*imgur\.com/[^/.]+$~', $url)) {
            $url .= '.jpg';
        }
        elseif (preg_match ('~^(.*imgur\.com/[^/]+)\.gifv$~', $url, $matches)) {
            $url = $matches[1].'.gif';
        }

        if (preg_match('~\.(jpe?g|gif|png)$~i', $url)) {
            $nodes = $dom->getElementsByTagName('img');
            // replace currently inserted image with larger version
            if ($nodes->length) {
                foreach ($dom->getElementsByTagName('img') as $img) {
                    if ($img->hasAttribute('data-fof-ondemand-src'))
                        $img->setAttribute('data-fof-ondemand-src', $url);
                    else
                        $img->setAttribute('src', $url);
                }
            }
            // create image if none was present before
            else {
                $img = $dom->createElement('img');
                $img->setAttribute('src', $url);
                $dom->appendChild($img);
            }
        }
    }

    return $dom;
}