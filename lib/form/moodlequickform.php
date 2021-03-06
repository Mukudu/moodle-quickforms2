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
 * MoodleQuickForm implementation
 *
 * You never extend this class directly. The class methods of this class are available from
 * the private $this->_form property on moodleform and its children. You generally only
 * call methods on this class from within abstract methods that you override on moodleform such
 * as definition and definition_after_data
 *
 * @package   core_form
 * @category  form
 * @copyright 2006 Jamie Pratt <me@jamiep.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once 'HTML/QuickForm2.php';

//class MoodleQuickForm extends HTML_QuickForm_DHTMLRulesTableless {
class MoodleQuickForm {

	/** @var QuickForm2 object definition */
	var $_qform2;

	/** @var array type (PARAM_INT, PARAM_TEXT etc) of element value */
	var $_types = array();

	/** @var array dependent state for the element/'s */
	var $_dependencies = array();

	/** @var array Array of buttons that if pressed do not result in the processing of the form. */
	var $_noSubmitButtons=array();

	/** @var array Array of buttons that if pressed do not result in the processing of the form. */
	var $_cancelButtons=array();

	/** @var array Array whose keys are element names. If the key exists this is a advanced element */
	var $_advancedElements = array();

	/**
	 * Array whose keys are element names and values are the desired collapsible state.
	 * True for collapsed, False for expanded. If not present, set to default in
	 * {@link self::accept()}.
	 *
	 * @var array
	 */
	var $_collapsibleElements = array();

	/**
	 * Whether to enable shortforms for this form
	 *
	 * @var boolean
	 */
	var $_disableShortforms = false;

	/** @var bool whether to automatically initialise M.formchangechecker for this form. */
	protected $_use_form_change_checker = true;

	/**
	 * The form name is derived from the class name of the wrapper minus the trailing form
	 * It is a name with words joined by underscores whereas the id attribute is words joined by underscores.
	 * @var string
	 */
	var $_formName = '';

	/**
	 * String with the html for hidden params passed in as part of a moodle_url
	 * object for the action. Output in the form.
	 * @var string
	 */
	var $_pageparams = '';

	/**
	 * Whether the form contains any client-side validation or not.
	 * @var bool
	 */
	protected $clientvalidation = false;

	/**
	 * Class constructor - same parameters as HTML_QuickForm_DHTMLRulesTableless
	 *
	 * @staticvar int $formcounter counts number of forms
	 * @param string $formName Form's name.
	 * @param string $method Form's method defaults to 'POST'
	 * @param string|moodle_url $action Form's action
	 * @param string $target (optional)Form's target defaults to none
	 * @param mixed $attributes (optional)Extra attributes for <form> tag
	 */
	public function __construct($formName, $method, $action, $target='', $attributes=null) {
		global $CFG, $OUTPUT;

		static $formcounter = 1;

		// TODO MDL-52313 Replace with the call to parent::__construct().
		//HTML_Common2::__construct($attributes);         // just merges attributes

		$target = empty($target) ? array() : array('target' => $target);

		$this->_formName = $formName;
		if (is_a($action, 'moodle_url')){
			$this->_pageparams = html_writer::input_hidden_params($action);
			$action = $action->out_omit_querystring();
		} else {
			$this->_pageparams = '';
		}
		// No 'name' atttribute for form in xhtml strict :
		$attributes = array('action' => $action, 'method' => $method, 'accept-charset' => 'utf-8') + $target;
		if (is_null($this->getAttribute('id'))) {
			$attributes['id'] = 'mform' . $formcounter;
		}
		$formcounter++;
		//$this->updateAttributes($attributes);

                /*
           HTML_QuickForm HTML_QuickForm( [string $formName = ''], [string $method = 'post'], [string $action = ''], [string $target = ''], [mixed $attributes = null], [bool $trackSubmit = false])
            string   	$formName   	—  	Form's name.
            string   	$method   	—  	(optional)Form's method defaults to 'POST'
            string   	$action   	—  	(optional)Form's action
            string   	$target   	—  	(optional)Form's target defaults to '_self'
            mixed   	$attributes   	—  	(optional)Extra attributes for <form> tag
            bool   	$trackSubmit   	—  	(optional)Whether to track if the form was submitted by adding a special hidden field

           HTML_QuickForm2 __construct( string $id, [string $method = 'post'], [string|array $attributes = null], [bool $trackSubmit = true])
            string   	$id   	—  	"id" attribute of <form> tag
            string   	$method   	—  	HTTP method used to submit the form
            string|array   	$attributes   	—  	Additional HTML attributes (either a string or an array)
            bool   	$trackSubmit   	—  	Whether to track if the form was submitted by adding a special hidden field
         */
        $tracksubmit = false;           // default in QF1 was false - true in QF2
        $this->_qform2 = new HTML_QuickForm2($formName, $method, $attributes, $tracksubmit);
        HTML_Common2::__construct($attributes);         // just merges attributes

		// This is custom stuff for Moodle :
		$oldclass=   $this->_qform2->getAttribute('class');
		if (!empty($oldclass)){
			$this->_qform2->updateAttributes(array('class'=>$oldclass.' mform'));
		}else {
			$this->_qform2->updateAttributes(array('class'=>'mform'));
		}
		$this->_qform2->_reqHTML = '<img class="req" title="'.get_string('requiredelement', 'form').'" alt="'.get_string('requiredelement', 'form').'" src="'.$OUTPUT->pix_url('req') .'" />';
		$this->_qform2->_advancedHTML = '<img class="adv" title="'.get_string('advancedelement', 'form').'" alt="'.get_string('advancedelement', 'form').'" src="'.$OUTPUT->pix_url('adv') .'" />';
		$this->setRequiredNote(get_string('somefieldsrequired', 'form', '<img alt="'.get_string('requiredelement', 'form').'" src="'.$OUTPUT->pix_url('req') .'" />'));
	}

	/**
	 * Old syntax of class constructor. Deprecated in PHP7.
	 *
	 * @deprecated since Moodle 3.1
	 */
	public function MoodleQuickForm($formName, $method, $action, $target='', $attributes=null) {
		debugging('Use of class name as constructor is deprecated', DEBUG_DEVELOPER);
		self::__construct($formName, $method, $action, $target, $attributes);
	}

	/**
	 * Use this method to indicate an element in a form is an advanced field. If items in a form
	 * are marked as advanced then 'Hide/Show Advanced' buttons will automatically be displayed in the
	 * form so the user can decide whether to display advanced form controls.
	 *
	 * If you set a header element to advanced then all elements it contains will also be set as advanced.
	 *
	 * @param string $elementName group or element name (not the element name of something inside a group).
	 * @param bool $advanced default true sets the element to advanced. False removes advanced mark.
	 */
	function setAdvanced($elementName, $advanced = true) {
		if ($advanced){
			$this->_advancedElements[$elementName]='';
		} elseif (isset($this->_advancedElements[$elementName])) {
			unset($this->_advancedElements[$elementName]);
		}
	}

	/**
	 * Use this method to indicate that the fieldset should be shown as expanded.
	 * The method is applicable to header elements only.
	 *
	 * @param string $headername header element name
	 * @param boolean $expanded default true sets the element to expanded. False makes the element collapsed.
	 * @param boolean $ignoreuserstate override the state regardless of the state it was on when
	 *                                 the form was submitted.
	 * @return void
	 */
	function setExpanded($headername, $expanded = true, $ignoreuserstate = false) {
		if (empty($headername)) {
			return;
		}
		$element = $this->_qform2->getElement($headername);
		if ($element->getType() != 'header') {
			debugging('Cannot use setExpanded on non-header elements', DEBUG_DEVELOPER);
			return;
		}
		if (!$headerid = $element->getAttribute('id')) {
			$element->_generateId();
			$headerid = $element->getAttribute('id');
		}
		if ($this->_qform2->getElementType('mform_isexpanded_' . $headerid) === false) {
			// See if the form has been submitted already.
			$formexpanded = optional_param('mform_isexpanded_' . $headerid, -1, PARAM_INT);
			if (!$ignoreuserstate && $formexpanded != -1) {
				// Override expanded state with the form variable.
				$expanded = $formexpanded;
			}
			// Create the form element for storing expanded state.
			$this->_qform2->addElement('hidden', 'mform_isexpanded_' . $headerid);
			$this->setType('mform_isexpanded_' . $headerid, PARAM_INT);
			$this->setConstant('mform_isexpanded_' . $headerid, (int) $expanded);
		}
		$this->_collapsibleElements[$headername] = !$expanded;
	}

	/**
	 * Use this method to add show more/less status element required for passing
	 * over the advanced elements visibility status on the form submission.
	 *
	 * @param string $headerName header element name.
	 * @param boolean $showmore default false sets the advanced elements to be hidden.
	 */
	function addAdvancedStatusElement($headerid, $showmore=false){
		// Add extra hidden element to store advanced items state for each section.
		if ($this->getElementType('mform_showmore_' . $headerid) === false) {
			// See if we the form has been submitted already.
			$formshowmore = optional_param('mform_showmore_' . $headerid, -1, PARAM_INT);
			if (!$showmore && $formshowmore != -1) {
				// Override showmore state with the form variable.
				$showmore = $formshowmore;
			}
			// Create the form element for storing advanced items state.
			$this->addElement('hidden', 'mform_showmore_' . $headerid);
			$this->setType('mform_showmore_' . $headerid, PARAM_INT);
			$this->setConstant('mform_showmore_' . $headerid, (int)$showmore);
		}
	}

	/**
	 * This function has been deprecated. Show advanced has been replaced by
	 * "Show more.../Show less..." in the shortforms javascript module.
	 *
	 * @deprecated since Moodle 2.5
	 * @param bool $showadvancedNow if true will show advanced elements.
	 */
	function setShowAdvanced($showadvancedNow = null){
		debugging('Call to deprecated function setShowAdvanced. See "Show more.../Show less..." in shortforms yui module.');
	}

	/**
	 * This function has been deprecated. Show advanced has been replaced by
	 * "Show more.../Show less..." in the shortforms javascript module.
	 *
	 * @deprecated since Moodle 2.5
	 * @return bool (Always false)
	 */
	function getShowAdvanced(){
		debugging('Call to deprecated function setShowAdvanced. See "Show more.../Show less..." in shortforms yui module.');
		return false;
	}

	/**
	 * Use this method to indicate that the form will not be using shortforms.
	 *
	 * @param boolean $disable default true, controls if the shortforms are disabled.
	 */
	function setDisableShortforms ($disable = true) {
		$this->_disableShortforms = $disable;
	}

	/**
	 * Call this method if you don't want the formchangechecker JavaScript to be
	 * automatically initialised for this form.
	 */
	public function disable_form_change_checker() {
		$this->_use_form_change_checker = false;
	}

	/**
	 * If you have called {@link disable_form_change_checker()} then you can use
	 * this method to re-enable it. It is enabled by default, so normally you don't
	 * need to call this.
	 */
	public function enable_form_change_checker() {
		$this->_use_form_change_checker = true;
	}

	/**
	 * @return bool whether this form should automatically initialise
	 *      formchangechecker for itself.
	 */
	public function is_form_change_checker_enabled() {
		return $this->_use_form_change_checker;
	}

	/**
	 * Accepts a renderer
	 *
	 * @param HTML_QuickForm_Renderer $renderer An HTML_QuickForm_Renderer object
	 */
	function accept(&$renderer) {
		if (method_exists($renderer, 'setAdvancedElements')){
			//Check for visible fieldsets where all elements are advanced
			//and mark these headers as advanced as well.
			//Also mark all elements in a advanced header as advanced.
			$stopFields = $renderer->getStopFieldSetElements();
			$lastHeader = null;
			$lastHeaderAdvanced = false;
			$anyAdvanced = false;
			$anyError = false;
			foreach (array_keys($this->_elements) as $elementIndex){
				$element =& $this->_elements[$elementIndex];

				// if closing header and any contained element was advanced then mark it as advanced
				if ($element->getType()=='header' || in_array($element->getName(), $stopFields)){
					if ($anyAdvanced && !is_null($lastHeader)) {
						$lastHeader->_generateId();
						$this->setAdvanced($lastHeader->getName());
						$this->addAdvancedStatusElement($lastHeader->getAttribute('id'), $anyError);
					}
					$lastHeaderAdvanced = false;
					unset($lastHeader);
					$lastHeader = null;
				} elseif ($lastHeaderAdvanced) {
					$this->setAdvanced($element->getName());
				}

				if ($element->getType()=='header'){
					$lastHeader =& $element;
					$anyAdvanced = false;
					$anyError = false;
					$lastHeaderAdvanced = isset($this->_advancedElements[$element->getName()]);
				} elseif (isset($this->_advancedElements[$element->getName()])){
					$anyAdvanced = true;
					if (isset($this->_errors[$element->getName()])) {
						$anyError = true;
					}
				}
			}
			// the last header may not be closed yet...
			if ($anyAdvanced && !is_null($lastHeader)){
				$this->setAdvanced($lastHeader->getName());
				$lastHeader->_generateId();
				$this->addAdvancedStatusElement($lastHeader->getAttribute('id'), $anyError);
			}
			$renderer->setAdvancedElements($this->_advancedElements);
		}
		if (method_exists($renderer, 'setCollapsibleElements') && !$this->_disableShortforms) {

			// Count the number of sections.
			$headerscount = 0;
			foreach (array_keys($this->_elements) as $elementIndex){
				$element =& $this->_elements[$elementIndex];
				if ($element->getType() == 'header') {
					$headerscount++;
				}
			}

			$anyrequiredorerror = false;
			$headercounter = 0;
			$headername = null;
			foreach (array_keys($this->_elements) as $elementIndex){
				$element =& $this->_elements[$elementIndex];

				if ($element->getType() == 'header') {
					$headercounter++;
					$element->_generateId();
					$headername = $element->getName();
					$anyrequiredorerror = false;
				} else if (in_array($element->getName(), $this->_required) || isset($this->_errors[$element->getName()])) {
					$anyrequiredorerror = true;
				} else {
					// Do not reset $anyrequiredorerror to false because we do not want any other element
					// in this header (fieldset) to possibly revert the state given.
				}

				if ($element->getType() == 'header') {
					if ($headercounter === 1 && !isset($this->_collapsibleElements[$headername])) {
						// By default the first section is always expanded, except if a state has already been set.
						$this->setExpanded($headername, true);
					} else if (($headercounter === 2 && $headerscount === 2) && !isset($this->_collapsibleElements[$headername])) {
						// The second section is always expanded if the form only contains 2 sections),
						// except if a state has already been set.
						$this->setExpanded($headername, true);
					}
				} else if ($anyrequiredorerror) {
					// If any error or required field are present within the header, we need to expand it.
					$this->setExpanded($headername, true, true);
				} else if (!isset($this->_collapsibleElements[$headername])) {
					// Define element as collapsed by default.
					$this->setExpanded($headername, false);
				}
			}

			// Pass the array to renderer object.
			$renderer->setCollapsibleElements($this->_collapsibleElements);
		}
		parent::accept($renderer);
	}

	/**
	 * Adds one or more element names that indicate the end of a fieldset
	 *
	 * @param string $elementName name of the element
	 */
	function closeHeaderBefore($elementName){
		$renderer =& $this->defaultRenderer();
		$renderer->addStopFieldsetElements($elementName);
	}

	/**
	 * Set an element to be forced to flow LTR.
	 *
	 * The element must exist and support this functionality. Also note that
	 * when setting the type of a field (@link self::setType} we try to guess the
	 * whether the field should be force to LTR or not. Make sure you're always
	 * calling this method last.
	 *
	 * @param string $elementname The element name.
	 * @param bool $value When false, disables force LTR, else enables it.
	 */
	public function setForceLtr($elementname, $value = true) {
		$this->getElement($elementname)->set_force_ltr($value);
	}

	/**
	 * Should be used for all elements of a form except for select, radio and checkboxes which
	 * clean their own data.
	 *
	 * @param string $elementname
	 * @param int $paramtype defines type of data contained in element. Use the constants PARAM_*.
	 *        {@link lib/moodlelib.php} for defined parameter types
	 */
	function setType($elementname, $paramtype) {
		$this->_types[$elementname] = $paramtype;

		// This will not always get it right, but it should be accurate in most cases.
		// When inaccurate use setForceLtr().
		if (!is_rtl_compatible($paramtype)
				&& $this->elementExists($elementname)
				&& ($element =& $this->getElement($elementname))
				&& method_exists($element, 'set_force_ltr')) {

					$element->set_force_ltr(true);
				}
	}

	/**
	 * This can be used to set several types at once.
	 *
	 * @param array $paramtypes types of parameters.
	 * @see MoodleQuickForm::setType
	 */
	function setTypes($paramtypes) {
		foreach ($paramtypes as $elementname => $paramtype) {
			$this->setType($elementname, $paramtype);
		}
	}

	/**
	 * Return the type(s) to use to clean an element.
	 *
	 * In the case where the element has an array as a value, we will try to obtain a
	 * type defined for that specific key, and recursively until done.
	 *
	 * This method does not work reverse, you cannot pass a nested element and hoping to
	 * fallback on the clean type of a parent. This method intends to be used with the
	 * main element, which will generate child types if needed, not the other way around.
	 *
	 * Example scenario:
	 *
	 * You have defined a new repeated element containing a text field called 'foo'.
	 * By default there will always be 2 occurence of 'foo' in the form. Even though
	 * you've set the type on 'foo' to be PARAM_INT, for some obscure reason, you want
	 * the first value of 'foo', to be PARAM_FLOAT, which you set using setType:
	 * $mform->setType('foo[0]', PARAM_FLOAT).
	 *
	 * Now if you call this method passing 'foo', along with the submitted values of 'foo':
	 * array(0 => '1.23', 1 => '10'), you will get an array telling you that the key 0 is a
	 * FLOAT and 1 is an INT. If you had passed 'foo[1]', along with its value '10', you would
	 * get the default clean type returned (param $default).
	 *
	 * @param string $elementname name of the element.
	 * @param mixed $value value that should be cleaned.
	 * @param int $default default constant value to be returned (PARAM_...)
	 * @return string|array constant value or array of constant values (PARAM_...)
	 */
	public function getCleanType($elementname, $value, $default = PARAM_RAW) {
		$type = $default;
		if (array_key_exists($elementname, $this->_types)) {
			$type = $this->_types[$elementname];
		}
		if (is_array($value)) {
			$default = $type;
			$type = array();
			foreach ($value as $subkey => $subvalue) {
				$typekey = "$elementname" . "[$subkey]";
				if (array_key_exists($typekey, $this->_types)) {
					$subtype = $this->_types[$typekey];
				} else {
					$subtype = $default;
				}
				if (is_array($subvalue)) {
					$type[$subkey] = $this->getCleanType($typekey, $subvalue, $subtype);
				} else {
					$type[$subkey] = $subtype;
				}
			}
		}
		return $type;
	}

	/**
	 * Return the cleaned value using the passed type(s).
	 *
	 * @param mixed $value value that has to be cleaned.
	 * @param int|array $type constant value to use to clean (PARAM_...), typically returned by {@link self::getCleanType()}.
	 * @return mixed cleaned up value.
	 */
	public function getCleanedValue($value, $type) {
		if (is_array($type) && is_array($value)) {
			foreach ($type as $key => $param) {
				$value[$key] = $this->getCleanedValue($value[$key], $param);
			}
		} else if (!is_array($type) && !is_array($value)) {
			$value = clean_param($value, $type);
		} else if (!is_array($type) && is_array($value)) {
			$value = clean_param_array($value, $type, true);
		} else {
			throw new coding_exception('Unexpected type or value received in MoodleQuickForm::getCleanedValue()');
		}
		return $value;
	}

	/**
	 * Updates submitted values
	 *
	 * @param array $submission submitted values
	 * @param array $files list of files
	 */
	function updateSubmission($submission, $files) {
		$this->_flagSubmitted = false;

		if (empty($submission)) {
			$this->_submitValues = array();
		} else {
			foreach ($submission as $key => $s) {
				$type = $this->getCleanType($key, $s);
				$submission[$key] = $this->getCleanedValue($s, $type);
			}
			$this->_submitValues = $submission;
			$this->_flagSubmitted = true;
		}

		if (empty($files)) {
			$this->_submitFiles = array();
		} else {
			$this->_submitFiles = $files;
			$this->_flagSubmitted = true;
		}

		// need to tell all elements that they need to update their value attribute.
		foreach (array_keys($this->_elements) as $key) {
			$this->_elements[$key]->onQuickFormEvent('updateValue', null, $this);
		}
	}

	/**
	 * Returns HTML for required elements
	 *
	 * @return string
	 */
	function getReqHTML(){
		return $this->_reqHTML;
	}

	/**
	 * Returns HTML for advanced elements
	 *
	 * @return string
	 */
	function getAdvancedHTML(){
		return $this->_advancedHTML;
	}

	/**
	 * Initializes a default form value. Used to specify the default for a new entry where
	 * no data is loaded in using moodleform::set_data()
	 *
	 * note: $slashed param removed
	 *
	 * @param string $elementName element name
	 * @param mixed $defaultValue values for that element name
	 */
	function setDefault($elementName, $defaultValue){
		$this->setDefaults(array($elementName=>$defaultValue));
	}

	/**
	 * Add a help button to element, only one button per element is allowed.
	 *
	 * This is new, simplified and preferable method of setting a help icon on form elements.
	 * It uses the new $OUTPUT->help_icon().
	 *
	 * Typically, you will provide the same identifier and the component as you have used for the
	 * label of the element. The string identifier with the _help suffix added is then used
	 * as the help string.
	 *
	 * There has to be two strings defined:
	 *   1/ get_string($identifier, $component) - the title of the help page
	 *   2/ get_string($identifier.'_help', $component) - the actual help page text
	 *
	 * @since Moodle 2.0
	 * @param string $elementname name of the element to add the item to
	 * @param string $identifier help string identifier without _help suffix
	 * @param string $component component name to look the help string in
	 * @param string $linktext optional text to display next to the icon
	 * @param bool $suppresscheck set to true if the element may not exist
	 */
	function addHelpButton($elementname, $identifier, $component = 'moodle', $linktext = '', $suppresscheck = false) {
		global $OUTPUT;
		if (array_key_exists($elementname, $this->_elementIndex)) {
			$element = $this->_elements[$this->_elementIndex[$elementname]];
			$element->_helpbutton = $OUTPUT->help_icon($identifier, $component, $linktext);
		} else if (!$suppresscheck) {
			debugging(get_string('nonexistentformelements', 'form', $elementname));
		}
	}

	/**
	 * Set constant value not overridden by _POST or _GET
	 * note: this does not work for complex names with [] :-(
	 *
	 * @param string $elname name of element
	 * @param mixed $value
	 */
	function setConstant($elname, $value) {
		$this->_constantValues = HTML_QuickForm::arrayMerge($this->_constantValues, array($elname=>$value));
		$element =& $this->getElement($elname);
		$element->onQuickFormEvent('updateValue', null, $this);
	}

	/**
	 * export submitted values
	 *
	 * @param string $elementList list of elements in form
	 * @return array
	 */
	function exportValues($elementList = null){
		$unfiltered = array();
		if (null === $elementList) {
			// iterate over all elements, calling their exportValue() methods
			foreach (array_keys($this->_elements) as $key) {
				if ($this->_elements[$key]->isFrozen() && !$this->_elements[$key]->_persistantFreeze) {
					$varname = $this->_elements[$key]->_attributes['name'];
					$value = '';
					// If we have a default value then export it.
					if (isset($this->_defaultValues[$varname])) {
						$value = $this->prepare_fixed_value($varname, $this->_defaultValues[$varname]);
					}
				} else {
					$value = $this->_elements[$key]->exportValue($this->_submitValues, true);
				}

				if (is_array($value)) {
					// This shit throws a bogus warning in PHP 4.3.x
					$unfiltered = HTML_QuickForm::arrayMerge($unfiltered, $value);
				}
			}
		} else {
			if (!is_array($elementList)) {
				$elementList = array_map('trim', explode(',', $elementList));
			}
			foreach ($elementList as $elementName) {
				$value = $this->exportValue($elementName);
				if (@PEAR::isError($value)) {
					return $value;
				}
				//oh, stock QuickFOrm was returning array of arrays!
				$unfiltered = HTML_QuickForm::arrayMerge($unfiltered, $value);
			}
		}

		if (is_array($this->_constantValues)) {
			$unfiltered = HTML_QuickForm::arrayMerge($unfiltered, $this->_constantValues);
		}
		return $unfiltered;
	}

	/**
	 * This is a bit of a hack, and it duplicates the code in
	 * HTML_QuickForm_element::_prepareValue, but I could not think of a way or
	 * reliably calling that code. (Think about date selectors, for example.)
	 * @param string $name the element name.
	 * @param mixed $value the fixed value to set.
	 * @return mixed the appropriate array to add to the $unfiltered array.
	 */
	protected function prepare_fixed_value($name, $value) {
		if (null === $value) {
			return null;
		} else {
			if (!strpos($name, '[')) {
				return array($name => $value);
			} else {
				$valueAry = array();
				$myIndex  = "['" . str_replace(array(']', '['), array('', "']['"), $name) . "']";
				eval("\$valueAry$myIndex = \$value;");
				return $valueAry;
			}
		}
	}

	/**
	 * Adds a validation rule for the given field
	 *
	 * If the element is in fact a group, it will be considered as a whole.
	 * To validate grouped elements as separated entities,
	 * use addGroupRule instead of addRule.
	 *
	 * @param string $element Form element name
	 * @param string $message Message to display for invalid data
	 * @param string $type Rule type, use getRegisteredRules() to get types
	 * @param string $format (optional)Required for extra rule data
	 * @param string $validation (optional)Where to perform validation: "server", "client"
	 * @param bool $reset Client-side validation: reset the form element to its original value if there is an error?
	 * @param bool $force Force the rule to be applied, even if the target form element does not exist
	 */
	function addRule($element, $message, $type, $format=null, $validation='server', $reset = false, $force = false)
	{
		//parent::addRule($element, $message, $type, $format, $validation, $reset, $force);

$form->addRule('username', 'Username should be at least 5 symbols long', 'minlength', 5, 'client');

$username->addRule('minlength', 'Username should be at least 5 symbols long', 5, HTML_QuickForm2_Rule::CLIENT_SERVER);


		// rule params have changed
		//HTML_QuickForm2_Node::addRule($rule, $messageOrRunAt = '', $options = NULL, $runAt = HTML_QuickForm2_Rule::SERVER)
		$options = array();

		$element->addRule($type, $message, $validation);

		if ($validation == 'client') {
			$this->clientvalidation = true;
		}

	}


	/**
	 * Adds a validation rule for the given group of elements
	 *
	 * Only groups with a name can be assigned a validation rule
	 * Use addGroupRule when you need to validate elements inside the group.
	 * Use addRule if you need to validate the group as a whole. In this case,
	 * the same rule will be applied to all elements in the group.
	 * Use addRule if you need to validate the group against a function.
	 *
	 * @param string $group Form group name
	 * @param array|string $arg1 Array for multiple elements or error message string for one element
	 * @param string $type (optional)Rule type use getRegisteredRules() to get types
	 * @param string $format (optional)Required for extra rule data
	 * @param int $howmany (optional)How many valid elements should be in the group
	 * @param string $validation (optional)Where to perform validation: "server", "client"
	 * @param bool $reset Client-side: whether to reset the element's value to its original state if validation failed.
	 */
	function addGroupRule($group, $arg1, $type='', $format=null, $howmany=0, $validation = 'server', $reset = false)
	{
		parent::addGroupRule($group, $arg1, $type, $format, $howmany, $validation, $reset);
		if (is_array($arg1)) {
			foreach ($arg1 as $rules) {
				foreach ($rules as $rule) {
					$validation = (isset($rule[3]) && 'client' == $rule[3])? 'client': 'server';
					if ($validation == 'client') {
						$this->clientvalidation = true;
					}
				}
			}
		} elseif (is_string($arg1)) {
			if ($validation == 'client') {
				$this->clientvalidation = true;
			}
		}
	}

	/**
	 * Returns the client side validation script
	 *
	 * The code here was copied from HTML_QuickForm_DHTMLRulesTableless who copied it from  HTML_QuickForm
	 * and slightly modified to run rules per-element
	 * Needed to override this because of an error with client side validation of grouped elements.
	 *
	 * @return string Javascript to perform validation, empty string if no 'client' rules were added
	 */
	function getValidationScript()
	{
		global $PAGE;

		if (empty($this->_rules) || $this->clientvalidation === false) {
			return '';
		}

		include_once('HTML/QuickForm/RuleRegistry.php');
		$registry =& HTML_QuickForm_RuleRegistry::singleton();
		$test = array();
		$js_escape = array(
				"\r"    => '\r',
				"\n"    => '\n',
				"\t"    => '\t',
				"'"     => "\\'",
				'"'     => '\"',
				'\\'    => '\\\\'
		);

		foreach ($this->_rules as $elementName => $rules) {
			foreach ($rules as $rule) {
				if ('client' == $rule['validation']) {
					unset($element); //TODO: find out how to properly initialize it

					$dependent  = isset($rule['dependent']) && is_array($rule['dependent']);
					$rule['message'] = strtr($rule['message'], $js_escape);

					if (isset($rule['group'])) {
						$group    =& $this->getElement($rule['group']);
						// No JavaScript validation for frozen elements
						if ($group->isFrozen()) {
							continue 2;
						}
						$elements =& $group->getElements();
						foreach (array_keys($elements) as $key) {
							if ($elementName == $group->getElementName($key)) {
								$element =& $elements[$key];
								break;
							}
						}
					} elseif ($dependent) {
						$element   =  array();
						$element[] =& $this->getElement($elementName);
						foreach ($rule['dependent'] as $elName) {
							$element[] =& $this->getElement($elName);
						}
					} else {
						$element =& $this->getElement($elementName);
					}
					// No JavaScript validation for frozen elements
					if (is_object($element) && $element->isFrozen()) {
						continue 2;
					} elseif (is_array($element)) {
						foreach (array_keys($element) as $key) {
							if ($element[$key]->isFrozen()) {
								continue 3;
							}
						}
					}
					//for editor element, [text] is appended to the name.
					$fullelementname = $elementName;
					if (is_object($element) && $element->getType() == 'editor') {
						if ($element->getType() == 'editor') {
							$fullelementname .= '[text]';
							// Add format to rule as moodleform check which format is supported by browser
							// it is not set anywhere... So small hack to make sure we pass it down to quickform.
							if (is_null($rule['format'])) {
								$rule['format'] = $element->getFormat();
							}
						}
					}
					// Fix for bug displaying errors for elements in a group
					$test[$fullelementname][0][] = $registry->getValidationScript($element, $fullelementname, $rule);
					$test[$fullelementname][1]=$element;
					//end of fix
				}
			}
		}

		// Fix for MDL-9524. If you don't do this, then $element may be left as a reference to one of the fields in
		// the form, and then that form field gets corrupted by the code that follows.
		unset($element);

		$js = '

require(["core/event", "jquery"], function(Event, $) {

    function qf_errorHandler(element, _qfMsg, escapedName) {
        var event = $.Event(Event.Events.FORM_FIELD_VALIDATION);
        $(element).trigger(event, _qfMsg);
        if (event.isDefaultPrevented()) {
            return _qfMsg == \'\';
        } else {
            // Legacy mforms.
            var div = element.parentNode;

            if ((div == undefined) || (element.name == undefined)) {
                // No checking can be done for undefined elements so let server handle it.
                return true;
            }

            if (_qfMsg != \'\') {
                var errorSpan = document.getElementById(\'id_error_\' + escapedName);
                if (!errorSpan) {
                    errorSpan = document.createElement("span");
                    errorSpan.id = \'id_error_\' + escapedName;
                    errorSpan.className = "error";
                    element.parentNode.insertBefore(errorSpan, element.parentNode.firstChild);
                    document.getElementById(errorSpan.id).setAttribute(\'TabIndex\', \'0\');
                    document.getElementById(errorSpan.id).focus();
                }

                while (errorSpan.firstChild) {
                    errorSpan.removeChild(errorSpan.firstChild);
                }

                errorSpan.appendChild(document.createTextNode(_qfMsg.substring(3)));

                if (div.className.substr(div.className.length - 6, 6) != " error"
                        && div.className != "error") {
                    div.className += " error";
                    linebreak = document.createElement("br");
                    linebreak.className = "error";
                    linebreak.id = \'id_error_break_\' + escapedName;
                    errorSpan.parentNode.insertBefore(linebreak, errorSpan.nextSibling);
                }

                return false;
            } else {
                var errorSpan = document.getElementById(\'id_error_\' + escapedName);
                if (errorSpan) {
                    errorSpan.parentNode.removeChild(errorSpan);
                }
                var linebreak = document.getElementById(\'id_error_break_\' + escapedName);
                if (linebreak) {
                    linebreak.parentNode.removeChild(linebreak);
                }

                if (div.className.substr(div.className.length - 6, 6) == " error") {
                    div.className = div.className.substr(0, div.className.length - 6);
                } else if (div.className == "error") {
                    div.className = "";
                }

                return true;
            } // End if.
        } // End if.
    } // End function.
    ';
		$validateJS = '';
		foreach ($test as $elementName => $jsandelement) {
			// Fix for bug displaying errors for elements in a group
			//unset($element);
			list($jsArr,$element)=$jsandelement;
			//end of fix
			$escapedElementName = preg_replace_callback(
					'/[_\[\]-]/',
					create_function('$matches', 'return sprintf("_%2x",ord($matches[0]));'),
					$elementName);
			$valFunc = 'validate_' . $this->_formName . '_' . $escapedElementName . '(ev.target, \''.$escapedElementName.'\')';

			if (!is_array($element)) {
				$element = [$element];
			}
			foreach ($element as $elem) {
				if (key_exists('id', $elem->_attributes)) {
					$js .= '
    function validate_' . $this->_formName . '_' . $escapedElementName . '(element, escapedName) {
      if (undefined == element) {
         //required element was not found, then let form be submitted without client side validation
         return true;
      }
      var value = \'\';
      var errFlag = new Array();
      var _qfGroups = {};
      var _qfMsg = \'\';
      var frm = element.parentNode;
      if ((undefined != element.name) && (frm != undefined)) {
          while (frm && frm.nodeName.toUpperCase() != "FORM") {
            frm = frm.parentNode;
          }
        ' . join("\n", $jsArr) . '
          return qf_errorHandler(element, _qfMsg, escapedName);
      } else {
        //element name should be defined else error msg will not be displayed.
        return true;
      }
    }

    document.getElementById(\'' . $elem->_attributes['id'] . '\').addEventListener(\'blur\', function(ev) {
        ' . $valFunc . '
    });
    document.getElementById(\'' . $elem->_attributes['id'] . '\').addEventListener(\'change\', function(ev) {
        ' . $valFunc . '
    });
';
				}
			}
			$validateJS .= '
      ret = validate_' . $this->_formName . '_' . $escapedElementName.'(frm.elements[\''.$elementName.'\'], \''.$escapedElementName.'\') && ret;
      if (!ret && !first_focus) {
        first_focus = true;
        Y.use(\'moodle-core-event\', function() {
            Y.Global.fire(M.core.globalEvents.FORM_ERROR, {formid: \'' . $this->_attributes['id'] . '\',
                                                           elementid: \'id_error_' . $escapedElementName . '\'});
            document.getElementById(\'id_error_' . $escapedElementName . '\').focus();
        });
      }
';

			// Fix for bug displaying errors for elements in a group
			//unset($element);
			//$element =& $this->getElement($elementName);
			//end of fix
			//$onBlur = $element->getAttribute('onBlur');
			//$onChange = $element->getAttribute('onChange');
			//$element->updateAttributes(array('onBlur' => $onBlur . $valFunc,
			//'onChange' => $onChange . $valFunc));
		}
		//  do not rely on frm function parameter, because htmlarea breaks it when overloading the onsubmit method
		$js .= '

    function validate_' . $this->_formName . '() {
      if (skipClientValidation) {
         return true;
      }
      var ret = true;

      var frm = document.getElementById(\''. $this->_attributes['id'] .'\')
      var first_focus = false;
    ' . $validateJS . ';
      return ret;
    }


    document.getElementById(\'' . $this->_attributes['id'] . '\').addEventListener(\'submit\', function(ev) {
        try {
            var myValidator = validate_' . $this->_formName . ';
        } catch(e) {
            return true;
        }
        if (typeof window.tinyMCE !== \'undefined\') {
            window.tinyMCE.triggerSave();
        }
        if (!myValidator()) {
            ev.preventDefault();
        }
    });

});
';

		$PAGE->requires->js_amd_inline($js);

		// Global variable used to skip the client validation.
		return html_writer::tag('script', 'var skipClientValidation = false;');
	} // end func getValidationScript

	/**
	 * Sets default error message
	 */
	function _setDefaultRuleMessages(){
		foreach ($this->_rules as $field => $rulesarr){
			foreach ($rulesarr as $key => $rule){
				if ($rule['message']===null){
					$a=new stdClass();
					$a->format=$rule['format'];
					$str=get_string('err_'.$rule['type'], 'form', $a);
					if (strpos($str, '[[')!==0){
						$this->_rules[$field][$key]['message']=$str;
					}
				}
			}
		}
	}

	/**
	 * Get list of attributes which have dependencies
	 *
	 * @return array
	 */
	function getLockOptionObject(){
		$result = array();
		foreach ($this->_dependencies as $dependentOn => $conditions){
			$result[$dependentOn] = array();
			foreach ($conditions as $condition=>$values) {
				$result[$dependentOn][$condition] = array();
				foreach ($values as $value=>$dependents) {
					$result[$dependentOn][$condition][$value] = array();
					$i = 0;
					foreach ($dependents as $dependent) {
						$elements = $this->_getElNamesRecursive($dependent);
						if (empty($elements)) {
							// probably element inside of some group
							$elements = array($dependent);
						}
						foreach($elements as $element) {
							if ($element == $dependentOn) {
								continue;
							}
							$result[$dependentOn][$condition][$value][] = $element;
						}
					}
				}
			}
		}
		return array($this->getAttribute('id'), $result);
	}

	/**
	 * Get names of element or elements in a group.
	 *
	 * @param HTML_QuickForm_group|element $element element group or element object
	 * @return array
	 */
	function _getElNamesRecursive($element) {
		if (is_string($element)) {
			if (!$this->elementExists($element)) {
				return array();
			}
			$element = $this->getElement($element);
		}

		if (is_a($element, 'HTML_QuickForm_group')) {
			$elsInGroup = $element->getElements();
			$elNames = array();
			foreach ($elsInGroup as $elInGroup){
				if (is_a($elInGroup, 'HTML_QuickForm_group')) {
					// not sure if this would work - groups nested in groups
					$elNames = array_merge($elNames, $this->_getElNamesRecursive($elInGroup));
				} else {
					$elNames[] = $element->getElementName($elInGroup->getName());
				}
			}

		} else if (is_a($element, 'HTML_QuickForm_header')) {
			return array();

		} else if (is_a($element, 'HTML_QuickForm_hidden')) {
			return array();

		} else if (method_exists($element, 'getPrivateName') &&
				!($element instanceof HTML_QuickForm_advcheckbox)) {
					// The advcheckbox element implements a method called getPrivateName,
					// but in a way that is not compatible with the generic API, so we
					// have to explicitly exclude it.
					return array($element->getPrivateName());

				} else {
					$elNames = array($element->getName());
				}

				return $elNames;
	}

	/**
	 * Adds a dependency for $elementName which will be disabled if $condition is met.
	 * If $condition = 'notchecked' (default) then the condition is that the $dependentOn element
	 * is not checked. If $condition = 'checked' then the condition is that the $dependentOn element
	 * is checked. If $condition is something else (like "eq" for equals) then it is checked to see if the value
	 * of the $dependentOn element is $condition (such as equal) to $value.
	 *
	 * When working with multiple selects, the dependentOn has to be the real name of the select, meaning that
	 * it will most likely end up with '[]'. Also, the value should be an array of required values, or a string
	 * containing the values separated by pipes: array('red', 'blue') or 'red|blue'.
	 *
	 * @param string $elementName the name of the element which will be disabled
	 * @param string $dependentOn the name of the element whose state will be checked for condition
	 * @param string $condition the condition to check
	 * @param mixed $value used in conjunction with condition.
	 */
	function disabledIf($elementName, $dependentOn, $condition = 'notchecked', $value='1') {
		// Multiple selects allow for a multiple selection, we transform the array to string here as
		// an array cannot be used as a key in an associative array.
		if (is_array($value)) {
			$value = implode('|', $value);
		}
		if (!array_key_exists($dependentOn, $this->_dependencies)) {
			$this->_dependencies[$dependentOn] = array();
		}
		if (!array_key_exists($condition, $this->_dependencies[$dependentOn])) {
			$this->_dependencies[$dependentOn][$condition] = array();
		}
		if (!array_key_exists($value, $this->_dependencies[$dependentOn][$condition])) {
			$this->_dependencies[$dependentOn][$condition][$value] = array();
		}
		$this->_dependencies[$dependentOn][$condition][$value][] = $elementName;
	}

	/**
	 * Registers button as no submit button
	 *
	 * @param string $buttonname name of the button
	 */
	function registerNoSubmitButton($buttonname){
		$this->_noSubmitButtons[]=$buttonname;
	}

	/**
	 * Checks if button is a no submit button, i.e it doesn't submit form
	 *
	 * @param string $buttonname name of the button to check
	 * @return bool
	 */
	function isNoSubmitButton($buttonname){
		return (array_search($buttonname, $this->_noSubmitButtons)!==FALSE);
	}

	/**
	 * Registers a button as cancel button
	 *
	 * @param string $addfieldsname name of the button
	 */
	function _registerCancelButton($addfieldsname){
		$this->_cancelButtons[]=$addfieldsname;
	}

	/**
	 * Displays elements without HTML input tags.
	 * This method is different to freeze() in that it makes sure no hidden
	 * elements are included in the form.
	 * Note: If you want to make sure the submitted value is ignored, please use setDefaults().
	 *
	 * This function also removes all previously defined rules.
	 *
	 * @param string|array $elementList array or string of element(s) to be frozen
	 * @return object|bool if element list is not empty then return error object, else true
	 */
	function hardFreeze($elementList=null)
	{
		if (!isset($elementList)) {
			$this->_freezeAll = true;
			$elementList = array();
		} else {
			if (!is_array($elementList)) {
				$elementList = preg_split('/[ ]*,[ ]*/', $elementList);
			}
			$elementList = array_flip($elementList);
		}

		foreach (array_keys($this->_elements) as $key) {
			$name = $this->_elements[$key]->getName();
			if ($this->_freezeAll || isset($elementList[$name])) {
				$this->_elements[$key]->freeze();
				$this->_elements[$key]->setPersistantFreeze(false);
				unset($elementList[$name]);

				// remove all rules
				$this->_rules[$name] = array();
				// if field is required, remove the rule
				$unset = array_search($name, $this->_required);
				if ($unset !== false) {
					unset($this->_required[$unset]);
				}
			}
		}

		if (!empty($elementList)) {
			return self::raiseError(null, QUICKFORM_NONEXIST_ELEMENT, null, E_USER_WARNING, "Nonexistant element(s): '" . implode("', '", array_keys($elementList)) . "' in HTML_QuickForm::freeze()", 'HTML_QuickForm_Error', true);
		}
		return true;
	}

	/**
	 * Hard freeze all elements in a form except those whose names are in $elementList or hidden elements in a form.
	 *
	 * This function also removes all previously defined rules of elements it freezes.
	 *
	 * @throws HTML_QuickForm_Error
	 * @param array $elementList array or string of element(s) not to be frozen
	 * @return bool returns true
	 */
	function hardFreezeAllVisibleExcept($elementList)
	{
		$elementList = array_flip($elementList);
		foreach (array_keys($this->_elements) as $key) {
			$name = $this->_elements[$key]->getName();
			$type = $this->_elements[$key]->getType();

			if ($type == 'hidden'){
				// leave hidden types as they are
			} elseif (!isset($elementList[$name])) {
				$this->_elements[$key]->freeze();
				$this->_elements[$key]->setPersistantFreeze(false);

				// remove all rules
				$this->_rules[$name] = array();
				// if field is required, remove the rule
				$unset = array_search($name, $this->_required);
				if ($unset !== false) {
					unset($this->_required[$unset]);
				}
			}
		}
		return true;
	}

	/**
	 * Tells whether the form was already submitted
	 *
	 * This is useful since the _submitFiles and _submitValues arrays
	 * may be completely empty after the trackSubmit value is removed.
	 *
	 * @return bool
	 */
	function isSubmitted()
	{
		return parent::isSubmitted() && (!$this->isFrozen());
	}

    /*
     * Start of QF1 Functions to map to QF2 functionality
     */


        /**
     * Sets required-note
     *
     * @param     string   $note        Message indicating some elements are required
     * @since     1.1
     * @access    public
     * @return    void
     */
    function setRequiredNote($note)
    {
        //$this->_requiredNote = $note;
        $this->setOption('required_note', $note);
    } // en

    /**
     * Updates the passed attributes without changing the other existing attributes
     * @param    mixed   $attributes     Either a typical HTML attribute string or an associative array
     * @access   public
     */
    function updateAttributes($attributes)
    {
        $this->_updateAttrArray($this->_attributes, $this->_parseAttributes($attributes));
    } // end func updateAttributes

    /**
     * Updates the attributes in $attr1 with the values in $attr2 without changing the other existing attributes
     * @param    array   $attr1      Original attributes array
     * @param    array   $attr2      New attributes array
     * @access   private
     */
    function _updateAttrArray(&$attr1, $attr2)
    {
        if (!is_array($attr2)) {
            return false;
        }
        foreach ($attr2 as $key => $value) {
            $attr1[$key] = $value;
        }
    } // end func _updateAtrrArray

    /**
     * Returns a valid attributes array from either a string or array
     * @param    mixed   $attributes     Either a typical HTML attribute string or an associative array
     * @access   private
     */
    function _parseAttributes($attributes)
    {
        if (is_array($attributes)) {
            $ret = array();
            foreach ($attributes as $key => $value) {
                if (is_int($key)) {
                    $key = $value = strtolower($value);
                } else {
                    $key = strtolower($key);
                }
                $ret[$key] = $value;
            }
            return $ret;

        } elseif (is_string($attributes)) {
            $preg = "/(([A-Za-z_:]|[^\\x00-\\x7F])([A-Za-z0-9_:.-]|[^\\x00-\\x7F])*)" .
                "([ \\n\\t\\r]+)?(=([ \\n\\t\\r]+)?(\"[^\"]*\"|'[^']*'|[^ \\n\\t\\r]*))?/";
            if (preg_match_all($preg, $attributes, $regs)) {
                for ($counter=0; $counter<count($regs[1]); $counter++) {
                    $name  = $regs[1][$counter];
                    $check = $regs[0][$counter];
                    $value = $regs[7][$counter];
                    if (trim($name) == trim($check)) {
                        $arrAttr[strtolower(trim($name))] = strtolower(trim($name));
                    } else {
                        if (substr($value, 0, 1) == "\"" || substr($value, 0, 1) == "'") {
                            $value = substr($value, 1, -1);
                        }
                        $arrAttr[strtolower(trim($name))] = trim($value);
                    }
                }
                return $arrAttr;
            }
        }
    } // end func _parseAttributes

    /**
     * Registers a new element type
     *
     * @param     string    $typeName   Name of element type
     * @param     string    $include    Include path for element type
     * @param     string    $className  Element class name
     * @since     1.0
     * @access    public
     * @return    void
     */
    static function registerElementType($typeName, $include, $className)
    {
        $GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES'][strtolower($typeName)] = array($include, $className);
    } // end func registerElementType

    /**
     * Registers a new validation rule
     *
     * @param     string    $ruleName   Name of validation rule
     * @param     string    $type       Either: 'regex', 'function' or 'rule' for an HTML_QuickForm_Rule object
     * @param     string    $data1      Name of function, regular expression or HTML_QuickForm_Rule classname
     * @param     string    $data2      Object parent of above function or HTML_QuickForm_Rule file path
     * @since     1.0
     * @access    public
     * @return    void
     */
    static function registerRule($ruleName, $type, $data1, $data2 = null)
    {
        /*include_once('HTML/QuickForm/RuleRegistry.php');
        $registry =& HTML_QuickForm_RuleRegistry::singleton();
        $registry->registerRule($ruleName, $type, $data1, $data2);*/
    } // end func registerRule

}
