<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * fof-sanitizer.php - custom sanitizer hook for SimplePie
 *
 *
 * Distributed under the GPL - see LICENSE
 *
 */

require_once "autoloader.php";

class FoF_Sanitizer extends SimplePie_Sanitize {
	public function sanitize($data, $type, $base = '') {
		// why doesn't simplepie let us just add a pipeline of process hooks?
		$data = parent::sanitize($data, $type, $base);

		// why doesn't simplepie separate out the type inference from the everything else?
		// or provide the inferred type in any way whatsoever?
		// fragment just copied from SimplePie's Sanitize.php...
		if ($type & SIMPLEPIE_CONSTRUCT_MAYBE_HTML) {
			if (preg_match('/(&(#(x[0-9a-fA-F]+|[0-9]+)|[a-zA-Z0-9]+)|<\/[A-Za-z][^\x09\x0A\x0B\x0C\x0D\x20\x2F\x3E]*' . SIMPLEPIE_PCRE_HTML_ATTRIBUTE . '>)/', $data)) {
				$type |= SIMPLEPIE_CONSTRUCT_HTML;
			} else {
				$type |= SIMPLEPIE_CONSTRUCT_TEXT;
			}
		}

		if ($type == SIMPLEPIE_CONSTRUCT_TEXT) {
			// We are plain text, so let's wrap ourselves in an appropriate block
			$data = $type . '<pre>' . $data . '</pre>';
		}

		return $data;
	}
}

?>