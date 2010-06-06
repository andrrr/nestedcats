<?php

	Class datasourceNestedCats extends Datasource{

		function __construct(&$parent){
			parent::__construct($parent);
		}

		function example(){
			return '<nested-cats>
	<main-tree>
		<item id="7" parent-id="1" level="0">Fruits</item>
		<item id="8" parent-id="7" level="1">Apples</item>
		<item id="9" parent-id="7" level="1">Bananas</item>
		<item id="10" parent-id="1" level="0">Animals</item>
		<item id="11" parent-id="10" level="1">Giraffes</item>
		<item id="12" parent-id="10" level="1">Pandas</item>
	</main-tree>
</nested-cats>

Example Usage:

<ul>
	<xsl:apply-templates select="nested-cats/main-tree/item[@level = 0]"/>
</ul>

<xsl:template match="nested-cats/main-tree/item">
	<li>
		<a href="{$root}/test/{@handle}"><xsl:value-of select="."/></a>
		<xsl:if test="/data/main-tree/nested-cats/item[@parent-id = current()/@id]">
			<ul>
				<xsl:apply-templates select="/data/main-tree/nested-cats/item[@parent-id = current()/@id]"/>
			</ul>
		</xsl:if>
	</li>
</xsl:template>
';
		}

		function about(){

			return array(
				"name" => __('Nested Categories'),
				"description" => __('Nested Categories'),
				"author" => array("name" => "Andrey Lubinov",
					"email" => "andrey.lubinov@gmail.com"),
					"version" => "2.0.1",
				"release-date" => "2010-06-06",
			);
		}

		function grab(){

			include_once(EXTENSIONS . '/nestedcats/extension.driver.php');
			$driver = $this->_Parent->ExtensionManager->create('nestedcats');
			$xml = new XMLElement('nested-cats');
			if(!$data = $driver->fetch(0)) return $xml->appendChild(new XMLElement('error', __('No data received.')));

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