<?php 

fof_add_item_filter('fof_fixdivs');

function fof_fixdivs($text)
{
   $text = str_replace('<div"', '<div "', $text);
   return $text;
}
?>
