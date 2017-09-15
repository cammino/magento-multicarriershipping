<?php
class Cammino_Multicarriershipping_Model_Source_Tablerate_Groups extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{

	public function getAllOptions()
	{
		return $this->toOptionArray();
	}

    public function toOptionArray($convert = false)
    {
		$resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');

        $query = 'SELECT * FROM multicarriershipping_tablerate_groups';
         
        $values = $readConnection->fetchAll($query);
		foreach($values as $value) {
			$groups[] = array('value' => ($convert ? $value['name'] : $value['id']), 'label' => $value['name']);
		}

		return $groups;
	}

	public function getAllGroups()
    {
		$resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');

        $query = 'SELECT * FROM multicarriershipping_tablerate_groups';
         
        $values = $readConnection->fetchAll($query);
		foreach($values as $value) {
			$groups[] = array('value' => $value['name'], 'label' => $value['name']);
		}

		return $groups;
	}
}