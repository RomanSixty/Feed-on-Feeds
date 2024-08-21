<?php

/* For an SVG item with a viewbox but no width/height attributes, generate those attributes, to stop social icons and emojis from filling up the entire page */

fof_add_domitem_filter('fof_fix_svg_dimensions');

function fof_fix_svg_dimensions($dom, $item) {
	foreach ($dom->getElementsByTagName('svg') as $svg) {
        if ($svg->hasAttribute('viewbox')) {
		$viewBox = preg_split('/\s+/', $svg->getAttribute('viewbox'), -1, PREG_SPLIT_NO_EMPTY);
            if (!$svg->hasAttribute('width')) {
                $svg->setAttribute('width', (int)$viewBox[2] - (int)$viewBox[0]);
            }
            if (!$svg->hasAttribute('height')) {
                $svg->setAttribute('height', (int)$viewBox[3] - (int)$viewBox[1]);
            }
        }
    }

    return $dom;
}

?>

