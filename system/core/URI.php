<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.1.6 or newer
 *
 * @package		CodeIgniter
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2008 - 2011, EllisLab, Inc.
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */

/**
 * URI Class
 *
 * Parses URIs and determines routing
 * The base class, CI_RouterBase, is defined in CodeIgniter.php and allows
 * access to protected methods between CodeIgniter, Router, and URI.
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	URI
 * @author		ExpressionEngine Dev Team
 * @link		http://codeigniter.com/user_guide/libraries/uri.html
 */
class CI_URI extends CI_RouterBase {
	protected $CI			= NULL;
	protected $keyval		= array();
	protected $segments		= array();
	protected $rsegments	= array();
	protected $uri_string	= '';

	/**
	 * Constructor
	 *
	 * Simply globalizes the Router object. The front loads the Router class early
	 * on so it's not available normally as other classes are.
	 *
	 * @param	object	parent reference
	 */
	public function __construct(CodeIgniter $CI) {
		$this->CI =& $CI;
		$CI->log_message('debug', 'URI Class Initialized');
	}

	/**
	 * Fetch a URI Segment
	 *
	 * This function returns the URI segment based on the number provided.
	 *
	 * @param	integer
	 * @param	bool
	 * @return	string
	 */
	public function segment($index, $no_result = FALSE) {
		return (isset($this->segments[$index]) ? $this->segments[$index] : $no_result);
	}

	/**
	 * Fetch a URI "routed" Segment
	 *
	 * This function returns the re-routed URI segment (assuming routing rules are used)
	 * based on the number provided. If there is no routing this function returns the
	 * same result as $this->segment()
	 *
	 * @param	integer
	 * @param	bool
	 * @return	string
	 */
	public function rsegment($index, $no_result = FALSE) {
		return (isset($this->rsegments[$index]) ? $this->rsegments[$index] : $no_result);
	}

	/**
	 * Generate a key value pair from the URI string
	 *
	 * This function generates and associative array of URI data starting
	 * at the supplied segment. For example, if this is your URI:
	 *
	 *	example.com/user/search/name/joe/location/UK/gender/male
	 *
	 * You can use this function to generate an array with this prototype:
	 *
	 * array (
	 *			name => joe
	 *			location => UK
	 *			gender => male
	 *		 )
	 *
	 * @param	integer	the	starting segment number
	 * @param	array	an	array of default values
	 * @return	array
	 */
	public function uri_to_assoc($index = 3, $default = array()) {
		return $this->_uri_to_assoc($index, $default, 'segment');
	}

	/**
	 * Identical to above only it uses the re-routed segment array
	 *
	 */
	public function ruri_to_assoc($index = 3, $default = array()) {
		return $this->_uri_to_assoc($index, $default, 'rsegment');
	}

	/**
	 * Generate a URI string from an associative array
	 *
	 *
	 * @param	array	an	associative array of key/values
	 * @return	array
	 */
	public function assoc_to_uri($array) {
		$temp = array();
		foreach ((array)$array as $key => $val) {
			$temp[] = $key;
			$temp[] = $val;
		}

		return implode('/', $temp);
	}

	/**
	 * Fetch a URI Segment and add a trailing slash
	 *
	 * @param	integer
	 * @param	string
	 * @return	string
	 */
	public function slash_segment($index, $where = 'trailing') {
		return $this->_slash_segment($index, $where, 'segment');
	}

	/**
	 * Fetch a URI Segment and add a trailing slash
	 *
	 * @param	integer
	 * @param	string
	 * @return	string
	 */
	public function slash_rsegment($index, $where = 'trailing') {
		return $this->_slash_segment($index, $where, 'rsegment');
	}

	/**
	 * Segment Array
	 *
	 * @return	array
	 */
	public function segment_array() {
		return $this->segments;
	}

	/**
	 * Routed Segment Array
	 *
	 * @return	array
	 */
	public function rsegment_array() {
		return $this->rsegments;
	}

	/**
	 * Total number of segments
	 *
	 * @return	integer
	 */
	public function total_segments() {
		return count($this->segments);
	}

	/**
	 * Total number of routed segments
	 *
	 * @return	integer
	 */
	public function total_rsegments() {
		return count($this->rsegments);
	}

	/**
	 * Fetch the entire URI string
	 *
	 * @return	string
	 */
	public function uri_string() {
		return $this->uri_string;
	}

	/**
	 * Fetch the entire Re-routed URI string
	 *
	 * @return	string
	 */
	public function ruri_string() {
		return '/'.implode('/', $this->rsegment_array());
	}

	/**
	 * Get the URI string as an array of segments
	 *
	 * @access	protected
	 * @return	array	uri segments
	 */
	protected function _fetch_uri_string() {
		// Get configured protocol
		$proto = strtoupper($this->CI->config->item('uri_protocol'));

		// Find URI according to protocol
		if ($proto == 'AUTO') {
			if (defined('STDIN')) {
				// Request came from the command line
				$uri = $this->_parse_cli_args();
			}
			else if ($path = $this->_detect_uri()) {
				// The REQUEST_URI will work in most situations
				$uri = $path;
			}
			else {
				// Is there a PATH_INFO variable?
				// Note: some servers seem to have trouble with getenv() so we'll test it two ways
				$path = (isset($_SERVER['PATH_INFO'])) ? $_SERVER['PATH_INFO'] : @getenv('PATH_INFO');
				if (trim($path, '/') != '' && $path != '/'.SELF) {
					$uri = $path;
				}
				else {
					// No PATH_INFO?... What about QUERY_STRING?
					$path = (isset($_SERVER['QUERY_STRING'])) ? $_SERVER['QUERY_STRING'] : @getenv('QUERY_STRING');
					if (trim($path, '/') != '') {
						$uri = $path;
					}
					else if (is_array($_GET) && count($_GET) == 1 && trim(key($_GET), '/') != '') {
						// As a last ditch effort use the $_GET array
						$uri = key($_GET);
					}
					else {
						// We've exhausted all our options...
						$this->uri_string = '';
						return array();
					}
				}
			}
		}
		else if ($proto == 'REQUEST_URI') {
			$uri = $this->_detect_uri();
		}
		else if ($proto == 'CLI') {
			$uri = $this->_parse_cli_args();
		}
		else {
			$uri = (isset($_SERVER[$proto])) ? $_SERVER[$proto] : @getenv($proto);
		}

		// Get path, remove any suffix, and set as URI
		if ($this->CI->config->item('url_suffix') != '') {
			$uri = preg_replace('|'.preg_quote($this->config->item('url_suffix')).'$|', '', $uri);
		}
		$this->_set_uri_string($uri);

		// Break URI into segments
		foreach (explode('/', preg_replace('|/*(.+?)/*$|', '\\1', $this->uri_string)) as $val) {
			// Filter segments for security
			$val = trim($this->_filter_uri($val));

			if ($val != '') {
				$this->segments[] = $val;
			}
		}

		// Return segments
		return $this->segments;
	}

	/**
	 * Filter segments for malicious characters
	 *
	 * @access	protected
	 * @param	string
	 * @return	string
	 */
	protected function _filter_uri($str) {
		if ($str != '' && $this->CI->config->item('permitted_uri_chars') != '' &&
		$this->CI->config->item('enable_query_strings') == FALSE) {
			// preg_quote() in PHP 5.3 escapes -, so the str_replace() and addition of - to preg_quote()
			// is to maintain backwards compatibility as many are unaware of how characters in the
			// permitted_uri_chars will be parsed as a regex pattern
			if ( ! preg_match('|^['.str_replace(array('\\-', '\-'), '-',
			preg_quote($this->CI->config->item('permitted_uri_chars'), '-')).']+$|i', $str)) {
				$this->CI->show_error('The URI you submitted has disallowed characters.', 400);
			}
		}

		// Convert programatic characters to entities
		$bad	= array('$',		'(',		')',		'%28',		'%29');
		$good	= array('&#36;',	'&#40;',	'&#41;',	'&#40;',	'&#41;');

		return str_replace($bad, $good, $str);
	}

	/**
	 * Set the routed URI Segments.
	 *
	 * @access	protected
	 * @param	array	routed segments
	 * @return	void
	 */
	protected function _set_rsegments(array $rsegments) {
		$this->rsegments = $rsegments;
	}

	/**
	 * Re-index Segments
	 *
	 * This function re-indexes the $this->segment array so that it
	 * starts at 1 rather than 0. Doing so makes it simpler to
	 * use functions like $this->uri->segment(n) since there is
	 * a 1:1 relationship between the segment array and the actual segments.
	 *
	 * @access	protected
	 * @return	void
	 */
	protected function _reindex_segments() {
		array_unshift($this->segments, NULL);
		array_unshift($this->rsegments, NULL);
		unset($this->segments[0]);
		unset($this->rsegments[0]);
	}

	/**
	 * Set the URI String
	 *
	 * @return	string
	 */
	protected function _set_uri_string($str) {
		// Filter out control characters
		$str = $this->CI->remove_invisible_characters($str, FALSE);

		// If the URI contains only a slash we'll kill it
		$this->uri_string = ($str == '/') ? '' : $str;
	}

	/**
	 * Detects the URI
	 *
	 * This function will detect the URI automatically and fix the query string
	 * if necessary.
	 *
	 * @access	protected
	 * @return	string
	 */
	protected function _detect_uri() {
		if (!isset($_SERVER['REQUEST_URI']) || !isset($_SERVER['SCRIPT_NAME'])) {
			return '';
		}

		$uri = $_SERVER['REQUEST_URI'];
		if (strpos($uri, $_SERVER['SCRIPT_NAME']) === 0) {
			$uri = substr($uri, strlen($_SERVER['SCRIPT_NAME']));
		}
		else if (strpos($uri, dirname($_SERVER['SCRIPT_NAME'])) === 0) {
			$uri = substr($uri, strlen(dirname($_SERVER['SCRIPT_NAME'])));
		}

		// This section ensures that even on servers that require the URI to be in the query string (Nginx) a correct
		// URI is found, and also fixes the QUERY_STRING server var and $_GET array.
		if (strncmp($uri, '?/', 2) === 0) {
			$uri = substr($uri, 2);
		}
		$parts = preg_split('#\?#i', $uri, 2);
		$uri = $parts[0];
		if (isset($parts[1])) {
			$_SERVER['QUERY_STRING'] = $parts[1];
			parse_str($_SERVER['QUERY_STRING'], $_GET);
		}
		else {
			$_SERVER['QUERY_STRING'] = '';
			$_GET = array();
		}

		if ($uri == '/' || empty($uri)) {
			return '/';
		}

		$uri = parse_url($uri, PHP_URL_PATH);

		// Do some final cleaning of the URI and return it
		return str_replace(array('//', '../'), '/', trim($uri, '/'));
	}

	/**
	 * Parse cli arguments
	 *
	 * Take each command line argument and assume it is a URI segment.
	 *
	 * @access	protected
	 * @return	string
	 */
	protected function _parse_cli_args() {
		$args = array_slice($_SERVER['argv'], 1);

		return $args ? '/' . implode('/', $args) : '';
	}

	/**
	 * Generate a key value pair from the URI string or Re-routed URI string
	 *
	 * @access	protected
	 * @param	int	the starting segment number
	 * @param	array	an array of default values
	 * @param	string	which array we should use
	 * @return	array
	 */
	protected function _uri_to_assoc($index = 3, $default = array(), $which = 'segment') {
		if ($which == 'segment') {
			$total_segments = 'total_segments';
			$segment_array = 'segment_array';
		}
		else {
			$total_segments = 'total_rsegments';
			$segment_array = 'rsegment_array';
		}

		if (!is_numeric($index)) {
			return $default;
		}

		if (isset($this->keyval[$index])) {
			return $this->keyval[$index];
		}

		if ($this->$total_segments() < $index) {
			if (count($default) == 0) {
				return array();
			}

			$retval = array();
			foreach ($default as $val) {
				$retval[$val] = FALSE;
			}
			return $retval;
		}

		$segments = array_slice($this->$segment_array(), ($index - 1));

		$i = 0;
		$lastval = '';
		$retval = array();
		foreach ($segments as $seg) {
			if ($i % 2) {
				$retval[$lastval] = $seg;
			}
			else {
				$retval[$seg] = FALSE;
				$lastval = $seg;
			}

			$i++;
		}

		if (count($default) > 0) {
			foreach ($default as $val) {
				if (!array_key_exists($val, $retval)) {
					$retval[$val] = FALSE;
				}
			}
		}

		// Cache the array for reuse
		$this->keyval[$index] = $retval;
		return $retval;
	}

	/**
	 * Fetch a URI Segment and add a trailing slash - helper function
	 *
	 * @access	protected
	 * @param	integer
	 * @param	string
	 * @param	string
	 * @return	string
	 */
	protected function _slash_segment($index, $where = 'trailing', $which = 'segment') {
		$leading	= '/';
		$trailing	= '/';

		if ($where == 'trailing') {
			$leading	= '';
		}
		else if ($where == 'leading') {
			$trailing	= '';
		}

		return $leading.$this->$which($index).$trailing;
	}
}
// END URI Class

/* End of file URI.php */
/* Location: ./system/core/URI.php */
