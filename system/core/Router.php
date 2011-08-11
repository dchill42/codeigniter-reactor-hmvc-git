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
 * Router Class
 *
 * Parses URIs and determines routing
 * The base class, CI_RouterBase, is defined in CodeIgniter.php and allows
 * access to protected methods between CodeIgniter, Router, and URI.
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @author		ExpressionEngine Dev Team
 * @category	Libraries
 * @link		http://codeigniter.com/user_guide/general/routing.html
 */
class CI_Router extends CI_RouterBase {
	protected $CI;
	protected $routes			= array();
	protected $class			= '';
	protected $method			= 'index';
	protected $directory		= '';
	protected $default_controller;

	/**
	 * Constructor
	 *
	 * Runs the route mapping function.
	 */
	public function __construct($CI) {
		$this->CI =& $CI;
		$CI->log_message('debug', 'Router Class Initialized');
	}

	/**
	 * Set the class name
	 *
	 * @param	string	class
	 * @return	void
	 */
	public function set_class($class) {
		$this->class = str_replace(array('/', '.'), '', $class);
	}

	/**
	 * Fetch the current class
	 *
	 * @return	string	class
	 */
	public function fetch_class() {
		return $this->class;
	}

	/**
	 *  Set the method name
	 *
	 * @param	string	method
	 * @return	void
	 */
	public function set_method($method) {
		$this->method = $method;
	}

	/**
	 *  Fetch the current method
	 *
	 * @return	string	method
	 */
	public function fetch_method() {
		if ($this->method == $this->fetch_class()) {
			return 'index';
		}

		return $this->method;
	}

	/**
	 *  Set the directory name
	 *
	 * @param	string	directory
	 * @return	void
	 */
	public function set_directory($dir) {
		$this->directory = str_replace(array('/', '.'), '', $dir).'/';
	}

	/**
	 *  Fetch the sub-directory (if any) that contains the requested controller class
	 *
	 * @return	string	directory
	 */
	public function fetch_directory() {
		return $this->directory;
	}

	/**
	 * Set the route mapping
	 *
	 * This function determines what should be served based on the URI request,
	 * as well as any "routes" that have been set in the routing config file.
	 * It is called from CodeIgniter
	 *
	 * @access	protected
	 * @return	void
	 */
	protected function _set_routing() {
		// Are query strings enabled in the config file? Normally CI doesn't utilize query strings
		// since URI segments are more search-engine friendly, but they can optionally be used.
		// If this feature is enabled, we will gather the directory/class/method a little differently
		$segments = array();
		$config =& $this->CI->config;
		$uri =& $this->CI->uri;
		if ($config->item('enable_query_strings') === TRUE AND isset($_GET[$config->item('controller_trigger')])) {
			if (isset($_GET[$config->item('directory_trigger')])) {
				$this->set_directory(trim($uri->_filter_uri($_GET[$config->item('directory_trigger')])));
				$segments[] = $this->fetch_directory();
			}

			if (isset($_GET[$config->item('controller_trigger')])) {
				$this->set_class(trim($uri->_filter_uri($_GET[$config->item('controller_trigger')])));
				$segments[] = $this->fetch_class();
			}

			if (isset($_GET[$config->item('function_trigger')])) {
				$this->set_method(trim($uri->_filter_uri($_GET[$config->item('function_trigger')])));
				$segments[] = $this->fetch_method();
			}
		}

		// Load the routes.php file.
		$path = $this->CI->get_env_path('routes.php');
		if (file_exists($path)) {
			include($path);
		}

		$this->routes = ( ! isset($route) OR ! is_array($route)) ? array() : $route;
		unset($route);

		// Set the default controller so we can display it in the event
		// the URI doesn't correlated to a valid controller.
		$this->default_controller = ( ! isset($this->routes['default_controller']) ||
			$this->routes['default_controller'] == '') ? FALSE : strtolower($this->routes['default_controller']);

		// Were there any query string segments? If so, we'll validate them and bail out since we're done.
		if (count($segments) > 0) {
			return $this->_validate_request($segments);
		}

		// Fetch the complete URI string
		$uri->_fetch_uri_string();

		// Is there a URI string? If not, the default controller specified in the "routes" file will be shown.
		if ($uri->uri_string() == '') {
			return $this->_set_default_controller();
		}

		// Do we need to remove the URL suffix?
		$uri->_remove_url_suffix();

		// Compile the segments into an array
		$uri->_explode_segments();

		// Parse any custom routing that may exist
		$this->_parse_routes();

		// Re-index the segment array so that it starts with 1 rather than 0
		$uri->_reindex_segments();
	}

	/**
	 * Set the controller overrides
	 *
	 * This function applies route overrides from index.php when called from CodeIgniter
	 *
	 * @access	protected
	 * @param	array	routing	overrides
	 * @return	null
	 */
	protected function _set_overrides($routing) {
		if ( ! is_array($routing)) {
			return;
		}

		if (isset($routing['directory'])) {
			$this->set_directory($routing['directory']);
		}

		if (isset($routing['controller']) AND $routing['controller'] != '') {
			$this->set_class($routing['controller']);
		}

		if (isset($routing['function'])) {
			$routing['function'] = ($routing['function'] == '') ? 'index' : $routing['function'];
			$this->set_method($routing['function']);
		}
	}

	/**
	 * Get a route override
	 *
	 * @access	protected
	 * @param	string	route name
	 * @return	string	override
	 */
	protected function _routes($route) {
		return (isset($this->routes[$route])) ? $this->routes[$route] : '';
	}

	/**
	 * Set the default controller
	 *
	 * @access	protected
	 * @return	void
	 */
	protected function _set_default_controller() {
		if ($this->default_controller === FALSE) {
			$this->CI->show_error('Unable to determine what should be displayed. '.
				'A default route has not been specified in the routing file.');
		}
		// Is the method being specified?
		if (strpos($this->default_controller, '/') !== FALSE) {
			$x = explode('/', $this->default_controller);

			$this->set_class($x[0]);
			$this->set_method($x[1]);
			$this->_set_request($x);
		}
		else {
			$this->set_class($this->default_controller);
			$this->set_method('index');
			$this->_set_request(array($this->default_controller, 'index'));
		}

		// re-index the routed segments array so it starts with 1 rather than 0
		$this->CI->uri->_reindex_segments();

		$this->CI->log_message('debug', 'No URI present. Default controller set.');
	}

	/**
	 * Set the Route
	 *
	 * This function takes an array of URI segments as
	 * input, and sets the current class/method
	 *
	 * @access	protected
	 * @param	array	segments
	 * @return	void
	 */
	protected function _set_request(array $segments = array()) {
		$segments = $this->_validate_request($segments);

		if (count($segments) == 0) {
			return $this->_set_default_controller();
		}

		$this->set_class($segments[0]);

		if (isset($segments[1])) {
			// A standard method request
			$this->set_method($segments[1]);
		}
		else {
			// This lets the "routed" segment array identify that the default
			// index method is being used.
			$segments[1] = 'index';
		}

		// Update our "routed" segment array to contain the segments.
		// Note: If there is no custom routing, this array will be
		// identical to $this->CI->uri->segments
		$this->CI->uri->_set_rsegments($segments);
	}

	/**
	 * Validates the supplied segments. Attempts to determine the path to
	 * the controller.
	 *
	 * @access	protected
	 * @param	array	segments
	 * @return	array	validated segments
	 */
	protected function _validate_request(array $segments) {
		// Determine if segments point to a valid route
		$route = $this->_route_exists($segments);
		if ($route === FALSE) {
			// Invalid request - show a 404
			// $segments[0] is safe, since _route_exists returns an empty
			// $segments instead of FALSE
			$this->CI->show_404($segments[0]);
		}

		return $route;
	}

	/**
	 * Determines if a route exists
	 *
	 * This functionality is shared between _validate_request(), which calls show_404()
	 * on failure, and override_404(), which returns control to show_404() on failure.
	 *
	 * @access	private
	 * @param	array	route segments
	 * @return	mixed	FALSE if route doesn't exist, otherwise array of segments
	 */
	protected function _route_exists(array $segments) {
		if (count($segments) == 0) {
			return $segments;
		}

		// Search paths for controller
		foreach ($this->CI->get_package_paths() as $path) {
			// Does the requested controller exist in the base folder?
			if (file_exists($path.'controllers/'.$segments[0].'.php')) {
				// Found it - clear directory and return segments
				$this->set_directory('');
				return $segments;
			}

			// Is the controller in a sub-folder?
			if (is_dir($path.'controllers/'.$segments[0])) {
				// Found a sub-folder - is there a controller name?
				if (count($segments) > 1) {
					// Does the requested controller exist in the sub-folder?
					if (file_exists($path.'controllers/'.$segments[0].$segments[1].'.php')) {
						// Found it - set directory and return remaining segments
						$this->set_directory($segments[0]);
						return array_slice($segments, 1);
					}
				}
				else {
					// Try the default controller - does it specify a method?
					if (strpos($this->default_controller, '/') !== FALSE) {
						list($class, $method) = explode('/', $this->default_controller, 2);
					}
					else {
						$class = $this->default_controller;
						$method = 'index';
					}

					// Does the default controller exist in the sub-folder?
					if (file_exists($path.'controllers/'.$segments[0].$class.'.php')) {
						// Yes - set directory, default controller class, and method and return empty segments
						$this->set_directory($segments[0]);
						$this->set_class($class);
						$this->set_method($method);
						return array();
					}
				}
			}
		}
		}

		// If we got here, no valid route was found
		return FALSE;
	}

	/**
	 * Parse Routes
	 *
	 * This function matches any routes that may exist in
	 * the config/routes.php file against the URI to
	 * determine if the class/method need to be remapped.
	 *
	 * @access	protected
	 * @return	void
	 */
	protected function _parse_routes() {
		// Turn the segment array into a URI string
		$uri = implode('/', $this->CI->uri->segment_array());

		// Is there a literal match? If so we're done
		if (isset($this->routes[$uri])) {
			return $this->_set_request(explode('/', $this->routes[$uri]));
		}

		// Loop through the route array looking for wild-cards
		foreach ($this->routes as $key => $val) {
			// Convert wild-cards to RegEx
			$key = str_replace(':any', '.+', str_replace(':num', '[0-9]+', $key));

			// Does the RegEx match?
			if (preg_match('#^'.$key.'$#', $uri)) {
				// Do we have a back-reference?
				if (strpos($val, '$') !== FALSE AND strpos($key, '(') !== FALSE) {
					$val = preg_replace('#^'.$key.'$#', $val, $uri);
				}

				return $this->_set_request(explode('/', $val));
			}
		}

		// If we got this far it means we didn't encounter a
		// matching route so we'll set the site default route
		$this->_set_request($this->CI->uri->segment_array());
	}
	/**
	 * Get 404 override
	 *
	 * Identifies the 404 override route, if defined, and validates it.
	 * On success, the explicit class, method, and any remaining URL segments
	 * are returned as an array.
	 *
	 * @access	public
	 * @return	mixed	array of segments on success, otherwise FALSE
	 */
	function override_404() {
		// See if 404_override is defined
		if (empty($this->routes['404_override'])) {
			// No override to apply
			return FALSE;
		}

		// Validate override path
		$segments = $this->_route_exists(explode('/', $this->routes['404_override']));
		if (empty($segments)) {
			// Override not found
			return FALSE;
		}

		// _route_exists set the directory accordingly - get the class and method
		$subdir = $this->directory;
		$class = $segments[0];
		if (isset($segments[1])) {
			// Method provided
			$method = $segments[1];
		}
		else {
			// Use default method and add to segments
			$method = 'index';
			$segments[] = $method;
		}

		// Load the 404 Controller and check the method
		$this->CI->load->controller($subdir.$class);
		if (in_array(strtolower($method), array_map('strtolower', get_class_methods($this->CI->$class)))) {
			// Success!
			return $segments;
		}

		// No dice
		return FALSE;
	}
}
// END Router Class

/* End of file Router.php */
/* Location: ./system/core/Router.php */
