<?php

fof_add_item_widget('fof_twitter');

function fof_twitter($item) {
	$url = html_entity_decode($item['item_link']);
	$tcolen = 23;
	$maxlen = 140 - $tcolen;
	$via = ' (via ' . $item['feed_title'] . ')';
	$title = $item['item_title'];

	$text = $title . $via;
	# first try making the via shorter
	if (strlen($text) > $maxlen) {
		$via = ' via ' . $item['feed_title'];
		$text = $title . $via;
	}

	# then try truncating the title
	if (strlen($text) > $maxlen) {
		$text = substr($title, 0, $maxlen - strlen($via) - 3) . '...' . $via;
	}

	# then try truncating the source as well
	if (strlen($text) > $maxlen) {
		$text = substr($title, 0, $maxlen * strlen($title) / strlen($text) - 3)
		. '...'
		. substr($via, 0, $maxlen * strlen($via) / strlen($text) - 3)
			. '...';
	}

	$posturl = 'http://twitter.com/home?status=' . urlencode("$text $url");
	$linktag = '<a href="' . $posturl . '" onClick="window.open(\'' . $posturl . '\',\'post to twitter\',\'width=800,height=500,status=0,toolbar=0,location=0,menubar=0,resizeable=1\');return false;">';

	return $linktag . '<img src="https://twitter.com/favicons/favicon.ico" height="16" width="16" /></a> '
		. $linktag . 'tweet</a>';
}