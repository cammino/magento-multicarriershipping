<?php
class Cammino_Multicarriershipping_Block_Adminhtml_Tablerate extends Mage_Adminhtml_Block_Widget_Grid_Container
{
  public function __construct()
  {
    $this->_controller = 'adminhtml_tablerate';
    $this->_blockGroup = 'multicarriershipping';
    $this->_headerText = Mage::helper('multicarriershipping')->__('Tablerate');
	$this->_addButtonLabel = Mage::helper('multicarriershipping')->__('Add Item');
    parent::__construct();
  }
}