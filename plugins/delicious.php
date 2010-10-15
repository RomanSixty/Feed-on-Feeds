<?php 

fof_add_item_widget('fof_delicious');

function fof_delicious($item)
{
   $url = urlencode(html_entity_decode($item['item_link']));
   $title = urlencode($item['item_title']);
   
   return "<a href='http://del.icio.us/post?v=4;url=$url;title=$title'><img src='plugins/delicious.png' height=12 width=12 border=0 /></a> <a href='http://del.icio.us/post?v=4;url=$url;title=$title'>bookmark</a>";
}
?>

