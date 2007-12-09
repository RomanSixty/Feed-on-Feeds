<?php 

fof_add_item_filter('fof_plain');
fof_add_pref('Strip (most) markup from items', 'plugin_plain_enable', 'boolean');

function fof_plain($text)
{
    $prefs = fof_prefs();
    $enable = $prefs['plugin_plain_enable'];
    
    if(!$enable) return $text;

    return strip_tags($text, "<a><b><i><blockquote>");
}
?>
