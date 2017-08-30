<?php
class Cammino_Multicarriershipping_Model_Carrier_Multicarrier extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface {

    protected $_code = "multicarrier";
    
    public function collectRates(Mage_Shipping_Model_Rate_Request $request) {
        //get cep typed by user
        $cep = $request->getDestPostcode();      
        
        $cartProducts = Mage::getSingleton('checkout/session')->getQuote()->getAllItems();

         //foreach product in the cart
        foreach($cartProducts as $cartProduct) {

            // product info
            $product =  Mage::getModel('catalog/product')->load($cartProduct->getProductId());

            //get dimensions
            $dimensions = $this->getDimensions($product);
            
            //get weight
            $weight += $this->getWeightUsingDimensions($dimensions, $cartProduct);
        }
        
        $result = $this->getTablerateByCepWeight($weight, $cep);
        return $result;
    }

    private function getDimensions($product) {
        //get all the dimensions of the product. If it isn`t filled, get store config in admin
        $dimensions = [
            'width' => $product->getWidth() != null ? $product->getWidth() : Mage::getStoreConfig('carriers/multicarrier/default_width'),
            'height' => $product->getHeight() != null ? $product->getHeight() : Mage::getStoreConfig('carriers/multicarrier/default_height'),
            'depth' => $product->getDepth() != null ? $product->getDepth() : Mage::getStoreConfig('carriers/multicarrier/default_depth')
        ];
        return $dimensions;
    }

    private function getWeightUsingDimensions($dimensions, $cartProduct) {
        //stored configs Tablerate
        $cubicCoefficient = Mage::getStoreConfig('carriers/multicarrier/tablerate_cubic_coefficient');
        $cubicLimit = Mage::getStoreConfig('carriers/multicarrier/tablerate_cubic_limit');
        $cubicWeight = $dimensions['height'] * $dimensions['width'] * $dimensions['depth'] / $cubicCoefficient;

        // if volume/coefficient is bigger than the limit, return it. if it is
        // smaller, return the product weight
        return ($cubicWeight > $cubicLimit) ? $cubicWeight : $cartProduct->getWeight();
       
    }
    
    private function getTablerateByCepWeight($weight, $cep) {
        $result = Mage::getModel("shipping/rate_result");
        $error = Mage::getModel("shipping/rate_result_error");
        $modelTablerate = Mage::getModel("multicarriershipping/tablerate");

        $tablerate = $modelTablerate->getCollection()
        ->addFieldToFilter("zipcode_start", array("lteq" => intval($cep)))
        ->addFieldToFilter("zipcode_end", array("gteq" => intval($cep)))
        ->addFieldToFilter("weight_start", array("lteq" => $weight))
        ->addFieldToFilter("weight_end", array("gteq" => $weight))
        ->setOrder("zipcode_start","DESC");

        if ($tablerate->getFirstitem()->getPrice() != NULL) {
            $this->addRateResult($result, $tablerate->getFirstitem()->getPrice(), '', $this->shippingDays($tablerate->getFirstitem()->getShippingDays()), 'Transportadora');
        }
        else {
            $this->addError($result, 'Não há frete disponível para sua região');
        }
        return $result;
    }
    private function addRateResult($result, $shippingPrice, $shippingCode, $shippingDays, $shippingTitle) {
        $method = Mage::getModel("shipping/rate_result_method");
        $method->setCarrier("multicarrier");
        $method->setCarrierTitle($this->getConfigData("title"));
        $method->setMethod("multicarrier");
        $method->setMethodTitle("$shippingTitle ($shippingDays) ");
        $method->setPrice($shippingPrice);
        $method->setCost($shippingPrice);
        $result->append($method);
    }
    
    private function addError($result, $errorMessage) {
        $error = Mage::getModel ("shipping/rate_result_error");        
        $error->setCarrier("multicarrier");
        $error->setCarrierTitle('Transportadora');
        $error->setErrorMessage("$errorMessage");
        $result->append($error);
    }
    
    private function shippingDays($days) {
        if(intval($days) == 1) {
            return "um dia útil";
        } else {
            return "$days dias úteis";
        }
    }

    
    public function getAllowedMethods() {
        return array("multicarrier" => $this->getConfigData("name"));
    }
}