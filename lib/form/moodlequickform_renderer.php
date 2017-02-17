<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * MoodleQuickForm renderer
 *
 * A renderer for MoodleQuickForm that only uses XHTML and CSS and no
 * table tags, extends PEAR class HTML_QuickForm_Renderer_Tableless
 *
 * Stylesheet is part of standard theme and should be automatically included.
 *
 * @package   core_form
 * @copyright 2007 Jamie Pratt <me@jamiep.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once 'HTML/QuickForm2/Renderer.php';

// class MoodleQuickForm_Renderer extends HTML_QuickForm_Renderer_Tableless{
class MoodleQuickForm_Renderer extends HTML_QuickForm2_Renderer{

	/** @var array Element template array */
	var $_elementTemplates;

	/**
	 * Template used when opening a hidden fieldset
	 * (i.e. a fieldset that is opened when there is no header element)
	 * @var string
	 */
	var $_openHiddenFieldsetTemplate = "\n\t<fieldset class=\"hidden\"><div>";

	/** @var string Header Template string */
	var $_headerTemplate =
	"\n\t\t<legend class=\"ftoggler\">{header}</legend>\n\t\t<div class=\"fcontainer clearfix\">\n\t\t";

	/** @var string Template used when opening a fieldset */
	var $_openFieldsetTemplate = "\n\t<fieldset class=\"{classes}\" {id}>";

	/** @var string Template used when closing a fieldset */
	var $_closeFieldsetTemplate = "\n\t\t</div></fieldset>";

	/** @var string Required Note template string */
	var $_requiredNoteTemplate =
	"\n\t\t<div class=\"fdescription required\">{requiredNote}</div>";

	/**
	 * Collapsible buttons string template.
	 *
	 * Note that the <span> will be converted as a link. This is done so that the link is not yet clickable
	 * until the Javascript has been fully loaded.
	 *
	 * @var string
	 */
	var $_collapseButtonsTemplate =
	"\n\t<div class=\"collapsible-actions\"><span class=\"collapseexpand\">{strexpandall}</span></div>";

	/**
	 * Array whose keys are element names. If the key exists this is a advanced element
	 *
	 * @var array
	 */
	var $_advancedElements = array();

	/**
	 * Array whose keys are element names and the the boolean values reflect the current state. If the key exists this is a collapsible element.
	 *
	 * @var array
	 */
	var $_collapsibleElements = array();

	/**
	 * @var string Contains the collapsible buttons to add to the form.
	 */
	var $_collapseButtons = '';

	/**
	 * Constructor
	 */
	public function __construct() {
		// switch next two lines for ol li containers for form items.
		//        $this->_elementTemplates=array('default'=>"\n\t\t".'<li class="fitem"><label>{label}{help}<!-- BEGIN required -->{req}<!-- END required --></label><div class="qfelement<!-- BEGIN error --> error<!-- END error --> {typeclass}"><!-- BEGIN error --><span class="error">{error}</span><br /><!-- END error -->{element}</div></li>');
		$this->_elementTemplates = array(
				'default' => "\n\t\t".'<div id="{id}" class="fitem {advanced}<!-- BEGIN required --> required<!-- END required --> fitem_{typeclass} {emptylabel} {class}" {aria-live}><div class="fitemtitle"><label>{label}<!-- BEGIN required -->{req}<!-- END required -->{advancedimg} </label>{help}</div><div class="felement {typeclass}<!-- BEGIN error --> error<!-- END error -->" data-fieldtype="{type}"><!-- BEGIN error --><span class="error" tabindex="0">{error}</span><br /><!-- END error -->{element}</div></div>',

				'actionbuttons' => "\n\t\t".'<div id="{id}" class="fitem fitem_actionbuttons fitem_{typeclass} {class}"><div class="felement {typeclass}" data-fieldtype="{type}">{element}</div></div>',

				'fieldset' => "\n\t\t".'<div id="{id}" class="fitem {advanced} {class}<!-- BEGIN required --> required<!-- END required --> fitem_{typeclass} {emptylabel}"><div class="fitemtitle"><div class="fgrouplabel"><label>{label}<!-- BEGIN required -->{req}<!-- END required -->{advancedimg} </label>{help}</div></div><fieldset class="felement {typeclass}<!-- BEGIN error --> error<!-- END error -->" data-fieldtype="{type}"><!-- BEGIN error --><span class="error" tabindex="0">{error}</span><br /><!-- END error -->{element}</fieldset></div>',

				'static' => "\n\t\t".'<div id="{id}" class="fitem {advanced} {emptylabel} {class}"><div class="fitemtitle"><div class="fstaticlabel">{label}<!-- BEGIN required -->{req}<!-- END required -->{advancedimg} {help}</div></div><div class="felement fstatic <!-- BEGIN error --> error<!-- END error -->" data-fieldtype="static"><!-- BEGIN error --><span class="error" tabindex="0">{error}</span><br /><!-- END error -->{element}</div></div>',

				'warning' => "\n\t\t".'<div id="{id}" class="fitem {advanced} {emptylabel} {class}">{element}</div>',

				'nodisplay' => '');

		parent::__construct();
	}

	/**
	 * Old syntax of class constructor. Deprecated in PHP7.
	 *
	 * @deprecated since Moodle 3.1
	 */
	public function MoodleQuickForm_Renderer() {
		debugging('Use of class name as constructor is deprecated', DEBUG_DEVELOPER);
		self::__construct();
	}

	/**
	 * Set element's as adavance element
	 *
	 * @param array $elements form elements which needs to be grouped as advance elements.
	 */
	function setAdvancedElements($elements){
		$this->_advancedElements = $elements;
	}

	/**
	 * Setting collapsible elements
	 *
	 * @param array $elements
	 */
	function setCollapsibleElements($elements) {
		$this->_collapsibleElements = $elements;
	}

	/**
	 * What to do when starting the form
	 *
	 * @param MoodleQuickForm $form reference of the form
	 */
	function startForm(&$form){
		global $PAGE;
		$this->_reqHTML = $form->getReqHTML();
		$this->_elementTemplates = str_replace('{req}', $this->_reqHTML, $this->_elementTemplates);
		$this->_advancedHTML = $form->getAdvancedHTML();
		$this->_collapseButtons = '';
		$formid = $form->getAttribute('id');
		parent::startForm($form);
		if ($form->isFrozen()){
			$this->_formTemplate = "\n<div class=\"mform frozen\">\n{content}\n</div>";
		} else {
			$this->_formTemplate = "\n<form{attributes}>\n\t<div style=\"display: none;\">{hidden}</div>\n{collapsebtns}\n{content}\n</form>";
			$this->_hiddenHtml .= $form->_pageparams;
		}

		if ($form->is_form_change_checker_enabled()) {
			$PAGE->requires->yui_module('moodle-core-formchangechecker',
					'M.core_formchangechecker.init',
					array(array(
							'formid' => $formid
					))
					);
			$PAGE->requires->string_for_js('changesmadereallygoaway', 'moodle');
		}
		if (!empty($this->_collapsibleElements)) {
			if (count($this->_collapsibleElements) > 1) {
				$this->_collapseButtons = $this->_collapseButtonsTemplate;
				$this->_collapseButtons = str_replace('{strexpandall}', get_string('expandall'), $this->_collapseButtons);
				$PAGE->requires->strings_for_js(array('collapseall', 'expandall'), 'moodle');
			}
			$PAGE->requires->yui_module('moodle-form-shortforms', 'M.form.shortforms', array(array('formid' => $formid)));
		}
		if (!empty($this->_advancedElements)){
			$PAGE->requires->strings_for_js(array('showmore', 'showless'), 'form');
			$PAGE->requires->yui_module('moodle-form-showadvanced', 'M.form.showadvanced', array(array('formid' => $formid)));
		}
	}

	/**
	 * Create advance group of elements
	 *
	 * @param MoodleQuickForm_group $group Passed by reference
	 * @param bool $required if input is required field
	 * @param string $error error message to display
	 */
	function startGroup(&$group, $required, $error){
		global $OUTPUT;

		// Make sure the element has an id.
		$group->_generateId();

		// Prepend 'fgroup_' to the ID we generated.
		$groupid = 'fgroup_' . $group->getAttribute('id');

		// Update the ID.
		$group->updateAttributes(array('id' => $groupid));
		$advanced = isset($this->_advancedElements[$group->getName()]);

		$html = $OUTPUT->mform_element($group, $required, $advanced, $error, false);
		$fromtemplate = !empty($html);
		if (!$fromtemplate) {
			if (method_exists($group, 'getElementTemplateType')) {
				$html = $this->_elementTemplates[$group->getElementTemplateType()];
			} else {
				$html = $this->_elementTemplates['default'];
			}

			if (isset($this->_advancedElements[$group->getName()])) {
				$html = str_replace(' {advanced}', ' advanced', $html);
				$html = str_replace('{advancedimg}', $this->_advancedHTML, $html);
			} else {
				$html = str_replace(' {advanced}', '', $html);
				$html = str_replace('{advancedimg}', '', $html);
			}
			if (method_exists($group, 'getHelpButton')) {
				$html = str_replace('{help}', $group->getHelpButton(), $html);
			} else {
				$html = str_replace('{help}', '', $html);
			}
			$html = str_replace('{id}', $group->getAttribute('id'), $html);
			$html = str_replace('{name}', $group->getName(), $html);
			$html = str_replace('{typeclass}', 'fgroup', $html);
			$html = str_replace('{type}', 'group', $html);
			$html = str_replace('{class}', $group->getAttribute('class'), $html);
			$emptylabel = '';
			if ($group->getLabel() == '') {
				$emptylabel = 'femptylabel';
			}
			$html = str_replace('{emptylabel}', $emptylabel, $html);
		}
		$this->_templates[$group->getName()] = $html;
		// Fix for bug in tableless quickforms that didn't allow you to stop a
		// fieldset before a group of elements.
		// if the element name indicates the end of a fieldset, close the fieldset
		if (in_array($group->getName(), $this->_stopFieldsetElements) && $this->_fieldsetsOpen > 0) {
			$this->_html .= $this->_closeFieldsetTemplate;
			$this->_fieldsetsOpen--;
		}
		if (!$fromtemplate) {
			parent::startGroup($group, $required, $error);
		} else {
			$this->_html .= $html;
		}
	}

	/**
	 * Renders element
	 *
	 * @param HTML_QuickForm_element $element element
	 * @param bool $required if input is required field
	 * @param string $error error message to display
	 */
	function renderElement(&$element, $required, $error){
		global $OUTPUT;

		// Make sure the element has an id.
		$element->_generateId();
		$advanced = isset($this->_advancedElements[$element->getName()]);

		$html = $OUTPUT->mform_element($element, $required, $advanced, $error, false);
		$fromtemplate = !empty($html);
		if (!$fromtemplate) {
			// Adding stuff to place holders in template
			// check if this is a group element first.
			if (($this->_inGroup) and !empty($this->_groupElementTemplate)) {
				// So it gets substitutions for *each* element.
				$html = $this->_groupElementTemplate;
			} else if (method_exists($element, 'getElementTemplateType')) {
				$html = $this->_elementTemplates[$element->getElementTemplateType()];
			} else {
				$html = $this->_elementTemplates['default'];
			}
			if (isset($this->_advancedElements[$element->getName()])) {
				$html = str_replace(' {advanced}', ' advanced', $html);
				$html = str_replace(' {aria-live}', ' aria-live="polite"', $html);
			} else {
				$html = str_replace(' {advanced}', '', $html);
				$html = str_replace(' {aria-live}', '', $html);
			}
			if (isset($this->_advancedElements[$element->getName()]) || $element->getName() == 'mform_showadvanced') {
				$html = str_replace('{advancedimg}', $this->_advancedHTML, $html);
			} else {
				$html = str_replace('{advancedimg}', '', $html);
			}
			$html = str_replace('{id}', 'fitem_' . $element->getAttribute('id'), $html);
			$html = str_replace('{typeclass}', 'f' . $element->getType(), $html);
			$html = str_replace('{type}', $element->getType(), $html);
			$html = str_replace('{name}', $element->getName(), $html);
			$html = str_replace('{class}', $element->getAttribute('class'), $html);
			$emptylabel = '';
			if ($element->getLabel() == '') {
				$emptylabel = 'femptylabel';
			}
			$html = str_replace('{emptylabel}', $emptylabel, $html);
			if (method_exists($element, 'getHelpButton')) {
				$html = str_replace('{help}', $element->getHelpButton(), $html);
			} else {
				$html = str_replace('{help}', '', $html);
			}
		} else {
			if ($this->_inGroup) {
				$this->_groupElementTemplate = $html;
			}
		}
		if (($this->_inGroup) and !empty($this->_groupElementTemplate)) {
			$this->_groupElementTemplate = $html;
		} else if (!isset($this->_templates[$element->getName()])) {
			$this->_templates[$element->getName()] = $html;
		}

		if (!$fromtemplate) {
			parent::renderElement($element, $required, $error);
		} else {
			if (in_array($element->getName(), $this->_stopFieldsetElements) && $this->_fieldsetsOpen > 0) {
				$this->_html .= $this->_closeFieldsetTemplate;
				$this->_fieldsetsOpen--;
			}
			$this->_html .= $html;
		}
	}

	/**
	 * Called when visiting a form, after processing all form elements
	 * Adds required note, form attributes, validation javascript and form content.
	 *
	 * @global moodle_page $PAGE
	 * @param moodleform $form Passed by reference
	 */
	function finishForm(&$form){
		global $PAGE;
		if ($form->isFrozen()){
			$this->_hiddenHtml = '';
		}
		parent::finishForm($form);
		$this->_html = str_replace('{collapsebtns}', $this->_collapseButtons, $this->_html);
		if (!$form->isFrozen()) {
			$args = $form->getLockOptionObject();
			if (count($args[1]) > 0) {
				$PAGE->requires->js_init_call('M.form.initFormDependencies', $args, true, moodleform::get_js_module());
			}
		}
	}
	/**
	 * Called when visiting a header element
	 *
	 * @param HTML_QuickForm_header $header An HTML_QuickForm_header element being visited
	 * @global moodle_page $PAGE
	 */
	function renderHeader(&$header) {
		global $PAGE;

		$header->_generateId();
		$name = $header->getName();

		$id = empty($name) ? '' : ' id="' . $header->getAttribute('id') . '"';
		if (is_null($header->_text)) {
			$header_html = '';
		} elseif (!empty($name) && isset($this->_templates[$name])) {
			$header_html = str_replace('{header}', $header->toHtml(), $this->_templates[$name]);
		} else {
			$header_html = str_replace('{header}', $header->toHtml(), $this->_headerTemplate);
		}

		if ($this->_fieldsetsOpen > 0) {
			$this->_html .= $this->_closeFieldsetTemplate;
			$this->_fieldsetsOpen--;
		}

		// Define collapsible classes for fieldsets.
		$arialive = '';
		$fieldsetclasses = array('clearfix');
		if (isset($this->_collapsibleElements[$header->getName()])) {
			$fieldsetclasses[] = 'collapsible';
			if ($this->_collapsibleElements[$header->getName()]) {
				$fieldsetclasses[] = 'collapsed';
			}
		}

		if (isset($this->_advancedElements[$name])){
			$fieldsetclasses[] = 'containsadvancedelements';
		}

		$openFieldsetTemplate = str_replace('{id}', $id, $this->_openFieldsetTemplate);
		$openFieldsetTemplate = str_replace('{classes}', join(' ', $fieldsetclasses), $openFieldsetTemplate);

		$this->_html .= $openFieldsetTemplate . $header_html;
		$this->_fieldsetsOpen++;
	}

	/**
	 * Return Array of element names that indicate the end of a fieldset
	 *
	 * @return array
	 */
	function getStopFieldsetElements(){
		return $this->_stopFieldsetElements;
	}
}
