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
 * CodeIgniter Config Class
 *
 * This class contains functions that enable config files to be managed
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		ExpressionEngine Dev Team
 * @link		http://codeigniter.com/user_guide/libraries/config.html
 */
class CI_Config {
	protected $config = array();
	protected $is_loaded = array();
	public $_config_paths = array(APPPATH);

	/**
	 * Constructor
	 *
	 * Sets the $config data from the primary config.php file as a class variable
	 *
	 * @param	string	the	config file name
	 * @param	boolean	if configuration values should be loaded into their own section
	 * @param	boolean	true if errors should just return false, false if an error message should be displayed
	 * @return	boolean  if the file was successfully loaded or not
	 */
	public function __construct()
	{
		$this->config =& get_config();
		log_message('debug', 'Config Class Initialized');

		// Set the base_url automatically if none was provided
		if ($this->config['base_url'] == '')
		{
			if (isset($_SERVER['HTTP_HOST']))
			{
				$base_url = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
				$base_url .= '://'. $_SERVER['HTTP_HOST'];
				$base_url .= str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
			}
			else
			{
				$base_url = 'http://localhost/';
			}

			$this->set_item('base_url', $base_url);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Load Config File
	 *
	 * @param	string	the config file name
	 * @param	boolean	if configuration values should be loaded into their own section
	 * @param	boolean	true if errors should just return false, false if an error message should be displayed
	 * @return	boolean	if the file was loaded correctly
	 */
	public function load($file = '', $use_sections = FALSE, $fail_gracefully = FALSE)
	{
		// Strip .php from file
		$file = str_replace('.php', '', $file);

		// Make sure file isn't already loaded
		if (in_array($file, $this->is_loaded))
		{
			return TRUE;
		}

		// Get config array and check result
		$config = $this->get($file.'.php', $file);
		if ($config === FALSE)
		{
			if ($fail_gracefully)
			{
				return FALSE;
			}
			show_error('The configuration file '.$file.'.php does not exist.');
		}
		else if (is_string($config))
		{
			if ($fail_gracefully)
			{
				return FALSE;
			}
			show_error('Your '.$config.' file does not appear to contain a valid configuration array.');
		}

		// Check for sections
		if ($use_sections === TRUE)
		{
			// Merge or set section
			if (isset($this->config[$file]))
			{
				$this->config[$file] = array_merge($this->config[$file], $config);
			}
			else
			{
				$this->config[$file] = $config;
			}
		}
		else
		{
			// Merge config
			$this->config = array_merge($this->config, $config);
		}

		// Mark file as loaded
		$this->is_loaded[] = $file;
		log_message('debug', 'Config file loaded: '.$file.'.php');
		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Get config file contents
	 *
	 * Reads and merges config arrays from named config files
	 *
	 * @param	string	the config file name
	 * @param	string	the array name to look for
	 * @return	mixed	merged config if found, otherwise FALSE
	 */
	public function get($file, $name)
	{
		// Ensure file ends with .php
		if (!preg_match('/\.php$/', $file))
		{
			$file .= '.php';
		}

		// Merge arrays from all viable config paths
		$merged = array();
		$check_locations = defined('ENVIRONMENT') ? array(ENVIRONMENT.'/'.$file, $file) : array($file);
		foreach ($this->_config_paths as $path)
		{
			// Check with/without ENVIRONMENT
			foreach ($check_locations as $location)
			{
				// Determine if file exists here
				$file_path = $path.'config/'.$location;
				if (file_exists($file_path))
				{
					// Include file
					include($file_path);

					// See if we have an array name to check for
					if (empty($name))
					{
						// Nope - just note we found something
						$merged = TRUE;
						continue;
					}

					// Check for config array
					if ( ! is_array($$name))
					{
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
		if (empty($merged))
		{
			// None - quit
			return FALSE;
		}

		// Return merged config
		return $merged;
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch a config file item
	 *
	 *
	 * @param	string	the config item name
	 * @param	string	the index name
	 * @param	bool
	 * @return	string
	 */
	public function item($item, $index = '')
	{
		if ($index == '')
		{
			if ( ! isset($this->config[$item]))
			{
				return FALSE;
			}

			$pref = $this->config[$item];
		}
		else
		{
			if ( ! isset($this->config[$index]))
			{
				return FALSE;
			}

			if ( ! isset($this->config[$index][$item]))
			{
				return FALSE;
			}

			$pref = $this->config[$index][$item];
		}

		return $pref;
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch a config file item - adds slash after item
	 *
	 * The second parameter allows a slash to be added to the end of
	 * the item, in the case of a path.
	 *
	 * @param	string	the config item name
	 * @param	bool
	 * @return	string
	 */
	public function slash_item($item)
	{
		if ( ! isset($this->config[$item]))
		{
			return FALSE;
		}

		return rtrim($this->config[$item], '/').'/';
	}

	// --------------------------------------------------------------------

	/**
	 * Site URL
	 *
	 * @param	string	the URI string
	 * @return	string
	 */
	public function site_url($uri = '')
	{
		if ($uri == '')
		{
			return $this->slash_item('base_url').$this->item('index_page');
		}

		if ($this->item('enable_query_strings') == FALSE)
		{
			if (is_array($uri))
			{
				$uri = implode('/', $uri);
			}

			$index = $this->item('index_page') == '' ? '' : $this->slash_item('index_page');
			$suffix = ($this->item('url_suffix') == FALSE) ? '' : $this->item('url_suffix');
			return $this->slash_item('base_url').$index.trim($uri, '/').$suffix;
		}
		else
		{
			if (is_array($uri))
			{
				$i = 0;
				$str = '';
				foreach ($uri as $key => $val)
				{
					$prefix = ($i == 0) ? '' : '&';
					$str .= $prefix.$key.'='.$val;
					$i++;
				}

				$uri = $str;
			}

			return $this->slash_item('base_url').$this->item('index_page').'?'.$uri;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * System URL
	 *
	 * @return	string
	 */
	public function system_url()
	{
		$x = explode("/", preg_replace("|/*(.+?)/*$|", "\\1", BASEPATH));
		return $this->slash_item('base_url').end($x).'/';
	}

	// --------------------------------------------------------------------

	/**
	 * Set a config file item
	 *
	 * @param	string	the config item key
	 * @param	string	the config item value
	 * @return	void
	 */
	public function set_item($item, $value)
	{
		$this->config[$item] = $value;
	}

	// --------------------------------------------------------------------

	/**
	 * Assign to Config
	 *
	 * This function is called by the front controller (CodeIgniter.php)
	 * after the Config class is instantiated.  It permits config items
	 * to be assigned or overriden by variables contained in the index.php file
	 *
	 * @access	private
	 * @param	array
	 * @return	void
	 */
	public function _assign_to_config($items = array())
	{
		if (is_array($items))
		{
			foreach ($items as $key => $val)
			{
				$this->set_item($key, $val);
			}
		}
	}
}

// END CI_Config class

/* End of file Config.php */
/* Location: ./system/core/Config.php */
