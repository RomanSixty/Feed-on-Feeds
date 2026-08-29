<?php

/* Remove annoying WordPress emoji fixes in favor of the actual emoji */

fof_add_domitem_filter('fof_demoji');

function fof_demoji($dom, $item) {
    $remove = [];
    foreach ($dom->getElementsByTagName('img') as $img) {
        if ($img->hasAttribute('alt') && ($img->getAttribute('class') == 'wp-smiley')) {
            // Just calling replace() doesn't work, because it screws up the iterator, because PHP I guess
            $img->before($img->getAttribute('alt'));
            $remove[] = $img;
        }
    }
    foreach ($remove as $dead) {
        $dead->remove();
    }

    return $dom;
}
?>
