<?php
class Cammino_Multicarriershipping_Adminhtml_TablerateController extends Mage_Adminhtml_Controller_action {
     
    public function indexAction()
    {  
        $model = Mage::getModel('multicarriershipping/tablerate')->getCollection();
        foreach($model as $a) {
        	var_dump($a->getPrice());
        }
        die;
        $helper = Mage::helper("multicarriershipping"); 
    	   
    } 
}