<?php

class Cammino_Multicarriershipping_Block_Adminhtml_Tablerate_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
	
	public function __construct()
	{
		parent::__construct();
		$this->setId('tablerateGrid');
		$this->setDefaultSort('tablerate_id');
		$this->setDefaultDir('ASC');
		$this->setSaveParametersInSession(true);
	}

	protected function _prepareCollection()
	{
		$collection = Mage::getModel('multicarriershipping/tablerate')->getCollection();
		$this->setCollection($collection);
		return parent::_prepareCollection();
	}

	protected function _prepareColumns()
	{
		$this->addColumn('tablerate_id', array(
			'header'    => Mage::helper('multicarriershipping')->__('ID'),
			'align'     =>'right',
			'width'     => '50px',
			'index'     => 'tablerate_id',
		));

		$this->addColumn('zipcode_start', array(
			'header'    => Mage::helper('multicarriershipping')->__('Initial ZIP Code'),
			'align'     =>'left',
			'index'     => 'zipcode_start',
		));
		
		$this->addColumn('zipcode_end', array(
			'header'    => Mage::helper('multicarriershipping')->__('Final ZIP Code'),
			'align'     =>'left',
			'index'     => 'zipcode_end',
		));

		$this->addColumn('weight_start', array(
			'header'    => Mage::helper('multicarriershipping')->__('Initial Weight'),
			'align'     =>'left',
			'index'     => 'weight_start',
		));

		$this->addColumn('weight_end', array(
			'header'    => Mage::helper('multicarriershipping')->__('Final Weight'),
			'align'     =>'left',
			'index'     => 'weight_end',
		));

		$this->addColumn('price', array(
			'header'    => Mage::helper('multicarriershipping')->__('Price'),
			'align'     =>'left',
			'index'     => 'price',
		));

		$this->addColumn('additional_price', array(
			'header'    => Mage::helper('multicarriershipping')->__('Additional Price'),
			'align'     =>'left',
			'index'     => 'additional_price',
		));

		$this->addColumn('action',
			array(
				'header'    =>  Mage::helper('multicarriershipping')->__('Action'),
				'width'     => '100',
				'type'      => 'action',
				'getter'    => 'getId',
				'actions'   => array(
					array(
						'caption'   => Mage::helper('multicarriershipping')->__('Edit Item'),
						'url'       => array('base'=> '*/*/edit'),
						'field'     => 'id'
					)
				),
			'filter'    => false,
			'sortable'  => false,
			'index'     => 'stores',
			'is_system' => true,
		));
	  
		return parent::_prepareColumns();
	}

	public function getRowUrl($row)
	{
		return $this->getUrl('*/*/edit', array('id' => $row->getId()));
	}
}