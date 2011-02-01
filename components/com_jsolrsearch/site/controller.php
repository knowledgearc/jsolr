<?php
/**
 * @version		$LastChangedBy$
 * @package		Wijiti
 * @subpackage	JSolrSearch
 * @copyright	Copyright (C) 2010 Wijiti Pty Ltd. All rights reserved.
 * @license     This file is part of the JSolrSearch Component for Joomla!.

   The JSolrSearch Component for Joomla! is free software: you can redistribute it 
   and/or modify it under the terms of the GNU General Public License as 
   published by the Free Software Foundation, either version 3 of the License, 
   or (at your option) any later version.

   The JSolrSearch Component for Joomla! is distributed in the hope that it will be 
   useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with the JSolrSearch Component for Joomla!.  If not, see 
   <http://www.gnu.org/licenses/>.

 * Contributors
 * Please feel free to add your name and email (optional) here if you have 
 * contributed any source code changes.
 * Name							Email
 * Hayden Young					<haydenyoung@wijiti.com>
 */

/** 
 * A controller for managing content sharing.
 * 
 * @author		$LastChangedBy$
 * @package		Wijiti
 * @subpackage	JSolrSearch
 * @copyright	Copyright (C) 2010 Wijiti Pty Ltd. All rights reserved.
 */

defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.controller');

class JSolrSearchController extends JController 
{
	function __construct()
	{
		parent::__construct();
	}
	
	function advanced()
	{
		$model = $this->getModel("advanced");
		
		$this->setRedirect($model->buildQueryURL(JRequest::get()));
	}
	
	function search()
	{
		$model = $this->getModel("search");
		$this->setRedirect($model->buildQueryURL(JRequest::get()));
	}

	function display()
	{
		$default = "basic";
		
		$viewName = JRequest::getWord("view", $default);
		
		$modelName = $viewName;
		
		if ($modelName == $default) {
			$modelName = "search";
		}

		$model = $this->getModel($modelName);
		
		$view = $this->getView($viewName, JRequest::getWord("format", "html"));		
		$view->setModel($model, true);

		if (($viewName == "" || $viewName == $default) && trim(JRequest::getString("q", null))) {
			$model->setQueryParams(JRequest::get("get"));
			$view->setLayout("results");
		}
		
		$view->display();
	}
}