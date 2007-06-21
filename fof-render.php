<?php

// From Brian Suda @ http://suda.co.uk/projects/SEHL/

function do_highlight($full_body, $q, $class){
	/* seperate tags and data from the HTML file INCLUDING comments, avoiding HTML in the comments */
	$pat = '/((<[^!][\/]*?[^<>]*?>)([^<]*))|((<!--[ \r\n\t]*)(.*)[ \r\n\t]*-->([^<]*))/si';
	preg_match_all($pat,$full_body,$tag_matches);

	/* loop through and highlight $q value in data and recombine with tags */
	for ($i=0; $i< count($tag_matches[0]); $i++) {
		/* ignore all text within these tags */
		if (
			(preg_match('/<!/i', $tag_matches[0][$i])) or 
			(preg_match('/<textarea/i', $tag_matches[2][$i])) or 
			(preg_match('/<script/i', $tag_matches[2][$i]))
		){
			/* array[0] is everything the REGEX found */
			$full_body_hl .= $tag_matches[0][$i];
		} else {
			$full_body_hl .= $tag_matches[2][$i];

			/* the slash-i is for case-insensitive and the slash-b's are for word boundries */

			/* this one ALMOST works, except if the string is at the start or end of a string*/
			$holder = preg_replace('/(.*?)(\W)('.preg_quote($q).')(\W)(.*?)/iu',"\$1\$2<span class=\"$class\">\$3</span>\$4\$5",' '.$tag_matches[3][$i].' ');
			$full_body_hl .= substr($holder,1,(strlen($holder)-2));
		}
	}
	/* return tagged text */
	return $full_body_hl;
}


function fof_render_item($item)
{
    $items = true;

	$feed_link = $item['feed_link'];
	$feed_title = $item['feed_title'];
	$feed_image = $item['feed_image'];
	$feed_description = $item['feed_description'];

	$item_link = $item['item_link'];
	$item_id = $item['item_id'];
	$item_title = $item['item_title'];
	$item_content = $item['item_content'];
	$item_read = $item['item_read'];

	$prefs = fof_prefs();
	$offset = $prefs['tzoffset'];

	$item_published = gmdate("Y-n-d g:ia", $item['item_published'] + $offset*60*60);
	$item_cached = gmdate("Y-n-d g:ia", $item['item_cached'] + $offset*60*60);
	$item_updated = gmdate("Y-n-d g:ia", $item['item_updated'] + $offset*60*60);

	if(!$item_title) $item_title = "[no title]";
	
	if($_GET['search'])
	{
		$item_content = do_highlight("<span>$item_content</span>", $_GET['search'], "highlight");
		$item_title = do_highlight("<span>$item_title</span>", $_GET['search'], "highlight");
	}
	    
    $tags = $item['tags'];

	$star = in_array("star", $tags) ? true : false;
	$star_image = $star ? "image/star-on.gif" : "image/star-off.gif";
		
	$unread = in_array("unread", $tags) ? true : false;
?>

<div class="header">

	<span class="controls">
		<a class='uparrow' href='javascript:hide_body("<?php echo $item_id ?>")'>&uarr;</a>
		<a class='downarrow' href='javascript:show_body("<?php echo $item_id ?>")'>&darr;</a>
		<input
			type="checkbox"
			name="c<?php echo $item_id ?>"
			value="checked"
			ondblclick='flag_upto("c<?php echo $item_id?>");'
            onclick='return checkbox(event);'
		/>
	</span>
	
	<h1 <?php if($unread) echo "class='unread-item'" ?> >
		<img
			height="16"
			width="16"
			src="<?php echo $star_image ?>"
			id="fav<?php echo $item_id ?>"
			onclick="return toggle_favorite('<?php echo $item_id ?>')"
		/>
		<script>
			document.getElementById('fav<?php echo $item_id ?>').star = <?php if($star) echo 'true'; else echo 'false'; ?>;
		</script>
		<a href="<?php echo $item_link ?>">
			<?php echo $item_title ?>
		</a>
	</h1>
	
	<span class="tags">

<?php
	if($tags)
	{
		foreach($tags as $tag)
		{
			if($tag == "unread" || $tag == "star") continue;
?>
		<a href='?what=<?php echo $tag ?>'><?php echo $tag ?></a>
		
		<a href='<?php echo $tag ?>' onclick='return remove_tag("<?php echo $item_id ?>", "<?php echo $tag ?>");'>[x]</a>
<?php
		}
    }
?>

		<a
			href=""
			onclick="document.getElementById('addtag<?php echo $item_id ?>').style.display = '';
					 this.style.display = 'none';
					 return false;">
			add tag
		</a>

		<div id="addtag<?php echo $item_id ?>" style="display: none !important">
			<input
				onfocus="this.value=''"
				onkeypress="if(event.keyCode == 13) add_tag('<?php echo $item_id ?>', document.getElementById('tag<?php echo $item_id ?>').value);"
				type="text"
				id="tag<?php echo $item_id ?>"
				size="12"
				value="enter tag here"
			>
			<input
				type="button"
				name="add tag"
				value="tag"
				onclick="add_tag('<?php echo $item_id ?>', document.getElementById('tag<?php echo $item_id ?>').value);"
			>
		</div>

    </span>
    
    <span class='dash'> - </span>
    
    <h2>

    <?php $prefs = fof_prefs(); if($feed_image && $prefs['favicons']) { ?>
    <a href="<?php echo $feed_link ?>" title='<?php echo $feed_description ?>'><img src="<?php echo $feed_image ?>" height="16" width="16" border="0" /></a>
    <?php } ?>
    <a href="<?php echo $feed_link ?>" title='<?php echo $feed_description ?>'><?php echo $feed_title ?></a>
    </h2>

	<span class="meta">on <?php echo $item_published ?></span>

</div>


<div class="body"><?php echo $item_content ?></div>

<?php
    $widgets = fof_get_widgets($item);
    
    if($widgets) {
?>

<div class="widgets">

<?php
    foreach($widgets as $widget)
    {
        echo "<span class='widget'>$widget</span> ";
    }
?>

</div>

<?php } ?>

<div class="clearer"></div>

<?php
}
?>
