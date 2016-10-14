<?php
// Image proxy for FeedOnFeeds, to assist with hotlinking and security policy issues

require_once 'fof-main.php';

if (!$_GET['item']) die("Missing item ID");
if (!$_GET['url']) die("Missing URL");

// TODO: make sure we're logged in

$item = fof_get_item(NULL, $_GET['item']);
if (!$item) die("couldn't get item");

// TODO: fix relative URLs with $item['item_link']

// This is a really annoying way to get the final header chunk. There's got to be a better way...
$final = false;
function dump_headers($ch, $header) {
    global $final;
    if (preg_match('@HTTP/.* [12456789]@', $header)) {
        $final = true;
    } else if ($final  && preg_match('/^\w: /', $header)) {
        header($header);
    }
    return strlen($header);
}

$curl = curl_init($_GET['url']);
curl_setopt_array($curl, array(
	CURLOPT_REFERER => $item['item_link'],
	CURLOPT_HEADERFUNCTION => 'dump_headers',
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_AUTOREFERER => true,
	CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT']
	));

curl_exec($curl);
curl_close($curl);
?>