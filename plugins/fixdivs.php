<?php 

fof_add_item_filter('fof_fixdivs');

function fof_fixdivs($text)
{
   $text = str_ireplace('<div"', '<div "', $text);
   return $text;
}
?>
