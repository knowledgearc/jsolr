<?php
/**
 * @package		JSolr
 * @subpackage	Index
 * @copyright	Copyright (C) 2012 - 2014 KnowledgeARC Ltd. All rights reserved.
 * @license     This file is part of the JSolr library for Joomla!.

   The JSolr library for Joomla! is free software: you can redistribute it 
   and/or modify it under the terms of the GNU General Public License as 
   published by the Free Software Foundation, either version 3 of the License, 
   or (at your option) any later version.

   The JSolr library for Joomla! is distributed in the hope that it will be 
   useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with the JSolrIndex component for Joomla!.  If not, see 
   <http://www.gnu.org/licenses/>.

 * Contributors
 * Please feel free to add your name and email (optional) here if you have 
 * contributed any source code changes.
 * Name							Email
 * @author Hayden Young <haydenyoung@knowledgearc.com>
 * 
 */
 
// no direct access
defined('_JEXEC') or die();

jimport('joomla.error.log');
jimport('joomla.language.helper');
jimport('joomla.plugin.plugin');

jimport('jsolr.helper');
jimport('jsolr.index.factory');
jimport('jsolr.apache.solr.service');
jimport('jsolr.apache.solr.document');

/**
 * An abstract class which all other crawler classes should derive from.
 */
abstract class JSolrIndexCrawler extends JPlugin 
{
	const STDOUT_SEPARATOR_WIDTH = 90;
	
	protected static $chunk;
	
    /**
     * The extension of the indexed item.
     * 
     * E.g. com_content
     *
     * @var string
     */
	protected $extension;
	
    /**
     * The view of the indexed item.
     * 
     * E.g. article
     *
     * @var string
     */
	protected $view;
	
	/**
     * True if verbose messaging should be output, false otherwise.
     * 
     * @var bool
	 */
	protected $verbose = false;
	
	/**
	 * The class name of the calling object.
	 * @var string
	 */
	protected $caller;
	
	/**
	 * An array of additional params that the crawler may require to 
	 * complete its tasks.
	 * @var array
	 */
	protected $indexingParams = array();

	public function __construct(&$subject, $config = array())
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
		
		self::$chunk = 1000;
		
		// load the jsolrindex component's params into plugin params for 
		// easy access.
		$params = JComponentHelper::getParams('com_jsolrindex', true);

		$this->params->loadArray(array('component'=>$params->toArray()));
		
		Jlog::addLogger(array('text_file'=>'jsolr.php'), JLog::ALL, array('jsolr', 'jsolrcrawler'));
	}
	
	/**
	* Prepares an article for indexing.
	*/
	protected abstract function getDocument(&$record);

	/**
	 * Get's the language, either from the item or from the Joomla environment.
	 * 
	 * @param JObject $item The item being indexed.
	 * @param bool $includeRegion True if the region should be included, false 
	 * otherwise. E.g. If true, en-AU would be returned, if false, just en 
	 * would be returned.
	 * 
	 *  @return string The language code.
	 */
	protected function getLanguage(&$item, $includeRegion = true)
	{
		if (isset($item->language) && $item->language != '*') {			
			$lang = $item->language;
		} else {
			if (!($lang = JLanguageHelper::detectLanguage())) {
				$lang = JFactory::getLanguage()->getDefault();
			}			
		}

		if ($includeRegion) {
			return $lang;
		} else {
			$parts = explode('-', $lang);
			
			// just return the xx part of the xx-XX language.
			return JArrayHelper::getValue($parts, 0);
		}
	}
	
	/**
	 * 
	 * 
	 * @return JDatabaseQuery A database query.
	 */
	abstract protected function buildQuery();
	
	protected function getItems()
	{
		$database = JFactory::getDBO();
		$database->setQuery($this->buildQuery());

		return $database->loadObjectList();
	}
	
	/**
	 * Builds the item's index key.
	 * 
	 * Takes the form extension.view.id, E.g. com_content.article.1.
	 * 
	 * The key can be customized by overriding this method but it is not 
	 * recommended.
	 * 
	 * @param JSolrApacheSolrDocument $document The document to use to build the 
	 * key.
	 * 
	 * @return string The item's key.
	 */
	protected function buildKey($document)
	{
		$extension = $document->getField('extension');
		$extension = JArrayHelper::getValue($extension, 'value');
		$extension = JArrayHelper::getValue($extension, 0);
		$view = $document->getField('view');
		$view = JArrayHelper::getValue($view, 'value');
		$view = JArrayHelper::getValue($view, 0);
		$id = $document->getField('id');
		$id = JArrayHelper::getValue($id, 'value');
		$id = JArrayHelper::getValue($id, 0);
		return $extension.'.'.$view.'.'.$id;
	}
	
	/**
	 * Fires the cleaning event.
	 * 
	 * Derived classes should override the clean() method when implementing 
	 * a custom clean task. 
	 *
	 * @params string caller The calling class.
	 * @params bool verbose True if verbose messaging should be enabled, false 
	 * otherwise. The default is false.
	 * @params array indexingParams A list of options to control various aspects of 
	 * the clean task.
	 */
	public function onClean($caller, $verbose = false, $indexingParams = array())
	{
		$this->set('caller', $caller);
		$this->set('verbose', $verbose);
		$this->set('indexingParams', $indexingParams);

		$this->out(array("task:clean extension:".$this->get('extension'),"[starting]"));
		
		$this->clean();
		
		$this->out(array("task:clean extension:".$this->get('extension'),"[completed]"));
	}

	/**
	 * Fires the indexing event.
	 * 
	 * Derived classes should override the index() method when implementing 
	 * a custom index task.
	 * 
	 * @params string caller The calling class.
	 * @params bool verbose True if verbose messaging should be enabled, false 
	 * otherwise. The default is false.
	 * @params array indexingParams A list of options to control various aspects of 
	 * the clean task.
	 */
	public function onIndex($caller, $verbose = false, $indexingParams = array())
	{
		$this->set('caller', $caller);
		$this->set('verbose', $verbose);
		$this->set('indexingParams', $indexingParams);

		$this->out(array("task:index crawler:".$this->get('extension'),"[starting]"));
		
		$this->index();
		
		$this->out(array("task:index crawler:".$this->get('extension'),"[completed]"));
	}

	/**
	 * Fires the purging event.
	 *
	 * Derived classes should override the purge() method when implementing
	 * a custom purge task.
	 * 
	 * @params string caller The calling class.
	 * @params bool verbose True if verbose messaging should be enabled, false 
	 * otherwise. The default is false.
	 * @params array indexingParams A list of options to control various aspects of 
	 * the clean task. 
	 */
	public function onPurge($caller, $verbose = false, $indexingParams = array())
	{
		$this->set('caller', $caller);
		$this->set('verbose', $verbose);
		$this->set('indexingParams', $indexingParams);
		
		$this->out(array('task;purge extension;'.$this->get('extension'),'[starting]'));
		
		$this->purge();
		
		$this->out(array('task;purge extension;'.$this->get('extension'),'[completed]'));
	}

	/**
	 * 
	 * @param string $context The context of the item being saved.
	 * @param mixed $item The item being deleted (must have an id property).
	 * @param bool $isNew True if the item is new, false otherwise.
	 */
	public function onJSolrIndexAfterSave($context, $item, $isNew)
	{
		if ($context == $this->get('extension').'.'.$this->get('view')) {
			$query = $this->buildQuery()->where('a.id='.$item->id);
				
			$database = JFactory::getDBO();
			$database->setQuery($query);
	
			$document = $this->prepare($database->loadObject());
				
			try {
				$solr = JSolrIndexFactory::getService();
	
				$solr->addDocument($document, false, true, true, $this->params->get('component.commitWithin', '1000'));
			} catch (Exception $e) {
				JLog::add($e->getMessage(), JLog::ERROR, 'jsolrcrawler');
			}
		}
	}

	/**
	 * Triggers an event to delete an indexed item.
	 * 
	 * @param string $context The context of the item being deleted.
	 * @param mixed $item The item being deleted (must have an id property).
	 */
	public function onJSolrIndexAfterDelete($context, $item)
	{
		try {
			if ($context == $this->get('extension').'.'.$this->get('view')) {
				$this->deleteItem($this->get('extension').'.'.$this->get('view').'.'.$item->id);
			}
		} catch (Exception $e) {
			JLog::add($e->getMessage(), JLog::ERROR, 'jsolrcrawler');
		}
	}
	
	/**
	 * A convenience event for adding a record to the index.
	 *
	 * Use this event when the plugin is known but the context is not.
	 *
	 * @param int $id The id of the record being added.
	 */
	public function onItemAdd($id)
	{
		$item = new stdClass();
		$item->id = $id;
	
		$this->onJSolrIndexAfterSave($this->get('extension').'.'.$this->get('view'), $item, true);
	}
	
	/**
	 * A convenience event for deleting an indexed item.
	 * 
	 * Use this event when the plugin is known but the context is not.
	 * 
	 * @param int $id The id of the item being deleted.
	 */
	public function onItemDelete($id)
	{
		$item = new stdClass();
		$item->id = $id;
		
		$this->onJSolrIndexAfterDelete($this->get('extension').'.'.$this->get('view'), $item);
	}
	
	/**
	 * Cleans deleted items from the index.
	 * 
	 * Derived classes should override this method when implementing a custom 
	 * clean operation.
	 */
	protected function clean()
	{
		
	}
	
	/**
	 * Adds items to/edits existing items in the index.
	 * 
	 * Derived classes should override this method when implementing a custom 
	 * index operation.
	 */
	protected function index()
	{
		$total = 0;

		$items = $this->getItems();
		
		$solr = JSolrIndexFactory::getService();
		
		if (is_array($items)) {				
			$documents = array();
			$i = 0;
			
			foreach ($items as $item) {
				$total++;
				$documents[$i] = $this->prepare($item);

				$this->out('document '.$this->buildKey($documents[$i]).' ready for indexing');
				
				$i++;

				// index when either the number of items retrieved matches 
				// the total number of items being indexed or when the 
				// index chunk size has been reached. 
				if ($total == count($items) || $i >= self::$chunk) {						
					$response = $solr->addDocuments($documents, false, true, true, $this->params->get('component.commitsWithin', '10000'));
											
					$this->out($i.'documents indexed [status:'.$response->getHttpStatus().']');
					
					$documents = array();
					$i = 0;
				}					
			}			
		}
					
		$this->out("items indexed: $total");
	}

	/**
	 * Permanently removes all items in the index which are managed by the 
	 * associated plugin..
	 * 
	 * Derived classes should override this method when implementing a custom 
	 * purge operation.
	 */
	protected function purge()
	{	
		$solr = JSolrIndexFactory::getService();
		$solr->deleteByQuery("extension:".$this->get('extension'));
		$solr->commit();
	}
	
	/**
	 * Deletes a single item from the index.
	 * 
	 * @param string $key The unique key of the item to delete.
	 */
	protected function deleteItem($key)
	{
		$solr = JSolrIndexFactory::getService();
		$solr->deleteById($key);
		$solr->commit();
	}
	
	/**
	 * Prepare the item for indexing.
	 * 
	 * @param stdClass $item
	 * @return JSolrApacheSolrDocument
	 */
	protected function prepare($item)
	{
		// Initialize the item's parameters.
		if (isset($item->params)) {
			$registry = new JRegistry();
			$registry->loadString($item->params);
			$item->params = JComponentHelper::getParams($this->get('extension'), true);
			$item->params->merge($registry);
		}
		
		if (isset($item->metadata)) {
			$registry = new JRegistry();
			$registry->loadString($item->metadata);
			$item->metadata = $registry;
		}
	
		$document = $this->getDocument($item);
		$document->addField('id', $item->id);
		$document->addField('extension', $this->get('extension'));
		$document->addField('view', $this->get('view'));
		$document->addField('lang', $this->getLanguage($item));
		
		$key = $this->buildKey($document);
		
		$document->addField('key', $key);

		return $document;
	}
	
	/**
	 * Command line formmatted output.
	 * 
	 * @param mixed $text String or array. 
	 * To provide a description with a message, E.g.
	 * 
	 * indexing				[started]
	 * 
	 * pass a 2 dimensional array; array('indexing', '[started]');
	 * 
	 * @param bool $nl True if a new line character should be appended, false 
	 * otherwise. The default is true.
	 * @return JSolrIndexCrawler Returns $this for chaining output.
	 */
	protected function out($text = '', $nl = true)
	{		
		if ($this->get('caller') == 'JSolrCrawlerCli') {
			if ($this->get('verbose')) {
				if (is_array($text)) {
					if (count($text) == 2) {
						$length = self::STDOUT_SEPARATOR_WIDTH - (strlen($text[0]) + strlen($text[1]));
						$text = implode(str_repeat(' ', ($length > 0) ? $length : 1), $text);
					} else if (count($text) > 2) {
						$text = implode(' ', $text);
					} else {
						$text = implode('', $text);
					}
					
					
				}
				
				fwrite(STDOUT, $text . ($nl ? "\n" : null));
			}
		}
	
		return $this;
	}
	
	protected function getAllowedContentTypes()
	{
		$types = $this->params->get('component.content_types_allowed');
		
		return array_map('trim', explode(',', trim($types)));
	}
	
	protected function getIndexContentContentTypes()
	{
		$types = $this->params->get('component.content_types_index_content');
		
		return array_map('trim', explode(',', trim($types)));
	}
	
	protected function isAllowedContentType($contentType)
	{
		$allowed = false;
		
		$types = $this->getAllowedContentTypes();

		while ((($type = current($types)) !== false) && !$allowed) {
			if (preg_match("#".$type."#i", $contentType)) {
				$allowed = true;
			}
			
			next($types);
		}
		
		return $allowed;
			
	}
	
	protected function isContentIndexable($contentType)
	{
		$allowed = false;
	
		$types = $this->getIndexContentContentTypes();
	
		while ((($type = current($types)) !== false) && !$allowed) {
			if (preg_match("#".$type."#i", $contentType)) {
				$allowed = true;
			}
				
			next($types);
		}
	
		return $allowed;
			
	}
	
	/**
	 * Gets a formatted facet based on the JSolrIndex configuration.
	 * 
	 * @param string $facet
	 * 
	 * @return string A formatted facet based on the JSolrIndex configuration.
	 */
	protected function getFacet($facet)
	{
		switch (intval($this->params->get('component.casesensitivity'))) {
			case 1:
				return JString::strtolower($facet);
				break;
	
			case 2:
				return JSolrHelper::toCaseInsensitiveFacet($facet);
				break;
					
			default:
				return $facet;
				break;
		}
	}
	
	/**
	 * Extracts a file's contents and metadata.
	 *
	 * To access the returned result's contents and metadata, use the
	 * properties body and metadata.
	 *
	 * @example
	 * $result = $this->_extract($path);
	 * $body = $result->body;
	 * $metadata = $result->metadata;
	 *
	 * @param string $path
	 * @param bool $excludeContent True if the content should also be excluded from extraction, false otherwise. Defaults to false.
	 * @return stdClass An object containing the file's body and metadata.
	 */
	protected function extract($path, $excludeContent = false)
	{
		$document = new stdClass();
	
		switch ($this->params->get('component.extractor')) {
			case "local":
				ob_start();
				passthru("java -jar ".$this->params->get('component.local_tika_app_path')." -d ".$path." 2> /dev/null");
				$result = ob_get_contents();
				ob_end_clean();
	
				// sometimes the charset is appended to the file type.
				$contentType = JArrayHelper::getValue(array_map('trim', explode(';', trim($result))), 0);
	
				if ($this->isAllowedContentType($contentType) == 1) {
					if (!$excludeContent && $this->isContentIndexable($contentType) == 1) {
						$this->out(array($path, "[extracting]"));
						ob_start();
						passthru("java -jar ".$this->params->get('component.local_tika_app_path')." ".$path." 2> /dev/null");
						$result = ob_get_contents();
						ob_end_clean();
						$document->body = $result;
					}
	
					ob_start();
					passthru("java -jar ".$this->params->get('component.local_tika_app_path')." -j ".$path." 2> /dev/null");
					$result = ob_get_contents();
					ob_end_clean();
	
					$document->metadata = new JRegistry();
					$document->metadata->loadString($result);
				} else {
					$document = null;
				}
	
				break;
	
			case "solr":
				// @todo not fully implemented. Needs allowed types and index content conditions.
				$url = $this->params->get('component.host');
	
				if ($username = $this->params->get('component.username') &&
						$password = $this->params->get('component.password')) {
					$url = $username . ":" . $password . "@" . $url;
				}
	
				$solr = new JSolrApacheSolrService($url, $this->params->get('component.port'), $this->params->get('component.path'));
	
				$extraction = $solr->extract($path, array("extractOnly"=>"true"));
	
				$response = json_decode($extraction->getRawResponse(), true);
	
				$document->content = $response[""];
	
				$metadata = array();
	
				foreach ($response->null_metadata as $key=>$value) {
					$metadata[$key] = JArrayHelper::getValue($value, 0);
				}
	
				$metadata = new JRegistry();
				$metadata->loadArray($data);
	
				$document->metadata = new JRegistry();
				$document->metadata->loadArray($metadata);
	
				break;
	
			default:
	
				break;
		}
	
		return $document;
	}
}