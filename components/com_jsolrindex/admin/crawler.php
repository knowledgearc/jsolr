#!/usr/bin/php
<?php
/**
 * @author $lastchangedby$
 * @package JSolr
 * @subpackage CLI
 * @copyright Copyright (C) 2010 Wijiti Pty Ltd. All rights reserved.
 */
 
// We are a valid Joomla entry point.
define('_JEXEC', 1);
 
// Setup the path related constants.
define('DS', DIRECTORY_SEPARATOR);
define('JPATH_BASE', dirname( __FILE__ ));
define('JPATH_ROOT', JPATH_BASE);
define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_BASE.DS."administrator".DS."components".DS."com_jsolrindex");

require_once(JPATH_BASE.DS.'includes'.DS.'defines.php');
 
// Load the library importer.
require_once (JPATH_LIBRARIES.DS.'joomla'.DS.'import.php');
require_once (JPATH_BASE.DS.'configuration.php');

// Import library dependencies.
jimport('joomla.application.application');
jimport('joomla.utilities.utility');
jimport('joomla.language.language');
jimport('joomla.utilities.string');
jimport('joomla.factory');
jimport('joomla.event.dispatcher');
jimport('joomla.plugin.helper');
jimport('joomla.error.log');
jimport('joomla.user.user');

require_once(JPATH_COMPONENT_ADMINISTRATOR.DS."lib".DS."apache".DS."solr".DS."service.php");
 
/**
 * Simple command line interface application class.
 *
 * @package Wijiti
 * @subpackage CLI
 */
class JSolrIndexCrawler extends JApplication
{
    /**
     * The name of the application
     *
     * @var array
     */
    public $_name = 'cli';
 
    /**
     * The client identifier.
     *
     * @var integer
     * @since 1.0
     */
    public $_clientId = 1000;
 
    /**
     * The current working directory of the application.
     *
     * @var string
     * @since 1.0
     */
    private $cwd = null;
 
    /**
     * The application argument values.
     *
     * @var array
     * @since 1.0
     */
    private $argv = array ();
 
    /**
     * The application argument options.
     *
     * The options are populated automatically by the private _initializeOptions
     * function
     * which sets each command line argument as a member of the options object.
     *
     * @var object
     * @since 1.0
     */
    private $options = null;
 
    private $shortargs = 'q';
 
    // Need to wait for PHP 5.3
    private $longargs = array('help');
 
    public function __construct()
    {
        // Initialize the execution arguments.
        $this->_initializeOptions();

        // If the help screen has been requested, print it and exit.
        if (isset($this->options->h)) {
            $this->help();
            exit (0);
        }
 
        // Get the current directory.
        $this->cwd = getcwd();

		$jconfig = new JConfig();
    	$config = JFactory::getConfig();
    	$config->loadObject($jconfig);
    	
    	$application = JFactory::getApplication("site");
    }
    
    public function getRobotsFile()
    {
    	return JPATH_ROOT.DS."administrator".DS."components".DS."com_jsolrindex".DS."ignore.txt";
    }
 
    public function execute()
    {
    	$rules = file($this->getRobotsFile(), FILE_IGNORE_NEW_LINES);

    	$dispatcher =& JDispatcher::getInstance();
    	
		JPluginHelper::importPlugin("jsolrcrawler", null, true, $dispatcher);

		try {
			$array = $dispatcher->trigger('onIndex', array($rules));
		} catch (Exception $e) {
			if (!isset($this->options->q)) {
				$this->out($e->getMessage());
			}
		}
    }
 
    /**
     * Method to print a line of text to stdout.
     *
     * @param string The line of text to print to stdout.
     * @return void
     * @since 1.0
     */
    function out($text = '')
    {
        echo "\n".$text;
    }
 
    /**
     * Method to build and print the help screen text to stdout.
     *
     * @return void
     * @since 1.0
     */
    protected function help()
    {
        // Initialize variables.
        $help = array();
 
        // Build the help screen information.
        $help[] = 'Your Help Screen';
        $help[] = '';
        $help[] = 'Here is where you put all your help screen information.';
        $help[] = '';
 
        // Print out the help information.
        echo implode("\n", $help);
 
    }
 
    /**
     * Initialize the command line options and arguments.
     *
     * @return void
     * @since 1.0
     */
    private function _initializeOptions()
    {
        // Get the options from the command line argument list and argument values.
        $opts = getopt($this->shortargs /*, $this->longargs*/);
        @$args = (array)$GLOBALS['argv'];
 
        // If the argument value list is not empty, make sure the options are unset.
        if (! empty($args)) {
            // Iterate over the found options.
            foreach ($opts as $o=>$a) {
                // Search for occurrences of the option with no value or with no space betweenoption and value.
                while ($k = array_search('-'.$o.$a, $args)) {
                    // Remove any found options from the argument value array.
                    if ($k) {
                        unset ($args[$k]);
                    }
                }
 
                // Search for remaining occurrences of the option (space between option and value).
                while ($k = array_search('-'.$o, $args)) {
                    // Remove any found options and values from the argument value array.
                    if ($k) {
                        unset ($args[$k]);
                        unset ($args[$k+1]);
                    }
                }
            }
        }
 
        // Set the options and argument values to internal members.
        $this->options = (object)$opts;
        $this->argv = (array)$args;
 
    }
}
 
// Set error handling.
JError::setErrorHandling(E_ALL, 'echo');
 
// Create the application object.
$crawler = new JSolrIndexCrawler();
 
// Execute the application.
$crawler->execute();