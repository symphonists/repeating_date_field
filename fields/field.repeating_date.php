<?php

	/**
	 * @package repeating_date_field
	 */

	/**
	 * Repeating date field definition.
	 */
	class FieldRepeating_Date extends Field {
		protected $_driver = null;
		protected $filter = 0;

		public function __construct() {
			parent::__construct();

			$this->_name = 'Repeating Date';
			$this->_driver = Symphony::ExtensionManager()->create('repeating_date_field');
		}

		/**
		 * Append the formatted xml output of this field as utilized as a data source.
		 *
		 * @param XMLElement $wrapper
		 *	the xml element to append the xml representation of this to.
		 * @param array $data
		 *	the current set of values for this field. the values are structured as
		 *	for displayPublishPanel.
		 * @param boolean $encode (optional)
		 *	flag as to whether this should be html encoded prior to output. this
		 *	defaults to false.
		 * @param string $mode
		 *	 A field can provide ways to output this field's data. For instance a mode
		 *  could be 'items' or 'full' and then the function would display the data
		 *  in a different way depending on what was selected in the datasource
		 *  included elements.
		 * @param integer $entry_id (optional)
		 *	the identifier of this field entry instance. defaults to null.
		 */
		public function appendFormattedElement($wrapper, $data, $encode = false) {
			$dates = $this->_driver->getEntryDates($data, $this->get('id'), $this->filter);
			$element = new XMLElement($this->get('element_name'));

			$element->appendChild(General::createXMLDateObject($data['start'], 'start'));

			foreach ($dates[0] as $index => $date) {
				$element->appendChild(General::createXMLDateObject($date['value'], 'before'));
			}

			foreach ($dates[1] as $index => $date) {
				$element->appendChild(General::createXMLDateObject(
					$date['value'], ($index == 0 ? 'current' : 'after')
				));
			}

			$element->appendChild(General::createXMLDateObject($data['end'], 'end'));

			$element->setAttribute('date-mode',
				isset($data['mode'])
					? $data['mode']
					: null
			);
			$element->setAttribute('date-units',
				isset($data['units'])
					? $data['units']
					: null
			);

			$wrapper->appendChild($element);
		}

		/**
		 * Construct the SQL statement fragments to use to retrieve the data of this
		 * field when utilized as a data source.
		 *
		 * @param array $data
		 *	the supplied form data to use to construct the query from
		 * @param string $joins
		 *	the join sql statement fragment to append the additional join sql to.
		 * @param string $where
		 *	the where condition sql statement fragment to which the additional
		 *	where conditions will be appended.
		 * @param boolean $andOperation (optional)
		 *	true if the values of the input data should be appended as part of
		 *	the where condition. this defaults to false.
		 * @return boolean
		 *	true if the construction of the sql was successful, false otherwise.
		 */
		public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation = false) {
			$field_id = $this->get('id');
			$this->filter = strtotime(@$data[0]);

			$joins .= "
				LEFT JOIN
					`tbl_entries_data_{$field_id}` AS t{$field_id}
					ON (e.id = t{$field_id}.entry_id)
			";
			$where .= "
				AND t{$field_id}.end > {$this->filter}
			";

			return true;
		}

		/**
		 * Build the SQL command to append to the default query to enable
		 * sorting of this field. By default this will sort the results by
		 * the entry id in ascending order.
		 *
		 * @param string $joins
		 *	the join element of the query to append the custom join sql to.
		 * @param string $where
		 *	the where condition of the query to append to the existing where clause.
		 * @param string $sort
		 *	the existing sort component of the sql query to append the custom
		 *	sort sql code to.
		 * @param string $order (optional)
		 *	an optional sorting direction. this defaults to ascending. if this
		 *	is declared either 'random' or 'rand' then a random sort is applied.
		 */
		public function buildSortingSQL(&$joins, &$where, &$sort, $order = 'ASC') {
			$field_id = $this->get('id');
			$joins .= "
				INNER JOIN
					`tbl_entries_data_{$field_id}` AS f
					ON (e.id = f.entry_id)
			";

			if (strtolower($order) == 'random') {
				$sort = 'ORDER BY RAND()';
			}

			else {
				$today = $this->_driver->getToday();
				$sort = "
					ORDER BY
						(
							SELECT
								d.value
							FROM
								`tbl_entries_data_{$field_id}_dates` d
							WHERE
								f.link_id = d.link_id
								AND d.value >= {$today}
							ORDER BY
								d.value ASC
							LIMIT 1
						) {$order}
				";
			}
		}

		/**
		 * Test whether this field can be filtered. This default implementation
		 * prohibits filtering. Filtering allows the xml output results to be limited
		 * according to an input parameter. Subclasses should override this if
		 * filtering is supported.
		 *
		 * @return boolean
		 *	true if this can be filtered, false otherwise.
		 */
		public function canFilter() {
			return true;
		}

		/**
		 * Test whether this field can be prepopulated with data. This default
		 * implementation does not support pre-population and, thus, returns false.
		 *
		 * @return boolean
		 *	true if this can be pre-populated, false otherwise.
		 */
		public function canPrePopulate() {
			return true;
		}

		/**
		 * Check the field data that has been posted from a form. This will set the
		 * input message to the error message or to null if there is none. Any existing
		 * message value will be overwritten.
		 *
		 * @param array $data
		 *	the input data to check.
		 * @param string $message
		 *	the place to set any generated error message. any previous value for
		 *	this variable will be overwritten.
		 * @param integer $entry_id (optional)
		 *	the optional id of this field entry instance. this defaults to null.
		 * @return integer
		 *	`self::__MISSING_FIELDS__` if there are any missing required fields,
		 *	`self::__OK__` otherwise.
		 */
		public function checkPostFieldData($data, &$message, $entry_id = null) {
			$status = self::__OK__;
			$data = array_merge(array(
				'start'	=> null,
				'end'	=> null,
				'units'	=> null,
				'mode'	=> null
			), $data);
			$message = null;

			if (!$this->validateDate($data['start'])) {
				$message = sprintf(
					"The start date specified in '%s' is invalid.",
					$this->get('label')
				);
				$status = self::__INVALID_FIELDS__;
			}

			else if (!$this->validateDate($data['end'])) {
				$message = sprintf(
					"The end date specified in '%s' is invalid.",
					$this->get('label')
				);
				$status = self::__INVALID_FIELDS__;
			}

			else if ((integer)$data['units'] < 0) {
				$message = sprintf(
					"The number of repeats specified in '%s' must be greater or equal to 1.",
					$this->get('label')
				);
				$status = self::__INVALID_FIELDS__;
			}

			return $status;
		}

		/**
		 * Commit the settings of this field from the section editor to
		 * create an instance of this field in a section.
		 *
		 * @return boolean
		 *	true if the commit was successful, false otherwise.
		 */
		public function commit() {
			if (!parent::commit()) return false;

			$id = $this->get('id');
			$handle = $this->handle();

			if ($id === false) return false;

			$fields = array();

			$fields['field_id'] = $id;
			$fields['pre_populate'] = (
				$this->get('pre_populate')
					? $this->get('pre_populate')
					: 'no'
			);

			Symphony::Database()->query("DELETE FROM `tbl_fields_{$handle}` WHERE `field_id` = '{$id}' LIMIT 1");
			Symphony::Database()->insert($fields, "tbl_fields_{$handle}");
		}

		/**
		 * The default field table construction method. This constructs the bare
		 * minimum set of columns for a valid field table. Subclasses are expected
		 * to overload this method to create a table structure that contains
		 * additional columns to store the specific data created by the field.
		 *
		 * @return boolean
		 */
		public function createTable() {
			$field_id = $this->get('id');

			Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$field_id}` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`entry_id` int(11) unsigned NOT NULL,
					`link_id` bigint(20) unsigned NOT NULL,
					`start` int(11) default NULL,
					`end` int(11) default NULL,
					`units` int(11) unsigned NOT NULL default 1,
					`mode` enum(
						'days',
						'weeks',
						'months-by-date',
						'months-by-weekday',
						'years-by-date',
						'years-by-weekday'
					) NOT NULL default 'days',
					PRIMARY KEY (`id`),
					KEY `entry_id` (`entry_id`),
					KEY `link_id` (`link_id`),
					KEY `start` (`start`),
					KEY `end` (`end`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;
			");

			Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$field_id}_dates` (
					`id` int(11) NOT NULL auto_increment,
					`link_id` bigint(20) default NULL,
					`value` int(11) NOT NULL default '0',
					PRIMARY KEY  USING BTREE (`id`),
					KEY `link_id` (`link_id`),
					KEY `value` (`value`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;
			");
		}

		protected function displayPublishDate($type, $wrapper, $data, $prefix = null, $suffix = null) {
			$timestamp = null;
			$name = $this->get('element_name');
			$subname = strtolower($type);

			if ($data) {
				$value = $data[$subname];
				$timestamp = (!is_numeric($value) ? strtotime($value) : $value);
			}

			$label = Widget::Label("{$type} date");
			$label->appendChild(Widget::Input(
				"fields{$prefix}[{$name}][{$subname}]{$suffix}", (
					$data || $this->get('pre_populate') == 'yes'
						? DateTimeObj::get(__SYM_DATETIME_FORMAT__, $timestamp) : null
				)
			));

			$label->setAttribute('class', 'date');
			$wrapper->appendChild($label);
		}

		protected function displayPublishMode($wrapper, $data, $prefix = null, $suffix = null) {
			$name = $this->get('element_name');
			$units = @((integer)$data['units'] > 0 ? (integer)$data['units'] : 1);

			$input = Widget::Input(
				"fields{$prefix}[{$name}][units]{$suffix}", (string)$units
			);
			$input->setAttribute('size', '2');

			$modes = array(
				array(
					'days', false, 'Days'
				),
				array(
					'weeks', false, 'Weeks'
				),
				array(
					'months-by-date', false, 'Months (by Date)'
				),
				array(
					'months-by-weekday', false, 'Months (by Weekday)'
				),
				array(
					'years-by-date', false, 'Years (by Date)'
				),
				array(
					'years-by-weekday', false, 'Years (by Weekday)'
				)
			);

			foreach ($modes as $index => $mode) {
				if ($mode[0] == @$data['mode']) {
					$modes[$index][1] = true;
				}
			}

			$select = Widget::Select(
				"fields{$prefix}[{$name}][mode]{$postfix}", $modes
			);

			$label = new XMLElement('p');
			$label->setValue('<label>Repeat every ' . $input->generate() . '</label>' . $select->generate());

			$wrapper->appendChild($label);
		}

		/**
		 * Display the publish panel for this field. The display panel is the
		 * interface shown to Authors that allow them to input data into this
		 * field for an Entry.
		 *
		 * @param XMLElement $wrapper
		 *	the xml element to append the html defined user interface to this
		 *	field.
		 * @param array $data (optional)
		 *	any existing data that has been supplied for this field instance.
		 *	this is encoded as an array of columns, each column maps to an
		 *	array of row indexes to the contents of that column. this defaults
		 *	to null.
		 * @param mixed $error (optional)
		 *	flag with error defaults to null.
		 * @param string $prefix (optional)
		 *	the string to be prepended to the display of the name of this field.
		 *	this defaults to null.
		 * @param string $suffix (optional)
		 *	the string to be appended to the display of the name of this field.
		 *	this defaults to null.
		 * @param integer $entry_id (optional)
		 *	the entry id of this field. this defaults to null.
		 */
		public function displayPublishPanel($wrapper, $data = null, $error = null, $prefix = null, $suffix = null) {
			Symphony::Engine()->Page->addStylesheetToHead(URL . '/extensions/repeating_date_field/assets/publish.css', 'screen');

			$label = Widget::Label($this->get('label'));
			$wrapper->appendChild($label);
			$div = new XMLElement('div');

			$this->displayPublishDate('Start', $div, $data, $prefix, $suffix);
			$this->displayPublishDate('End', $div, $data, $prefix, $suffix);
			$this->displayPublishMode($div, $data, $prefix, $suffix);

			if ($error) {
				$div->setAttribute('id', 'error');
				$div->addClass('invalid');
			}

			$wrapper->appendChild($div);
		}

		/**
		 * Display the default settings panel, calls the `buildSummaryBlock`
		 * function after basic field settings are added to the wrapper.
		 *
		 * @see buildSummaryBlock()
		 * @param XMLElement $wrapper
		 *	the input XMLElement to which the display of this will be appended.
		 * @param mixed errors (optional)
		 *	the input error collection. this defaults to null.
		 */
		public function displaySettingsPanel($wrapper) {
			parent::displaySettingsPanel($wrapper);

			$label = Widget::Label();
			$input = Widget::Input('fields['.$this->get('sortorder').'][pre_populate]', 'yes', 'checkbox');

			if ($this->get('pre_populate') == 'yes') {
				$input->setAttribute('checked', 'checked');
			}

			$label->setValue($input->generate() . ' Pre-populate this field with today\'s date');
			$wrapper->appendChild($label);

			$this->appendShowColumnCheckbox($wrapper);
		}

		/**
		 * Allows a field to set default settings.
		 *
		 * @param array $settings
		 *	the array of settings to populate with their defaults.
		 */
		public function findDefaults(&$fields){
			if (!isset($fields['pre_populate'])) $fields['pre_populate'] = 'yes';
		}

		/**
		 * Test whether this field can be sorted. This default implementation
		 * returns false.
		 *
		 * @return boolean
		 *	true if this field is sortable, false otherwise.
		 */
		public function isSortable() {
			return true;
		}

		/**
		 * Format this field value for display in the publish index tables. By default,
		 * Symphony will truncate the value to the configuration setting `cell_truncation_length`.
		 * This function will attempt to use PHP's `mbstring` functions if they are available.
		 *
		 * @param array $data
		 *	an associative array of data for this string. At minimum this requires a
		 *  key of 'value'.
		 * @param XMLElement $link (optional)
		 *	an xml link structure to append the content of this to provided it is not
		 *	null. it defaults to null.
		 * @return string
		 *	the formatted string summary of the values of this field instance.
		 */
		public function prepareTableValue($data, XMLElement $link = null) {
			$date = $this->_driver->getEntryDate($data, $this->get('id'));
			$date = DateTimeObj::get(__SYM_DATE_FORMAT__, $date);

			return parent::prepareTableValue(
				array(
					'value' => "{$date}"
				), $link
			);
		}

		/**
		 * Process the raw field data.
		 *
		 * @param mixed $data
		 *	post data from the entry form
		 * @param integer $status
		 *	the status code resultant from processing the data.
		 * @param boolean $simulate (optional)
		 *	true if this will tell the CF's to simulate data creation, false
		 *	otherwise. this defaults to false. this is important if clients
		 *	will be deleting or adding data outside of the main entry object
		 *	commit function.
		 * @param mixed $entry_id (optional)
		 *	the current entry. defaults to null.
		 * @return array
		 *	the processed field data.
		 */
		public function processRawFieldData($data, &$status, &$message=null, $simulate = false, $entry_id = null) {
			$status = self::__OK__;
			$data = array_merge(array(
				'start'		=> null,
				'end'		=> null,
				'units'		=> 1,
				'mode'		=> 'weeks'
			), $data);

			$data['start'] = strtotime(DateTimeObj::get('c', strtotime($data['start'])));
			$data['end'] = strtotime(DateTimeObj::get('c', strtotime($data['end'])));
			$data['units'] = (integer)$data['units'];

			// Build data:
			if (!$simulate) {
				$field_id = $this->get('id');
				$link_id = $this->_driver->getEntryLinkId($field_id, $entry_id);
				$data['link_id'] = $link_id;

				$dates = $this->_driver->getDates($data);

				// Remove old dates:
				Symphony::Database()->query("
					DELETE QUICK FROM
						`tbl_entries_data_{$field_id}_dates`
					WHERE
						`link_id` = {$link_id}
				");

				// Insert new dates:
				foreach ($dates as $date) {
					Symphony::Database()->query("
						INSERT INTO
							`tbl_entries_data_{$field_id}_dates`
						SET
							`link_id` = {$link_id},
							`value` = {$date}
					");
				}

				// Clean up indexes:
				Symphony::Database()->query("
					OPTIMIZE TABLE
						`tbl_entries_data_{$field_id}_dates`
				");
			}

			return $data;
		}

		/**
		 * Check that a date is parsable.
		 *
		 * @param string $date
		 */
		protected function validateDate($date) {
			$string = trim((string)$date);

			if (empty($string)) return false;

			if (strtotime($string) === false) return false;

			return true;
		}
	}

?>