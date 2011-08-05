<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.1.6 or newer
 *
 * @package		CodeIgniter
 * @author		Darren Hill <dchill42@gmail.com>, St. Petersburg College
 * @copyright	Copyright (c) 2008 - 2011, EllisLab, Inc.
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * CodeIgniter Application Root Class
 *
 * This class object is the super class that every library in
 * CodeIgniter will be assigned to.
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		Darren Hill <dchill42@gmail.com>, St. Petersburg College
 * @link		http://codeigniter.com/user_guide/general/controllers.html
 */
class CI_Root {

	private static $instance = NULL;

	/**
	 * Constructor
	 */
	private function __construct()
	{
		// Assign single object to static instance
		self::$instance = $this;
		
		// Assign all the class objects that were instantiated by the
		// bootstrap file (CodeIgniter.php) to local class variables
		// so that CI can run as one big super object.
		// Later core loads will be done through load_core() below.
		foreach (is_loaded() as $var => $class)
		{
			$this->$var =& load_class($class);
		}

		// Get Loader
		$this->load =& load_class('Loader', 'core');

		log_message('debug', 'Root Class Initialized');
	}

	/**
	 * Load core class
	 *
	 * Loads a core class and registers it with root object
	 *
	 * @param   string  class name
	 * @return  object
	 */
	public function load_core($class)
	{
		// Load class, immediately assign, and return object
		$name = strtolower($class);
		$this->$name =& load_class($class, 'core');
		return $this->$name;
	}

	/**
	 * Call magic method
	 *
	 * Calls method of routed controller if not existent in root
	 *
	 * @param   string  method name
	 * @param   array   method arguments
	 * @return  mixed
	 */
	public function __call($name, $arguments)
	{
		// Check for routed controller and method
		if (isset($this->routed) && method_exists($this->routed, $name))
		{
			return call_user_func_array(array($this->routed, $name), $arguments);
		}
	}

	/**
	 * Get instance
	 *
	 * Returns singleton instance of root object
	 *
	 * @return  object
	 */
	public static function &get_instance()
	{
		// Check for existing instance
		if (is_null(self::$instance))
		{
			// Instantiate object
			new CI_Root();
		}
		return self::$instance;
	}
}
// END Root class

/* End of file Root.php */
/* Location: ./system/core/Root.php */
