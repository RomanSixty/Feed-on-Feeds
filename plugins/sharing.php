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
	$shared_link = $shared ? "return remove_tag($id, 'shared');" : "return add_tag($id, 'shared');";
	$shared_text = $shared ? "unshare" : "share";
   
    return "<a href=\"\" onClick=\"$shared_link\"><img src=\"$shared_image\" width=\"12\" height=\"12\" border=\"0\"/></a> <a href=\"\" onClick=\"$shared_link\">$shared_text</a> ";
}
?>
