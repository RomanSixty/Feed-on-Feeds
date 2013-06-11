<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * fof-render.php - contains function used to render a single item
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

require_once('fof-asset.php');

// From Brian Suda @ http://suda.co.uk/projects/SEHL/

function do_highlight($full_body, $q, $class){
    $full_body_hl = "";

	/* seperate tags and data from the HTML file INCLUDING comments, avoiding HTML in the comments */
	$pat = '/((<[^!][\/]*?[^<>]*?>)([^<]*))|<!---->|<!--(.*?)-->|((<!--[ \r\n\t]*?)(.*?)[ \r\n\t]*?-->([^<]*))/si';
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

			/* this one ALMOST works, except if the string is at the start or end of a string*/
			$holder = preg_replace('/(.*?)('.preg_quote($q,'/').')(.*?)/iu',"\$1<span class=\"$class\">\$2</span>\$3",' '.$tag_matches[3][$i].' ');
			$full_body_hl .= substr($holder,1,(strlen($holder)-2));
		}
	}
	/* return tagged text */
	return $full_body_hl;
}

/* quell warnings */
function fof_render_get_key_($array, $key, $default=NULL) {
	return (empty($array[$key]) ? $default : $array[$key]);
}

function fof_render_item($item, $include_div=true)
{
    global $fof_asset;

	$feed_link = fof_render_get_key_($item, 'feed_link');
	if ($feed_link == "[no link]")
	    $feed_link = $item['feed_url'];
	$feed_title = fof_render_get_key_($item, 'display_title');
	if ($feed_title == "[no title]")
	    $feed_title = $feed_link;
	$feed_image = fof_render_get_key_($item, 'display_image', $fof_asset['feed_icon']);
	$feed_description = fof_render_get_key_($item, 'feed_description');

	$item_link = fof_render_get_key_($item, 'item_link');
	$item_id = fof_render_get_key_($item, 'item_id');
	$item_title = fof_render_get_key_($item, 'item_title', '[no title]');
	$item_content = fof_render_get_key_($item, 'item_content');
	$item_read = fof_render_get_key_($item, 'item_read');

	$prefs = fof_prefs();
	$offset = fof_render_get_key_($prefs, 'tzoffset') * 60 * 60;

	$item_published = gmdate("Y-n-d g:ia", $item['item_published'] + $offset);
	$item_cached = gmdate("Y-n-d g:ia", $item['item_cached'] + $offset);
	$item_updated = gmdate("Y-n-d g:ia", $item['item_updated'] + $offset);

	if ( ! empty($_GET['search']))
	{
		$item_content = do_highlight("<span>$item_content</span>", $_GET['search'], "highlight");
		$item_title = do_highlight("<span>$item_title</span>", $_GET['search'], "highlight");
	}

    $tags = fof_render_get_key_($item, 'tags', array());

	$star = in_array("star", $tags) ? true : false;
	$star_image = $star ? $fof_asset['star_on_image'] : $fof_asset['star_off_image'];

	$unread = in_array("unread", $tags) ? true : false;

	$folded = in_array('folded', $tags) ? true : false;
	if ($include_div) {
		echo '<div class="item ' . ($folded ? 'hidden' : 'shown') . '" id="i' . $item_id . '" onclick="return itemClicked(event)">' . "\n";
	}
?>

<div class="header">
	<span class="controls">
		<a class="uparrow" href="#" onclick="hide_body('<?php echo $item_id ?>');return false;">fold &uarr;</a>
		<a class="downarrow" href="#" onclick="show_body('<?php echo $item_id ?>');return false;">unfold &darr;</a>
		<a href="#" onclick="ajax_mark_read('<?php echo $item_id ?>'); return false;">mark read</a>
	</span>
	<h1 <?php if($unread) echo "class='unread-item'" ?>>
		<input type="checkbox"
			   name="c<?php echo $item_id ?>"
			   id="c<?php echo $item_id ?>"
			   value="checked"
			   ondblclick="flag_upto('c<?php echo $item_id?>');"
			   onclick="return checkbox(event);"
			   title="shift-click or double-click to flag all items up to this one" />
<?php
    echo '		<img id="fav' . $item_id . '" class="' . ($star ? '' : 'un') . 'starred" src="' . $star_image . '" onclick="return toggle_favorite(' . $item_id . ')" />' . "\n";
?>
		<script>
			document.getElementById('fav<?php echo $item_id ?>').star = <?php if($star) echo 'true'; else echo 'false'; ?>;
		</script>
<?php
	echo "		<a href=\"$item_link\"" . (fof_render_get_key_($prefs, 'item_target') ? ' target="_blank"' : '') . ">$item_title</a>\n";
?>
	</h1>

	<span class="tags">
<?php
	/* show non-system tags */
	foreach (array_diff($tags, array('unread', 'star', 'folded')) as $tag) {
		echo '		<a href="' . fof_url('.', array('what' => $tag)) . '">' . htmlentities($tag) . '</a>';
		echo '		<a href="#" class="untag" title="remove ' . htmlentities('"' . $tag . '"') . ' tag" onclick="return remove_tag(' . $item_id . ',' . htmlentities(json_encode($tag), ENT_QUOTES) . ');">[x]</a>' . "\n";
	}
?>
		<a href="#" onclick="return itemTagAddShow('<?php echo $item_id; ?>', this);">add tag</a>
		<div id="addtag<?php echo $item_id ?>" style="display: none !important">
			<input onfocus="this.value=''" onkeypress="itemTagAdd('<?php echo $item_id; ?>', event.keyCode);" type="text" id="tag<?php echo $item_id ?>" size="12" value="enter tag here" />
			<input type="button" name="add tag" value="tag" onclick="itemTagAdd('<?php echo $item_id; ?>');" />
		</div>
	</span>

	<span class="dash"> - </span>

	<h2>
<?php
		if ( $feed_image && $prefs [ 'favicons' ] )
			echo '		<img class="feed-icon" src="' . $feed_image . '" />' . "\n";
?>
		<a href="<?php echo $feed_link ?>" title="<?php echo htmlspecialchars($feed_description); ?>"><?php echo $feed_title; ?></a>
	</h2>

	<span class="meta published">on <?php echo $item_published ?></span>
</div>

<div class="body"><?php echo $item_content ?></div>

<?php
    $widgets = fof_get_widgets($item);

    $widgets[] = '<a href="#" onclick="return ajax_mark_read(\'' . $item_id . '\');">mark read</a>';

    if ( ! empty($widgets)) {
        echo '<div class="clearer"></div>' . "\n";
        echo '<div class="widgets">';
        foreach ($widgets as $widget) {
            echo '<span class="widget">' . $widget . "</span>";
        }
        echo "</div>\n";
    }

    if ($include_div) {
        echo "</div>\n";
    }
}
?>
