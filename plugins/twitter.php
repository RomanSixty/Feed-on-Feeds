<?php 

fof_add_item_widget('fof_twitter');

function fof_twitter($item)
{
   $url = urlencode(html_entity_decode($item['item_link']));
   $title = urlencode($item['feed_title'] . ': ' . $item['item_title']);
   $posturl = 'http://twitter.com/home?status=' . $title . '%20' . $url;
   $linktag = '<a href="' . $posturl . '" onClick="window.open(\'' . $posturl . '\',\'post to twitter\',\'width=800,height=500,status=0,toolbar=0,location=0,menubar=0,resizeable=1\');return false;">';
   
   return $linktag . '<img src="https://twitter.com/favicons/favicon.ico" height=16 width=16 /></a> '
	. $linktag . 'tweet</a>';
}
?>

