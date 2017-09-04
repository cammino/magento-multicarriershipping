<?php
class Cammino_Multicarriershipping_Model_Carrier_Multicarrier extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface {

    protected $_code = "multicarrier";
    
    public function collectRates(Mage_Shipping_Model_Rate_Request $request) { 
        $result = Mage::getModel("shipping/rate_result");
        $dimensionsSum = [];
        
        //get cep typed by user
        $destinationCep = str_replace('-', '', trim($request->getDestPostcode()));      
        
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
            $dimensions = $this->getDimensions($product, $cartProduct->getQty());
            // if the product config named multicarrier_carrier is not set, presume it is 
            if ($product->getAttributeText('multicarrier_carrier') == "Correios" or !$product->getAttributeText('multicarrier_carrier')) {
                $correiosDimensionsSum = $this->getDimensionsSum($dimensionsSum, $dimensions);
                // weight used by correios
                $correiosWeightSum += ($cartProduct->getWeight() * $cartProduct->getQty());
             } else if ($product->getAttributeText('multicarrier_carrier') == "Tablerate") {

                // weight used by correios
                $tablerateWeightSum += $this->getWeightUsingDimensions($dimensions, $cartProduct);
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
            $this->addRates($joinedRates, $result);
        } 
        else if ($correiosWeightSum > 0 && $tablerateWeightSum == 0) {
            $this->addRates($correiosRates, $result);
        } 
        else if ($tablerateWeightSum > 0 && $correiosWeightSum == 0) {
            $this->addRates($tablerateRates, $result);
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

    private function addRates($_services, $result) {
        $dataHelper = Mage::helper('multicarriershipping/data');
        $error = Mage::getModel("shipping/rate_result_error");
        foreach($_services as $service) {
            $this->addRateResult($result, $service["price"], $service["code"], $dataHelper->shippingDays($service["days"]), $_shippingTitlePrefix.$this->shippingTitle($service["code"]));
        }
    }

    private function getCarrierCorreios($weight, $destinationCep, $result, $dimensionsSum) {
        $_services = null;
        $originPostcode = str_replace('-', '', trim(Mage::getStoreConfig("shipping/origin/postcode", $this->getStore())));
        $_services = $this->getShippingAmount($originPostcode, $destinationCep, $weight, $dimensionsSum);
        $_shippingDaysExtra = floatval(Mage::getStoreConfig("carriers/webservicecorreios/shippingdaysextra"));
            
        foreach($_services as &$service) {
            if ($_shippingDaysExtra > 0) 
                $service["days"] += $_shippingDaysExtra; 
        }
        
        return $_services;
    }

    private function getDimensions($product, $quantity) {
        //get all the dimensions of the product. If it isn`t filled, get store config in admin
        $dimensions = [
            'width' => ($product->getWidth() != null ? $product->getWidth() : Mage::getStoreConfig('carriers/multicarrier/default_width')) * $quantity,
            'height' => ($product->getHeight() != null ? $product->getHeight() : Mage::getStoreConfig('carriers/multicarrier/default_height')) * $quantity,
            'depth' => ($product->getDepth() != null ? $product->getDepth() : Mage::getStoreConfig('carriers/multicarrier/default_depth')) * $quantity
        ];
        return $dimensions;
    }

    private function getDimensionsSum($dimensionsSum, $dimensions) {
        $dimensionsSum = [
            'width' => $dimensionsSum['width'] + $dimensions['width'],
            'height' => $dimensionsSum['height'] + $dimensions['height'],
            'depth' => $dimensionsSum['depth'] + $dimensions['depth']
        ];
        return $dimensionsSum;
    }

    private function getWeightUsingDimensions($dimensions, $cartProduct) {
        
            //stored configs Tablerate
            $cubicCoefficient = Mage::getStoreConfig('carriers/multicarrier/tablerate_cubic_coefficient');
            $cubicLimit = Mage::getStoreConfig('carriers/multicarrier/tablerate_cubic_limit');
            $cubicWeight = $dimensions['height'] * $dimensions['width'] * $dimensions['depth'] / $cubicCoefficient;

            // return weight of product multiplied by quantity of this item. If volume/coefficient is bigger than the limit, return it. if it is  smaller, return the product weight
             return ((($cubicWeight > $cubicLimit) ? $cubicWeight : $cartProduct->getWeight()) * $cartProduct->getQty());

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
            //$this->addError($result, 'Não há frete disponível para sua região');
        }
        //return $result;
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
  
    private function shippingTitle($code)
    {
        switch ($code) {
            case '00000':
                return "Grátis";
                break;
            case '41106': // sem contrato
            case '41211': // com contrato
            case '41068': // com contrato
                return 'PAC';
                break;
            
            case '40045': // sem contrato
            case '40126': // com contrato
                return 'SEDEX a cobrar';
                break;

            case '81019': // com contrato
            case '81868': // com contrato (grupo 1)
            case '81833': // com contrato (grupo 2)
            case '81850': // com contrato (grupo 3)
                return 'e-SEDEX';
                break;

            case '81027': // com contrato
                return 'e-SEDEX prioritário';
                break;
                    
            case '81035': // com contrato
                return 'e-SEDEX express';
                break;

            case '40010': // sem contrato
            case '40096': // com contrato
            case '40436': // com contrato
            case '40444': // com contrato
            case '40568': // com contrato
            case '40606': // com contrato
                return 'SEDEX';
                break;

            case '40215':
                return 'SEDEX 10';
                break;

            case '40290':
                return 'SEDEX Hoje';    
                break;

            default:
                break;
        }
    }

    public function getShippingAmount($originPostcode, $destPostcode, $weight, $dimensions) {

        $dimensions = $this->restrictDimensionsForWebservice($dimensions);
        $formatedWeight = $this->restrictWeightForWebservice($weight);
        
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
        // var_dump($url);die;
        $result = $this->getXml($url);

        return $result;
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

    public function restrictWeightForWebservice($weight) {
        
        if ($weight == 0)
            $weight = 0.3;

        if ($weight > 30)
            $weight = 30;

        //formated weight
        return (number_format($weight, 2, ',', ''));
    }

    public function restrictDimensionsForWebservice($dimensions) {
        // depth can't be less than 16 and more than 105
        if ($dimensions['depth'] < 16)
            $dimensions['depth'] = 16;
        
       if ($dimensions['depth'] > 105)
            $dimensions['depth'] = 105;
        
        // height can't be less than 2 and more than 105
        if ($dimensions['height'] < 2)
            $dimensions['height'] = 2;

        if ($dimensions['height'] > 105)
            $dimensions['height'] = 105;

        // width can't be less than 11 and more than 105
        if ($dimensions['width'] < 11)
            $dimensions['width'] = 11;

        if ($dimensions['width'] > 105)
            $dimensions['width'] =  105;
        
        // if sum if dimensions is higher than 200, make them 66
        if (($dimensions['width']+$dimensions['depth']+$dimensions['height']) > 200) {
            $dimensions['width'] = 66;
            $dimensions['depth'] = 66;
            $dimensions['height'] = 66;
        }
        return $dimensions;
    }
    
    public function getAllowedMethods() {

        return array("multicarrier" => $this->getConfigData("name"),
                     "webservicecorreios" => $this->getConfigData("name"));
    }
}