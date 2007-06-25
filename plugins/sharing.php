<?php 

fof_add_item_widget('fof_sharing');

function fof_sharing($item)
{
    $prefs = fof_prefs();
    $sharing = $prefs['sharing'];
    if($sharing != "tagged") return false;
    
    $tags = $item['tags'];
    $id = $item['item_id'];
    $shared = in_array("shared", $tags) ? true : false;
	$shared_image = $shared ? "plugins/share-on.gif" : "plugins/share-off.gif";
	$shared_link = $shared ? "javascript:remove_tag($id, 'shared')" : "javascript:add_tag($id, 'shared')";
	$shared_text = $shared ? "shared" : "not shared";
   
    return "<a href=\"$shared_link\"><img src=\"$shared_image\" width=\"12\" height=\"12\" /></a> <a href=\"$shared_link\">$shared_text</a> ";
}
?>
