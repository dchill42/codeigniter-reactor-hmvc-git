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
 * Exceptions Class
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Exceptions
 * @author		ExpressionEngine Dev Team
 * @link		http://codeigniter.com/user_guide/libraries/exceptions.html
 */
class CI_Exceptions {
    protected $CI = NULL;
	public $action;
	public $severity;
	public $message;
	public $filename;
	public $line;
	public $ob_level = 0;
	public $levels = array(
        E_ERROR				=>	'Error',
        E_WARNING			=>	'Warning',
        E_PARSE				=>	'Parsing Error',
        E_NOTICE			=>	'Notice',
        E_CORE_ERROR		=>	'Core Error',
        E_CORE_WARNING		=>	'Core Warning',
        E_COMPILE_ERROR		=>	'Compile Error',
        E_COMPILE_WARNING	=>	'Compile Warning',
        E_USER_ERROR		=>	'User Error',
        E_USER_WARNING		=>	'User Warning',
        E_USER_NOTICE		=>	'User Notice',
        E_STRICT			=>	'Runtime Notice'
    );

	/**
	 * Constructor
	 *
	 * @param	object	parent reference
	 */
	public function __construct(CodeIgniter $CI) {
		// Attach parent reference
        $this->CI =& $CI;

		// Set output buffer level
		$this->ob_level = ob_get_level();

		// Note: Do not log messages from this constructor.
	}

	/**
	 * Exception Logger
	 *
	 * This function logs PHP generated error messages
	 *
	 * @access	private
	 * @param	string	the error severity
	 * @param	string	the error string
	 * @param	string	the error filepath
	 * @param	string	the error line number
	 * @return	string
	 */
	public function log_exception($severity, $message, $filepath, $line) {
		$severity = ( ! isset($this->levels[$severity])) ? $severity : $this->levels[$severity];

		$this->CI->log_message('error', 'Severity: '.$severity.'  --> '.$message. ' '.$filepath.' '.$line, TRUE);
	}

	/**
	 * 404 Page Not Found Handler
	 *
	 * Calls the 404 override method if available, or displays a generic 404 error.
	 * The 404 override method will get the requested page as its first argument,
	 * followed by any trailing segments of 404_override. So, if "foo/bar" triggered
	 * a 404, and 404_override was "my404/method/one/two", the effect would be to call:
	 *	my404->method("foo/bar", "one", "two");
	 *
	 * @access	private
	 * @param	string
	 * @return	string
	 */
	public function show_404($page = '', $log_error = TRUE) {
		// By default we log this, but allow a dev to skip it
		if ($log_error) {
			$this->CI->log_message('error', '404 Page Not Found --> '.$page);
		}

		// Call show_error for the 404 - it will exit
		$this->show_error('404 Page Not Found', 'The page you requested was not found.', 'error_404', 404);
	}

	/**
	 * General Error Page
	 *
	 * This function takes an error message as input
	 * (either as a string or an array) and displays
	 * it using the specified template.
	 *
	 * @param	string	the heading
	 * @param	string	the message
	 * @param	string	the template name
	 * @return	string
	 */
	public function show_error($heading, $message, $template = 'error_general', $status_code = 500) {
		// Set status header
		$this->CI->output->set_status_header($status_code);

		// Clear any output buffering
		if (ob_get_level() > $this->ob_level + 1) {
			ob_end_flush();
		}

		// Check Router for an error (or 404) override
		$route = $this->CI->router->get_error_route($status_code == 404);
		if ($route !== FALSE) {
			// Insert or append arguments
			if (count($route) > CI_Router::SEG_ARGS) {
				// Insert heading and message after path, subdir, class, and method and before other args
				$route = array_merge(
					array_slice($route, 0, CI_Router::SEG_ARGS),
					array($heading, $message),
					array_slice($route, CI_Router::SEG_ARGS)
				);
			}
			else {
				// Just append heading and message to the end
				$route[] = $heading;
				$route[] = $message;
			}

			// Load the error Controller and call the method
			if ($this->CI->load->controller($route)) {
				// Display the output and exit
				$this->CI->output->_display();
				exit;
			}
		}

		// If the override didn't exit above, just display the generic error template
		ob_start();
		$message = '<p>'.implode('</p><p>', (is_array($message)) ? $message : array($message)).'</p>';
		include(APPPATH.'errors/'.$template.'.php');
		echo ob_get_clean();
		exit;
	}

	/**
	 * Native PHP error handler
	 *
	 * @param	string	the error severity
	 * @param	string	the error string
	 * @param	string	the error filepath
	 * @param	string	the error line number
	 * @return	string
	 */
	public function show_php_error($severity, $message, $filepath, $line) {
		$severity = isset($this->levels[$severity]) ? $this->levels[$severity] : $severity;

		$filepath = str_replace("\\", '/', $filepath);

		// For safety reasons we do not show the full file path
		if (FALSE !== strpos($filepath, '/')) {
			$x = explode('/', $filepath);
			$filepath = $x[count($x)-2].'/'.end($x);
		}

		if (ob_get_level() > $this->ob_level + 1) {
			ob_end_flush();
		}
		ob_start();
		include(APPPATH.'errors/error_php.php');
		$buffer = ob_get_contents();
		ob_end_clean();
		echo $buffer;
	}
}
// END Exceptions Class

/* End of file Exceptions.php */
/* Location: ./system/core/Exceptions.php */
