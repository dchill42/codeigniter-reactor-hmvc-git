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

// Define the CodeIgniter Version and Branch (Core = TRUE, Reactor = FALSE)
define('CI_VERSION', '2.0.2');
define('CI_CORE', FALSE);

/**
 * CodeIgniter Loader Base Class
 *
 * This class declares the protected loader methods of the application root, below.
 * By deriving both the CodeIgniter class and the Loader class from this common
 * parent, both can access these protected methods. This allows the core loading
 * mechanisms to live in CodeIgniter, where these methods are overloaded with their
 * actual functionality, but forces users to load resources through the Loader API.
 * The reverse works for _autoload(), which is defined in Loader and called from
 * CodeIgniter.
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		Darren Hill <dchill42@gmail.com>, St. Petersburg College
 */
class CI_LoaderBase {
	protected function _load_library($class, $params, $object_name) { }
	protected function _load_helper($file) { }
	protected function _load_user_class($class, $model, $subdir, $name) { }
	protected function _load_file($_ci_path, $_ci_view, $_ci_return, array $_ci_vars) { }
	protected function _autoloader($autoload) { }
}

/**
 * CodeIgniter Router Base Class
 *
 * Just like CI_LoaderBase above, this class allows sharing of protected members
 * and methods between CodeIgniter, URI, and Router.
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		Darren Hill <dchill42@gmail.com>, St. Petersburg College
 */
class CI_RouterBase {
	protected function _set_routing() { }
	protected function _fetch_uri_string() { }
	protected function _filter_uri($str) { }
	protected function _set_rsegments(array $rsegments) { }
	protected function _reindex_segments() { }
}

/**
 * CodeIgniter Application Root Class
 *
 * This class object is the super class that every library in
 * CodeIgniter will be assigned to.
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		ExpressionEngine Dev Team
 * @link		http://codeigniter.com/user_guide/general/controllers.html
 */
class CodeIgniter extends CI_LoaderBase, CI_RouterBase {
	private static $_ci_instance		= NULL;
	protected static $_ci_config_paths	= array(APPPATH);
	protected $_ci_app_paths			= array(APPPATH => TRUE);
	protected $_ci_base_paths			= array(APPPATH, BASEPATH);
	protected $_ci_subclass_prefix		= '';
	protected $_ci_classes				= array();
	protected $_ci_ob_level				= 0;

	/**
	 * Constructor
	 *
	 * This constructor is protected so CodeIgniter can't be instantiated directly.
	 * Instead, the static CodeIgniter::instance() method must be called,
	 * which enforces the singleton behavior of the object.
	 */
	protected function __construct() {
		// Define a custom error handler so we can log PHP errors
		set_error_handler(array($this, '_exception_handler'));

		if ( ! $this->is_php('5.3')) {
			@set_magic_quotes_runtime(0); // Kill magic quotes
		}

		// Set a liberal script execution time limit
		if (function_exists('set_time_limit') == TRUE AND @ini_get('safe_mode') == 0) {
			@set_time_limit(300);
		}

		// Load the framework constants
		self::get_config('constants.php');

		// Set initial output buffering level
		$this->_ci_ob_level = ob_get_level();
	}

	/**
	 * Destructor
	 */
	public function __destruct() {
		// Close the DB connection if one exists
		if (isset($this->db)) {
			$this->db->close();
		}

		// Check for failed load of routed Controller
		if (isset($this->routed) && is_string($this->routed)) {
			$this->show_error('Unable to load your default controller: '.$this->routed.
				'. Please make sure the controller specified in your Routes.php file is valid.');
		}
	}

	/**
	 * Get instance
	 *
	 * Returns singleton instance of CodeIgniter object
	 * THERE CAN BE ONLY ONE!! (Mu-ha-ha-ha-haaaaa!!)
	 *
	 * @param	array	$assign_to_config	from index.php
	 * @return	object
	 */
	public static function &instance($assign_to_config = NULL) {
		// Check for existing instance
		if (is_null(self::$_ci_instance)) {
			// Get config file contents and check for errors
			$config = self::get_config('config.php', 'config');
			if ($config === FALSE) {
				exit('The configuration file does not exist.');
			}
			else if (is_string($config)) {
				exit('Your config file '.$config.' does not appear to be formatted correctly.');
			}

			// Apply any overrides
			if (is_array($assign_to_config)) {
				foreach ($assign_to_config as $key => $val) {
					$config[$key] = $val;
				}
			}

			// Get autoload file if present
			$autoload = self::get_config('autoload.php', 'autoload');
			if (!is_array($autoload)) {
				$autoload = array();
			}

			// Check for subclass prefix
			$class = 'CodeIgniter';
			$pre = isset($config['subclass_prefix']) ? $config['subclass_prefix'] : '';
			if (!empty($pre)) {
				// Search for a subclass
				$paths = array(APPPATH);

				// Get any autoloaded package paths
				if (isset($autoload['packages'])) {
					foreach ($autoload['packages'] as $package) {
						$paths = array_unshift($paths, $package);
					}
				}

				// Check each path for file
				$file = 'core/'.$pre.$class.'.php';
				foreach ($paths as $path) {
					$file_path = $path.$file;
					if (file_exists($file_path)) {
						// Include the source and set the subclass name
						include($file_path);
						$class = $pre.$class;
						break;
					}
				}
			}

			// Instantiate object and assign to static instance
			self::$_ci_instance = new $class();
			self::$_ci_instance->init($config, $autoload);
		}

		return self::$_ci_instance;
	}

	/**
	 * Determine if a class method can actually be called (from outside the class)
	 *
	 * @param	mixed	class name or object
	 * @param	string	method
	 * @return	boolean	TRUE if publicly callable, otherwise FALSE
	 */
	public function is_callable($class, $method)
	{
		// Just return whether the case-insensitive method is in the public methods
		return in_array(strtolower($method), array_map('strtolower', get_class_methods($class)));
	}

	/**
	 * Call a controller method
	 *
	 * Requires that controller already be loaded, validates method name, and calls
	 * _remap if available.
	 *
	 * @param	string	class name
	 * @param	string	method
	 * @param	array	arguments
	 * @param	string	optional object name
	 * @return	boolean	TRUE on success, otherwise FALSE
	 */
	public function call_controller($class, $method, array $args = array(), $name = '')
	{
		// Default name if not provided
		if (empty($name))
		{
			$name = strtolower($class);
		}

		// Class must be loaded, and method cannot start with underscore, nor be a member of the base class
		if (isset($this->$name) && strncmp($method, '_', 1) != 0 &&
		in_array(strtolower($method), array_map('strtolower', get_class_methods('CI_Controller'))) == FALSE)
		{
			// Check for _remap
			if ($this->is_callable($class, '_remap'))
			{
				// Call _remap
				call_user_func_array(array(&$this->$name, '_remap'), array($method, $args));
				return TRUE;
			}
			else if ($this->is_callable($class, $method))
			{
				// Call method
				call_user_func_array(array(&$this->$name, $method), $args);
				return TRUE;
			}
		}

		// Neither _remap nor method could be called
		return FALSE;
	}

	/**
	 * Initialize configuration
	 *
	 * This function finishes bootstrapping the CodeIgniter object by loading
	 * the Config object and installing the primary configuration.
	 *
	 * @param	array	config array
	 * @param	array	autoload array
	 * @return	void
	 */
	protected function _init($config, $autoload) {
		// Establish configured subclass prefix
		if (isset($config['subclass_prefix'])) {
			$this->_ci_subclass_prefix = $config['subclass_prefix'];
		}

		// Autoload package paths so they can be searched
		if (isset($autoload['packages'])) {
			foreach ($autoload['packages'] as $package_path) {
				$this->add_package_path($package_path);
			}
		}

		// Instantiate the config class and initialize
		$this->_load_core_class('Config');
		$this->config->_init($config);

		// Load any custom config files
		if (isset($autoload['config'])) {
			foreach ($autoload['config'] as $val) {
				$this->config->load($val);
			}
		}

		// Save remaining items for second phase
		$this->_ci_autoload =& $autoload;
	}

	/**
	 * Get config file contents
	 *
	 * This function searches the package paths for the named config file.
	 * If $name is defined, it requires the file to contain an array by that name.
	 * Otherwise, it just includes each matching file found.
	 *
	 * @param	string	file name
	 * @param	string	array name
	 * @return	mixed	config array on success (or TRUE if no name), file path string on invalid contents,
	 *					or FALSE if no matching file found
	 */
	public static function get_config($file, $name = NULL) {
		// Ensure file starts with a slash and ends with .php
		$file = '/'.ltrim($file, "/\\");
		if (!preg_match('/\.php$/', $file)) {
			$file .= '.php';
		}

		// Set relative file paths to search
		$files = array();
		if (defined('ENVIRONMENT')) {
			// Check ENVIRONMENT for file
			$files[] = 'config/'.ENVIRONMENT.$file;
		}
		$files[] = 'config'.$file;

		// Merge arrays from all viable config paths
		$merged = array();
		foreach (self::_ci_config_paths as $path) {
			// Check each variation
			foreach ($files as $file) {
				$file_path = $path.$file;
				if (file_exists($file_path)) {
					// Include file
					include($file_path);

					// See if we have an array to check for
					if (empty($name)) {
						// Nope - just note we found something
						$merged = TRUE;
						continue;
					}

					// Check for named array
					if ( ! is_array($$name)) {
						// Invalid - return bad filename
						return $file_path;
					}

					// Merge config and unset local
					$merged = array_merge($merged, $$name);
					unset($$name);
				}
			}
		}

		// Test for merged config
		if (empty($merged)) {
			// None - quit
			return FALSE;
		}

		// Return merged config
		return $merged;
	}

	/**
	 * Run the CodeIgniter application
	 *
	 * @param	array	$routing	from index.php
	 * @return	void
	 */
	public function run($routing) {
		// Load benchmark if enabled
		if ($this->config->item('enable_benchmarks')) {
			$this->_load_core_class('Benchmark');

			// Start the timer... tick tock tick tock...
			$this->benchmark->mark('total_execution_time_start');
			$this->benchmark->mark('loading_time:_base_classes_start');
		}

		// Load hooks if enabled
		if ($this->config->item('enable_hooks')) {
			$this->_load_core_class('Hooks');

			// Call pre_system hook
			$this->hooks->_call_hook('pre_system');
		}

		// Load URI and Router, which depends on URI
		$this->_load_core_class('URI');
		$this->_load_core_class('Router');

		// Set routing with any overrides from index.php
		$this->router->_set_routing($routing);

		// Load Output
		$this->_load_core_class('Output');

		// Is there a valid cache file? If so, we're done...
		if ((isset($this->hooks) && $this->hooks->_call_hook('cache_override') === TRUE) ||
		$this->output->_display_cache() == FALSE) {
			// Load remaining core classes
			$this->_load_core_class('Security');
			$this->_load_core_class('Utf8');
			$this->_load_core_class('Input');			// Input depends on Security and UTF-8
			$this->_load_core_class('Lang');
			$this->_load_core_class('Loader', 'load');	// Loader depends on Lang

			if (isset($this->benchmark)) {
				$this->benchmark->mark('loading_time:_base_classes_end');
			}

			$this->run_controller();
		}
	}

	/**
	 * Load and run the routed Controller
	 *
	 * @return	void
	 */
	protected function run_controller() {
		// Autoload any remaining resources
		if (isset($this->_ci_autoload)) {
			$this->load->_autoloader($this->_ci_autoload);
			unset($this->_ci_autoload);
		}

		// Call the "pre_controller" hook
		if (isset($this->hooks)) {
			$this->hooks->_call_hook('pre_controller');
		}

		// Mark a start point so we can benchmark the controller
		if (isset($this->benchmark)) {
			$this->benchmark->mark('controller_execution_time_( '.$class.' / '.$method.' )_start');
		}

		// Get the parsed route and identify class, method, and arguments
		$route = $this->router->fetch_route();
		$args = array_slice(CI_Router::SEG_CLASS);
		$class = array_unshift($args);
		$method = array_unshift($args);

		// Load the controller, but don't call the method yet
		if ($this->load->controller($route, '', FALSE) == FALSE) {
			$this->show_404($class.'/'.$method);
		}

		// Set special "routed" reference to routed Controller
		$this->routed =& $this->$class;

		// Call the "post_controller_constructor" hook
		if (isset($this->hooks)) {
			$this->hooks->_call_hook('post_controller_constructor');
		}

		// Call the requested method
		if ($CI->call_controller($class, $method, $args) == FALSE) {
			// Both _remap and $method failed - go to 404
			$this->show_404($class.'/'.$method);
		}

		// Mark a benchmark end point
		if (isset($this->benchmark)) {
			$this->benchmark->mark('controller_execution_time_( '.$class.' / '.$method.' )_end');
		}

		// Display output
		if (isset($this->hooks)) {
			// Call the "post_controller" hook
			$this->hooks->_call_hook('post_controller');

			// Send the final rendered output to the browser
			if ($this->hooks->_call_hook('display_override') === FALSE) {
				$this->output->_display();
			}

			// Call the "post_system" hook
			$this->hooks->_call_hook('post_system');
		}
		else {
			// Just call display
			$this->output->_display();
		}
	}

	/**
	 * Is Loaded
	 *
	 * A utility function to test if a class is in the self::$_ci_classes array.
	 * This function returns the object name if the class tested for is loaded,
	 * and returns FALSE if it isn't.
	 *
	 * @param	string	class being checked for
	 * @param	string	optional object name to check
	 * @return	mixed	class object name or FALSE
	 */
	public function is_loaded($class, $obj_name = '') {
		// Lowercase class
		$class = strtolower($class);

		// See if class is loaded
		if (isset($this->_ci_classes[$class])) {
			// Yes - are we checking a specific object name?
			if ($obj_name != '') {
				// Return whether that name was used
				return in_array($obj_name, $this->_ci_classes[$class]);
			}

			// Return the first object name loaded
			return reset($this->_ci_classes[$class]);
		}
				
		// Never loaded
		return FALSE;
	}

	/**
	 * Add Package Path
	 *
	 * Prepends a parent path to the base, app, and config path arrays
	 *
	 * @param	string	path
	 * @param	boolean	view	cascade flag
	 * @return	void
	 */
	public function add_package_path($path, $view_cascade = TRUE) {
		// Resolve path
		$path = $this->_resolve_path($path);

		// Prepend config and library/helper file paths
		array_unshift(self::_ci_config_paths, $path);
		array_unshift($this->_ci_base_paths, $path);

		// Prepend MVC path with view cascade param
		$this->_ci_app_paths = array($path => $view_cascade) + $this->_ci_app_paths;
	}

	/**
	 * Remove Package Path
	 *
	 * Remove a path from the base, app, and config path arrays if it exists
	 * If no path is provided, the most recently added path is removed.
	 *
	 * @param	string	path
	 * @param	boolean	remove	from config path flag
	 * @return	void
	 */
	public function remove_package_path($path = '', $remove_config_path = TRUE) {
		if ($path == '') {
			// Shift last added path from each list
			if ($remove_config_path) {
				array_shift(self::_ci_config_paths);
			}
			array_shift($this->_ci_base_paths);
			array_shift($this->_ci_app_paths);
			return;
		}

		// Resolve path
		$path = $this->_resolve_path($path);

		// Prevent app path removal - it is a default for all lists
		if ($path == APPPATH) {
			return;
		}

		// Unset path from config list
		if ($remove_config_path && ($key = array_search($path, self::_ci_config_paths)) !== FALSE) {
			unset(self::_ci_config_paths[$key]);
		}

		// Unset from library/helper list unless base path
		if ($path != BASEPATH && ($key = array_search($path, $this->_ci_base_paths)) !== FALSE) {
			unset($this->_ci_base_paths[$key]);
		}

		// Unset path from MVC list
		if (isset($this->_ci_app_paths[$path])) {
			unset($this->_ci_app_paths[$path]);
		}
	}

	/**
	 * Get Package Paths
	 *
	 * Return a list of all package paths, by default it will ignore BASEPATH.
	 *
	 * @param	boolean	include base path flag
	 * @return	array	package path list
	 */
	public function get_package_paths($include_base = FALSE) {
		// Just return path list - only the loader needs the MVC cascade feature
		return $include_base === TRUE ? $this->_ci_base_paths : array_keys($this->_ci_app_paths);
	}

	/**
	 * Determines if the current version of PHP is greater then the supplied value
	 *
	 * @param	string
	 * @return	bool	TRUE if the current version is $version or higher
	 */
	public function is_php($version = '5.0.0') {
		// Just return whether version is >= value provided
		return (version_compare(PHP_VERSION, $version) >= 0);
	}

	/**
	 * Tests for file writability
	 *
	 * is_writable() returns TRUE on Windows servers when you really can't write to
	 * the file, based on the read-only attribute. is_writable() is also unreliable
	 * on Unix servers if safe_mode is on.
	 *
	 * @param	string	file path
	 * @return	boolean TRUE if writeable, otherwise FALSE
	 */
	public function is_really_writable($file) {
		// If we're on a Unix server with safe_mode off we call is_writable
		if (DIRECTORY_SEPARATOR == '/' && @ini_get('safe_mode') == FALSE) {
			return is_writable($file);
		}

		// For windows servers and safe_mode "on" installations we'll actually
		// write a file then read it. Bah...
		if (is_dir($file)) {
			$file = rtrim($file, '/').'/'.md5(mt_rand(1,100).mt_rand(1,100));

			if (($fp = @fopen($file, FOPEN_WRITE_CREATE)) === FALSE) {
				return FALSE;
			}

			fclose($fp);
			@chmod($file, DIR_WRITE_MODE);
			@unlink($file);
			return TRUE;
		}
		elseif ( ! is_file($file) || ($fp = @fopen($file, FOPEN_WRITE_CREATE)) === FALSE) {
			return FALSE;
		}

		fclose($fp);
		return TRUE;
	}

	/**
	 * Error Handler
	 *
	 * This function lets us invoke the exception class and display errors using
	 * the standard error template located in application/errors/errors.php
	 * This function will send the error page directly to the browser and exit.
	 *
	 * @param	string	error message
	 * @param	int	status code
	 * @param	string	heading
	 * @return	void
	 */
	public function show_error($message, $status_code = 500, $heading = 'An Error Was Encountered') {
		// Ensure Exceptions is loaded
		if (!isset($this->exceptions)) {
			$this->_load_core_class('Exceptions');
		}

		// Call show_error and exit
		echo $this->exceptions->show_error($heading, $message, 'error_general', $status_code);
		exit;
	}

	/**
	 * 404 Page Handler
	 *
	 * This function is similar to the show_error() function above
	 * However, instead of the standard error template it displays 404 errors.
	 *
	 * @param	string	page URL
	 * @param	boolean	FALSE to skip logging
	 * @return	void
	 */
	public function show_404($page = '', $log_error = TRUE) {
		// Ensure Exceptions is loaded
		if (!isset($this->exceptions)) {
			$this->_load_core_class('Exceptions');
		}

		// Call show_404 and exit
		$this->exceptions->show_404($page, $log_error);
		exit;
	}

	/**
	 * Error Logging Interface
	 *
	 * We use this as a simple mechanism to access the logging
	 * class and send messages to be logged.
	 *
	 * @param	string	error level
	 * @param	string	error message
	 * @param	boolean	TRUE if native error
	 * @return	void
	 */
	public function log_message($level = 'error', $message, $php_error = FALSE) {
		// Check log threshold
		if ($this->config->item('log_threshold') == 0) {
			return;
		}

		// Ensure Log is loaded
		if (!isset($this->log)) {
			$this->_load_library('Log');
		}

		// Write log message
		$this->log->write_log($level, $message, $php_error);
	}

	/**
	 * Remove Invisible Characters
	 *
	 * This prevents sandwiching null characters
	 * between ascii characters, like Java\0script.
	 *
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	public function remove_invisible_characters($str, $url_encoded = TRUE) {
		$non_displayables = array();

		// every control character except newline (dec 10)
		// carriage return (dec 13), and horizontal tab (dec 09)
		if ($url_encoded) {
			$non_displayables[] = '/%0[0-8bcef]/';	// url encoded 00-08, 11, 12, 14, 15
			$non_displayables[] = '/%1[0-9a-f]/';	// url encoded 16-31
		}

		$non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';	// 00-08, 11, 12, 14-31, 127

		do {
			$str = preg_replace($non_displayables, '', $str, -1, $count);
		}
		while ($count);

		return $str;
	}

	/**
	 * Exception Handler
	 *
	 * This is the custom exception handler that is declaired in the constructor.
	 * The main reason we use this is to permit PHP errors to be logged in our
	 * own log files since the user may not have access to server logs. Since
	 * this function effectively intercepts PHP errors, however, we also need
	 * to display errors based on the current error_reporting level.
	 * We do that with the use of a PHP error template.
	 *
	 * @access	private
	 * @return	void
	 */
	public function _exception_handler($severity, $message, $filepath, $line) {
		 // We don't bother with "strict" notices since they tend to fill up
		 // the log file with excess information that isn't normally very helpful.
		 // For example, if you are running PHP 5 and you use version 4 style
		 // class functions (without prefixes like "public", "private", etc.)
		 // you'll get notices telling you that these have been deprecated.
		if ($severity == E_STRICT) {
			return;
		}

		// Ensure the exception class is loaded
		if (!isset($this->exceptions)) {
			$this->_load_core_class('Exceptions');
		}

		// Should we display the error? We'll get the current error_reporting
		// level and add its bits with the severity bits to find out.
		if (($severity & error_reporting()) == $severity) {
			$this->exceptions->show_php_error($severity, $message, $filepath, $line);
		}

		// Should we log the error?
		if ($this->config->item('log_threshold') != 0) {
			$this->exceptions->log_exception($severity, $message, $filepath, $line);
		}
	}

	/**
	 * Call magic method
	 *
	 * Calls method of routed controller if not existent in CodeIgniter
	 *
	 * @param	string	method name
	 * @param	array	method arguments
	 * @return	mixed
	 */
	public function __call($name, $arguments) {
		// Check for routed controller and method
		if (isset($this->routed) && method_exists($this->routed, $name)) {
			return call_user_func_array(array($this->routed, $name), $arguments);
		}
	}

	/**
	 * Load library
	 *
	 * This function loads the requested library class on behalf of the Loader
	 *
	 * @access	protected
	 * @param	string	class name
	 * @param	string	class subdirectory
	 * @param	mixed	any	additional parameters
	 * @param	mixed	object name or FALSE to prevent attachment
	 * @return	void
	 */
	protected function _load_library($class, $subdir, $params, $obj_name) {
		// Check for name conflict
		if ($obj_name !== FALSE && isset($this->$obj_name)) {
			$this->show_error('The library name you are loading is the name of a resource that is '.
				'already being used: '.$obj_name);
		}

		// Is this a class extension request?
		// Extensions may exist anywhere in the app paths, building on a core
		// library in the base path. Each path is checked for lowercase and
		// first-uppercase versions of the filename
		$classnm = '';
		$exists = FALSE;
		if (!empty($this->_ci_subclass_prefix)) {
			// Search app paths for extension
			foreach ($this->_ci_app_paths as $path => $cascade) {
				// Check for prefix on capitalized and lowercase class name
				$path .= 'libraries/'.$subdir.$this->_ci_subclass_prefix;
				foreach (array(strtolower($class), ucfirst($class)) as $file) {
					// See if extension file exists
					$file = $path.$file.'.php';
					if (file_exists($file)) {
						// Found extension - require base class from base path
						$basefile = BASEPATH.'libraries/'.ucfirst($class).'.php';
						if (file_exists($basefile)) {
							// Include base class followed by subclass for inheritance
							include_once($basefile);
							include_once($file);

							// If we're not attaching, we're done
							if ($obj_name === FALSE) {
								return;
							}

							// Set class name and break
							$classnm = $this->_ci_subclass_prefix.ucfirst($class);
							if (class_exists($classnm)) {
								$exists = TRUE;
							}
							break 2;
						}
						else {
							$msg = 'Unable to load the requested class: '.$class;
							$this->log_message('error', $msg);
							$this->show_error($msg);
						}
					}
				}
			}
		}

		// Did we find an extension?
		if ($classnm == '') {
			// No - search base paths for the requested library
			// The library may exist anywhere in the base paths, including
			// added package paths. Each path is checked for lowercase and
			// first-uppercase versions of the filename
			foreach ($this->_ci_base_paths as $path) {
				// Check for capitalized and lowercase class name
				$path .= 'libraries/'.$subdir;
				foreach (array(strtolower($class), ucfirst($class)) as $file) {
					// See if file exists
					$file = $path.$file.'.php';
					if (file_exists($file)) {
						// Include file
						include_once($file);

						// If we're not attaching, we're done
						if ($obj_name === FALSE) {
							return;
						}

						// Determine class name and break
						$name = ucfirst($class);
						if (class_exists('CI_'.$name)) {
							$classnm = 'CI_'.$name;
							$exists = TRUE;
						}
						else if (class_exists($this->_ci_subclass_prefix.$name)) {
							$classnm = $this->_ci_subclass_prefix.$name;
							$exists = TRUE;
						}
						else if (class_exists($name)) {
							$classnm = $name;
							$exists = TRUE;
						}
						break 2;
					}
				}
			}
		}

		// See if a class name was found
		if ($classnm == '') {
			// No - one last attempt. Maybe the library is in a subdirectory, but it wasn't specified?
			if ($classnm == '' && $subdir == '') {
				$path = strtolower($class).'/'.$class;
				return $this->_load_library($path, $params);
			}

			// If we got this far we were unable to find the requested class.
			$msg = 'Unable to load the requested class: '.$class;
			$this->log_message('error', $msg);
			$this->show_error($msg);
		}

		// Check if class exists
		if ($exists == FALSE) {
			$msg = 'Non-existent class: '.$classnm;
			$this->log_message('error', $msg);
			$this->show_error($msg);
		}

		// Map object name to class
		$class = strtolower($class);
		$this->_ci_classes[$class][] = $obj_name;

		// Do we need to check for configs?
		if (is_null($params)) {
			// See if there's a config file for the class
			$config = $this->config->get($class, TRUE);
			if ($config === FALSE) {
				// Try uppercase
				$config = $this->config->get(ucfirst($class), TRUE);
			}

			// Set params to config if found
			if ($config !== FALSE) {
				$params = $config;
			}
		}

		// Instantiate the class
		if (is_null($params)) {
			$this->$obj_name = new $classnm();
		}
		else {
			$this->$obj_name = new $classnm($params);
		}
	}

	/**
	 * Load helper file
	 *
	 * This function loads the requested helper file on behalf of the Loader
	 *
	 * @access	protected
	 * @param	string	helper name
	 * @return	void
	 */
	protected function _load_helper($name) {
		// Append helper extension
		$name .= '_helper.php';

		// Is this a helper extension request?
		if (!empty($this->_ci_subclass_prefix)) {
			// Search app paths for extension
			$file = 'helpers/'.$this->_ci_subclass_prefix.$name;
			foreach ($this->_ci_app_paths as $path => $cascade) {
				// Check each path for extension
				$path .= $file;
				if (file_exists($path)) {
					// Found extension - require base class from base path
					$basefile = BASEPATH.'helpers/'.$name;
					if (file_exists($basefile)) {
						// Include extension followed by base, so extension overrides base functions
						include_once($path);
						include_once($basefile);
						$this->log_message('debug', 'Helper loaded: '.$path);
						return;
					}
					else {
						$this->show_error('Unable to load the requested file: helpers/'.$name);
					}
				}
			}
		}

		// Search base paths for the requested helper
		$file = 'helpers/'.$name;
		foreach ($this->_ci_base_paths as $path) {
			// Check each path for helper
			$path .= $file;
			if (file_exists($path)) {
				// Include helper and return
				include_once($path);
				$this->log_message('debug', 'Helper loaded: '.$path);
				return;
			}
		}

		// Unable to load the helper
		$this->show_error('Unable to load the requested file: helpers/'.$name);
	}

	/**
	 * Load user class object
	 *
	 * Loads Model or Controller object on behalf of the Loader
	 *
	 * @access	protected
	 * @param	string	class name
	 * @param	string	package path for controller
	 * @param	string	class subdirectory
	 * @param	string	object name
	 * @return	void
	 */
	protected function _load_user_class($class, $path, $subdir, $obj_name) {
		// Set type
		$type = empty($path) ? 'model' : 'controller';

		// Check for name conflict
		if (isset($this->$obj_name)) {
			$this->show_error('The '.$type.' name you are loading is the name of a resource that is '.
				'already being used: '.$obj_name);
		}

		// Load base class(es) if not already done
		$base = ucfirst($type);
		if (!class_exists('CI_'.$base)) {
			$this->_load_core_class($base, FALSE);
		}

		// See if path was provided
		$class = strtolower($class);
		if (empty($path)) {
			// Search path list for class
			$file = $type.'s/'.$subdir.$class.'.php';
			foreach ($this->_ci_app_paths as $app_path => $view_cascade) {
				// Check each path for filename
				if (file_exists($app_path.$file)) {
					$path = $app_path;
					break;
				}
			}
		}

		// Check for valid path
		if (empty($path)) {
			$this->show_error('Unable to locate the '.$type.' you have specified: '.$class);
		}

		// Include source
		require_once($mvc_path.$file);

		// Instantiate class and attach
		$classnm = ucfirst($class);
		$this->$obj_name = new $classnm();

		// Map object name to class
		$this->_ci_classes[$class][] = $obj_name;
	}

	/**
	 * Load user file
	 *
	 * This function is used to load views and files on behalf of the Loader.
	 * Variables are prefixed with _ci_ to avoid symbol collision with
	 * variables made available to view files.
	 * Files automatically have access to all loaded objects via $this->object.
	 *
	 * @access	protected
	 * @param	string	view name or path to file
	 * @param	boolean	is view
	 * @param	boolean	return output as string
	 * @param	array	local vars to declare
	 * @return	mixed	output if $_ci_return is TRUE, otherwise void
	 */
	protected function _load_file($_ci_path, $_ci_view, $_ci_return, array $_ci_vars = NULL) {
		// Set the path to the requested file
		$exists = FALSE;
		if ($_ci_view) {
			// Path is a view name - search for real path
			$file = (pathinfo($_ci_path, PATHINFO_EXTENSION) == '') ? $_ci_path.'.php' : $_ci_path;
			foreach ($this->_ci_app_paths as $path => $cascade) {
				$path .= 'views/'.$file;
				if (file_exists($path)) {
					$_ci_path = $path;
					$exists = TRUE;
					break;
				}

				if ( ! $cascade) {
					break;
				}
			}
			unset($path);
			unset($cascade);
		}
		else {
			// Path points to file - break out filename and check existence
			$file = end(explode('/', $_ci_path));
			$exists = file_exists($_ci_path);
		}
		unset($_ci_view);

		if ( ! $exists) {
			$this->show_error('Unable to load the requested file: '.$file);
		}
		unset($exists);
		unset($file);

		// Extract local variables
		if (!empty($_ci_vars)) {
			extract($_ci_vars);
		}
		unset($_ci_vars);

		/*
		 * Buffer the output
		 *
		 * We buffer the output for two reasons:
		 * 1. Speed. You get a significant speed boost.
		 * 2. So that the final rendered template can be post-processed by the
		 *	output class. Why do we need post processing? For one thing, in
		 *	order to show the elapsed page load time. Unless we can intercept
		 *	the content right before it's sent to the browser and then stop
		 *	the timer it won't be accurate.
		 */
		ob_start();

		// If the PHP installation does not support short tags we'll
		// do a little string replacement, changing the short tags
		// to standard PHP echo statements.
		if ((bool) @ini_get('short_open_tag') === FALSE && $this->config->item('rewrite_short_tags') == TRUE) {
			echo eval('?>'.preg_replace('/;*\s*\?>/', '; ?>', str_replace('<?=', '<?php echo ',
				file_get_contents($_ci_path))));
		}
		else {
			include($_ci_path); // include() vs include_once() allows for multiple views with the same name
		}

		$this->log_message('debug', 'File loaded: '.$_ci_path);

		// Return the file data if requested
		if ($_ci_return === TRUE) {
			$buffer = ob_get_contents();
			@ob_end_clean();
			return $buffer;
		}

		/*
		 * Flush the buffer... or buff the flusher?
		 *
		 * In order to permit views to be nested within other views, we need to
		 * flush the content back out whenever we are beyond the first level of
		 * output buffering so that it can be seen and included properly by the
		 * first included template and any subsequent ones. Oy!
		 */
		if (ob_get_level() > $this->_ci_ob_level + 1) {
			ob_end_flush();
		}
		else {
			$this->output->append_output(ob_get_contents());
			@ob_end_clean();
		}
	}

	/**
	 * Core class loader
	 *
	 * If the requested class does not exist it is instantiated and attached
	 *
	 * @access	private
	 * @param	string	the	class name being requested
	 * @param	mixed	the object name or FALSE to prevent attachment
	 * @return	void
	 */
	private function _load_core_class($class, $obj_name = '') {
		// Set name default
		if ($obj_name == '') {
			$obj_name = strtolower($class);
		}

		// Is the object already loaded? If so, we're done...
		if ($obj_name !== FALSE && isset($this->$obj_name)) {
			return;
		}

		$name = FALSE;

		// See if the class already exists
		$basename = 'CI_'.$class;
		if (class_exists($basename)) {
			// Class exists - set base to be attached
			$name = $basename;
		}
		else {
			// Look for the class first in the native system/libraries folder
			// then in the local application/libraries folder
			$file = 'core/'.$class.'.php';
			foreach (array(BASEPATH, APPPATH) as $path) {
				// See if file exists
				if (file_exists($path.$file)) {
					// Include file and set base to be attached
					include($path.$file);
					$name = 'CI_'.$class;
					break;
				}
			}
		}

		// Is there a class extension to be loaded?
		if (!empty($this->_ci_subclass_prefix)) {
			// See if class is already loaded
			$extname = $this->_ci_subclass_prefix.$class;
			if (class_exists($extname)) {
				// Yes - set extension to be attached
				$name = $extname;
			}
			else {
				// Check app paths for extension
				$file = 'core/'.$this->_ci_subclass_prefix.$class.'.php';
				foreach ($this->_ci_app_paths as $path => $cascade) {
					if (file_exists($path.$file)) {
						// Found it - include file and set extension to be attached
						include($file);
						$name = $extname;
						break;
					}
				}
			}
		}

		// Did we find the class?
		if ($name === FALSE) {
			// Note: We use exit() rather then show_error() in order to avoid a
			// self-referencing loop with the Excptions class
			exit('Unable to locate the specified class: '.$class.'.php');
		}

		// Instantiate class object
		if ($obj_name !== FALSE) {
			$this->$obj_name = new $name($this);
		}
	}

	/**
	 * Resolves package path
	 *
	 * This function is used to identify absolute paths in the filesystem and include path
	 *
	 * @access	private
	 * @param	string	initial path
	 * @return	string	resolved path
	 */
	private function _resolve_path($path) {
		// Assert trailing slash
		$path = rtrim($path, "/\\").'/';

		// See if path exists as-is
		if (file_exists($path)) {
			return $path;
		}

		// Strip any leading slash and pair with include directories
		$dir = ltrim($path, "/\\");
		foreach (explode(PATH_SEPARATOR, get_include_path()) as $include) {
			$include = rtrim($include, "/\\");
			if (file_exists($include.'/'.$dir)) {
				// Found include path - clean up and return
				return $include.'/'.$dir;
			}
		}

		// If we got here, it's not a real path - just return as-is
		return $path;
	}
}
// END Root class

/**
 * Get instance
 *
 * Global function to return singleton instance of root object
 *
 * @return	object
 */
function &get_instance() {
	// Call static instance
	return CodeIgniter::instance();
}

/**
 * Show error
 *
 * Global function to call root method
 *
 * @param	string	error message
 * @param	int	status code
 * @param	string	heading
 * @return	void
 */
function show_error($message, $status_code = 500, $heading = 'An Error Was Encountered') {
	// Get instance and call show_error
	$CI =& get_instance();
	$CI->show_error($message, $status_code, $heading);
}

/**
 * Log message
 *
 * Global function to call root method
 *
 * @param	string	error level
 * @param	string	error message
 * @param	boolean	TRUE if native error
 * @return	void
 */
function log_message($level, $message, $php_error = FALSE) {
	// Get instance and call log_message
	$CI =& get_instance();
	$CI->log_message($level, $message, $php_error);
}

/**
 * System Initialization
 *
 * Loads the base classes and executes the request.
 *
 * @package		CodeIgniter
 * @subpackage	codeigniter
 * @category	Front-controller
 * @author		ExpressionEngine Dev Team
 * @link		http://codeigniter.com/user_guide/
 */

// Load and run the application
$CI =& CodeIgniter::instance($assign_to_config);
$CI->run($routing);

/* End of file CodeIgniter.php */
/* Location: ./system/core/CodeIgniter.php */
