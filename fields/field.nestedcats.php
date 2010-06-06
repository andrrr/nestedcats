<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	Class fieldNestedCats extends Field{

		protected $_driver = null;

		function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = __('Nested Categories');
			$this->_required = true;

			$this->_driver = $this->_engine->ExtensionManager->create('nestedcats');

			// Set default
			$this->set('show_column', 'no');
			$this->set('required', 'yes');
		}

		function canFilter(){
			return true;
		}

		function allowDatasourceOutputGrouping(){
			if($this->get('allow_multiple_selection') == 'yes') return false;
			return true;
		}

		function allowDatasourceParamOutput(){
			return true;
		}

		public function getParameterPoolValue($data){
			return $data['relation_id'];
		}

		public function set($field, $value){
			if($field == 'related_field_id' && !is_array($value)){
				$value = explode(',', $value);
			}
			$this->_fields[$field] = $value;
		}

		public function setArray($array){
			if(empty($array) || !is_array($array)) return;
			foreach($array as $field => $value) $this->set($field, $value);
		}

		public function groupRecords($records){
			if(!is_array($records) || empty($records)) return;

			$groups = array($this->get('element_name') => array());

			foreach($records as $r){
				$data = $r->getData($this->get('id'));
				$value = $data['relation_id'];
				if(!isset($groups[$this->get('element_name')][$value])){
					$groups[$this->get('element_name')][$value] = array('attr' => array('link-id' => $data['relation_id'], 'link-handle' => $data['handle']));
				}
				$groups[$this->get('element_name')][$value]['records'][] = $r;
			}

			return $groups;
		}

		public function prepareTableValue($data, XMLElement $link=NULL){
			if(!is_array($data) || (is_array($data) && !isset($data['relation_id']))) return parent::prepareTableValue(NULL);

			if(!is_array($data['relation_id'])){
				$data['relation_id'] = array($data['relation_id']);
				$data['value'] = array($data['value']);
				$data['handle'] = array($data['handle']);
			}

			$output = NULL;

			foreach($data['relation_id'] as $k => $v){
				$link = Widget::Anchor($data['value'][$k], URL . '/symphony/extension/nestedcats/list/view/' . $data['relation_id'][$k]);
				$output .= $link->generate() . ' ';
			}

			return trim($output);

		}


		function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){
			$status = self::__OK__;

			if(empty($data)) return NULL;

			if(!is_array($data)) $data = array('relation_id' => $data);
			$result = array();

			foreach($data as $a => $value) {
			  $result['relation_id'][] = $data[$a];
			  $cat = $this->_driver->get($value);
			  $result['value'][] = $cat['title'];
			  $result['handle'][] = $cat['handle'];
			}

			return $result;

		}

		public function appendFormattedElement(&$wrapper, $data, $encode = false) {
			if (!is_array($data) || empty($data)) return;

			$list = new XMLElement($this->get('element_name'));

			if (!is_array($data['relation_id'])) {
				$data['relation_id'] = array($data['relation_id']);
				$data['handle'] = array($data['handle']);
				$data['value'] = array($data['value']);
			}

			foreach ($data['relation_id'] as $k => $v) {
				$list->appendChild(new XMLElement('item', General::sanitize($data['value'][$k]), array(
					'handle'	=> $data['handle'][$k],
					'id' => $v,
				)));
			}

			$wrapper->appendChild($list);
		}

		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){

			if(!is_array($data['relation_id'])){
				$entry_ids = array($data['relation_id']);
			}else{
				$entry_ids = array_values($data['relation_id']);
			}

			if(!$root = $this->get('related_field_id')){
				$select = Widget::Select(NULL, NULL, array('disabled' => 'true'));
			} else {

				$multiple = $this->get('allow_multiple_selection') == 'yes';
				$select = $this->_driver->buildSelectAtPublishPannel($root[0], $entry_ids, $fieldnamePrefix, $this->get('element_name'), $fieldnamePostfix, $multiple);

			}

			$label = Widget::Label($this->get('label'));
			$label->appendChild($select);

			if($flagWithError != NULL) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			else $wrapper->appendChild($label);
		}


		function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);
			$div = new XMLElement('div', NULL, array('class' => 'group'));
			$label = Widget::Label(__('Root'));

			$sectionManager = new SectionManager($this->_engine);
			$sections = $sectionManager->fetch(NULL, 'ASC', 'name');

			$field_groups = array();

			if(is_array($sections) && !empty($sections)){
				foreach($sections as $section) $field_groups[$section->get('id')] = array('fields' => $section->fetchFields(), 'section' => $section);
			}
			$current = $this->get('related_field_id');
			$select = $this->_driver->buildSelectAtSettingsPannel($current[0], $this->get('sortorder'), 'related_field_id', null);

			$label->appendChild($select);
			$div->appendChild($label);

			if(isset($errors['related_field_id'])) $wrapper->appendChild(Widget::wrapFormElementWithError($div, $errors['related_field_id']));
			else $wrapper->appendChild($div);

			## Allow multiple selection
			$label = Widget::Label();
			$input = Widget::Input('fields['.$this->get('sortorder').'][allow_multiple_selection]', 'yes', 'checkbox');
			if($this->get('allow_multiple_selection') == 'yes') $input->setAttribute('checked', 'checked');
			$label->setValue($input->generate() . ' ' . __('Allow selection of multiple options'));
			$wrapper->appendChild($label);

			$this->appendShowColumnCheckbox($wrapper);
			$this->appendRequiredCheckbox($wrapper);

		}



		function commit(){

			if(!parent::commit()) return false;

			$id = $this->get('id');

			if($id === false) return false;

			$fields = array();

			$fields['field_id'] = $id;
			if($this->get('related_field_id') != '') $fields['related_field_id'] = $this->get('related_field_id');

			$fields['related_field_id'] = implode(',', $this->get('related_field_id'));
			$fields['allow_multiple_selection'] = ($this->get('allow_multiple_selection') ? $this->get('allow_multiple_selection') : 'no');

			$this->Database->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id'");

			if(!$this->Database->insert($fields, 'tbl_fields_' . $this->handle())) return false;

			$this->removeSectionAssociation($id);

			foreach($this->get('related_field_id') as $field_id){
				$this->createSectionAssociation(NULL, $id, $field_id);
			}

			return true;

		}

		function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC'){

			$joins .= "INNER JOIN `tbl_entries_data_".$this->get('id')."` AS `ed` ON (`e`.`id` = `ed`.`entry_id`) ";
			$sort = 'ORDER BY ' . (in_array(strtolower($order), array('random', 'rand')) ? 'RAND()' : "`ed`.`relation_id` $order");
		}

		function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false){

			$field_id = $this->get('id');

			$mode = is_numeric($data[0]) ? 'id' : 'handle';

			$tree = ($mode == 'id') ? $this->_driver->fetch($data[0]) : $this->_driver->fetchByHandle($data[0]);
			if(!$tree) return false;

			$cats = array();
			foreach($tree as $cat) {
				$cats[] = "'$cat[$mode]'";
			}
			unset($tree);

			if(!$cats) return false;

			$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";

			if($mode == 'handle') {
				$where .= " AND `t$field_id`.handle IN (".@implode(', ', $cats).") ";
				return true;
			}

			$where .= " AND `t$field_id`.relation_id IN (".@implode(', ', $cats).") ";
			return true;

		}

		function createTable(){

			return $this->_engine->Database->query(

				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				`id` int(11) unsigned NOT NULL auto_increment,
				`entry_id` int(11) unsigned NOT NULL,
				`relation_id` int(11) unsigned NOT NULL,
				`handle` varchar(50) NOT NULL,
				`value` varchar(250) NOT NULL,
				PRIMARY KEY  (`id`),
				KEY `entry_id` (`entry_id`),
				KEY `relation_id` (`relation_id`)
				) TYPE=MyISAM;"
			);
		}

		public function getExampleFormMarkup(){
			return Widget::Input('fields['.$this->get('element_name').']', '...', 'hidden');
		}

	}
