<?php

class Cammino_Multicarriershipping_Model_Mysql4_Tablerate extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {    
    	// Note that the banners_id refers to the key field in your database table.
        $this->_init('multicarriershipping/tablerate', 'tablerate_id');
    }
}