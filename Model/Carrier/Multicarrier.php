<?php
class Cammino_Multicarriershipping_Model_Carrier_Multicarrier extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface {

    protected $_code = "multicarrier";
    
    public function collectRates(Mage_Shipping_Model_Rate_Request $request) { 
        $result = Mage::getModel("shipping/rate_result");
        $dimensionsSum = array();
        
        //pega cep fornecido pelo usuário
        $destinationCep = str_replace('-', '', trim($request->getDestPostcode()));      
        
        //pega todos os produtos no carrinho
        $cartProducts = Mage::getSingleton('checkout/session')->getQuote()->getAllItems(); 

        $tablerateRates = array();
        $correiosDimensionsSum = 0;
        $correiosWeightSum = 0;

         //para cada produto no carrinho
        foreach($cartProducts as $cartProduct) {
            $product =  Mage::getModel('catalog/product')->load($cartProduct->getProductId());
            // se é produto configurável, pula o pai
            if ($cartProduct->getParentItem()) continue;
            // pega as dimensões baseado na quantidade de produtos iguais no carrinho
            $dimensions = Mage::helper("multicarriershipping/custom")->getDimensions($product, $cartProduct->getQty());
            // se a config "multicarrier_carrier" não estiver setada, assume-se que seja "correios"
            if ($product->getAttributeText('multicarrier_carrier') == "Correios" or !$product->getAttributeText('multicarrier_carrier')) {
                $correiosDimensionsSum = Mage::helper("multicarriershipping/custom")->getDimensionsSum($dimensionsSum, $dimensions);
                $correiosWeightSum += ($cartProduct->getWeight() * $cartProduct->getQty());
             } else if ($product->getAttributeText('multicarrier_carrier') == "Tablerate") {
                $tablerateProductWeight = Mage::helper("multicarriershipping/custom")->getWeightUsingDimensions($dimensions, $cartProduct);
                $tablerateRates = array_merge($tablerateRates, $this->getCarrierTransportadora($tablerateProductWeight, $destinationCep, $product->getAttributeText('multicarrier_group')));

            }
        }
        $this->chooseCarrier($tablerateRates, $correiosDimensionsSum, $correiosWeightSum, $destinationCep, $result);        

        return $result;
    }

    private function chooseCarrier($tablerateRates, $correiosDimensionsSum, $correiosWeightSum, $destinationCep, $result) {

        // exibe as cotações de frete dos correios
        $correiosRates = $this->getCarrierCorreios($correiosWeightSum, $destinationCep, $correiosDimensionsSum); 
        
        // caso não haja cotação para aquela localidade
        if ((($correiosWeightSum > 0) && count($correiosRates) == 0) || ((!is_array($tablerateRates)) && count($tablerateRates) == 0)) {
            $this->addError($result, 'Não há frete disponível para sua região');
        }

        // se tiver produto(s) no carrinho com multicarrier_carrier="correios" e multicarrier_carrier="tablerate"
        else if ($correiosWeightSum > 0 && !empty($tablerateRates)) {
            $tablerateRates = $this->sumAllTablerate($tablerateRates);
            $joinedRates = $this->prepareRateTablerateCorreios($tablerateRates,$correiosRates);        
            $this->addRates($joinedRates, $result, 'Transportadora');
        } 

        // se tiver somente produto(s) no carrinho com multicarrier_carrier="correios"
        else if ($correiosWeightSum > 0 && empty($tablerateRates)) {
            $this->addRates($correiosRates, $result);
        } 

        // se tiver somente produto(s) no carrinho com multicarrier_carrier="tablerate"
        else if (!empty($tablerateRates) && $correiosWeightSum == 0) {
            $tablerateRates = $this->sumAllTablerate($tablerateRates);
            $this->addRates($tablerateRates, $result, 'Transportadora');
        }

        // se tiver somente produto(s) no carrinho com multicarrier_carrier=NULL, calcula a cotação baseada no correiosRates
        else if (empty($tablerateRates) && $correiosWeightSum == 0) {
            $this->addRates($correiosRates, $result);
        }
       
    }

    // função responsável por somar todos os produtos com carrier = transportadora
    private function sumAllTablerate($tablerateRates) {
        foreach ($tablerateRates as $rates) {
            $price += $rates['price']; 
            $days = array_reduce($rates, function($max, $rates) {
                return max($max, $rates['days']);
            }, 0);
        }
         
         return array(
            array(
                'price' => $price,
                'days' => $days
                )
            );

    }

    private function prepareRateTablerateCorreios($tablerateRates, $correiosRates) {
        // price: soma valores da tablerate e dos correios
        // days: valor máximo entre as cotações
        return array(
            array(
                'price' => $tablerateRates[0]["price"] + $correiosRates[0]["price"],
                'days' => max($tablerateRates[0]["days"], $correiosRates[0]["days"])
            )
        );
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
        
        //  monta a url com os parâmetros necessários pra o Web Service dos correios
        $url = $this->getShippingAmount($originPostcode, $destinationCep, $weight, $dimensionsSum);
        
        // pega o xml de retorno dos correios
        $_services = $this->getXml($url);
        
        // caso a config de dias extras esteja setada, atualiza quantidade de dias de cada serviço
        $_shippingDaysExtra = floatval(Mage::getStoreConfig("carriers/webservicecorreios/shippingdaysextra"));
        foreach($_services as &$service) {
            if ($_shippingDaysExtra > 0) 
                $service["days"] += $_shippingDaysExtra; 
        }
       return $_services;
    }

    private function getCarrierTransportadora($weightList, $destinationCep, $group) {
        $modelTablerate = Mage::getModel("multicarriershipping/tablerate");
            for ($rounds = 0; $rounds < $weightList['rounds']; $rounds++) {
                $priceAndDays = $this->getTableratePriceDays($weightList['limitWeight'], $destinationCep, $modelTablerate, $group);
                if ($priceAndDays == NULL) return;
                $price += $priceAndDays['price'];
                
                $days = max($days, $priceAndDays['days']);  
            }
            $priceAndDays = $this->getTableratePriceDays($weightList['lastWeight'], $destinationCep, $modelTablerate, $group);
            $price += $priceAndDays['price'];
            $days = max($days, $priceAndDays['days']);
       


        return array(array("price" => $price, "days" => $days)); 
    }

    private function getTableratePriceDays($weight, $cep, $modelTablerate, $group) {
        $tablerate = $modelTablerate->getCollection()
            ->addFieldToFilter("zipcode_start", array("lteq" => intval($cep)))
            ->addFieldToFilter("zipcode_end", array("gteq" => intval($cep)))
            ->addFieldToFilter("weight_start", array("lteq" => $weight))
            ->addFieldToFilter("weight_end", array("gteq" => $weight))
            ->addFieldToFilter("group", $group)
            ->setOrder("zipcode_start","DESC");
            if ($tablerate->getFirstitem()->getPrice() != NULL) {
                return array(
                    'price' =>  $tablerate->getFirstitem()->getPrice(),
                    'days'  =>  $tablerate->getFirstitem()->getShippingDays(),
                );
            }
            else {
                return null;
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