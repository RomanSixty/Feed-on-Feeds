<?php
// Image proxy for FeedOnFeeds, to assist with hotlinking and security policy issues

require_once 'fof-main.php';
require_once 'library/urljoin.php';

$item_id = $_GET['item'];
if (!$item_id) {
	die("Missing item ID");
}

$url = $_GET['url'];
if (!$url) {
	die("Missing URL");
}

// TODO: make sure we're logged in

$item = fof_get_item(NULL, $item_id, false);
if (!$item) {
	die("couldn't get item");
}

// fix relative URLs with $item['item_link']
$url = urljoin($item['item_link'], $url);

// This is a really annoying way to get the final header chunk. There's got to be a better way...
$final = false;
function dump_headers($ch, $header) {
	global $final;
	if (preg_match('@HTTP/[^ ]* [12456789]@', $header)) {
		$final = true;
	} else if ($final && strpos($header, ':')) {
		fof_log("image $url header: $header");
		header(rtrim($header));
	}
	return strlen($header);
}

$curl = curl_init($url);
curl_setopt_array($curl, array(
	CURLOPT_REFERER => $item['item_link'],
	CURLOPT_HEADERFUNCTION => 'dump_headers',
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_AUTOREFERER => true,
	CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'],
));

curl_exec($curl);
fof_log(curl_error($curl));
curl_close($curl);
?>