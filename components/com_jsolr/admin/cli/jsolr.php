#!/usr/bin/php
<?php
/**
 * @package     JSolr
 * @subpackage  Cli
 * @copyright   Copyright (C) 2012-2016 KnowledgeArc Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// Make sure we're being called from the command line, not a web interface
if (array_key_exists('REQUEST_METHOD', $_SERVER)) die();

/**
 * This is a CRON script which should be called from the command-line, not the
 * web. For example something like:
 * /usr/bin/php /path/to/site/cli/jcrawl.php
 */

// Set flag that this is a parent file.
define('_JEXEC', 1);

// Load system defines
if (file_exists(dirname(dirname(__FILE__)) . '/defines.php')) {
        require_once dirname(dirname(__FILE__)) . '/defines.php';
}

if (!defined('_JDEFINES')) {
    define('JPATH_BASE', dirname(dirname(__FILE__)));
    require_once JPATH_BASE . '/includes/defines.php';
}

// Get the framework.
if (file_exists(JPATH_LIBRARIES . '/import.legacy.php'))
    require_once JPATH_LIBRARIES . '/import.legacy.php';
else
    require_once JPATH_LIBRARIES . '/import.php';

// Bootstrap the CMS libraries.
require_once JPATH_LIBRARIES . '/cms.php';

// Load the configuration
require_once JPATH_CONFIGURATION . '/configuration.php';

// System configuration.
$config = new JConfig;

// Configure error reporting to maximum for CLI output.
error_reporting(E_ALL & ~E_STRICT);
ini_set('display_errors', 1);

// Load Library language
$lang = JFactory::getLanguage();
$lang->load('com_jsolr', JPATH_SITE);

\JLoader::registerNamespace('JSolr', JPATH_PLATFORM);

$path = JPATH_ADMINISTRATOR.'/components/com_jsolr/helpers/jsolr.php';
\JLoader::register('JSolrHelper', $path);

\JLoader::import('joomla.application.component.helper');

use \Joomla\Utilities\ArrayHelper;
use \Joomla\Input\Input;
use \Joomla\Registry\Registry;
use Solarium\QueryType\Luke\Query as LukeQuery;

/**
 * Simple command line interface application class.
 *
 * @package     JSolr
 * @subpackage  Cli
 */
class JSolrCli extends JApplicationCli
{
    public function __construct(Input $input = null, Registry $config = null)
    {
        parent::__construct($input, $config);

        $GLOBALS['application'] = $this;

        // fool the system into thinking we are running as JSite with JSolr as the active component
        $_SERVER['HTTP_HOST'] = 'domain.com';
        JFactory::getApplication('site');

        // Disable caching.
        $config = JFactory::getConfig();
        $config->set('caching', 0);
        $config->set('cache_handler', 'file');
    }

    public function doExecute()
    {
        $command = ArrayHelper::getValue($this->input->args, 0, null, 'word');

        try {
            if ($command) {
                $this->$command();
            } else {
                $this->help();
            }
        } catch (Exception $e) {
            JSolrHelper::log($e->getMessage(), \JLog::ERROR);
            JSolrHelper::log($e->getTraceAsString(), \JLog::DEBUG);
        }
    }

    protected function index()
    {
        if (count($this->input->args) > 3) {
            throw new Exception("Unknown option: ".array_pop($this->input->args));
            return;
        }

        $subCommand = ArrayHelper::getValue($this->input->args, 1, null, 'word');

        $lastModified = null;

        if ($subCommand) {
            switch ($subCommand) {
                case "help":
                    $this->out(JText::_("COM_JSOLR_CLI_INDEX_HELP"));
                    return;

                case "update":
                    $subCommand = ArrayHelper::getValue($this->input->args, 2, null, 'word');

                    if ($subCommand === 'help') {
                        $this->out(JText::_("COM_JSOLR_CLI_INDEX_UPDATE_HELP"));
                        return;
                    }

                    $format = "Y-m-d\TH:i:sP";
                    $lastModified = ArrayHelper::getValue($this->input->args, 2, null, 'string');
                    $tz = new DateTimeZone(JFactory::getConfig()->get('offset'));

                    if ($lastModified) {
                        $date = JDate::createFromFormat($format, $lastModified, $tz);

                        if ($date === false) {
                            throw new Exception("Invalid last modified date: ".$lastModified);
                        }
                    } else { // use lastmodified from Solr index.
                        $client = \JSolr\Index\Factory::getClient();

                        $client->registerQueryType(LukeQuery::QUERY_LUKE, 'Solarium\\QueryType\\Luke\\Query');
                        $luke = $client->createQuery(LukeQuery::QUERY_LUKE);
                        $response = $client->execute($luke);

                        $date = JFactory::getDate($response->getLastModified(), $tz);
                    }

                    $lastModified = $date->format($format);

                    break;

                default:
                    throw new Exception("Unknown command: ".$subCommand);
                    break;
            }
        }

        $start = new JDate('now');

        JSolrHelper::log("crawl start ".$start->format("c"), JLog::DEBUG);

        $this->fireEvent('onJSolrIndex', array($lastModified));

        $end = new JDate('now');

        JSolrHelper::log("crawl end ".$end->format("c"), JLog::DEBUG);

        $time = $start->diff($end);

        JSolrHelper::log("execution time: ".$time->format("%H:%I:%S"), JLog::DEBUG);
    }

    protected function optimize()
    {
        $argsCount = count($this->input->args);

        if (($argsCount === 2 && end($this->input->args) == 'help')) {
            $this->out(JText::_("COM_JSOLR_CLI_OPTIMIZE_HELP"));
            return;
        } else if ($argsCount !== 1) {
            throw new Exception("Invalid option: ".array_pop($this->input->args));
        }

        JSolrHelper::log('optimizing...', JLog::DEBUG);

        $client = \JSolr\Index\Factory::getClient();

        $update = $client->createUpdate();
        $update->addOptimize(); // TODO: using solr defaults. Need to research further.
        $result = $client->update($update);

        JSolrHelper::log("optimization finished: ".$result->getStatus(), JLog::DEBUG);
    }

    protected function purge()
    {
        $argsCount = count($this->input->args);

        if (($argsCount === 2 && end($this->input->args) == 'help')) {
            $this->out(JText::_("COM_JSOLR_CLI_PURGE_HELP"));
            return;
        } else if ($argsCount > 2) {
            throw new Exception("Invalid option: ".array_pop($this->input->args));
        }

        $plugin = ArrayHelper::getValue($this->input->args, 1, null, 'word');

        if ($plugin) {
            JSolrHelper::log('purging '.$plugin.' items...', \JLog::DEBUG);

            $this->fireEvent('onPurge', array(), $plugin);

            JSolrHelper::log('purging '.$plugin.' items completed', \JLog::DEBUG);
        } else {
            $client = \JSolr\Index\Factory::getClient();

            JSolrHelper::log('purging all items from index...', JLog::DEBUG);

            // more efficient than calling each plugin's onPurge.
            $update = $client->createUpdate();

            $update->addDeleteQuery('*:*');
            $update->addCommit();

            $result = $client->update($update);

            JSolrHelper::log('purging index completed: '.$result->getStatus(), JLog::DEBUG);
        }
    }

    protected function config()
    {
        $argsCount = count($this->input->args);

        if (($argsCount === 2 && end($this->input->args) == 'help')) {
            $this->out(JText::_("COM_JSOLR_CLI_CONFIG_HELP"));
            return;
        } else if ($argsCount !== 1) {
            throw new Exception("Invalid option: ".array_pop($this->input->args));
        }

        $config = \JSolr\Index\Factory::getConfig();

        echo <<<EOT

Copyright (C) 2012-2016 KnowledgeArc Ltd. All rights reserved.
GNU General Public License version 2 or later; see LICENSE.txt


EOT;

        foreach ($config->flatten() as $key=>$value) {
            echo <<<EOT
{$key}={$value}

EOT;
        }

        echo <<<EOT


EOT;
    }

    /**
     * Method to build and print the help screen text to stdout.
     */
    protected function help($commands = null)
    {
        $this->out(JText::_("COM_JSOLR_CLI_HELP"));
    }

    private function fireEvent($name, $args = array(), $plugin = null)
    {
        if ($plugin) {
            if (!is_a(JPluginHelper::getPlugin('jsolr', $plugin), 'stdClass')) {
                throw new Exception('The specified plugin does not exist or is not enabled.');
            }
        }

        $dispatcher = JEventDispatcher::getInstance();

        JPluginHelper::importPlugin("jsolr", $plugin, true, $dispatcher);

        return $dispatcher->trigger($name, $args);
    }
}

JApplicationCli::getInstance('JSolrCli')->execute();
