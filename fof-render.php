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

require_once 'fof-asset.php';

// From Brian Suda @ http://suda.co.uk/projects/SEHL/

function do_highlight($full_body, $q, $class) {
	// this is really gross and should be replaced with DOM methods
	$full_body_hl = "";

	/* seperate tags and data from the HTML file INCLUDING comments, avoiding HTML in the comments */
	$pat = '/((<[^!][\/]*?[^<>]*?>)([^<]*))|<!---->|<!--(.*?)-->|((<!--[ \r\n\t]*?)(.*?)[ \r\n\t]*?-->([^<]*))/si';
	preg_match_all($pat, $full_body, $tag_matches);

	if (empty($tag_matches[0])) {
		$full_body_hl = preg_replace('/(.*?)(' . preg_quote($q, '/') . ')(.*?)/iu', "\$1<mark class=\"$class\">\$2</mark>\$3", $full_body);
	}
	/* loop through and highlight $q value in data and recombine with tags */
	else for ($i = 0; $i < count($tag_matches[0]); $i++) {
		/* ignore all text within these tags */
		if (
			(preg_match('/<!/i', $tag_matches[0][$i])) or
			(preg_match('/<textarea/i', $tag_matches[2][$i])) or
			(preg_match('/<script/i', $tag_matches[2][$i]))
		) {
			/* array[0] is everything the REGEX found */
			$full_body_hl .= $tag_matches[0][$i];
		} else {
			$full_body_hl .= $tag_matches[2][$i];

			/* this one ALMOST works, except if the string is at the start or end of a string*/
			$holder = preg_replace('/(.*?)(' . preg_quote($q, '/') . ')(.*?)/iu', "\$1<mark class=\"$class\">\$2</mark>\$3", ' ' . $tag_matches[3][$i] . ' ');
			$full_body_hl .= substr($holder, 1, (strlen($holder) - 2));
		}
	}
	/* return tagged text */
	return $full_body_hl;
}

/* quell warnings */
function fof_render_get_key_($array, $key, $default = NULL) {
	return (empty($array[$key]) ? $default : $array[$key]);
}

function fof_render_item($item, $include_div = true) {
	global $fof_asset,
		$fof_render_filters,
		$fof_domitem_filters;

	$feed_link = fof_render_get_key_($item, 'feed_link');
	if ($feed_link == "[no link]") {
		$feed_link = $item['feed_url'];
	}

	$feed_title = fof_render_get_key_($item, 'display_title');
	if ($feed_title == "[no title]") {
		$feed_title = $feed_link;
	}

	$feed_image = fof_render_get_key_($item, 'display_image', $fof_asset['feed_icon']);
	$feed_description = fof_render_get_key_($item, 'feed_description');

	$item_link = fof_render_get_key_($item, 'item_link');
	$item_id = fof_render_get_key_($item, 'item_id');
	$item_title = fof_render_get_key_($item, 'item_title', '[no title]');
	$item_author = fof_render_get_key_($item, 'item_author', '');
	$item_content = fof_render_get_key_($item, 'item_content');
	$item_read = fof_render_get_key_($item, 'item_read');

	foreach ($fof_render_filters as $filter) {
		$item_content = $filter($item_content, $item);
	}

	$dom = fof_content_to_dom($item_content);

	// enable controls, if an item contains a video
	foreach ($dom->getElementsByTagName('video') as $video) {
		$video->setAttribute('controls', '');
	}

	// also make images clickable and demand-loaded
	foreach ($dom->getElementsByTagName('img') as $img) {
		$img->setAttribute('tabindex', '0');
		$img->setAttribute('loading', 'lazy');
	}

	// run user-configured DOM filters
	foreach ($fof_domitem_filters as $filter) {
		$dom = $filter($dom, $item);
	}

	$item_content = fof_dom_to_content($dom);

	// Get the local datetime
	$prefs = fof_prefs();
	$offset = fof_render_get_key_($prefs, 'tzoffset') * 60 * 60;

	$item_published = gmdate("Y-n-d g:ia", $item['item_published'] + $offset);
	$item_cached = gmdate("Y-n-d g:ia", $item['item_cached'] + $offset);
	$item_updated = gmdate("Y-n-d g:ia", $item['item_updated'] + $offset);

	if (!empty($_GET['search'])) {
		$item_content = do_highlight($item_content, $_GET['search'], "fof-highlight");
		$item_title = do_highlight($item_title, $_GET['search'], "fof-highlight");
	}

	$tags = fof_render_get_key_($item, 'tags', array());

	$star = in_array("star", $tags) ? true : false;

	$unread = in_array("unread", $tags) ? true : false;

	$folded = in_array('folded', $tags) ? true : false;
	if ($include_div) {
		echo '<div data-href="' . $item_link . '" class="item ' . ($folded ? 'hidden' : 'shown') . '" id="i' . $item_id . '" onclick="return itemClicked(event)">' . "\n";
	}
	?>

<div class="header">
	<span class="controls hide-on-mobile">
		<a class="uparrow" href="#" onclick="hide_body('<?=$item_id?>');return false;">fold &uarr;</a>
		<a class="downarrow" href="#" onclick="show_body('<?=$item_id?>');return false;">unfold &darr;</a>
		<a href="#" onclick="ajax_mark_read('<?=$item_id?>'); return false;">mark read</a>
	</span>
	<h1 <?php if ($unread) {
		echo "class='unread-item'";
	}
	?>>
		<input type="checkbox"
			   name="c<?=$item_id?>"
			   id="c<?=$item_id?>"
			   value="checked"
			   ondblclick="flag_upto('c<?=$item_id?>');"
			   onclick="return checkbox(event);"
			   title="shift-click or double-click to flag all items up to this one"
		       class="hide-on-mobile"/>
<?php
echo '		<span id="fav' . $item_id . '" class="' . ($star ? '' : 'un') . 'starred" onclick="return toggle_favorite(' . $item_id . ')"></span>' . "\n";
	?>
		<script>
			document.getElementById('fav<?=$item_id?>').star = <?php if ($star) {
		echo 'true';
	} else {
		echo 'false';
	}
	?>;
		</script>
<?php
echo "		<a href=\"$item_link\"" . (fof_render_get_key_($prefs, 'item_target') ? ' target="_blank"' : '') . ">$item_title</a>\n";
	if ($item_author) {
		echo '<span class="author">' . htmlentities($item_author) . '</span>';
	}
	?>
	</h1>


	<span class="dash"> - </span>

	<h2>
<?php
if ($feed_image && $prefs['favicons']) {
		echo '		<img class="feed-icon" src="' . $feed_image . '" />' . "\n";
	}

	?>
		<a href="<?=$feed_link?>" title="<?=htmlspecialchars($feed_description)?>"><?=$feed_title;?></a>
	</h2>

	<span class="meta published">on <?=$item_published?></span>
</div>

<div class="body"><?=$item_content?></div>

<div class="clearer"></div>
<div class="widgets">
	<span class="tags">
 	<a href="#" onclick="return ajax_mark_read('<?php echo $item_id; ?>');">mark read</a>
	<a href="#" onclick="return itemTagAddShow('<?=$item_id?>', this);">add tag</a>
	<span id="addtag<?=$item_id?>" style="display: none !important">
		<input onkeypress="itemTagAdd('<?=$item_id?>', event.key);" type="text" id="tag<?=$item_id?>" size="12" placeholder="enter tag here" />
		<input type="button" name="add tag" value="tag" onclick="itemTagAdd('<?=$item_id?>');" />
	</span>
<?php
/* show non-system tags */
	foreach (array_diff($tags, array('unread', 'star', 'folded')) as $tag) {
		echo '<span id="tag_' . $item_id . '_' . htmlspecialchars($tag) . '" class="tag tag_'.htmlspecialchars($tag).'">';
		echo '<a href="#" class="untag" title="remove ' . htmlspecialchars('"' . $tag . '"') . ' tag" data-itemid="'.$item_id.'" data-tag="'.htmlspecialchars($tag).'" onclick="return remove_tag(' . $item_id . ',' . htmlspecialchars($tag) . ');">[x]</a>';
		echo '<a href="' . fof_url('.', array('what' => $tag)) . '">' . htmlspecialchars($tag) . '</a>';
		echo '</span>' . "\n";
	}
	?>
	</span>
<?php
$widgets = fof_get_widgets($item);

	if (!empty($widgets)) {
		foreach ($widgets as $widget) {
			echo '<span class="widget">' . $widget . "</span>";
		}
	}
?>
</div><!-- .widgets -->
<?php
	if ($include_div) {
		echo "</div>\n";
	}
}
?>
