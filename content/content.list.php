<?php

	require_once(TOOLKIT . '/class.administrationpage.php');

	define_safe('BASE_URL', URL . '/symphony/extension/nestedcats');
	define_safe('EXTENSION', URL . '/extensions/nestedcats');

	Class contentExtensionNestedcatsList extends AdministrationPage{

		private $_driver;
		private $_page;
		private $_id;
		private $_flag;

		private $aTableHead;

		function __construct(&$parent){
			parent::__construct($parent);
			$this->_driver = $this->_Parent->ExtensionManager->create('nestedcats');
		}

		function view(){
			$this->__switchboard('view');
		}

		function action(){
			$this->__switchboard('action');
		}

		function __switchboard($type){

			$this->_page = isset($this->_context[0]) ? $this->_context[0] : 'view';
			$this->_id = (!empty($this->_context[1]) && is_numeric($this->_context[1])) ? $this->_context[1] : 0;
			$this->_flag = $this->_context[2];

			// Notices
			if(isset($this->_flag)){
				$result = null;
				switch($this->_flag){
					case 'edited': $result = __('Category updated at %1$s.'); break;
					case 'deleted': $result = __('Category deleted'); break;
					case 'created': $result = __('Category created at %1$s. <a href="%2$s">Create another?</a>'); break;
				}

				if ($result)
						$this->pageAlert(__(
							$result, array(
								DateTimeObj::get(__SYM_TIME_FORMAT__),
								BASE_URL . '/list/new/'.$this->_id,
							)
						), Alert::SUCCESS);
			}

			$function = ($type == 'action' ? '__action' : '__view') . ($this->_page == 'view' ? 'Index' : ucfirst($this->_page));
			if(!method_exists($this, $function)) {
				if($type == 'action') return;
				$this->_Parent->errorPageNotFound();
			}
			return $this->$function();
		}


		function __viewIndex(){

			$this->addStylesheetToHead(EXTENSION . '/assets/content.nestedcats.css', 'screen', 120);
			$this->addScriptToHead(EXTENSION . '/assets/content.nestedcats.js', 200);
	//     $this->addScriptToHead(EXTENSION . '/assets/order.js', 210);
			$this->setTitle(__('Symphony &ndash; Categories &ndash; View'));
			$this->setPageType('table');

			$this->appendSubheading(__('List'), Widget::Anchor(__('Create New'), BASE_URL . '/list/new/' . $this->_id, __('Create New'), 'create button'));

			$data = $this->_driver->fetchByParent($this->_id);

			if($this->_id != 0){

				$this->aTableHead = array(array(__('Nested categories'), 'col'));

				// breadcrumbs
				if($path = $this->_driver->getPath($this->_id)){
					$ul = new XMLElement('ul');
					$ul->setAttribute('class','nc-breadcrumbs');

					$li = new XMLElement('li');
					$li->appendChild(Widget::Anchor(__('Full list &#8594;'), BASE_URL . '/list/view/', __('To the begining')));
					$ul->appendChild($li);

					foreach($path as $c){
						$li = new XMLElement('li');

						if($c['id'] == $this->_id){
							$a = Widget::Anchor($c['title'], BASE_URL . '/list/edit/' . $c['id'], __('Edit'));
						} else {
							$a = Widget::Anchor($c['title'] . ' &#8594;', BASE_URL . '/list/view/' . $c['id'], __('Categories: ') . $c['title']);
						}
						$li->appendChild($a);
						$ul->appendChild($li);
					}
					$this->Form->appendChild($ul);
				}else{
					$this->Form->appendChild(new XMLElement('h2', __('Can\'t find category')));
				}

			} else {
				$this->aTableHead = array(array(__('Title'), 'col'));
			}

			if($data){
				$aTableBody = $this->_driver->buildListView($data);
			} else {
				$aTableBody = array(Widget::TableRow(array(Widget::TableData(__('None found.'), 'inactive', NULL, count($this->aTableHead)))));
			}

			$pid = 'pid' . $this->_id;

			$table = Widget::Table(Widget::TableHead($this->aTableHead), NULL, Widget::TableBody($aTableBody), 'selectable', $pid);
			$this->Form->appendChild($table);

			// WITH SELECTED
			$tableActions = new XMLElement('div');
			$tableActions->setAttribute('class', 'actions');

			$options = array(
				array(NULL, false, __('With Selected...')),
				array('delete', false, __('Delete'))
			);

			$wrapDiv = new XMLElement('div');
			$wrapDiv->appendChild(Widget::Select('with-selected', $options, array('id' => 'sel')));
			$wrapDiv->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));
			$tableActions->appendChild($wrapDiv);

			$notice = new XMLElement('p', __('All nested Categories will be also deleted'));
			$notice->setAttribute('id', 'note');
			$notice->setAttribute('class', 'hidden');

			$tableActions->appendChild($notice);
			$this->Form->appendChild($tableActions);

		}

		function __viewNew(){

			$this->addStylesheetToHead(EXTENSION . '/assets/content.nestedcats.css', 'screen', 120);
			$this->addScriptToHead(EXTENSION . '/assets/content.nestedcats.js', 200);
	//     $this->addScriptToHead(EXTENSION . '/assets/order.js', 210);
			$this->setTitle(__('Symphony &ndash; New Category'));

			$this->setPageType('form');
			$this->Form->setAttribute('enctype', 'multipart/form-data');

			$this->appendSubheading(__('New Category'));

			if($this->_id) $parent = $this->_driver->get($this->_id);

			// Название категории
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'primary');

			$label = Widget::Label(__('Title'));
			$label->appendChild(Widget::Input('fields[title]', $_POST['fields']['title'], 'text'));

			if($this->_errors['title']){
				$label = Widget::wrapFormElementWithError($label, __('This is a required field'));
			}

			$fieldset->appendChild($label);

			$fieldset->appendChild(Widget::Input('fields[level]', $parent['level'] ? $parent['level'] : 0, 'hidden'));
			$fieldset->appendChild(Widget::Input('fields[rgt]', $parent['rgt'] ? $parent['rgt'] : 0, 'hidden'));
			$fieldset->appendChild(Widget::Input('fields[parent]', $parent['id'] ? $parent['id'] : 0, 'hidden'));


			$this->Form->appendChild($fieldset);

			// Parent
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'secondary');

			$label = Widget::Label(__('Parent Category'));
			$label->appendChild(Widget::Input(Null, $parent['title'] ? $parent['title'] : __('None'), 'text', array('disabled' => 'true')));

			$fieldset->appendChild($label);
			$this->Form->appendChild($fieldset);

			// Submit
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(Widget::Input('action[save]', __('Create'), 'submit', array('accesskey' => 's')));

			$this->Form->appendChild($div);

		}


		function __viewEdit(){

			if(!$cat = $this->_driver->get($this->_id)) $this->_Parent->errorPageNotFound();

			$this->addStylesheetToHead(EXTENSION . '/assets/content.nestedcats.css', 'screen', 120);
			$this->addScriptToHead(EXTENSION . '/assets/content.nestedcats.js', 200);
	//     $this->addScriptToHead(EXTENSION . '/assets/order.js', 210);
			$this->setTitle(__('Symphony &ndash; Edit Category &ndash; ') . $cat['title']);

			$this->setPageType('form');
			$this->Form->setAttribute('enctype', 'multipart/form-data');

			$this->appendSubheading(__('Edit Category ') . $cat['title']);

			// breadcrumbs
			if($path = $this->_driver->getPath($this->_id)){
				$ul = new XMLElement('ul');
				$ul->setAttribute('class','nc-breadcrumbs');

				$li = new XMLElement('li');
				$li->appendChild(Widget::Anchor(__('Full list &#8594;'), BASE_URL . '/list/view/', __('To the begining')));
				$ul->appendChild($li);

				foreach($path as $c){
					$li = new XMLElement('li');

					if($c['id'] == $this->_id){
						$a = new XMLElement('span', $c['title']);
					} else {
						$a = Widget::Anchor($c['title'] . ' &#8594;', BASE_URL . '/list/view/' . $c['id'], __('Category: ') . $c['title']);
					}
					$li->appendChild($a);
					$ul->appendChild($li);
				}
				$this->Form->appendChild($ul);
			}

			// Category Title
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'primary');

			$label = Widget::Label(__('Title'));
			$label->appendChild(Widget::Input('fields[title]', $cat['title'], 'text'));

			if($this->_errors['title']){
				$label = Widget::wrapFormElementWithError($label, __('This is a required field'));
			}

			$fieldset->appendChild($label);

			$fieldset->appendChild(Widget::Input('fields[level]', $cat['level'] ? $cat['level'] : 0, 'hidden'));
			$fieldset->appendChild(Widget::Input('fields[rgt]', $cat['rgt'], 'hidden'));
			$fieldset->appendChild(Widget::Input('fields[lft]', $cat['lft'], 'hidden'));
			$fieldset->appendChild(Widget::Input('fields[id]', $cat['id'], 'hidden'));
			$label->appendChild(Widget::Input('fields[parent]', $cat['parent'], 'hidden'));

			$this->Form->appendChild($fieldset);

			// Parent Category
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'secondary');

			$parent = $this->_driver->get($cat['parent']);

			$label = Widget::Label(__('Parent Category'));
			$label->appendChild(Widget::Input(Null, $cat['parent'] == 0 ? __('None') : $parent['title'], 'text', array('disabled' => 'true')));

			$fieldset->appendChild($label);
			$this->Form->appendChild($fieldset);



			// Submit
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(Widget::Input('action[update]', __('Save Changes'), 'submit', array('accesskey' => 's')));

			$button = new XMLElement('button', __('Delete'));
			$button->setAttributeArray(array('name' => 'action[delete]', 'class' => 'confirm delete', 'title' => __('Delete this Category')));
			$div->appendChild($button);

			$this->Form->appendChild($div);

		}


		function __actionIndex(){
			if($_POST['with-selected'] == 'delete' && (!empty($_POST['items']))){
				$this->_driver->delete($_POST);
				redirect(BASE_URL . '/list/view/' . $this->_id . '/deleted/');
			}
		}


		function __actionNew(){

			if(empty($_POST['fields']['title'])) {
				$this->_errors = 'title';
				$this->pageAlert(__('Title is a required field'), Alert::ERROR);
				return;
			}

			if($this->_driver->newCat($_POST['fields'])) {
				redirect(BASE_URL . '/list/view/' . $_POST['fields']['parent'] . '/created/');
			} else {
				define_safe('__SYM_DB_INSERT_FAILED__', true);
				$this->pageAlert(NULL, AdministrationPage::PAGE_ALERT_ERROR);
			}
		}

		function __actionEdit(){

			if(@array_key_exists('update', $_POST['action'])){
				if(empty($_POST['fields']['title'])) {
					$this->_errors = 'title';
					$this->pageAlert(__('Title is a required field'), Alert::ERROR);
					return;
				}

				if($this->_driver->edit($_POST)){
					redirect(BASE_URL . '/list/edit/' . $this->_id . '/edited/');
				} else {
					define_safe('__SYM_DB_INSERT_FAILED__', true);
					$this->pageAlert('Error', Alert::ERROR);
				}
			}

			if(@array_key_exists("delete", $_POST['action'])){
				if($this->_driver->delete($_POST)){
					redirect(BASE_URL . '/list/view/' . $_POST['fields']['parent'] . '/deleted/');
				} else {
					define_safe('__SYM_DB_INSERT_FAILED__', true);
					$this->pageAlert('Error', Alert::ERROR);
				}
			}
		}

	}

?>