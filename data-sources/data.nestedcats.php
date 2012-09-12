<?php

	Class datasourceNestedCats extends Datasource{

		public $dsParamROOTELEMENT = 'nested-categories';
		public $dsParamROOTNODE = '0'; // id or handle of the root category to be displayed as "main-tree"
		public $dsParamFILTERS = array(
				'filter' => '{$cat}',
		);

		public function __construct($env=NULL, $process_params=true){
			parent::__construct($env, $process_params);
			$this->_dependencies = array();
		}

		function example(){
			return '<'. $this->dsParamROOTELEMENT .'>
	<main-tree>
		<item id="7" handle="fruits" parent-id="1" level="0">Fruits</item>
		<item id="8" handle="apples" parent-id="7" level="1">Apples</item>
		<item id="9" handle="bananas" parent-id="7" level="1">Bananas</item>
		<item id="10" handle="animals" parent-id="1" level="0">Animals</item>
		<item id="11" handle="giraffes" parent-id="10" level="1">Giraffes</item>
		<item id="12" handle="pandas" parent-id="10" level="1">Pandas</item>
	</main-tree>
</'. $this->dsParamROOTELEMENT .'>

Usage Example:

<ul>
	<xsl:apply-templates select="'. $this->dsParamROOTELEMENT .'/main-tree/item[@level = 0]"/>
</ul>

<xsl:template match="'. $this->dsParamROOTELEMENT .'/main-tree/item">
	<li>
		<a href="{$root}/test/{@handle}"><xsl:value-of select="."/></a>
		<xsl:if test="/data/'. $this->dsParamROOTELEMENT .'/main-tree/item[@parent-id = current()/@id]">
			<ul>
				<xsl:apply-templates select="/data/'. $this->dsParamROOTELEMENT .'/main-tree/item[@parent-id = current()/@id]"/>
			</ul>
		</xsl:if>
	</li>
</xsl:template>
';
		}

		function about(){

			return array(
				"name" => __('Nested Categories'),
				"description" => __('Nested Categories Data Source'),
				"author" => array("name" => "Andrey Lubinov",
					"email" => "andrey.lubinov@gmail.com"),
					"version" => "2.0.1",
				"release-date" => "2010-06-06",
			);
		}

		function grab(&$param_pool=NULL){
			include_once(EXTENSIONS . '/nestedcats/extension.driver.php');
			$driver = Symphony::ExtensionManager()->create('nestedcats');

			// $this->dsParamROOTNODE = !empty($this->dsParamFILTERS['filter']) ? $this->dsParamFILTERS['filter'] : $this->dsParamROOTNODE;

			$xml = new XMLElement($this->dsParamROOTELEMENT);

			if(!$data = $driver->fetch($this->dsParamROOTNODE)) return $xml->appendChild(new XMLElement('error', __('No data received.')));

			$main_tree = new XMLElement('main-tree');
			foreach($data as $c) {
				$item = new XMLElement('item', $c['title'],
					array(
						'id' => $c['id'],
						'handle' => $c['handle'],
						'parent-id' => $c['parent'],
						'level' => $c['level']
					)
				);

				$main_tree->appendChild($item);
			}

			$xml->appendChild($main_tree);

			return $xml;
		}
	}

?>