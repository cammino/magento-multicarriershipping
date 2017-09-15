<?php
// formulario para adicionar tablerate
class Cammino_Multicarriershipping_Block_Adminhtml_Tablerate_Edit_Tab_Form extends Mage_Adminhtml_Block_Widget_Form
{
	protected function _prepareForm()
	{
		$form = new Varien_Data_Form();
		$this->setForm($form);
		$fieldset = $form->addFieldset('tablerate_form', array('legend'=>Mage::helper('multicarriershipping')->__('Item Information')));

		$fieldset->addField('zipcode_start', 'text', array(
			'label'     => Mage::helper('multicarriershipping')->__('Initial ZIP Code'),
			'name'      => 'zipcode_start',
			'required'  => true,
			'class'     => 'validate-number',
		));

		$fieldset->addField('zipcode_end', 'text', array(
			'label'     => Mage::helper('multicarriershipping')->__('Final ZIP Code'),
			'name'      => 'zipcode_end',
			'required'  => true,
			'class'     => 'validate-number',
		));

		$fieldset->addField('weight_start', 'text', array(
			'label'     => Mage::helper('multicarriershipping')->__('Initial Weight'),
			'class'     => 'required-entry',
			'required'  => true,
			'name'      => 'weight_start',
			'class'     => 'validate-number',
		));

		$fieldset->addField('weight_end', 'text', array(
			'label'     => Mage::helper('multicarriershipping')->__('Final Weight'),
			'class'     => 'required-entry',
			'required'  => true,
			'name'      => 'weight_end',
			'class'     => 'validate-number',
		));

		$fieldset->addField('price', 'text', array(
			'label'     => Mage::helper('multicarriershipping')->__('Price'),
			'class'     => 'required-entry',
			'required'  => true,
			'name'      => 'price',
			'class'     => 'validate-number',
		));

		$fieldset->addField('additional_price', 'text', array(
			'label'     => Mage::helper('multicarriershipping')->__('Additional Price'),
			'class'     => 'required-entry',
			'required'  => true,
			'name'      => 'additional_price',
			'class'     => 'validate-number',
		));

		$fieldset->addField('shipping_days', 'text', array(
			'label'     => Mage::helper('multicarriershipping')->__('Shipping Days'),
			'class'     => 'required-entry',
			'required'  => true,
			'name'      => 'shipping_days',
			'class'     => 'validate-number',
		));

		$fieldset->addField('group', 'select', array(
			'label'     => Mage::helper('multicarriershipping')->__('Group'),
			'class'     => 'required-entry',
			'required'  => true,
			'name'      => 'group',
			'values'    => Mage::getModel('multicarriershipping/source_tablerate_groups')->getAllGroups(true)
		));

		if (Mage::getSingleton('adminhtml/session')->getTablerateData()) {
			$form->setValues(Mage::getSingleton('adminhtml/session')->getTablerateData());
			Mage::getSingleton('adminhtml/session')->setTablerateData(null);
		} elseif ( Mage::registry('tablerate_data') ) {
			$form->setValues(Mage::registry('tablerate_data')->getData());
		}
		return parent::_prepareForm();
	}
}