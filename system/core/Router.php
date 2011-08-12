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

// ------------------------------------------------------------------------

/**
 * Router Class
 *
 * Parses URIs and determines routing
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @author		ExpressionEngine Dev Team
 * @category	Libraries
 * @link		http://codeigniter.com/user_guide/general/routing.html
 */
class CI_Router {

	var $CI;
	var $routes			= array();
	var $error_routes	= array();
	var $class			= '';
	var $method			= 'index';
	var $directory		= '';
	var $default_controller;

	/**
	 * Constructor
	 *
	 * Runs the route mapping function.
	 */
	function __construct()
	{
		$this->CI =& get_instance();
		log_message('debug', 'Router Class Initialized');
	}

	// --------------------------------------------------------------------

	/**
	 * Set the route mapping
	 *
	 * This function determines what should be served based on the URI request,
	 * as well as any "routes" that have been set in the routing config file.
	 *
	 * @access	private
	 * @return	void
	 */
	function _set_routing()
	{
		// Load the routes.php file.
		if (defined('ENVIRONMENT') AND is_file(APPPATH.'config/'.ENVIRONMENT.'/routes.php'))
		{
			include(APPPATH.'config/'.ENVIRONMENT.'/routes.php');
		}
		elseif (is_file(APPPATH.'config/routes.php'))
		{
			include(APPPATH.'config/routes.php');
		}

		// Set routes
		$this->routes = ( ! isset($route) OR ! is_array($route)) ? array() : $route;
		unset($route);

		// Set the default controller so we can display it in the event
		// the URI doesn't correlate to a valid controller.
		$this->default_controller = (isset($this->routes['default_controller']) &&
			$this->routes['default_controller'] != '') ? strtolower($this->routes['default_controller']) : FALSE;

		// Are query strings enabled in the config file? Normally CI doesn't utilize query strings
		// since URI segments are more search-engine friendly, but they can optionally be used.
		// If this feature is enabled, we will gather the directory/class/method a little differently
		$uri =& $this->CI->uri;
		$config =& $this->CI->config;
		$ctl_trigger = $config->item('controller_trigger');
		if ($config->item('enable_query_strings') === TRUE && isset($_GET[$ctl_trigger]))
		{
			$segments = array();

			// Add directory segment if provided
			$dir_trigger = $config->item('directory_trigger');
			if (isset($_GET[$dir_trigger]))
			{
				$segments[] = trim($uri->_filter_uri($_GET[$dir_trigger]));
			}

			// Add controller segment - this was qualified above
			$segments[] = trim($uri->_filter_uri($_GET[$ctl_trigger]));

			// Add function segment if provided
			$fun_trigger = $config->item('function_trigger');
			if (isset($_GET[$fun_trigger]))
			{
				$segments[] = trim($uri->_filter_uri($_GET[$fun_trigger]));
			}

			// Validate and set the segments
			return $this->_set_request($segments);
		}

		// Fetch the complete URI string
		$uri->_fetch_uri_string();

		// Do we need to remove the URL suffix?
		$uri->_remove_url_suffix();

		// Compile the segments into an array
		$uri->_explode_segments();

		// Parse any custom routing that may exist
		// The default route will be applied if valid and necessary
		$this->_parse_routes();

		// Re-index the segment array so that it starts with 1 rather than 0
		$uri->_reindex_segments();
	}

	// --------------------------------------------------------------------

	/**
	 * Set the Route
	 *
	 * This function takes an array of URI segments as
	 * input, and sets the current class/method
	 *
	 * @access	private
	 * @param	array
	 * @param	bool
	 * @return	void
	 */
	function _set_request($segments = array())
	{
		// Determine if segments point to a valid route
		$route = $this->route_exists($segments);
		if ($route === FALSE)
		{
			// Invalid request - show a 404
			$page = isset($segments[0]) ? $segments[0] : '';
			show_404($page);
		}

		// Set directory, class, and method
		$this->set_directory($route[0]);
		$this->set_class($route[1]);
		$this->set_method($route[2]);

		// Update our "routed" segment array to contain the segments without the directory.
		// Note: If there is no custom routing, this array will be
		// identical to $this->CI->uri->segments
		array_shift($route);
		$this->CI->uri->rsegments = $route;
	}

	// --------------------------------------------------------------------

	/**
	 * Determines if a route exists
	 *
	 * This functionality is shared between _set_request(), which calls show_404()
	 * on failure, and override_404(), which returns control to show_404() on failure.
	 * On success, a complete array of segments is returned, starting with the directory,
	 * which may be an empty string. This gurantees at least 3 segments.
	 *
	 * @access	public
	 * @param	array	route segments
	 * @return	mixed	FALSE if route doesn't exist, otherwise array of 3+ segments
	 */
	function route_exists($segments)
	{
		// If we don't have any segments, the default will have to do
		if (count($segments) == 0)
		{
			// Get default segments and validate
			$default = $this->_default_segments();
			if (count($default) >= 2 && file_exists($path.'controllers/'.$default[0].'.php') &&
			$this->CI->is_callable($default[0], $default[1]))
			{
				// Prepend empty directory and return
				array_unshift($default, '');
				return $default;
			}

			// Default isn't valid, either
			return FALSE;
		}

		// Search paths for controller
		foreach ($this->CI->load->get_package_paths() as $path)
		{
			// Does the requested controller exist in the base folder?
			if (file_exists($path.'controllers/'.$segments[0].'.php'))
			{
				// Found it - check method availability
				$method = isset($segments[1]) ? $segments[1] : 'index';
				if ($this->CI->is_callable($segments[0], $method))
				{
					// Found it - prepend empty directory
					array_unshift($segments, '');

					// Append method if missing
					if ( ! isset($segments[1]))
					{
						$segments[] = $method;
					}

					return $segments;
				}
			}

			// Is the controller in a sub-folder?
			if (is_dir($path.'controllers/'.$segments[0]))
			{
				// Found a sub-folder - is there a controller name?
				if (isset($segments[1])
				{
					// Yes - get class and method
					$class = $segments[1];
					$method = isset($segments[2]) ? $segments[2] : 'index';
				}
				else
				{
					// Get default controller segments
					$default = $this->_default_segments();
					if (empty($default))
					{
						// No default controller to apply - carry on
						continue;
					}

					// Get class and method
					$class = $default[0];
					$method = $default[1];
				}

				// Validate class and method
				if (file_exists($path.'controllers/'.$segments[0].$class.'.php') &&
				$this->CI->is_callable($class, $method))
				{
					// Found it - return full set of segments
					if ( ! isset($segments[1]))
					{
						$segments[] = $class;
					}
					if ( ! isset($segments[2]))
					{
						$segments[] = $method;
					}
					if (isset($default) && count($default) > 2)
					{
						$segments += $default;
					}
					return $segments;
				}
			}
		}

		// If we got here, no valid route was found
		return FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * Parse Routes
	 *
	 * This function matches any routes that may exist in
	 * the config/routes.php file against the URI to
	 * determine if the class/method need to be remapped.
	 *
	 * @access	private
	 * @return	void
	 */
	function _parse_routes()
	{
		// Turn the segment array into a URI string
		$uri = implode('/', $this->CI->uri->segments);

		// Is there a literal match? If so we're done
		if (isset($this->routes[$uri]))
		{
			return $this->_set_request(explode('/', $this->routes[$uri]));
		}

		// Loop through the route array looking for wild-cards
		foreach ($this->routes as $key => $val)
		{
			// Convert wild-cards to RegEx
			$key = str_replace(':any', '.+', str_replace(':num', '[0-9]+', $key));

			// Does the RegEx match?
			if (preg_match('#^'.$key.'$#', $uri))
			{
				// Do we have a back-reference?
				if (strpos($val, '$') !== FALSE AND strpos($key, '(') !== FALSE)
				{
					$val = preg_replace('#^'.$key.'$#', $val, $uri);
				}

				return $this->_set_request(explode('/', $val));
			}
		}

		// If we got this far it means we didn't encounter a
		// matching route so we'll set the site default route
		$this->_set_request($this->CI->uri->segments);
	}

	// --------------------------------------------------------------------

	/**
	 * Set the class name
	 *
	 * @access	public
	 * @param	string
	 * @return	void
	 */
	function set_class($class)
	{
		$this->class = str_replace(array('/', '.'), '', $class);
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch the current class
	 *
	 * @access	public
	 * @return	string
	 */
	function fetch_class()
	{
		return $this->class;
	}

	// --------------------------------------------------------------------

	/**
	 * Set the method name
	 *
	 * @access	public
	 * @param	string
	 * @return	void
	 */
	function set_method($method)
	{
		$this->method = $method;
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch the current method
	 *
	 * @access	public
	 * @return	string
	 */
	function fetch_method()
	{
		if ($this->method == $this->fetch_class())
		{
			return 'index';
		}

		return $this->method;
	}

	// --------------------------------------------------------------------

	/**
	 * Set the directory name
	 *
	 * @access	public
	 * @param	string
	 * @return	void
	 */
	function set_directory($dir)
	{
		$this->directory = str_replace(array('/', '.'), '', $dir).'/';
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch the sub-directory (if any) that contains the requested controller class
	 *
	 * @access	public
	 * @return	string
	 */
	function fetch_directory()
	{
		return $this->directory;
	}

	// --------------------------------------------------------------------

	/**
	 * Get 404 override
	 *
	 * Identifies the 404 override route, if defined, and validates it.
	 * On success, the 404 override method will get the requested page as its first argument,
	 * followed by any trailing segments of 404_override. So, if "foo/bar" triggered
	 * a 404, and 404_override was "my404/method/one/two", the effect would be to call:
	 *	my404->method("foo/bar", "one", "two");
	 *
	 * @access	public
	 * @param	string	requested page
	 * @return	boolean	TRUE on success, otherwise FALSE
	 */
	function override_404($page)
	{
		// See if 404_override is defined
		if (empty($this->routes['404_override']))
		{
			// No override to apply
			return FALSE;
		}

		// Validate override path
		$segments = $this->route_exists(explode('/', $this->routes['404_override']));
		if ($segments === FALSE)
		{
			// Override not found
			return FALSE;
		}

		// Pull off 404 directory, class, and method, and prepend requested page
		$subdir = array_shift($segments);
		$class = array_shift($segments);
		$method = array_shift($segments);
		array_unshift($segments, $page);

		// Load the 404 Controller, call the method, and display the output
		$this->CI->load->controller($subdir.$class);
		call_user_func_array(array(&$CI->$class, $method), $segments);
		$CI->output->_display();

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Set the controller overrides
	 *
	 * @access	public
	 * @param	array
	 * @return	null
	 */
	function _set_overrides($routing)
	{
		if ( ! is_array($routing))
		{
			return;
		}

		if (isset($routing['directory']))
		{
			$this->set_directory($routing['directory']);
		}

		if (isset($routing['controller']) AND $routing['controller'] != '')
		{
			$this->set_class($routing['controller']);
		}

		if (isset($routing['function']))
		{
			$routing['function'] = ($routing['function'] == '') ? 'index' : $routing['function'];
			$this->set_method($routing['function']);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Get segments of default controller
	 *
	 * @access	private
	 * @return	array	array of segments
	 */
	function _default_segments()
	{
		// Check for default controller
		if ($this->default_controller === FALSE)
		{
			// Return empty array
			return array();
		}

		// Break out default controller
		$default = explode('/', $this->default_controller);
		if ( ! isset($default[1]))
		{
			// Add default method
			$default[] = 'index';
		}

		return $default;
	}

}
// END Router Class

/* End of file Router.php */
/* Location: ./system/core/Router.php */
