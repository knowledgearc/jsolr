<?php
/**
 * Supports a text field
 * 
 * @author		$LastChangedBy: bartlomiejkielbasa $
 * @package		JSolr
 *
 * @author Bartlomiej Kielbasa <bartlomiejkielbasa@wijiti.com> * * 
 */

defined('JPATH_BASE') or die;

jimport('jsolr.form.fields.abstract');

class JSolrFormFieldText extends JSolrFormFieldAbstract
{
	protected $type = 'JSolr.Text';
	
	/**
	 * @inheritdoc
	 */
	function getInputFacetFilter()
	{
		$name = (string)$this->element['name'];

		$attr = '';
		
		// Initialize some field attributes.
		$attr .= $this->element['class'] ? ' class="'.(string) $this->element['class'].'"' : '';
		
		// To avoid user's confusion, readonly="true" should imply disabled="true".
		if ( (string) $this->element['readonly'] == 'true' || (string) $this->element['disabled'] == 'true') {
			$attr .= ' disabled="disabled"';
		}
		
		$attr .= $this->element['size'] ? ' size="'.(int) $this->element['size'].'"' : '';
		$attr .= $this->multiple ? ' multiple="multiple"' : '';
		
		// Initialize JavaScript field attributes.
		$attr .= $this->element['onchange'] ? ' onchange="'.(string) $this->element['onchange'].'"' : '';
		
		return '<input type="text" name="' . htmlspecialchars($this->name) . '" value="' .htmlspecialchars($this->value, ENT_COMPAT, 'UTF-8') . '" id="' . $this->element['name'] . '" ' . $attr . '/>';
	}
	
	/**
	 * @inheritdoc
	 */
	function getInputSearchTool()
	{
		$name = (string)$this->element['name'];

		$attr = '';
		
		// Initialize some field attributes.
		$attr .= $this->element['class'] ? ' class="'.(string) $this->element['class'].'"' : '';
		
		// To avoid user's confusion, readonly="true" should imply disabled="true".
		if ( (string) $this->element['readonly'] == 'true' || (string) $this->element['disabled'] == 'true') {
			$attr .= ' disabled="disabled"';
		}
		
		$attr .= $this->element['size'] ? ' size="'.(int) $this->element['size'].'"' : '';
		$attr .= $this->multiple ? ' multiple="multiple"' : '';
		
		// Initialize JavaScript field attributes.
		$attr .= $this->element['onchange'] ? ' onchange="'.(string) $this->element['onchange'].'"' : '';
		
		return '<input type="text" name="' . htmlspecialchars($this->name) . '" value="' . htmlspecialchars($this->value, ENT_COMPAT, 'UTF-8') . '" id="' . $this->element['name'] . '" ' . $attr . '/>';
	}
	
	function getFilter()
	{
		$filter = '';
		$name = (string)$this->element['name'];
		$facet = (string)$this->element['facet'];

		$value = htmlspecialchars($this->value, ENT_COMPAT, 'UTF-8');

		if (empty($value)) {
			return '';
		}

		return $facet . ':' . $value . '';
	}
	
	public function fillQuery() {
		$filter = $this->getFilter();

		if( !empty($filter) ) {
			$this->form->getQuery()
				->mergeFilters( $filter );
		}
	}
}