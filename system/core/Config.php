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
	protected $CI;
	protected $config = array();
	protected $is_loaded = array();

	/**
	 * Constructor
	 *
	 * Sets the config data from the primary config.php file as a class variable.
	 * The primary config.php is bootstrapped here instead of using $this->load()
	 * because of special handling and the unavailability of the rest of the core
	 * objects when Config is loaded.
	 *
	 * @param	object	parent reference
	 */
	public function __construct($CI) {
		// Attach parent reference
		$this->CI =& $CI;

		// Get path relative to ENVIRONMENT
		$file_path = $CI->get_env_path('config.php');

		// Fetch the config file
		if ( ! file_exists($file_path)) {
			exit('The configuration file does not exist.');
		}
		require($file_path);

		// Does the $config array exist in the file?
		if ( ! isset($config) || ! is_array($config)) {
			exit('Your config file does not appear to be formatted correctly.');
		}
		$this->config =& $config;

		// Set the base_url automatically if none was provided
		if ($this->config['base_url'] == '') {
			if (isset($_SERVER['HTTP_HOST'])) {
				$base_url = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
				$base_url .= '://'. $_SERVER['HTTP_HOST'];
				$base_url .= str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
			}
			else {
				$base_url = 'http://localhost/';
			}

			$this->set_item('base_url', $base_url);
		}
	}

	/**
	 * Load Config File
	 *
	 * @param	string	the	config file name
	 * @param	boolean	if configuration values should be loaded into their own section
	 * @param	boolean	TRUE if errors should just return FALSE, otherwise an error message is displayed
	 * @return	boolean	TRUE if the file was loaded correctly, otherwise FALSE
	 */
	public function load($file, $use_sections = FALSE, $fail_gracefully = FALSE) {
		// Strip .php from file
		$file = str_replace('.php', '', $file);

		// Get config array and check result
		$config = $this->get($file, $fail_gracefully);
		if ($config === FALSE) {
			// Graceful failure - just return FALSE
			return FALSE;
		}

		// Make sure file isn't already loaded
		if ( ! in_array($file, $this->is_loaded)) {
			// Check for sections
			if ($use_sections === TRUE) {
				// Merge or set section
				if (isset($this->config[$file])) {
					$this->config[$file] = array_merge($this->config[$file], $config);
				}
				else {
					$this->config[$file] = $config;
				}
			}
			else {
				// Merge config
				$this->config = array_merge($this->config, $config);
			}

			// Mark file as loaded
			$this->is_loaded[] = $file;
		}

		return TRUE;
	}

	/**
	 * Get config file contents
	 *
	 * Reads and merges config arrays from named config files
	 *
	 * @param	string	the	config file name
	 * @param	boolean	TRUE if errors should just return FALSE, otherwise an error message is displayed
	 * @return	mixed	merged config if found, otherwise FALSE
	 */
	public function get($file, $fail_gracefully = FALSE) {
		// Ensure file ends with .php
		if (!preg_match('/\.php$/', $file)) {
			$file .= '.php';
		}

		// Merge arrays from all viable config paths
		$merged = array();
		foreach ($this->CI->get_env_path($file, TRUE) as $path) {
			// Include file
			include($path);

			// Check for $config array
			if ( ! is_array($config)) {
				// Invalid - quit or exit
				$this->CI->log_message('debug', 'Invalid config file: '.$path);
				if ($fail_gracefully === TRUE) {
					return FALSE;
				}
				$this->CI->show_error('Your '.$path.' file does not appear to contain a valid configuration array.');
			}

			// Merge config and unset local
			$merged = array_merge($merged, $config);
			unset($config);

			// Log success for this file
			$this->CI->log_message('debug', 'Config file loaded: '.$path);
		}

		// Test for merged config
		if (empty($merged)) {
			// None - quit or exit
			if ($fail_gracefully === TRUE) {
				return FALSE;
			}
			$this->CI->show_error('The configuration file '.$file.' does not exist.');
		}

		// Return merged config
		return $merged;
	}

	/**
	 * Fetch a config file item
	 *
	 * @param	string	the	config item name
	 * @param	string	the	index name
	 * @return	string
	 */
	public function item($item, $index = '') {
		if ($index == '') {
			if ( ! isset($this->config[$item])) {
				return FALSE;
			}

			$pref = $this->config[$item];
		}
		else {
			if ( ! isset($this->config[$index])) {
				return FALSE;
			}

			if ( ! isset($this->config[$index][$item])) {
				return FALSE;
			}

			$pref = $this->config[$index][$item];
		}

		return $pref;
	}

	/**
	 * Fetch a config file item - adds slash after item (if item is not empty)
	 *
	 * @param	string	the	config item name
	 * @param	bool
	 * @return	string
	 */
	public function slash_item($item) {
		if ( ! isset($this->config[$item])) {
			return FALSE;
		}
		if( trim($this->config[$item]) == '') {
			return '';
		}

		return rtrim($this->config[$item], '/').'/';
	}

	/**
	 * Site URL
	 * Returns base_url . index_page [. uri_string]
	 *
	 * @param	string	the	URI string
	 * @return	string
	 */
	public function site_url($uri = '') {
		if ($uri == '') {
			return $this->slash_item('base_url').$this->item('index_page');
		}

		if ($this->item('enable_query_strings') == FALSE) {
			$suffix = ($this->item('url_suffix') == FALSE) ? '' : $this->item('url_suffix');
			return $this->slash_item('base_url').$this->slash_item('index_page').$this->_uri_string($uri).$suffix;
		}
		else {
			return $this->slash_item('base_url').$this->item('index_page').'?'.$this->_uri_string($uri);
		}
	}

	/**
	 * Base URL
	 * Returns base_url [. uri_string]
	 *
	 * @param	string	$uri
	 * @return	string
	 */
	public function base_url($uri = '') {
		return $this->slash_item('base_url').ltrim($this->_uri_string($uri), '/');
	}

	/**
	 * Build URI string for use in Config::site_url() and Config::base_url()
	 *
	 * @access	protected
	 * @param	mixed	URI	string or array of URIs
	 * @return	string
	 */
	protected function _uri_string($uri) {
		if ($this->item('enable_query_strings') == FALSE) {
			if (is_array($uri)) {
				$uri = implode('/', $uri);
			}
			$uri = trim($uri, '/');
		}
		else {
			if (is_array($uri)) {
				$i = 0;
				$str = '';
				foreach ($uri as $key => $val) {
					$prefix = ($i == 0) ? '' : '&';
					$str .= $prefix.$key.'='.$val;
					$i++;
				}
				$uri = $str;
			}
		}
		return $uri;
	}

	/**
	 * System URL
	 *
	 * @return	string
	 */
	public function system_url() {
		$x = explode('/', preg_replace('|/*(.+?)/*$|', '\\1', BASEPATH));
		return $this->slash_item('base_url').end($x).'/';
	}

	/**
	 * Set a config file item
	 *
	 * @param	string	the	config item key
	 * @param	string	the	config item value
	 * @return	void
	 */
	public function set_item($item, $value) {
		$this->config[$item] = $value;
	}
}
// END CI_Config class

/* End of file Config.php */
/* Location: ./system/core/Config.php */
