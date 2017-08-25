<?php

class Cammino_Multicarriershipping_Block_Adminhtml_Tablerate_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{

  public function __construct()
  {
      parent::__construct();
      $this->setId('tablerate_tabs');
      $this->setDestElementId('edit_form');
      $this->setTitle(Mage::helper('multicarriershipping')->__('Tablerate'));
  }

  protected function _beforeToHtml()
  {
      $this->addTab('form_section', array(
          'label'     => Mage::helper('multicarriershipping')->__('Fields'),
          'title'     => Mage::helper('multicarriershipping')->__('Fields'),
          'content'   => $this->getLayout()->createBlock('multicarriershipping/adminhtml_tablerate_edit_tab_form')->toHtml(),
      ));
     
      return parent::_beforeToHtml();
  }
}