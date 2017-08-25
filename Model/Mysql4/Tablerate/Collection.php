<?php

class Cammino_Multicarriershipping_Model_Mysql4_Tablerate_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    public function _construct()
    {
    	parent::_construct();
        $this->_init('multicarriershipping/tablerate');
    }
}