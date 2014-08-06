<?php 
/**
 * A model that provides JSolr-specific form functionality.
 * 
 * @package    JSolr
 * @subpackage Search
 * @copyright  Copyright (C) 2012-2014 KnowledgeARC Ltd. All rights reserved.
 * @license     This file is part of the JSolrSearch component for Joomla!.
 *
 *   The JSolrSearch component for Joomla! is free software: you can redistribute it 
 *   and/or modify it under the terms of the GNU General Public License as 
 *   published by the Free Software Foundation, either version 3 of the License, 
 *   or (at your option) any later version.
 *
 *   The JSolrSearch component for Joomla! is distributed in the hope that it will be 
 *   useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with the JSolrSearch component for Joomla!.  If not, see 
 *   <http://www.gnu.org/licenses/>.
 *
 * Contributors
 * Please feel free to add your name and email (optional) here if you have 
 * contributed any source code changes.
 * @author Hayden Young <hayden@knowledgearc.com>
 * 
 */

defined('_JEXEC') or die('Restricted access');

abstract class JSolrSearchModelForm extends JModelForm
{
    /**
     * Gets the custom form path for the specified form.
     * 
     * If a plugin has been selected (using the "o" parameter) then the method will attempt to 
     * load the plugin's override. If no override is found, it will attempt to load the default 
     * filters path.
     * 
     * This method will attempt to load the form in the following order:
     *
     * 1. /path/to/joomla/templates/current/html/com_jsolrsearch/forms/ using a 
     * plugin-specific override. The plugin override takes the form [type].[plugin_name].xml.
     * 
     * 2. /path/to/joomla/templates/current/html/com_jsolrsearch/forms/ for a generic 
     * override. The override is named [type].xml.
     *
     * 3. The plugin override, I.e. 
     * /path/to/joomla/plugins/jsolrsearch/[plugin]/forms/[type].xml).
     * 
     * 4. The JSolr Search component's [type].xml, I.e. 
     * /path/to/joomla/component/com_jsolrsearch/models/forms.
     */
    protected function getCustomFormPath($type)
    {
        $paths = array();
        $template = JFactory::getApplication()->getTemplate();
        $overridePath = JPATH_ROOT.'/templates/'.$template.'/html/com_jsolrsearch/forms/';
        $loaded = null;

        // load plugin filter override.
        if ($this->getState('query.o'))
        {
            $plugins = $this->getPlugins();
            
            while (($plugin = current($plugins)) && !$loaded)
            {
                if (JArrayHelper::getValue($plugin, 'name') == $this->getState('query.o')) 
                {
                    $loaded = JArrayHelper::getValue($plugin, 'name');
                }
                
                next($plugins);
            }
        }
        
        if ($loaded)
        {
            $paths[] = $overridePath.$type.'.'.$loaded.'.xml';

            $paths[] = JPATH_ROOT.'/plugins/jsolrsearch/'.$loaded.'/forms/'.$type.'.xml';
        }

        // if the plugin is loaded, make sure the generic filters.xml sits above the plugin's 
        // filters.xml.
        if (count($paths) > 0)
        {
            array_splice($paths, 1, 0, $overridePath.$type.'.xml');
        }
        else
        {
            $paths[] = $overridePath.$type.'.xml';
        }

        $found = null;
        
        while (($path = current($paths)) && !$found)
        {
            if (JFile::exists($path))
            {
                $found = $path;
            }
            
            next($paths);
        }
        
        // if no override exists, just return default.
        if (!$found)
        {
            $found = JPATH_ROOT.'/components/com_jsolrsearch/models/forms/'.$type.'.xml';
        }
        
        return $found;
    }
    
    /**
     * Get the list of enabled plugins for search results.
     */
    public function getPlugins()
    {
        JPluginHelper::importPlugin("jsolrsearch");

        $class = "JEventDispatcher";
        if (version_compare(JVERSION, "3.0", "l"))
        {
            $class = "JDispatcher";
        }

        $dispatcher = $class::getInstance();

        $array = $dispatcher->trigger('onJSolrSearchRegisterPlugin');

        $array = array_merge(array(array('plugin'=>'', 'label'=>JText::_('Everything'))), $array);

        for ($i = 0; $i < count($array); $i++)
        {
            $uri = clone JSolrSearchFactory::getQueryRoute();

            if (JArrayHelper::getValue($array[$i], 'name'))
            {
                $uri->setVar('o', $array[$i]['name']);
            }
            else
            {
                $uri->delVar('o');
            }

            $array[$i]['uri'] = htmlentities((string)$uri, ENT_QUOTES, 'UTF-8');
        }

        return $array;
    }
}