<?php
class Cammino_Multicarriershipping_Adminhtml_TablerateController extends Mage_Adminhtml_Controller_action {
     
     protected function _initAction() {
        $this->loadLayout();
        return $this;
     }
    public function indexAction()
    {
        $this->_initAction()->renderLayout();
    }


    public function editAction() {
        $id     = $this->getRequest()->getParam('id');
        $model  = Mage::getModel('multicarriershipping/tablerate')->load($id);

        if ($model->getId() || $id == 0) {
            $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
            if (!empty($data)) {
                $model->setData($data);
            }

            Mage::register('tablerate_data', $model);

            $this->loadLayout();
            
            $this->_setActiveMenu('banners/items');

            $this->getLayout()->getBlock('head')->setCanLoadExtJs(true);
            
            
            $this->_addContent($this->getLayout()->createBlock('multicarriershipping/adminhtml_tablerate_edit'))
                ->_addLeft($this->getLayout()->createBlock('multicarriershipping/adminhtml_tablerate_edit_tabs'));

            $this->renderLayout();
            
        } else {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('multicarriershipping')->__('Item não encontrado'));
            $this->_redirect('*/*/');
        }
    }
 
    public function newAction() {
        $this->_forward('edit');
    }
 
    public function saveAction() {
        if ($data = $this->getRequest()->getPost()) {
            
           
            // FIX BUG VERSION 1.5
            // Ao mandar uma variavel vazia estava salvando o valor no banco de 0000-00-00 00:00:00, entao caso for vazio forçamos o valor NULL.

            $model = Mage::getModel('multicarriershipping/tablerate');     
            $model->setData($data)
                ->setId($this->getRequest()->getParam('id'));
             
            try {
                
                
                $model->save();
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('multicarriershipping')->__('Informações de Frete salvas com sucesso'));
                Mage::getSingleton('adminhtml/session')->setFormData(false);

                if ($this->getRequest()->getParam('back')) {
                    $this->_redirect('*/*/edit', array('id' => $model->getId()));
                    return;
                }
                $this->_redirect('*/*/');
                return;
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                Mage::getSingleton('adminhtml/session')->setFormData($data);
                $this->_redirect('*/*/edit', array('id' => $this->getRequest()->getParam('id')));
                return;
            }
        }
        Mage::getSingleton('adminhtml/session')->addError(Mage::helper('banners')->__('Não foi possível salvar a informação de frete'));
        $this->_redirect('*/*/');
    }
 
    public function deleteAction() {
        if( $this->getRequest()->getParam('id') > 0 ) {
            try {
                $model = Mage::getModel('multicarriershipping/tablerate');
                 
                $model->setId($this->getRequest()->getParam('id'))
                    ->delete();
                     
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('multicarriershipping')->__('Informação de frete excluida com sucesso'));
                $this->_redirect('*/*/');
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                $this->_redirect('*/*/edit', array('id' => $this->getRequest()->getParam('id')));
            }
        }
        $this->_redirect('*/*/');
    }
}