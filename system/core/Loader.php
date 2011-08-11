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
 * Loader Class
 *
 * Loads resources (libraries, controllers, views, etc.) into CodeIgniter.
 * The base class, CI_LoaderBase, is defined in CodeIgniter.php and allows
 * Loader access to protected loading methods in CodeIgniter.
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @author		ExpressionEngine Dev Team
 * @category	Loader
 * @link		http://codeigniter.com/user_guide/libraries/loader.html
 */
class CI_Loader extends CI_LoaderBase {
	// All these are set automatically. Don't mess with them.
    protected $CI;
	protected $_ci_libraries		= array();
	protected $_ci_helpers			= array();
	protected $_ci_controllers		= array();
	protected $_ci_models			= array();
	protected $_ci_cached_vars		= array();
	protected $_ci_varmap;

	/**
	 * Constructor
	 *
	 * Sets default package paths, gets the initial output buffering level,
	 * and autoloads additional paths and config files
	 */
	public function __construct($CI) {
        // Attach parent reference
        $this->CI =& $CI;

		// Load name mapping
		$this->_ci_varmap = array('unit_test' => 'unit', 'user_agent' => 'agent');

		$CI->log_message('debug', 'Loader Class Initialized');
	}

	/**
	 * Library loader
	 *
	 * This function lets users load and instantiate library classes.
	 *
	 * @param	mixed	the name of the class or an array of names
	 * @param	array	optional parameters
	 * @param	string	an optional object name
	 * @return	void
	 */
	public function library($class, array $params = NULL, $obj_name = NULL) {
		// Check for missing class
		if (empty($class)) {
			return FALSE;
		}

		// Delegate multiples
		if (is_array($class)) {
			foreach ($class as $class) {
				$this->library($class, $params);
			}

			return;
		}

		// Get the class name, and trim any leading slashes.
		// The directory path may be part of the class name with non-leading slashes.
		$class = str_replace('.php', '', trim($class, '/'));

		// Parse out the filename and path.
		$subdir = $this->_get_path($class);

		// Set object name if not provided
		if (is_null($obj_name)) {
			$obj_name = isset($this->_ci_varmap[$class]) ? $this->_ci_varmap[$class] : strtolower($class);
		}

		// Check if already loaded
		if (in_array($obj_name, $this->_ci_libraries, TRUE)) {
			return;
		}

        // Load class in root
		$this->CI->_load_library($class, $params, $obj_name);

		// If the call didn't blow up, it must have loaded
		$this->_ci_libraries[] = $obj_name;
	}

	/**
	 * Driver loader
	 *
	 * Loads a driver library
	 *
	 * @param	string	the name of the class
	 * @param	array	the optional parameters
	 * @param	string	an optional object name
	 * @return	void
	 */
	public function driver($class, array $params = NULL, $obj_name = NULL) {
		if ( ! class_exists('CI_Driver_Library')) {
			// we aren't instantiating an object here, that'll be done by the Library itself
			require BASEPATH.'libraries/Driver.php';
		}

		// We can save the loader some time since Drivers will *always* be in a subfolder,
		// and typically identically named to the library
		if ( ! strpos($class, '/')) {
			$class = ucfirst($class).'/'.$class;
		}

		return $this->library($class, $params, $obj_name);
	}

	/**
	 * Helper loader
	 *
	 * This function loads the specified helper file.
	 *
	 * @param	mixed	the name of the helper or an array of names
	 * @return	void
	 */
	public function helper($helpers) {
		// Check for missing name
		if (empty($helpers)) {
			return FALSE;
		}

		// Delegate multiples
		if (is_array($helpers)) {
			foreach ($helpers as $helper) {
				$this->helper($helper);
			}
			return;
		}

		// Prep filename
		$helper = str_replace(array('.php', '_helper'), '', strtolower($helpers)).'_helper';

		// Check if already loaded
		if (isset($this->_ci_helpers[$helper])) {
			return;
		}

        // Load helper in root
		$this->CI->_load_helper($helper);

		// If the call didn't blow up, it must have loaded
		$this->_ci_helpers[] = $helper;
	}

	/**
	 * Load Helpers
	 *
	 * This is simply an alias to the above function in case the
	 * user has written the plural form of this function.
	 *
	 * @param	array
	 * @return	void
	 */
	public function helpers($helpers = array()) {
		$this->helper($helpers);
	}

	/**
	 * Controller Loader
	 *
	 * This function lets users load and instantiate (sub)controllers.
	 *
	 * @access	public
	 * @param	string	the name of the class
	 * @param	string	an optional object name
	 * @return	void
	 */
	public function controller($class, $obj_name = '') {
		// Check for missing class
		if (empty($class)) {
			return;
		}

		// Delegate multiples
		if (is_array($class)) {
			foreach($class as $item) {
				$this->controller($item);
			}
			return;
		}

		// Parse out the filename and path.
		$subdir = $this->_get_path($class);

		// Set object name if not provided
		if ($obj_name == '') {
			$obj_name = strtolower($class);
		}

		// Check if already loaded
		if (in_array($obj_name, $this->_ci_controllers, TRUE)) {
			return;
		}

        // Load class in root
        $this->CI->_load_user_class($class, FALSE, $subdir, $obj_name);

		// If the call didn't blow up, it must have loaded
		$this->_ci_controllers[] = $obj_name;
	}

	/**
	 * Model Loader
	 *
	 * This function lets users load and instantiate models.
	 *
	 * @param	string	the name of the class
	 * @param	string	an optional object name
	 * @param	mixed	database connection name or TRUE to load default
	 * @return	void
	 */
	public function model($class, $obj_name = '', $db_conn = FALSE) {
		// Delegate multiples
		if (is_array($class)) {
			foreach ($class as $babe) {
				$this->model($babe);
			}
			return;
		}

		// Check for missing class
		if ($class == '') {
			return;
		}

		$subdir = '';

		// Parse out the filename and path.
		$subdir = $this->_get_path($class);

		// Set name if not provided
		if ($obj_name == '') {
			$obj_name = $class;
		}

		// Check if already loaded
		if (in_array($obj_name, $this->_ci_models, TRUE)) {
			return;
		}

		// Load database if needed
		if ($db_conn !== FALSE AND ! class_exists('CI_DB')) {
			if ($db_conn === TRUE) {
				$db_conn = '';
			}

			$this->database($db_conn, FALSE, TRUE);
		}

        // Load class in root
        $this->CI->_load_user_class($class, TRUE, $subdir, $obj_name);

		// If the call didn't blow up, it must have loaded
		$this->_ci_models[] = $obj_name;
	}

	/**
	 * Load View
	 *
	 * This function is used to load a "view" file.
     * You can either set variables using the dedicated vars() function or
     * via the second parameter of this function. We'll merge the two types and
     * cache them so that views that are embedded within other views can have
     * access to these variables.
	 *
	 * @param	string  view name
	 * @param	array   associative array of local variables for the view
	 * @param	bool    TRUE to return the output
	 * @return	mixed   output if $return is TRUE, otherwise void
	 */
	public function view($view, array $vars = array(), $return = FALSE) {
		// Append any vars to cache
		if (!empty($vars)) {
			$this->vars($vars);
		}

		// Load file in root context
		return $this->CI->_load_file($view, TRUE, $return, $this->_ci_cached_vars);
	}

	/**
	 * Loads a language file
	 *
	 * @param	mixed	file name or array of names
	 * @param	string	language name
	 * @return	void
	 */
	public function language($file, $lang = '') {
		// Force file to array
		if ( ! is_array($file)) {
			$file = array($file);
		}

		// Load each file via Lang
		foreach ($file as $langfile) {
			$this->CI->lang->load($langfile, $lang);
		}
	}

	/**
	 * Loads a config file
	 *
	 * @param	mixed	file name or array of names
	 * @param	boolean	if configuration values should be loaded into their own section
	 * @param	boolean	TRUE if errors should just return FALSE, otherwise an error message is displayed
	 * @return	void
	 */
	public function config($file, $use_sections = FALSE, $fail_gracefully = FALSE) {
		// Force file to array
		if ( ! is_array($file)) {
			$file = array($file);
		}

		// Load each file via Config
		foreach ($file as $config) {
			$this->CI->config->load($config, $use_sections, $fail_gracefully);
		}
	}

	/**
	 * Database Loader
	 *
	 * @param	string	the DB credentials
	 * @param	bool	whether to return the DB object
	 * @param	bool	whether to enable active record (this allows us to override the config setting)
	 * @return	object
	 */
	public function database($params = '', $return = FALSE, $active_record = NULL) {
		// Do we even need to load the database class?
		if (class_exists('CI_DB') && $return == FALSE && $active_record == NULL && isset($this->CI->db) &&
        is_object($this->CI->db)) {
			return FALSE;
		}

		require_once(BASEPATH.'database/DB.php');

		if ($return === TRUE) {
			return DB($params, $active_record);
		}

		// Initialize the db variable.  Needed to prevent
		// reference errors with some configurations
		$this->CI->db = '';

		// Load the DB class
		$this->CI->db =& DB($params, $active_record);
	}

	/**
	 * Load the Utilities Class
	 *
	 * @return	void
	 */
	public function dbutil() {
		if ( ! class_exists('CI_DB')) {
			$this->database();
		}

		// for backwards compatibility, load dbforge so we can extend dbutils off it
		// this use is deprecated and strongly discouraged
		$this->dbforge();

        $driver = $this->CI->db->dbdriver;
		require_once(BASEPATH.'database/DB_utility.php');
		require_once(BASEPATH.'database/drivers/'.$driver.'/'.$driver.'_utility.php');
		$class = 'CI_DB_'.$driver.'_utility';

		$this->CI->dbutil = new $class();
	}

	/**
	 * Load the Database Forge Class
	 *
	 * @return	void
	 */
	public function dbforge() {
		if ( ! class_exists('CI_DB')) {
			$this->database();
		}

        $driver = $this->CI->db->dbdriver;
		require_once(BASEPATH.'database/DB_forge.php');
		require_once(BASEPATH.'database/drivers/'.$driver.'/'.$driver.'_forge.php');
		$class = 'CI_DB_'.$driver.'_forge';

		$this->CI->dbforge = new $class();
	}

	/**
	 * Load File
	 *
	 * This is a generic file loader
	 *
	 * @param	string  file path
	 * @param	bool    TRUE to return output
	 * @return	mixed   output if $return is TRUE, otherwise void
	 */
	public function file($path, $return = FALSE) {
		// Load file in root context
		return $this->CI->_load_file($path, FALSE, $return);
	}

	/**
	 * Set Variables
	 *
	 * Once variables are set they become available within
	 * the controller class and its "view" files.
	 *
	 * @param   mixed   variable name or array of vars
     * @param   mixed   variable value
	 * @return	void
	 */
	public function vars($vars = array(), $val = NULL) {
        // Handle non-array arguments
		if ($val != NULL && is_string($vars)) {
			$vars = array($vars => $val);
		}
        else if (is_object($vars)) {
            $vars = get_object_vars($vars);
        }

        // Set values into cached vars
		if (is_array($vars) && count($vars) > 0) {
			foreach ($vars as $key => $val) {
				$this->_ci_cached_vars[$key] = $val;
			}
		}
	}

	/**
	 * Get Variable
	 *
	 * Check if a variable is set and retrieve it.
	 *
	 * @param	string	var key
	 * @return	mixed	var value
	 */
	public function get_var($key) {
		return isset($this->_ci_cached_vars[$key]) ? $this->_ci_cached_vars[$key] : NULL;
	}

	/**
	 * Add Package Path
	 *
	 * Prepends a parent path to the library, mvc, and config path arrays
	 *
	 * @param	string  path
	 * @param 	boolean view cascade flag
	 * @return	void
	 */
	public function add_package_path($path, $view_cascade = TRUE) {
        $this->CI->add_package_path($path, $view_cascade);
	}

	/**
	 * Get Package Paths
	 *
	 * Return a list of all package paths, by default it will ignore BASEPATH.
	 *
	 * @param	boolean include base path flag
	 * @return	void
	 */
	public function get_package_paths($include_base = FALSE) {
		return $this->CI->get_package_paths($include_base);
	}

	/**
	 * Remove Package Path
	 *
	 * Remove a path from the library, mvc, and config path arrays if it exists
	 * If no path is provided, the most recently added path is removed.
	 *
	 * @param	string  path
	 * @param   boolean remove from config path flag
	 * @return	void
	 */
	public function remove_package_path($path = '', $remove_config_path = TRUE) {
        $this->CI->remove_packate_path($path, $remove_config_path);
	}

	/**
	 * Autoloader
	 *
	 * The config/autoload.php file contains an array that permits various
	 * resources to be loaded automatically. The CodeIgniter object calls
	 * this protected method.
	 *
	 * @access	protected
	 * @param	array	autoload array
	 * @return	void
	 */
	protected function _autoloader($autoload) {
		// A little tweak to remain backward compatible
		// The $autoload['core'] item was deprecated
		if ( ! isset($autoload['libraries']) && isset($autoload['core'])) {
			$autoload['libraries'] = $autoload['core'];
		}

		// Autoload languages
		if (isset($autoload['language']) && count($autoload['language']) > 0) {
			$this->language($autoload['language']);
		}

		// Load libraries
		if (isset($autoload['libraries']) && count($autoload['libraries']) > 0) {
			// Load the database driver.
			if (in_array('database', $autoload['libraries'])) {
				$this->database();
				$autoload['libraries'] = array_diff($autoload['libraries'], array('database'));
			}

			// Load all other libraries
			foreach ($autoload['libraries'] as $item) {
				$this->library($item);
			}
		}

		// Autoload controllers and models
		foreach (array('helper', 'controller', 'model') as $type) {
			if (isset($autoload[$type]) && count($autoload[$type]) > 0) {
				$this->$type($autoload[$type]);
			}
		}
	}

	/**
	 * Get path from filename
	 *
	 * Separates dirname, if present, from file
	 *
	 * @param	string	reference to filename (to be modified)
	 * @return	string	path name
	 */
	protected function _get_path(&$file) {
		// Get any leading dirname
		$path = dirname($file);

		// Strip filename to basename
		$file = basename($file);

		// Return leading dirname, if any
		return ($path == '.') ? '' : $path.'/';
	}
}

/* End of file Loader.php */
/* Location: ./system/core/Loader.php */
