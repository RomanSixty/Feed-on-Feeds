<?php
/**	Locate a suitable favicon for a site.

	Copyright (C) 2013 Justin Wind <justin.wind@gmail.com>

*/
class FavIcon {
	const VERSION = '1.0';
	const BUILD = '20130725000000';
	const SRC_URL = '';

	static protected function default_user_agent() {
		return sprintf('%s/%s (Caching Utility; %s; Allow like Gecko) Build/%s', __CLASS__, self::VERSION, self::SRC_URL, self::BUILD);
	}

	protected $site_url; /* whose favicons are we interested in */
	protected $site_url_parts; /* broken down by parse_url() */
	protected $favicons; /* list of extant favicons referenced by site */
	protected $stream_context_options; /* user-agent &c */


	/**	Create a new FavIcon instance, which will learn about all the potential
		favicons at the given url.
	*/
	function __construct($site_url, $user_agent=null) {
		$this->favicons = array();
		$this->site_url = $site_url;
		$this->site_url_parts = parse_url($site_url);
		$this->stream_context_options = array( 'http' => array() );
		$this->stream_context_options['http']['user_agent'] = ($user_agent !== null) ? $user_agent : self::default_user_agent();
		/* refuse compressed streams for now, as decompression isn't automatic */
		$this->stream_context_options['http']['accept_encoding'] = "gzip;q=0, compress;q=0";

		$this->links_from_site();

		if (empty($this->favicons))
			$this->links_from_rote();

		/* TODO: sort by something other than first-occurence */
	}


	/**	Return the url of the first favicon.
	*/
	function __toString() {
		list($first) = $this->favicons;
		return empty($first) ? '' : $first['href'];
	}


	/**	Return an array containing all the information about the first favicon.
		href: icon url
		type: icon content-type
		sizes: sizes provided by icon (only parsed out of <link> for now)
		data: icon file data
	*/
	function getIcon() {
		@list($first) = $this->favicons;
		if (empty($first))
			return null;
		return $first;
	}


	/**	Load the page, parse for iconic links, and add them to icon list if
		they are valid.
	*/
	protected function links_from_site() {
		/*
			Quietly fetch the site contents into a DOM
		*/
		$dom = new DOMDocument();
		$dom->recover = true;
		$dom->strictErrorChecking = false;
		$default_context = stream_context_get_default();
		$stream_context = stream_context_create($this->stream_context_options);

		libxml_set_streams_context($stream_context);
		$libxml_err_state = libxml_use_internal_errors(true);
		$dom->loadHTMLFile($this->site_url);
		libxml_clear_errors();
		libxml_use_internal_errors($libxml_err_state);
		libxml_set_streams_context($default_context);

		/*
			If we followed any redirects, rewrite the site_url with the current
			location, so that relative urls may be correctly converted into
			their absolute form.
		*/
		$location = self::header_findr($http_response_header, 'Location');
		if ($location !== null)
			$this->site_url = $location;

		if ($dom !== false) {
			/* check all the links which relate to icons */
			foreach ($dom->getElementsByTagName('link') as $link) {
				$relations = explode(' ', $link->getAttribute('rel'));
				if (in_array('icon', array_map('strtolower', $relations))) {
					$href = $link->getAttribute('href');
					$href_absolute = $this->absolutize_url($href);
					$icon = $this->validate_icon($href_absolute);
					if ($icon !== null) {
						if (empty($icon['type']))
							$icon['type'] = $link->getAttribute('type');
						if (empty($icon['sizes']))
							$icon['sizes'] = $link->getAttribute('sizes');
						$this->favicons[] = $icon;
					}
				}
			}
		}
	}


	/**	Add standard favicon locations to icon list.
	*/
	protected function links_from_rote() {
		$favicon_url = array();

		/* take only what we want */
		foreach (array('scheme', 'user', 'pass', 'host', 'port') as $key) {
			if ( ! empty($this->site_url_parts[$key]))
				$favicon_url[$key] = $this->site_url_parts[$key];
		}

		/* add our own */
		$favicon_url['path'] = '/favicon.ico';

		/* put back together */
		$favicon_url = self::unparse_url($favicon_url);

		/* look for it */
		$icon = $this->validate_icon($favicon_url);
		if ($icon !== null)
			$this->favicons[] = $icon;
	}


	/**	Returns the relevant header value.  Null search returns status.
		This matches in reverse order, because I guess headers are cumulative
		when http wrappers follow redirects.
	*/
	static protected function header_findr($headers, $header=null) {
		if (empty($headers))
			return null;

		end($headers);
		while (key($headers) !== null) {
			if ($header === null) {
				@list($proto, $code, $msg) = explode(' ', current($headers), 3);
				@list($protocol, $version) = explode('/', $proto, 2);
				if ($protocol === 'HTTP')
					return current($headers);
			} else {
				@list($name, $value) = explode(': ', current($headers), 2);
				if (strcasecmp($header, $name) === 0)
					return $value;
			}
			prev($headers);
		}
		return null;
	}

	/**	Validate an icon resource by attempting to fetch it.
	*/
	protected function validate_icon($url, $fetch=false) {
		$icon = array('href' => $url);

		$stream_context = stream_context_create($this->stream_context_options);
		$icon['data'] = file_get_contents($url, NULL, $stream_context);

		/* did we get a useful response */
		$status = self::header_findr($http_response_header, null);
		@list ( , $status, ) = explode(' ', $status, 3);
		$status = (integer)$status;
		if ($status !== 200) {
			trigger_error('icon resource \'' . $url . '\' returned ' . $status, E_USER_NOTICE);
			return null;
		}

		if (empty($icon['data'])) {
			trigger_error('icon resource \'' . $url . '\' is empty');
			return null;
		}

		/* is it displayable */
		$icon['type'] = self::header_findr($http_response_header, 'Content-Type');
		@list($icon['type'], ) = explode(';', $icon['type']);
		@list($type, $subtype) = explode('/', $icon['type'], 2);
		if (strcasecmp($type, 'image') !== 0) {
                        if (!class_exists('finfo')) {
                                fof_log("Couldn't find finfo class for favicons", "warning");
                                return null;
                        }

			/*
				Is their server possibly just sending the wrong content-type?
				This turns out to be a fairly common problem with .ico files.
				Double-check against magic mimetypes before giving up.
			*/
			$finfo = new finfo(FILEINFO_MIME);
			@list($icon['type'], ) = explode(';', $finfo->buffer($icon['data']));
			@list($type, $subtype) = explode('/', $icon['type'], 2);
			if (strcasecmp($type, 'image') !== 0) {
				/* really not an image */
				trigger_error('icon resource \'' . $url . '\' is not an image', E_USER_NOTICE);
				return null;
			}
		}

		return $icon;
	}


	/**	Return a full url from what might be just a path.
	*/
	protected function absolutize_url($url) {
		/* If there's a scheme, it's already good to go. */
		if (strpos($url, '://'))
			return $url;

		/* If there's no scheme, $url is just a path, so we need to fill in
			the preambling parts from the site's url. */
		$url_parts = array();
		foreach (array('scheme', 'user', 'pass', 'host', 'port') as $key) {
			if (empty($url_parts[$key])
			&&  ! empty($this->site_url_parts[$key]))
				$url_parts[$key] = $this->site_url_parts[$key];
		}

		/* If it starts with a /, it's a complete path. */
		if ($url[0] === '/') {
			$url_parts['path'] = $url;
		} else {
			/* Otherwise, we need to tack this relative path on to the site's
			 	path's directory, without the trailing-most non-directory bit..
			*/
			$last_slash_pos = strrpos($this->site_url_parts['path'], '/');
			if ($last_slash_pos === false) {
				$base_path = '/';
			} else {
				$base_path = substr($this->site_url_parts['path'], 0, $last_slash_pos + 1);
			}
			$url_parts['path'] = $base_path . $url;
		}

		/* Put it all together. */
		return self::unparse_url($url_parts);
	}


	/**	Assemble a url from its parse_url components.
	*/
	static protected function unparse_url($parts) {
		$url = array();

		if ( ! empty($parts['scheme'])) {
			$url[] = $parts['scheme'];
			$url[] = '://';
		}

		if ( ! empty($parts['user']) || ! empty($parts['pass'])) {
			if ( ! empty($parts['user']))
				$url[] = $parts['user'];
			if ( ! empty($parts['pass'])) {
				$url[] = ':';
				$url[] = $parts['pass'];
			}
			$url[] = '@';
		}

		$url[] = $parts['host'];

		if ( ! empty($parts['port']))
			$url[] = ':' . $parts['port'];

		if ( ! empty($parts['path']))
			$url[] = $parts['path'];

		if ( ! empty($parts['query'])) {
			$url[] = '?';
			$url[] = $parts['query'];
		}

		if ( ! empty($parts['fragment'])) {
			$url[] = '#';
			$url[] = $parts['fragment'];
		}

		return implode($url);
	}
}
?>
