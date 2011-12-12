<?php

	Class datasourceNC_Breadcrumbs extends Datasource{

		public $dsParamROOTELEMENT = 'nc-breadcrumbs';
		public $dsParamFILTERS = array(
				'current' => '{$category}' // change 'category' to the name of your URL Parameter  
		);

		public function __construct(&$parent, $env=NULL, $process_params=true){
			parent::__construct($parent, $env, $process_params);
			$this->_dependencies = array();
		}

		function example(){
			return false;
		}

		function about(){

			return array(
				"name" => __('Nested Categories Breadcrumbs'),
				"description" => false,
				"author" => array("name" => "Andrey Lubinov",
					"email" => "andrey.lubinov@gmail.com"),
					"version" => "1.0",
				"release-date" => "2011-11-12",
			);
		}

		function grab(&$param_pool=NULL){

			$result = new XMLElement($this->dsParamROOTELEMENT);
			$current = $this->dsParamFILTERS['current'];
			
			if(!(bool)$current) return $result;

			$driver = Symphony::ExtensionManager()->getInstance('nestedcats');
			
			if(!is_numeric($current)) {
				$current = Symphony::Database()->fetchVar('id', 0, 
					sprintf(
						"SELECT `id` FROM `tbl_%s` WHERE `handle` = '%s' LIMIT 1", 
						$driver->extension_handle,
						Mysql::cleanValue($current)
					)
				);
			}
			
			$data = $driver->getPath($current);
			
			if(!is_array($data) || empty($data)) {
				return $result->appendChild(new XMLElement('error', __('None found')));
			}

			$path = new XMLElement('path');

			foreach($data as $c) {
				$path->appendChild(
					new XMLElement(
						'item', 
						$c['title'],
						array(
							'id' => $c['id'],
							'handle' => $c['handle'],
							'parent-id' => $c['parent'],
							'level' => $c['level']
						)
					)
				);
			}

			$result->appendChild($path);

			return $result;
		}
	}

?>