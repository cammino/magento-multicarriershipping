<?php
class Cammino_Multicarriershipping_Model_Carrier_Multicarrier extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface {

    protected $_code = "multicarrier";
    
    public function collectRates(Mage_Shipping_Model_Rate_Request $request) { 
        $result = Mage::getModel("shipping/rate_result");
        $dimensionsSum = [];
        
        //get cep typed by user
        $destinationCep = str_replace('-', '', trim($request->getDestPostcode()));      
        
        //get all products in the cart
        $cartProducts = Mage::getSingleton('checkout/session')->getQuote()->getAllItems(); 

        $tablerateWeightSum = 0;
        $correiosDimensionsSum = 0;
        $correiosWeightSum = 0;

         //foreach product in the cart
        foreach($cartProducts as $cartProduct) {
             // product info
            $product =  Mage::getModel('catalog/product')->load($cartProduct->getProductId());

            // if is a configurable product, skip the parent
            if ($cartProduct->getParentItem()) continue;

            //get dimensions
            $dimensions = Mage::helper("multicarriershipping/custom")->getDimensions($product, $cartProduct->getQty());

            // if the product config named multicarrier_carrier is not set, presume it is correios
            if ($product->getAttributeText('multicarrier_carrier') == "Correios" or !$product->getAttributeText('multicarrier_carrier')) {
                $correiosDimensionsSum = Mage::helper("multicarriershipping/custom")->getDimensionsSum($dimensionsSum, $dimensions);
                $correiosWeightSum += ($cartProduct->getWeight() * $cartProduct->getQty());
             } else if ($product->getAttributeText('multicarrier_carrier') == "Tablerate") {
                $tablerateWeightSum += Mage::helper("multicarriershipping/custom")->getWeightUsingDimensions($dimensions, $cartProduct);
            }

        }

        $this->chooseCarrier($tablerateWeightSum, $correiosDimensionsSum, $correiosWeightSum, $destinationCep, $result);        

        return $result;
    }

    private function chooseCarrier($tablerateWeightSum, $correiosDimensionsSum, $correiosWeightSum, $destinationCep, $result) {
         // show rates inside the shipping/carriers table according to which type of carriers it belongs (tablerate or correios) 
        $tablerateRates = $this->getCarrierTransportadora($tablerateWeightSum, $destinationCep);
        $correiosRates = $this->getCarrierCorreios($correiosWeightSum, $destinationCep, $correiosDimensionsSum); 
        if ((($correiosWeightSum > 0) && count($correiosRates) == 0) || (($tablerateWeightSum > 0) && count($tablerateRates) == 0)) {
            $this->addError($result, 'Não há frete disponível para sua região');
        }
        else if ($correiosWeightSum > 0 && $tablerateWeightSum > 0) {
            $joinedRates = $this->prepareRateTablerateCorreios($tablerateRates,$correiosRates);        
            $this->addRates($joinedRates, $result, 'Transportadora');
        } 
        else if ($correiosWeightSum > 0 && $tablerateWeightSum == 0) {
            $this->addRates($correiosRates, $result);
        } 
        else if ($tablerateWeightSum > 0 && $correiosWeightSum == 0) {
            $this->addRates($tablerateRates, $result, 'Transportadora');
        }
        else if ($tablerateWeightSum == 0 && $correiosWeightSum == 0) {
            // when $product->getAttributeText('multicarrier_carrier') is not set, calc the rate based on correiosRates
            $this->addRates($correiosRates, $result);
        }
    }

    private function prepareRateTablerateCorreios($tablerateRates, $correiosRates) {
        // sum prices of tablerate and correios and get the maxdays of shipping between them
        return [
            [
                'price' => $tablerateRates[0]["price"] + $correiosRates[0]["price"],
                'days' => max($tablerateRates[0]["days"], $correiosRates[0]["days"])
            ]
        ];
    }

    private function addRates($_services, $result, $title = '') {
        $dataHelper = Mage::helper('multicarriershipping/custom');
        $error = Mage::getModel("shipping/rate_result_error");
        foreach($_services as $service) {
           $this->addRateResult($result, $service["price"], $service["code"], $dataHelper->shippingDays($service["days"]), $title . Mage::helper("multicarriershipping/custom")->shippingTitle($service["code"]));
        }
    }

    private function getCarrierCorreios($weight, $destinationCep, $dimensionsSum) {
        $_services = null;
        $originPostcode = str_replace('-', '', trim(Mage::getStoreConfig("shipping/origin/postcode", $this->getStore())));
        
        // assemble the url with the parameters for the correios web service
        $url = $this->getShippingAmount($originPostcode, $destinationCep, $weight, $dimensionsSum);
        // get xml given the url
        $_services = $this->getXml($url);
        $_shippingDaysExtra = floatval(Mage::getStoreConfig("carriers/webservicecorreios/shippingdaysextra"));
            
        foreach($_services as &$service) {
            if ($_shippingDaysExtra > 0) 
                $service["days"] += $_shippingDaysExtra; 
        }
       return $_services;
    }

    private function getCarrierTransportadora($weight, $destinationCep) {
        $modelTablerate = Mage::getModel("multicarriershipping/tablerate");

        $tablerate = $modelTablerate->getCollection()
        ->addFieldToFilter("zipcode_start", array("lteq" => intval($destinationCep)))
        ->addFieldToFilter("zipcode_end", array("gteq" => intval($destinationCep)))
        ->addFieldToFilter("weight_start", array("lteq" => $weight))
        ->addFieldToFilter("weight_end", array("gteq" => $weight))
        ->setOrder("zipcode_start","DESC");

        if ($tablerate->getFirstitem()->getPrice() != NULL) {
            return array(array("price" => $tablerate->getFirstitem()->getPrice(), "days" => $tablerate->getFirstitem()->getShippingDays()));
        }
        else {
            return array();
         }
    }

    private function getShippingAmount($originPostcode, $destPostcode, $weight, $dimensions) {

        $dimensions = Mage::helper("multicarriershipping/custom")->restrictDimensionsForWebservice($dimensions);
        $formatedWeight = Mage::helper("multicarriershipping/custom")->restrictWeightForWebservice($weight);
        
        // Configs
        $_services = Mage::getStoreConfig('carriers/webservicecorreios/services');
        $_user = Mage::getStoreConfig('carriers/webservicecorreios/user');
        $_pass = Mage::getStoreConfig('carriers/webservicecorreios/pass');
         $url = "http://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx";
        $url .= "?nCdEmpresa=" . $_user;
        $url .= "&sDsSenha=" . $_pass;
        $url .= "&nCdServico=" . $_services;
        $url .= "&sCepOrigem=" . $originPostcode;
        $url .= "&sCepDestino=" . $destPostcode;
        $url .= "&nVlPeso=" . $formatedWeight;
        $url .= "&nCdFormato=1";
        $url .= "&nVlComprimento=" . $dimensions['depth'];
        $url .= "&nVlAltura=" . $dimensions['height'];
        $url .= "&nVlLargura=" . $dimensions['width'];
        $url .= "&sCdMaoPropria=n";
        $url .= "&nVlValorDeclarado=0";
        $url .= "&sCdAvisoRecebimento=n";
        $url .= "&nVlDiametro=0";
        $url .= "&StrRetorno=xml";
        $url .= "&nIndicaCalculo=3";
        return $url;
        
    }

    public function getXml($url) {
        $content = file_get_contents($url);
        $xml = simplexml_load_string($content);
        $services = null;

        foreach ($xml->cServico as $cServico) {

            if ((strval($cServico->MsgErro) != "") && (intval($cServico->Erro) != 10))
                continue;

            $services[] = array (
                "code" => intval($cServico->Codigo),
                "days" => intval($cServico->PrazoEntrega),
                "price" => floatval(str_replace(",", ".", str_replace(".", "", $cServico->Valor)))
            );
        }

        if (is_array($services)) {
            return $services;
        }

        return null;
    }

    private function addRateResult($result, $shippingPrice, $shippingCode, $shippingDays, $shippingTitle) {
        $method = Mage::getModel("shipping/rate_result_method");
        $method->setCarrier("multicarrier");
        $method->setCarrierTitle(Mage::getStoreConfig('carriers/multicarrier_tablerate/tablerate_cubic_coefficient'));
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
    
    public function getAllowedMethods() {

        return array("multicarrier" => $this->getConfigData("name"),
                     "webservicecorreios" => $this->getConfigData("name"));
    }
}