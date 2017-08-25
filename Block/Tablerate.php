<?php
class Cammino_Multicarriershipping_Block_Tablerate extends Mage_Core_Block_Template
{
	public function _prepareLayout()
    {
		return parent::_prepareLayout();
    }
    
     public function getTablerate()     
     { 
        if (!$this->hasData('tablerate')) {
            $this->setData('tablerate', Mage::registry('tablerate'));
        }
        return $this->getData('tablerate');
        
    }
}