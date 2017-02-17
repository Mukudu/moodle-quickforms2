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
 * Wrapper that separates quickforms syntax from moodle code
 *
 * Moodle specific wrapper that separates quickforms syntax from moodle code. You won't directly
 * use this class you should write a class definition which extends this class or a more specific
 * subclass such a moodleform_mod for each form you want to display and/or process with formslib.
 *
 * You will write your own definition() method which performs the form set up.
 *
 * @package   core_form
 * @copyright 2006 Jamie Pratt <me@jamiep.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @todo      MDL-19380 rethink the file scanning
 */

require_once 'moodlequickform.php';

abstract class moodleform {
	/** @var string name of the form */
	protected $_formname;       // form name

	/** @var MoodleQuickForm quickform object definition */
	protected $_form;

	/** @var array globals workaround */
	protected $_customdata;

	/** @var array submitted form data when using mforms with ajax */
	protected $_ajaxformdata;

	/** @var object definition_after_data executed flag */
	protected $_definition_finalized = false;

	/** @var bool|null stores the validation result of this form or null if not yet validated */
	protected $_validated = null;

	/**
	 * The constructor function calls the abstract function definition() and it will then
	 * process and clean and attempt to validate incoming data.
	 *
	 * It will call your custom validate method to validate data and will also check any rules
	 * you have specified in definition using addRule
	 *
	 * The name of the form (id attribute of the form) is automatically generated depending on
	 * the name you gave the class extending moodleform. You should call your class something
	 * like
	 *
	 * @param mixed $action the action attribute for the form. If empty defaults to auto detect the
	 *              current url. If a moodle_url object then outputs params as hidden variables.
	 * @param mixed $customdata if your form defintion method needs access to data such as $course
	 *              $cm, etc. to construct the form definition then pass it in this array. You can
	 *              use globals for somethings.
	 * @param string $method if you set this to anything other than 'post' then _GET and _POST will
	 *               be merged and used as incoming data to the form.
	 * @param string $target target frame for form submission. You will rarely use this. Don't use
	 *               it if you don't need to as the target attribute is deprecated in xhtml strict.
	 * @param mixed $attributes you can pass a string of html attributes here or an array.
	 * @param bool $editable
	 * @param array $ajaxformdata Forms submitted via ajax, must pass their data here, instead of relying on _GET and _POST.
	 */
	public function __construct($action=null, $customdata=null, $method='post', $target='', $attributes=null, $editable=true,
			$ajaxformdata=null) {
				global $CFG, $FULLME;
				// no standard mform in moodle should allow autocomplete with the exception of user signup
				if (empty($attributes)) {
					$attributes = array('autocomplete'=>'off');
				} else if (is_array($attributes)) {
					$attributes['autocomplete'] = 'off';
				} else {
					if (strpos($attributes, 'autocomplete') === false) {
						$attributes .= ' autocomplete="off" ';
					}
				}


				if (empty($action)){
					// do not rely on PAGE->url here because dev often do not setup $actualurl properly in admin_externalpage_setup()
					$action = strip_querystring($FULLME);
					if (!empty($CFG->sslproxy)) {
						// return only https links when using SSL proxy
						$action = preg_replace('/^http:/', 'https:', $action, 1);
					}
					//TODO: use following instead of FULLME - see MDL-33015
					//$action = strip_querystring(qualified_me());
				}
				// Assign custom data first, so that get_form_identifier can use it.
				$this->_customdata = $customdata;
				$this->_formname = $this->get_form_identifier();
				$this->_ajaxformdata = $ajaxformdata;

				$this->_form = new MoodleQuickForm($this->_formname, $method, $action, $target, $attributes);
				if (!$editable){
					$this->_form->hardFreeze();
				}

				$this->definition();

				$this->_form->addElement('hidden', 'sesskey', null); // automatic sesskey protection
				$this->_form->setType('sesskey', PARAM_RAW);
				$this->_form->setDefault('sesskey', sesskey());
				$this->_form->addElement('hidden', '_qf__'.$this->_formname, null);   // form submission marker
				$this->_form->setType('_qf__'.$this->_formname, PARAM_RAW);
				$this->_form->setDefault('_qf__'.$this->_formname, 1);
				$this->_form->_setDefaultRuleMessages();

				// we have to know all input types before processing submission ;-)
				$this->_process_submission($method);
	}

	/**
	 * Old syntax of class constructor. Deprecated in PHP7.
	 *
	 * @deprecated since Moodle 3.1
	 */
	public function moodleform($action=null, $customdata=null, $method='post', $target='', $attributes=null, $editable=true) {
		debugging('Use of class name as constructor is deprecated', DEBUG_DEVELOPER);
		self::__construct($action, $customdata, $method, $target, $attributes, $editable);
	}

	/**
	 * It should returns unique identifier for the form.
	 * Currently it will return class name, but in case two same forms have to be
	 * rendered on same page then override function to get unique form identifier.
	 * e.g This is used on multiple self enrollments page.
	 *
	 * @return string form identifier.
	 */
	protected function get_form_identifier() {
		$class = get_class($this);

		return preg_replace('/[^a-z0-9_]/i', '_', $class);
	}

	/**
	 * To autofocus on first form element or first element with error.
	 *
	 * @param string $name if this is set then the focus is forced to a field with this name
	 * @return string javascript to select form element with first error or
	 *                first element if no errors. Use this as a parameter
	 *                when calling print_header
	 */
	function focus($name=NULL) {
		$form =& $this->_form;
		$elkeys = array_keys($form->_elementIndex);
		$error = false;
		if (isset($form->_errors) &&  0 != count($form->_errors)){
			$errorkeys = array_keys($form->_errors);
			$elkeys = array_intersect($elkeys, $errorkeys);
			$error = true;
		}

		if ($error or empty($name)) {
			$names = array();
			while (empty($names) and !empty($elkeys)) {
				$el = array_shift($elkeys);
				$names = $form->_getElNamesRecursive($el);
			}
			if (!empty($names)) {
				$name = array_shift($names);
			}
		}

		$focus = '';
		if (!empty($name)) {
			$focus = 'forms[\''.$form->getAttribute('id').'\'].elements[\''.$name.'\']';
		}

		return $focus;
	}

	/**
	 * Internal method. Alters submitted data to be suitable for quickforms processing.
	 * Must be called when the form is fully set up.
	 *
	 * @param string $method name of the method which alters submitted data
	 */
	function _process_submission($method) {
		$submission = array();
		if (!empty($this->_ajaxformdata)) {
			$submission = $this->_ajaxformdata;
		} else if ($method == 'post') {
			if (!empty($_POST)) {
				$submission = $_POST;
			}
		} else {
			$submission = $_GET;
			merge_query_params($submission, $_POST); // Emulate handling of parameters in xxxx_param().
		}

		// following trick is needed to enable proper sesskey checks when using GET forms
		// the _qf__.$this->_formname serves as a marker that form was actually submitted
		if (array_key_exists('_qf__'.$this->_formname, $submission) and $submission['_qf__'.$this->_formname] == 1) {
			if (!confirm_sesskey()) {
				print_error('invalidsesskey');
			}
			$files = $_FILES;
		} else {
			$submission = array();
			$files = array();
		}
		$this->detectMissingSetType();

		$this->_form->updateSubmission($submission, $files);
	}

	/**
	 * Internal method - should not be used anywhere.
	 * @deprecated since 2.6
	 * @return array $_POST.
	 */
	protected function _get_post_params() {
		return $_POST;
	}

	/**
	 * Internal method. Validates all old-style deprecated uploaded files.
	 * The new way is to upload files via repository api.
	 *
	 * @param array $files list of files to be validated
	 * @return bool|array Success or an array of errors
	 */
	function _validate_files(&$files) {
		global $CFG, $COURSE;

		$files = array();

		if (empty($_FILES)) {
			// we do not need to do any checks because no files were submitted
			// note: server side rules do not work for files - use custom verification in validate() instead
			return true;
		}

		$errors = array();
		$filenames = array();

		// now check that we really want each file
		foreach ($_FILES as $elname=>$file) {
			$required = $this->_form->isElementRequired($elname);

			if ($file['error'] == 4 and $file['size'] == 0) {
				if ($required) {
					$errors[$elname] = get_string('required');
				}
				unset($_FILES[$elname]);
				continue;
			}

			if (!empty($file['error'])) {
				$errors[$elname] = file_get_upload_error($file['error']);
				unset($_FILES[$elname]);
				continue;
			}

			if (!is_uploaded_file($file['tmp_name'])) {
				// TODO: improve error message
				$errors[$elname] = get_string('error');
				unset($_FILES[$elname]);
				continue;
			}

			if (!$this->_form->elementExists($elname) or !$this->_form->getElementType($elname)=='file') {
				// hmm, this file was not requested
				unset($_FILES[$elname]);
				continue;
			}

			// NOTE: the viruses are scanned in file picker, no need to deal with them here.

			$filename = clean_param($_FILES[$elname]['name'], PARAM_FILE);
			if ($filename === '') {
				// TODO: improve error message - wrong chars
				$errors[$elname] = get_string('error');
				unset($_FILES[$elname]);
				continue;
			}
			if (in_array($filename, $filenames)) {
				// TODO: improve error message - duplicate name
				$errors[$elname] = get_string('error');
				unset($_FILES[$elname]);
				continue;
			}
			$filenames[] = $filename;
			$_FILES[$elname]['name'] = $filename;

			$files[$elname] = $_FILES[$elname]['tmp_name'];
		}

		// return errors if found
		if (count($errors) == 0){
			return true;

		} else {
			$files = array();
			return $errors;
		}
	}

	/**
	 * Internal method. Validates filepicker and filemanager files if they are
	 * set as required fields. Also, sets the error message if encountered one.
	 *
	 * @return bool|array with errors
	 */
	protected function validate_draft_files() {
		global $USER;
		$mform =& $this->_form;

		$errors = array();
		//Go through all the required elements and make sure you hit filepicker or
		//filemanager element.
		foreach ($mform->_rules as $elementname => $rules) {
			$elementtype = $mform->getElementType($elementname);
			//If element is of type filepicker then do validation
			if (($elementtype == 'filepicker') || ($elementtype == 'filemanager')){
				//Check if rule defined is required rule
				foreach ($rules as $rule) {
					if ($rule['type'] == 'required') {
						$draftid = (int)$mform->getSubmitValue($elementname);
						$fs = get_file_storage();
						$context = context_user::instance($USER->id);
						if (!$files = $fs->get_area_files($context->id, 'user', 'draft', $draftid, 'id DESC', false)) {
							$errors[$elementname] = $rule['message'];
						}
					}
				}
			}
		}
		// Check all the filemanager elements to make sure they do not have too many
		// files in them.
		foreach ($mform->_elements as $element) {
			if ($element->_type == 'filemanager') {
				$maxfiles = $element->getMaxfiles();
				if ($maxfiles > 0) {
					$draftid = (int)$element->getValue();
					$fs = get_file_storage();
					$context = context_user::instance($USER->id);
					$files = $fs->get_area_files($context->id, 'user', 'draft', $draftid, '', false);
					if (count($files) > $maxfiles) {
						$errors[$element->getName()] = get_string('err_maxfiles', 'form', $maxfiles);
					}
				}
			}
		}
		if (empty($errors)) {
			return true;
		} else {
			return $errors;
		}
	}

	/**
	 * Load in existing data as form defaults. Usually new entry defaults are stored directly in
	 * form definition (new entry form); this function is used to load in data where values
	 * already exist and data is being edited (edit entry form).
	 *
	 * note: $slashed param removed
	 *
	 * @param stdClass|array $default_values object or array of default values
	 */
	function set_data($default_values) {
		if (is_object($default_values)) {
			$default_values = (array)$default_values;
		}
		$this->_form->setDefaults($default_values);
	}

	/**
	 * Check that form was submitted. Does not check validity of submitted data.
	 *
	 * @return bool true if form properly submitted
	 */
	function is_submitted() {
		return $this->_form->isSubmitted();
	}

	/**
	 * Checks if button pressed is not for submitting the form
	 *
	 * @staticvar bool $nosubmit keeps track of no submit button
	 * @return bool
	 */
	function no_submit_button_pressed(){
		static $nosubmit = null; // one check is enough
		if (!is_null($nosubmit)){
			return $nosubmit;
		}
		$mform =& $this->_form;
		$nosubmit = false;
		if (!$this->is_submitted()){
			return false;
		}
		foreach ($mform->_noSubmitButtons as $nosubmitbutton){
			if (optional_param($nosubmitbutton, 0, PARAM_RAW)){
				$nosubmit = true;
				break;
			}
		}
		return $nosubmit;
	}


	/**
	 * Check that form data is valid.
	 * You should almost always use this, rather than {@link validate_defined_fields}
	 *
	 * @return bool true if form data valid
	 */
	function is_validated() {
		//finalize the form definition before any processing
		if (!$this->_definition_finalized) {
			$this->_definition_finalized = true;
			$this->definition_after_data();
		}

		return $this->validate_defined_fields();
	}

	/**
	 * Validate the form.
	 *
	 * You almost always want to call {@link is_validated} instead of this
	 * because it calls {@link definition_after_data} first, before validating the form,
	 * which is what you want in 99% of cases.
	 *
	 * This is provided as a separate function for those special cases where
	 * you want the form validated before definition_after_data is called
	 * for example, to selectively add new elements depending on a no_submit_button press,
	 * but only when the form is valid when the no_submit_button is pressed,
	 *
	 * @param bool $validateonnosubmit optional, defaults to false.  The default behaviour
	 *             is NOT to validate the form when a no submit button has been pressed.
	 *             pass true here to override this behaviour
	 *
	 * @return bool true if form data valid
	 */
	function validate_defined_fields($validateonnosubmit=false) {
		$mform =& $this->_form;
		if ($this->no_submit_button_pressed() && empty($validateonnosubmit)){
			return false;
		} elseif ($this->_validated === null) {
			$internal_val = $mform->validate();

			$files = array();
			$file_val = $this->_validate_files($files);
			//check draft files for validation and flag them if required files
			//are not in draft area.
			$draftfilevalue = $this->validate_draft_files();

			if ($file_val !== true && $draftfilevalue !== true) {
				$file_val = array_merge($file_val, $draftfilevalue);
			} else if ($draftfilevalue !== true) {
				$file_val = $draftfilevalue;
			} //default is file_val, so no need to assign.

			if ($file_val !== true) {
				if (!empty($file_val)) {
					foreach ($file_val as $element=>$msg) {
						$mform->setElementError($element, $msg);
					}
				}
				$file_val = false;
			}

			$data = $mform->exportValues();
			$moodle_val = $this->validation($data, $files);
			if ((is_array($moodle_val) && count($moodle_val)!==0)) {
				// non-empty array means errors
				foreach ($moodle_val as $element=>$msg) {
					$mform->setElementError($element, $msg);
				}
				$moodle_val = false;

			} else {
				// anything else means validation ok
				$moodle_val = true;
			}

			$this->_validated = ($internal_val and $moodle_val and $file_val);
		}
		return $this->_validated;
	}

	/**
	 * Return true if a cancel button has been pressed resulting in the form being submitted.
	 *
	 * @return bool true if a cancel button has been pressed
	 */
	function is_cancelled(){
		$mform =& $this->_form;
		if ($mform->isSubmitted()){
			foreach ($mform->_cancelButtons as $cancelbutton){
				if (optional_param($cancelbutton, 0, PARAM_RAW)){
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Return submitted data if properly submitted or returns NULL if validation fails or
	 * if there is no submitted data.
	 *
	 * note: $slashed param removed
	 *
	 * @return object submitted data; NULL if not valid or not submitted or cancelled
	 */
	function get_data() {
		$mform =& $this->_form;

		if (!$this->is_cancelled() and $this->is_submitted() and $this->is_validated()) {
			$data = $mform->exportValues();
			unset($data['sesskey']); // we do not need to return sesskey
			unset($data['_qf__'.$this->_formname]);   // we do not need the submission marker too
			if (empty($data)) {
				return NULL;
			} else {
				return (object)$data;
			}
		} else {
			return NULL;
		}
	}

	/**
	 * Return submitted data without validation or NULL if there is no submitted data.
	 * note: $slashed param removed
	 *
	 * @return object submitted data; NULL if not submitted
	 */
	function get_submitted_data() {
		$mform =& $this->_form;

		if ($this->is_submitted()) {
			$data = $mform->exportValues();
			unset($data['sesskey']); // we do not need to return sesskey
			unset($data['_qf__'.$this->_formname]);   // we do not need the submission marker too
			if (empty($data)) {
				return NULL;
			} else {
				return (object)$data;
			}
		} else {
			return NULL;
		}
	}

	/**
	 * Save verified uploaded files into directory. Upload process can be customised from definition()
	 *
	 * @deprecated since Moodle 2.0
	 * @todo MDL-31294 remove this api
	 * @see moodleform::save_stored_file()
	 * @see moodleform::save_file()
	 * @param string $destination path where file should be stored
	 * @return bool Always false
	 */
	function save_files($destination) {
		debugging('Not used anymore, please fix code! Use save_stored_file() or save_file() instead');
		return false;
	}

	/**
	 * Returns name of uploaded file.
	 *
	 * @param string $elname first element if null
	 * @return string|bool false in case of failure, string if ok
	 */
	function get_new_filename($elname=null) {
		global $USER;

		if (!$this->is_submitted() or !$this->is_validated()) {
			return false;
		}

		if (is_null($elname)) {
			if (empty($_FILES)) {
				return false;
			}
			reset($_FILES);
			$elname = key($_FILES);
		}

		if (empty($elname)) {
			return false;
		}

		$element = $this->_form->getElement($elname);

		if ($element instanceof MoodleQuickForm_filepicker || $element instanceof MoodleQuickForm_filemanager) {
			$values = $this->_form->exportValues($elname);
			if (empty($values[$elname])) {
				return false;
			}
			$draftid = $values[$elname];
			$fs = get_file_storage();
			$context = context_user::instance($USER->id);
			if (!$files = $fs->get_area_files($context->id, 'user', 'draft', $draftid, 'id DESC', false)) {
				return false;
			}
			$file = reset($files);
			return $file->get_filename();
		}

		if (!isset($_FILES[$elname])) {
			return false;
		}

		return $_FILES[$elname]['name'];
	}

	/**
	 * Save file to standard filesystem
	 *
	 * @param string $elname name of element
	 * @param string $pathname full path name of file
	 * @param bool $override override file if exists
	 * @return bool success
	 */
	function save_file($elname, $pathname, $override=false) {
		global $USER;

		if (!$this->is_submitted() or !$this->is_validated()) {
			return false;
		}
		if (file_exists($pathname)) {
			if ($override) {
				if (!@unlink($pathname)) {
					return false;
				}
			} else {
				return false;
			}
		}

		$element = $this->_form->getElement($elname);

		if ($element instanceof MoodleQuickForm_filepicker || $element instanceof MoodleQuickForm_filemanager) {
			$values = $this->_form->exportValues($elname);
			if (empty($values[$elname])) {
				return false;
			}
			$draftid = $values[$elname];
			$fs = get_file_storage();
			$context = context_user::instance($USER->id);
			if (!$files = $fs->get_area_files($context->id, 'user', 'draft', $draftid, 'id DESC', false)) {
				return false;
			}
			$file = reset($files);

			return $file->copy_content_to($pathname);

		} else if (isset($_FILES[$elname])) {
			return copy($_FILES[$elname]['tmp_name'], $pathname);
		}

		return false;
	}

	/**
	 * Returns a temporary file, do not forget to delete after not needed any more.
	 *
	 * @param string $elname name of the elmenet
	 * @return string|bool either string or false
	 */
	function save_temp_file($elname) {
		if (!$this->get_new_filename($elname)) {
			return false;
		}
		if (!$dir = make_temp_directory('forms')) {
			return false;
		}
		if (!$tempfile = tempnam($dir, 'tempup_')) {
			return false;
		}
		if (!$this->save_file($elname, $tempfile, true)) {
			// something went wrong
			@unlink($tempfile);
			return false;
		}

		return $tempfile;
	}

	/**
	 * Get draft files of a form element
	 * This is a protected method which will be used only inside moodleforms
	 *
	 * @param string $elname name of element
	 * @return array|bool|null
	 */
	protected function get_draft_files($elname) {
		global $USER;

		if (!$this->is_submitted()) {
			return false;
		}

		$element = $this->_form->getElement($elname);

		if ($element instanceof MoodleQuickForm_filepicker || $element instanceof MoodleQuickForm_filemanager) {
			$values = $this->_form->exportValues($elname);
			if (empty($values[$elname])) {
				return false;
			}
			$draftid = $values[$elname];
			$fs = get_file_storage();
			$context = context_user::instance($USER->id);
			if (!$files = $fs->get_area_files($context->id, 'user', 'draft', $draftid, 'id DESC', false)) {
				return null;
			}
			return $files;
		}
		return null;
	}

	/**
	 * Save file to local filesystem pool
	 *
	 * @param string $elname name of element
	 * @param int $newcontextid id of context
	 * @param string $newcomponent name of the component
	 * @param string $newfilearea name of file area
	 * @param int $newitemid item id
	 * @param string $newfilepath path of file where it get stored
	 * @param string $newfilename use specified filename, if not specified name of uploaded file used
	 * @param bool $overwrite overwrite file if exists
	 * @param int $newuserid new userid if required
	 * @return mixed stored_file object or false if error; may throw exception if duplicate found
	 */
	function save_stored_file($elname, $newcontextid, $newcomponent, $newfilearea, $newitemid, $newfilepath='/',
			$newfilename=null, $overwrite=false, $newuserid=null) {
				global $USER;

				if (!$this->is_submitted() or !$this->is_validated()) {
					return false;
				}

				if (empty($newuserid)) {
					$newuserid = $USER->id;
				}

				$element = $this->_form->getElement($elname);
				$fs = get_file_storage();

				if ($element instanceof MoodleQuickForm_filepicker) {
					$values = $this->_form->exportValues($elname);
					if (empty($values[$elname])) {
						return false;
					}
					$draftid = $values[$elname];
					$context = context_user::instance($USER->id);
					if (!$files = $fs->get_area_files($context->id, 'user' ,'draft', $draftid, 'id DESC', false)) {
						return false;
					}
					$file = reset($files);
					if (is_null($newfilename)) {
						$newfilename = $file->get_filename();
					}

					if ($overwrite) {
						if ($oldfile = $fs->get_file($newcontextid, $newcomponent, $newfilearea, $newitemid, $newfilepath, $newfilename)) {
							if (!$oldfile->delete()) {
								return false;
							}
						}
					}

					$file_record = array('contextid'=>$newcontextid, 'component'=>$newcomponent, 'filearea'=>$newfilearea, 'itemid'=>$newitemid,
							'filepath'=>$newfilepath, 'filename'=>$newfilename, 'userid'=>$newuserid);
					return $fs->create_file_from_storedfile($file_record, $file);

				} else if (isset($_FILES[$elname])) {
					$filename = is_null($newfilename) ? $_FILES[$elname]['name'] : $newfilename;

					if ($overwrite) {
						if ($oldfile = $fs->get_file($newcontextid, $newcomponent, $newfilearea, $newitemid, $newfilepath, $newfilename)) {
							if (!$oldfile->delete()) {
								return false;
							}
						}
					}

					$file_record = array('contextid'=>$newcontextid, 'component'=>$newcomponent, 'filearea'=>$newfilearea, 'itemid'=>$newitemid,
							'filepath'=>$newfilepath, 'filename'=>$newfilename, 'userid'=>$newuserid);
					return $fs->create_file_from_pathname($file_record, $_FILES[$elname]['tmp_name']);
				}

				return false;
	}

	/**
	 * Get content of uploaded file.
	 *
	 * @param string $elname name of file upload element
	 * @return string|bool false in case of failure, string if ok
	 */
	function get_file_content($elname) {
		global $USER;

		if (!$this->is_submitted() or !$this->is_validated()) {
			return false;
		}

		$element = $this->_form->getElement($elname);

		if ($element instanceof MoodleQuickForm_filepicker || $element instanceof MoodleQuickForm_filemanager) {
			$values = $this->_form->exportValues($elname);
			if (empty($values[$elname])) {
				return false;
			}
			$draftid = $values[$elname];
			$fs = get_file_storage();
			$context = context_user::instance($USER->id);
			if (!$files = $fs->get_area_files($context->id, 'user', 'draft', $draftid, 'id DESC', false)) {
				return false;
			}
			$file = reset($files);

			return $file->get_content();

		} else if (isset($_FILES[$elname])) {
			return file_get_contents($_FILES[$elname]['tmp_name']);
		}

		return false;
	}

	/**
	 * Print html form.
	 */
	function display() {
		//finalize the form definition if not yet done
		if (!$this->_definition_finalized) {
			$this->_definition_finalized = true;
			$this->definition_after_data();
		}

		$this->_form->display();
	}

	/**
	 * Renders the html form (same as display, but returns the result).
	 *
	 * Note that you can only output this rendered result once per page, as
	 * it contains IDs which must be unique.
	 *
	 * @return string HTML code for the form
	 */
	public function render() {
		ob_start();
		$this->display();
		$out = ob_get_contents();
		ob_end_clean();
		return $out;
	}

	/**
	 * Form definition. Abstract method - always override!
	 */
	protected abstract function definition();

	/**
	 * Dummy stub method - override if you need to setup the form depending on current
	 * values. This method is called after definition(), data submission and set_data().
	 * All form setup that is dependent on form values should go in here.
	 */
	function definition_after_data(){
	}

	/**
	 * Dummy stub method - override if you needed to perform some extra validation.
	 * If there are errors return array of errors ("fieldname"=>"error message"),
	 * otherwise true if ok.
	 *
	 * Server side rules do not work for uploaded files, implement serverside rules here if needed.
	 *
	 * @param array $data array of ("fieldname"=>value) of submitted data
	 * @param array $files array of uploaded files "element_name"=>tmp_file_path
	 * @return array of "element_name"=>"error_description" if there are errors,
	 *         or an empty array if everything is OK (true allowed for backwards compatibility too).
	 */
	function validation($data, $files) {
		return array();
	}

	/**
	 * Helper used by {@link repeat_elements()}.
	 *
	 * @param int $i the index of this element.
	 * @param HTML_QuickForm_element $elementclone
	 * @param array $namecloned array of names
	 */
	function repeat_elements_fix_clone($i, $elementclone, &$namecloned) {
		$name = $elementclone->getName();
		$namecloned[] = $name;

		if (!empty($name)) {
			$elementclone->setName($name."[$i]");
		}

		if (is_a($elementclone, 'HTML_QuickForm_header')) {
			$value = $elementclone->_text;
			$elementclone->setValue(str_replace('{no}', ($i+1), $value));

		} else if (is_a($elementclone, 'HTML_QuickForm_submit') || is_a($elementclone, 'HTML_QuickForm_button')) {
			$elementclone->setValue(str_replace('{no}', ($i+1), $elementclone->getValue()));

		} else {
			$value=$elementclone->getLabel();
			$elementclone->setLabel(str_replace('{no}', ($i+1), $value));
		}
	}

	/**
	 * Method to add a repeating group of elements to a form.
	 *
	 * @param array $elementobjs Array of elements or groups of elements that are to be repeated
	 * @param int $repeats no of times to repeat elements initially
	 * @param array $options a nested array. The first array key is the element name.
	 *    the second array key is the type of option to set, and depend on that option,
	 *    the value takes different forms.
	 *         'default'    - default value to set. Can include '{no}' which is replaced by the repeat number.
	 *         'type'       - PARAM_* type.
	 *         'helpbutton' - array containing the helpbutton params.
	 *         'disabledif' - array containing the disabledIf() arguments after the element name.
	 *         'rule'       - array containing the addRule arguments after the element name.
	 *         'expanded'   - whether this section of the form should be expanded by default. (Name be a header element.)
	 *         'advanced'   - whether this element is hidden by 'Show more ...'.
	 * @param string $repeathiddenname name for hidden element storing no of repeats in this form
	 * @param string $addfieldsname name for button to add more fields
	 * @param int $addfieldsno how many fields to add at a time
	 * @param string $addstring name of button, {no} is replaced by no of blanks that will be added.
	 * @param bool $addbuttoninside if true, don't call closeHeaderBefore($addfieldsname). Default false.
	 * @return int no of repeats of element in this page
	 */
	function repeat_elements($elementobjs, $repeats, $options, $repeathiddenname,
			$addfieldsname, $addfieldsno=5, $addstring=null, $addbuttoninside=false){
				if ($addstring===null){
					$addstring = get_string('addfields', 'form', $addfieldsno);
				} else {
					$addstring = str_ireplace('{no}', $addfieldsno, $addstring);
				}
				$repeats = optional_param($repeathiddenname, $repeats, PARAM_INT);
				$addfields = optional_param($addfieldsname, '', PARAM_TEXT);
				if (!empty($addfields)){
					$repeats += $addfieldsno;
				}
				$mform =& $this->_form;
				$mform->registerNoSubmitButton($addfieldsname);
				$mform->addElement('hidden', $repeathiddenname, $repeats);
				$mform->setType($repeathiddenname, PARAM_INT);
				//value not to be overridden by submitted value
				$mform->setConstants(array($repeathiddenname=>$repeats));
				$namecloned = array();
				for ($i = 0; $i < $repeats; $i++) {
					foreach ($elementobjs as $elementobj){
						$elementclone = fullclone($elementobj);
						$this->repeat_elements_fix_clone($i, $elementclone, $namecloned);

						if ($elementclone instanceof HTML_QuickForm_group && !$elementclone->_appendName) {
							foreach ($elementclone->getElements() as $el) {
								$this->repeat_elements_fix_clone($i, $el, $namecloned);
							}
							$elementclone->setLabel(str_replace('{no}', $i + 1, $elementclone->getLabel()));
						}

						$mform->addElement($elementclone);
					}
				}
				for ($i=0; $i<$repeats; $i++) {
					foreach ($options as $elementname => $elementoptions){
						$pos=strpos($elementname, '[');
						if ($pos!==FALSE){
							$realelementname = substr($elementname, 0, $pos)."[$i]";
							$realelementname .= substr($elementname, $pos);
						}else {
							$realelementname = $elementname."[$i]";
						}
						foreach ($elementoptions as  $option => $params){

							switch ($option){
								case 'default' :
									$mform->setDefault($realelementname, str_replace('{no}', $i + 1, $params));
									break;
								case 'helpbutton' :
									$params = array_merge(array($realelementname), $params);
									call_user_func_array(array(&$mform, 'addHelpButton'), $params);
									break;
								case 'disabledif' :
									foreach ($namecloned as $num => $name){
										if ($params[0] == $name){
											$params[0] = $params[0]."[$i]";
											break;
										}
									}
									$params = array_merge(array($realelementname), $params);
									call_user_func_array(array(&$mform, 'disabledIf'), $params);
									break;
								case 'rule' :
									if (is_string($params)){
										$params = array(null, $params, null, 'client');
									}
									$params = array_merge(array($realelementname), $params);
									call_user_func_array(array(&$mform, 'addRule'), $params);
									break;

								case 'type':
									$mform->setType($realelementname, $params);
									break;

								case 'expanded':
									$mform->setExpanded($realelementname, $params);
									break;

								case 'advanced' :
									$mform->setAdvanced($realelementname, $params);
									break;
							}
						}
					}
				}
				$mform->addElement('submit', $addfieldsname, $addstring);

				if (!$addbuttoninside) {
					$mform->closeHeaderBefore($addfieldsname);
				}

				return $repeats;
	}

	/**
	 * Adds a link/button that controls the checked state of a group of checkboxes.
	 *
	 * @param int $groupid The id of the group of advcheckboxes this element controls
	 * @param string $text The text of the link. Defaults to selectallornone ("select all/none")
	 * @param array $attributes associative array of HTML attributes
	 * @param int $originalValue The original general state of the checkboxes before the user first clicks this element
	 */
	function add_checkbox_controller($groupid, $text = null, $attributes = null, $originalValue = 0) {
		global $CFG, $PAGE;

		// Name of the controller button
		$checkboxcontrollername = 'nosubmit_checkbox_controller' . $groupid;
		$checkboxcontrollerparam = 'checkbox_controller'. $groupid;
		$checkboxgroupclass = 'checkboxgroup'.$groupid;

		// Set the default text if none was specified
		if (empty($text)) {
			$text = get_string('selectallornone', 'form');
		}

		$mform = $this->_form;
		$selectvalue = optional_param($checkboxcontrollerparam, null, PARAM_INT);
		$contollerbutton = optional_param($checkboxcontrollername, null, PARAM_ALPHAEXT);

		$newselectvalue = $selectvalue;
		if (is_null($selectvalue)) {
			$newselectvalue = $originalValue;
		} else if (!is_null($contollerbutton)) {
			$newselectvalue = (int) !$selectvalue;
		}
		// set checkbox state depending on orignal/submitted value by controoler button
		if (!is_null($contollerbutton) || is_null($selectvalue)) {
			foreach ($mform->_elements as $element) {
				if (($element instanceof MoodleQuickForm_advcheckbox) &&
						$element->getAttribute('class') == $checkboxgroupclass &&
						!$element->isFrozen()) {
							$mform->setConstants(array($element->getName() => $newselectvalue));
						}
			}
		}

		$mform->addElement('hidden', $checkboxcontrollerparam, $newselectvalue, array('id' => "id_".$checkboxcontrollerparam));
		$mform->setType($checkboxcontrollerparam, PARAM_INT);
		$mform->setConstants(array($checkboxcontrollerparam => $newselectvalue));

		$PAGE->requires->yui_module('moodle-form-checkboxcontroller', 'M.form.checkboxcontroller',
				array(
						array('groupid' => $groupid,
								'checkboxclass' => $checkboxgroupclass,
								'checkboxcontroller' => $checkboxcontrollerparam,
								'controllerbutton' => $checkboxcontrollername)
				)
				);

		require_once("$CFG->libdir/form/submit.php");
		$submitlink = new MoodleQuickForm_submit($checkboxcontrollername, $attributes);
		$mform->addElement($submitlink);
		$mform->registerNoSubmitButton($checkboxcontrollername);
		$mform->setDefault($checkboxcontrollername, $text);
	}

	/**
	 * Use this method to a cancel and submit button to the end of your form. Pass a param of false
	 * if you don't want a cancel button in your form. If you have a cancel button make sure you
	 * check for it being pressed using is_cancelled() and redirecting if it is true before trying to
	 * get data with get_data().
	 *
	 * @param bool $cancel whether to show cancel button, default true
	 * @param string $submitlabel label for submit button, defaults to get_string('savechanges')
	 */
	function add_action_buttons($cancel = true, $submitlabel=null){
		if (is_null($submitlabel)){
			$submitlabel = get_string('savechanges');
		}
		$mform =& $this->_form;
		if ($cancel){
			//when two elements we need a group
			$buttonarray=array();
			$buttonarray[] = &$mform->createElement('submit', 'submitbutton', $submitlabel);
			$buttonarray[] = &$mform->createElement('cancel');
			$mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
			$mform->closeHeaderBefore('buttonar');
		} else {
			//no group needed
			$mform->addElement('submit', 'submitbutton', $submitlabel);
			$mform->closeHeaderBefore('submitbutton');
		}
	}

	/**
	 * Adds an initialisation call for a standard JavaScript enhancement.
	 *
	 * This function is designed to add an initialisation call for a JavaScript
	 * enhancement that should exist within javascript-static M.form.init_{enhancementname}.
	 *
	 * Current options:
	 *  - Selectboxes
	 *      - smartselect:  Turns a nbsp indented select box into a custom drop down
	 *                      control that supports multilevel and category selection.
	 *                      $enhancement = 'smartselect';
	 *                      $options = array('selectablecategories' => true|false)
	 *
	 * @since Moodle 2.0
	 * @param string|element $element form element for which Javascript needs to be initalized
	 * @param string $enhancement which init function should be called
	 * @param array $options options passed to javascript
	 * @param array $strings strings for javascript
	 */
	function init_javascript_enhancement($element, $enhancement, array $options=array(), array $strings=null) {
		global $PAGE;
		if (is_string($element)) {
			$element = $this->_form->getElement($element);
		}
		if (is_object($element)) {
			$element->_generateId();
			$elementid = $element->getAttribute('id');
			$PAGE->requires->js_init_call('M.form.init_'.$enhancement, array($elementid, $options));
			if (is_array($strings)) {
				foreach ($strings as $string) {
					if (is_array($string)) {
						call_user_func_array(array($PAGE->requires, 'string_for_js'), $string);
					} else {
						$PAGE->requires->string_for_js($string, 'moodle');
					}
				}
			}
		}
	}

	/**
	 * Returns a JS module definition for the mforms JS
	 *
	 * @return array
	 */
	public static function get_js_module() {
		global $CFG;
		return array(
				'name' => 'mform',
				'fullpath' => '/lib/form/form.js',
				'requires' => array('base', 'node')
		);
	}

	/**
	 * Detects elements with missing setType() declerations.
	 *
	 * Finds elements in the form which should a PARAM_ type set and throws a
	 * developer debug warning for any elements without it. This is to reduce the
	 * risk of potential security issues by developers mistakenly forgetting to set
	 * the type.
	 *
	 * @return void
	 */
	private function detectMissingSetType() {
		global $CFG;

		if (!$CFG->debugdeveloper) {
			// Only for devs.
			return;
		}

		$mform = $this->_form;
		foreach ($mform->_elements as $element) {
			$group = false;
			$elements = array($element);

			if ($element->getType() == 'group') {
				$group = $element;
				$elements = $element->getElements();
			}

			foreach ($elements as $index => $element) {
				switch ($element->getType()) {
					case 'hidden':
					case 'text':
					case 'url':
						if ($group) {
							$name = $group->getElementName($index);
						} else {
							$name = $element->getName();
						}
						$key = $name;
						$found = array_key_exists($key, $mform->_types);
						// For repeated elements we need to look for
						// the "main" type, not for the one present
						// on each repetition. All the stuff in formslib
						// (repeat_elements(), updateSubmission()... seems
						// to work that way.
						while (!$found && strrpos($key, '[') !== false) {
							$pos = strrpos($key, '[');
							$key = substr($key, 0, $pos);
							$found = array_key_exists($key, $mform->_types);
						}
						if (!$found) {
							debugging("Did you remember to call setType() for '$name'? ".
									'Defaulting to PARAM_RAW cleaning.', DEBUG_DEVELOPER);
						}
						break;
				}
			}
		}
	}

	/**
	 * Used by tests to simulate submitted form data submission from the user.
	 *
	 * For form fields where no data is submitted the default for that field as set by set_data or setDefault will be passed to
	 * get_data.
	 *
	 * This method sets $_POST or $_GET and $_FILES with the data supplied. Our unit test code empties all these
	 * global arrays after each test.
	 *
	 * @param array  $simulatedsubmitteddata       An associative array of form values (same format as $_POST).
	 * @param array  $simulatedsubmittedfiles      An associative array of files uploaded (same format as $_FILES). Can be omitted.
	 * @param string $method                       'post' or 'get', defaults to 'post'.
	 * @param null   $formidentifier               the default is to use the class name for this class but you may need to provide
	 *                                              a different value here for some forms that are used more than once on the
	 *                                              same page.
	 */
	public static function mock_submit($simulatedsubmitteddata, $simulatedsubmittedfiles = array(), $method = 'post',
			$formidentifier = null) {
				$_FILES = $simulatedsubmittedfiles;
				if ($formidentifier === null) {
					$formidentifier = get_called_class();
					$formidentifier = str_replace('\\', '_', $formidentifier); // See MDL-56233 for more information.
				}
				$simulatedsubmitteddata['_qf__'.$formidentifier] = 1;
				$simulatedsubmitteddata['sesskey'] = sesskey();
				if (strtolower($method) === 'get') {
					$_GET = $simulatedsubmitteddata;
				} else {
					$_POST = $simulatedsubmitteddata;
				}
	}
}
