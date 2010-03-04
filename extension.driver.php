<?php

	Class extension_nestedcats extends Extension{

		public function about(){
			return array('name' => __('Nested Cats beta2'),
						 'version' => '2.0',
						 'release-date' => '2010-03-04',
						 'author' => array('name' => 'Andrey Lubinov',
								   'email' => 'andrey.lubinov@gmail.com')
				 		);
		}

    public $extension_handle = 'nestedcats';

		public function install(){

			$this->_Parent->Database->query("CREATE TABLE IF NOT EXISTS `tbl_{$this->extension_handle}` (
						`id` int(11) NOT NULL auto_increment,
						`parent` int(11) NOT NULL,
						`lft` int(11) NOT NULL,
						`rgt` int(11) NOT NULL,
						`level` int(3) NOT NULL default '0',
						`title` varchar(255),
						PRIMARY KEY  (`id`),
						KEY `lft` (`lft`,`rgt`,`level`)
					) ENGINE=MyISAM  DEFAULT CHARSET=utf8
			");

			$this->_Parent->Database->query("CREATE TABLE IF NOT EXISTS `tbl_fields_{$this->extension_handle}` (
				`id` int(11) unsigned NOT NULL auto_increment,
				`field_id` int(11) unsigned NOT NULL,
				`related_field_id` VARCHAR(255) NOT NULL,
				`allow_multiple_selection` enum('yes','no') default 'no',
				PRIMARY KEY  (`id`),
				KEY `field_id` (`field_id`)
			)");

			return true;
		}

		public function uninstall(){
 			$this->_Parent->Database->query("DROP TABLE IF EXISTS `tbl_fields_{$this->extension_handle}`");
			$this->_Parent->Database->query("DROP TABLE IF EXISTS `tbl_{$this->extension_handle}`");
			$this->_Parent->Database->query("DELETE FROM `tbl_fields` WHERE `type` = '{$this->extension_handle}'");
		}

		public function fetchNavigation(){
			return array(
				array(
					'location' => 10,
					'name' => __('Categories'),
					'children' => array(
						array(
							'name' => __('List View'),
							'link' => '/list/'
						),
						array(
							'name' => __('Tree View'),
							'link' => '/tree/'
						),
					)
				)
			);
		}

		public function get($id) {
			return  $this->_Parent->Database->fetchRow(0,"
						SELECT * FROM `tbl_{$this->extension_handle}`
						WHERE `id` = $id
						LIMIT 1
			");
		}

		public function fetch($id=null, $includeCurrent=true){
			$where = null;
			if($id){

				$c = $this->_Parent->Database->fetchRow(0, "
						SELECT `lft`, `rgt` FROM `tbl_{$this->extension_handle}`
						WHERE `id` = $id LIMIT 1
					");

				if(empty($c)) return false;

				$where = $includeCurrent ?
					" WHERE `lft` >= {$c['lft']} AND `rgt` <= {$c['rgt']}" : " WHERE `lft` > {$c['lft']} AND `rgt` < {$c['rgt']}";
			}
			return $this->_Parent->Database->fetch("SELECT * FROM `tbl_{$this->extension_handle}`" . $where . " ORDER BY `lft` ASC");
		}

		public function fetchWithExclude($exclude){

			return $data = $this->_Parent->Database->fetch("
					SELECT * FROM `tbl_{$this->extension_handle}`
					WHERE `lft` NOT BETWEEN {$exclude['lft']} AND {$exclude['rgt']}
					ORDER BY `lft` ASC
				");

		}

		public function fetchByParent($parent) {
			return $this->_Parent->Database->fetch("
						SELECT * FROM `tbl_{$this->extension_handle}`
						WHERE parent = $parent
						ORDER BY `lft` ASC
			");
		}

    public function getPath($id) {

      if(!$cat = $this->_Parent->Database->fetchRow(0, "
            SELECT `lft`, `rgt`
            FROM tbl_{$this->extension_handle}
            WHERE `id` = $id
            LIMIT 1
      ")) return false;

      return $this->_Parent->Database->fetch("
            SELECT * FROM `tbl_{$this->extension_handle}`
            WHERE `lft` <= {$cat['lft']} AND `rgt` >= {$cat['rgt']}
            ORDER BY `lft`
      ");
    }

		public function newCat($fields){

			if($fields['parent'] == 0) {

				if(!$rgt = $this->_Parent->Database->fetchVar("max", 0, "
						SELECT MAX(`rgt`) AS `max`
						FROM `tbl_{$this->extension_handle}`
						LIMIT 1
				")) $rgt = 0;

				return $this->_Parent->Database->query("
						INSERT INTO `tbl_{$this->extension_handle}`
						SET `parent` = 0,
								`lft` = $rgt + 1,
								`rgt` = $rgt + 2,
								`level` = 0,
								`title` = '".General::sanitize(mysql_real_escape_string($fields['title']))."'
				") or die('Ошибка при создании №1' . mysql_error());

			} else {

				// if creating cat in tree view
				if($fields['rgt'] == 0 || $fields['level'] == 0){
					$tmp = $this->get($fields['parent']);
					$fields['rgt'] = $tmp['rgt'];
					$fields['level'] = $tmp['level'];
				}

				$sql = "UPDATE `tbl_{$this->extension_handle}`
					SET `rgt` = `rgt` + 2,
							`lft` =  CASE
														WHEN `lft` > {$fields['rgt']}
															THEN `lft` + 2
														ELSE `lft`
													END
					WHERE `rgt` >= {$fields['rgt']}";

				$this->_Parent->Database->query($sql);

				return $this->_Parent->Database->query("INSERT INTO `tbl_{$this->extension_handle}`
					SET `lft` = {$fields['rgt']},
							`rgt` = {$fields['rgt']} + 1,
							`level` = {$fields['level']} + 1,
							`parent` = {$fields['parent']},
							`title` = '{$fields['title']}'
				") or die('Ошибка при создании №2' . mysql_error());

			}
		}


		public function edit($post){

			if(@array_key_exists('update', $post['action'])){
				return $this->updateCat($post['fields']['lft'], $post['fields']['title']);
			}

			if(@array_key_exists('edit', $post['action'])){

					if($post['fields']['parent'] == $this->_Parent->Database->fetchVar("parent", 0, "
							SELECT `parent` AS `parent` FROM `tbl_{$this->extension_handle}`
							WHERE `lft` = {$post['fields']['lft']} LIMIT 1
						")) return $this->updateCat($post['fields']['lft'], $post['fields']['title']);

					return $this->move($post['fields']);
			}

		}


		public function move($fields){

			if($fields['parent'] == 0){

				if(!$rgt = $this->_Parent->Database->fetchVar("max", 0, "
						SELECT MAX(`rgt`) AS `max`
						FROM `tbl_{$this->extension_handle}`
						LIMIT 1
				")) return false;

				$this->_Parent->Database->query("
						UPDATE `tbl_{$this->extension_handle}`
						SET
							`title` = '{$fields['title']}',
							`parent` = 0,
							`lft` = $rgt+1,
							`level` = 0
						WHERE `lft` = {$fields['lft']}
					") or die(mysql_error());

				return $this->rebuildTree(0,0);

			}

			if(!$newp = $this->_Parent->Database->fetchRow(0, "
											SELECT `id`, `level`, `rgt` FROM `tbl_{$this->extension_handle}`
											WHERE `id` = {$fields['parent']} LIMIT 1
				")) return false;

			$this->_Parent->Database->query("
					UPDATE `tbl_{$this->extension_handle}`
					SET
						`title` = '{$fields['title']}',
						`parent` = {$newp['id']},
						`lft` = {$newp['rgt']},
						`level` = {$newp['level']} + 1
					WHERE `lft` = {$fields['lft']}
				") or die(mysql_error());

			return $this->rebuildTree(0,0);
		}


		public function updateCat($lft, $title){
			return $this->_Parent->Database->query("
					UPDATE `tbl_{$this->extension_handle}` SET `title` = '".General::sanitize(mysql_real_escape_string($title))."'
					WHERE `lft` = $lft
			");
		}


		public function delete($post){

			if(@array_key_exists('with-selected', $post)){

				if(count($post['items'] > 1)) // Delete Multiple With selected
				return $this->deleteMultiple($post);
				$item = array_keys($post['items']);
				$lft = $lft[$item[0]];
				$rgt = $rgt[$item[0]];

			} else {

				$lft = $post['fields']['lft'];
				$rgt = $post['fields']['rgt'];

			}

			$this->_Parent->Database->delete("tbl_{$this->extension_handle}", '
					`lft` >= '.$lft.' AND `rgt` <= '.$rgt.'
				');

			return $this->_Parent->Database->query("
					UPDATE `tbl_{$this->extension_handle}`
					SET `lft` =  CASE
															WHEN `lft` > $lft
																THEN `lft` - ( $rgt - $lft + 1 )
															ELSE `lft`
														END,
							`rgt` = `rgt` - ( $rgt - $lft + 1 )
					WHERE `rgt` > $rgt
			") or die('Ошибка при удалении №1' . mysql_error());

		}


		public function deleteMultiple($post){

			$lft = $post['lft'];
			$rgt = $post['rgt'];

			foreach($post['items'] as $cat => $v){
				$this->_Parent->Database->delete("tbl_{$this->extension_handle}", "
					(`lft` >= {$lft[$cat]} AND `rgt` <= {$rgt[$cat]})
				");
			}

			$this->rebuildTree(0, 0);
			return $this->_Parent->Database->query("OPTIMIZE TABLE `tbl_{$this->extension_handle}`");

		}


		function rebuildTree($parent, $lft) {

			$cats = $this->_Parent->Database->fetch("
					SELECT `id` FROM `tbl_{$this->extension_handle}`
					WHERE `parent` = $parent
					ORDER BY `lft` ASC
				");
			$rgt = $lft+1;
			foreach ($cats as $cat) {
				$rgt = $this->rebuildTree($cat['id'], $rgt);
			}
			$this->_Parent->Database->query("
					UPDATE `tbl_{$this->extension_handle}`
					SET `lft` = $lft, `rgt` = $rgt
					WHERE `id` = $parent
			");
			return $rgt+1;
		}


		function buildSelectAtCatsPage($current, $exclude=false){

			$data = $exclude ? $this->fetchWithExclude($exclude) : $this->fetch();

			$options = array(array(0, null, __('None')));

			foreach ($data as $o){
				$options[] = array(
					$o['id'],
					$o['id'] == $current,
					str_repeat('- ', $o['level']) . $o['title']
				);
			}

			$select = Widget::Select(
				'fields[parent]',
				$options, count($data)>0 ? NULL : array('disabled' => 'true'));

			return $select;

		}


		function buildSelectAtPublishPannel($root, $selected, $fieldnamePrefix=NULL, $elementName, $fieldnamePostfix=NULL, $multiple){

			if(!$data = $this->fetch($root, $includeCurrent=false)) return new XMLElement('p', __('It looks like youre trying to create an entry. Perhaps you want categories first? <br/><a
href="%s">Click here to create some.</a>', array(URL . '/symphony/extension/nestedcats/list/')));

			$options = array(array(NULL, NULL, __('Choose')));

			foreach ($data as $o){
				$options[] = array(
					$o['id'],
					in_array($o['id'], $selected),
					str_repeat('- ', $o['level']) . $o['title']
				);

			}

			$fieldname = 'fields['.$elementName.']';
			$attributes = array();

			if($multiple) {
				$fieldname .= '[]';
				$attributes['multiple'] = 'true';
			}

			if(count($data) == 0) $attributes['disabled'] = 'true';

			$select = Widget::Select($fieldname, $options, $attributes);

			return $select;

		}

		function buildSelectAtSettingsPannel($current, $fieldnamePrefix=NULL, $elementName, $fieldnamePostfix=NULL){

			if(!$data = $this->fetch()) return new XMLElement('p', __('It looks like youre trying to create a field. Perhaps you want categories first? <br/><a href="%s">Click here to create some.</a>',
array(URL . '/symphony/extension/nestedcats/list/')));

			$options = array(array(0, NULL, __('Full tree')));

			foreach ($data as $o){
				$options[] = array(
					$o['id'],
					$o['id'] == $current,
					str_repeat('- ', $o['level']) . $o['title']
				);

			}

			$select = Widget::Select(
				'fields['.$fieldnamePrefix.']['.$elementName.']['.$fieldnamePostfix.']',
				$options, count($data)>0 ? NULL : array('disabled' => 'true'));

			return $select;

		}


    public function buildListView($data) {

      foreach($data as $cat){

        $title = $cat['rgt'] == ($cat['lft'] + 1) ? $cat['title'] : $cat['title'] . ' &#8594;';

        $item = Widget::TableData(Widget::Anchor($title, URL . '/symphony/extension/nestedcats/list/view/' . $cat['id'] . '/', __('View Category: ') . $cat['title'], $class));
        $item->appendChild(Widget::Input('items['.$cat['id'].']', NULL, 'checkbox'));
        $item->appendChild(Widget::Input('lft['.$cat['id'].']', $cat['lft'], 'text'));
        $item->appendChild(Widget::Input('rgt['.$cat['id'].']', $cat['rgt'], 'text'));

        $result[] = Widget::TableRow(array($item), ($bEven ? 'even' : NULL));
        $bEven = !$bEven;

      }
      return $result;
    }


    public function buildTreeView($data) {

      foreach($data as $cat){

        $title = $cat['level'] == 0 ? $cat['title'] : '&#8594; ' . $cat['title'];

        $item = Widget::TableData(Widget::Anchor($title, URL . '/symphony/extension/nestedcats/tree/view/' . $cat['id'] . '/', __('View Category: ') . $cat['title'], 'n'.$cat['level']));
        $item->appendChild(Widget::Input('items['.$cat['id'].']', NULL, 'checkbox'));
        $item->appendChild(Widget::Input('lft['.$cat['id'].']', $cat['lft'], 'text'));
        $item->appendChild(Widget::Input('rgt['.$cat['id'].']', $cat['rgt'], 'text'));

        $result[] = Widget::TableRow(array($item), ($bEven ? 'even' : NULL));
        $bEven = !$bEven;

      }
      return $result;
    }



		function buildSelectField($field, $start, $current, $parent=NULL, $element_name, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL, $exclude=NULL, $settingsPannel=NULL) {

			if(!$tree = $this->getTree($field,$start,$exclude)) return Widget::Select(NULL, NULL, array('disabled' => 'true'));

			$right = array($tree[0]['rgt']);

			if(!$settingsPannel) {
				$options = array(array(NULL,NULL,'None'));
			} elseif ($settingsPannel && count($tree) == 1) {

				return new XMLElement('p', __('It looks like youre trying to create a field. Perhaps you want categories first? <br/><a href="%s">Click here to create some.</a>', array(URL . '/symphony/extension/nestedcats/overview/new/')));

			} else {
				$options = array(array($tree[0]['id'], NULL, __('Full Tree')));
			}

			array_shift($tree);

			$selected = isset($parent) ? $parent : $current;

			foreach ($tree as $o){

				while ($right[count($right)-1]<$o['rgt']) {
					array_pop($right);
				}

				$options[] = array(
					$o['id'],
					$o['id'] == $selected,
					str_repeat('- ',count($right)-1) . $o['title']
				);

				$right[] = $o['rgt'];
			}

			$select = Widget::Select(
				'fields'.$fieldnamePrefix.'['.$element_name.']'.$fieldnamePostfix,
				$options, count($tree)>0 ? NULL : array('disabled' => 'true'));

			return $select;

		}

		function makeTitle($title){
			return General::sanitize(
					function_exists('mysql_real_escape_string') ? mysql_real_escape_string(trim($title)) : addslashes(trim($title))
				);
		}




}

