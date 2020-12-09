<?php
class Cammino_Multicarriershipping_Model_Carrier_Multicarrier extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface {

    protected $_code = "multicarrier";
    private $_request = null;

    private function getRequest() {
        return $this->_request;
    }
    
    public function collectRates(Mage_Shipping_Model_Rate_Request $request) { 
        $result = Mage::getModel("shipping/rate_result");
        $dimensionsSum = array();

        $this->_request = $request;
        
        //pega cep fornecido pelo usuário
        $destinationCep = str_replace('-', '', trim($request->getDestPostcode()));      
        
        //pega todos os produtos no carrinho
        $cartProducts = $request->getAllItems(); 

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
                
                if (!Mage::getStoreConfig("carriers/multicarrier_tablerate/tablerate_individual_calc")) {
                    $itemAmount = ($cartProduct->getRowTotal() / $cartProduct->getQty());
                    $tablerateProductWeight = Mage::helper("multicarriershipping/custom")->getWeightUsingDimensions($dimensions, $cartProduct);
                    $tablerateRates = array_merge($tablerateRates, $this->getCarrierTransportadora($tablerateProductWeight, $destinationCep, $product->getAttributeText('multicarrier_group'), null, $itemAmount));
               } else {
                    $itemAmount = $cartProduct->getRowTotal();
                    $tablerateProductWeight = Mage::helper("multicarriershipping/custom")->getWeightUsingDimensions($dimensions, $cartProduct, 1);
                    $tablerateRates = array_merge($tablerateRates, $this->getCarrierTransportadora($tablerateProductWeight, $destinationCep, $product->getAttributeText('multicarrier_group'), $cartProduct->getQty(), $itemAmount));
               }
                
            }
        }

        $this->chooseCarrier($tablerateRates, $correiosDimensionsSum, $correiosWeightSum, $destinationCep, $result);

        return $result;
    }

    private function chooseCarrier($tablerateRates, $correiosDimensionsSum, $correiosWeightSum, $destinationCep, $result) {

        // exibe as cotações de frete dos correios
        $correiosRates = $this->getCarrierCorreios($correiosWeightSum, $destinationCep, $correiosDimensionsSum); 
        
        // caso não haja cotação para aquela localidade
        // if ((($correiosWeightSum > 0) && count($correiosRates) == 0) || ( $tablerateRates[0]['price'] == 0 && !$tablerateRates[0]['days'])) {
        if (
            ( ($correiosWeightSum > 0) && (count($correiosRates) == 0) )
            ||
            ( (count($tablerateRates) == 0) && (count($correiosRates) == 0) )
            ||
            ( (count($tablerateRates) > 0) && ($tablerateRates[0]['price'] == 0) && (!$tablerateRates[0]['days']) )
        ) {
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
        }

        $days = array_reduce($tablerateRates, function($max, $rates) {
            return max($max, $rates['days']);
        }, 0);
         
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

        if (($this->getRequest()->getFreeShipping() === true) && (count($_services) > 0)) {
            $_services[0]["price"] = 0;
        }

        foreach($_services as $service) {
           $this->addRateResult($result, $service["price"], $service["code"], $dataHelper->shippingDays($service["days"]), $title . Mage::helper("multicarriershipping/custom")->shippingTitle($service["code"]));
        }
    }

    private function getCarrierCorreios($weight, $destinationCep, $dimensionsSum) {
        $_services = null;
        $originPostcode = str_replace('-', '', trim(Mage::getStoreConfig("shipping/origin/postcode", $this->getStore())));
        
        //  monta a url com os parâmetros necessários pra o Web Service dos correios
        $urls = $this->getShippingAmount($originPostcode, $destinationCep, $weight, $dimensionsSum);
        
        // pega o xml de retorno dos correios
        $_services = $this->getXml($urls);

        // caso a config de dias extras esteja setada, atualiza quantidade de dias de cada serviço
        $_shippingDaysExtra = floatval(Mage::getStoreConfig("carriers/webservicecorreios/shippingdaysextra"));
        foreach($_services as &$service) {
            if ($_shippingDaysExtra > 0) 
                $service["days"] += $_shippingDaysExtra; 
        }
    
        return $_services;
    }

    private function getCarrierTransportadora($weightList, $destinationCep, $group, $qty = null, $itemAmount = null) {
        $modelTablerate = Mage::getModel("multicarriershipping/tablerate");
    
        for ($rounds = 0; $rounds < $weightList['rounds']; $rounds++) {
            $priceAndDays = $this->getTableratePriceDays($weightList['limitWeight'], $destinationCep, $modelTablerate, $group, null, $itemAmount);
            if ($priceAndDays == NULL) return;
            $price += $priceAndDays['price'];
            
            $days = max($days, $priceAndDays['days']);  
        }

        $priceAndDays = $this->getTableratePriceDays($weightList['lastWeight'], $destinationCep, $modelTablerate, $group, $qty, $itemAmount);
    
        $price += $priceAndDays['price'];
        $days = max($days, $priceAndDays['days']);

        return array(array("price" => $price, "days" => $days)); 
    }

    private function getTableratePriceDays($weight, $cep, $modelTablerate, $group, $qty, $itemAmount) {
        $tablerate = $modelTablerate->getCollection()
            ->addFieldToFilter("zipcode_start", array("lteq" => intval($cep)))
            ->addFieldToFilter("zipcode_end", array("gteq" => intval($cep)))
            ->addFieldToFilter("weight_start", array("lteq" => $weight))
            ->addFieldToFilter("weight_end", array("gteq" => $weight))
            ->addFieldToFilter("group", $group)
            ->setOrder("zipcode_start","DESC");

            if ($tablerate->getFirstitem()->getPrice() != NULL) {

                if (Mage::getStoreConfig("carriers/multicarrier_tablerate/percent_mode")) {

                    $finalPrice = $itemAmount * ($tablerate->getFirstitem()->getPrice() / 100);

                } else {
                    if ($qty) {
                        $finalPrice = $tablerate->getFirstitem()->getPrice() * $qty;
                    } else {
                        $finalPrice = $tablerate->getFirstitem()->getPrice();
                    }
                }

                return array(
                    'price' =>  $finalPrice,
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

        if (strpos($_services, ',') !== false) {
            $_services = explode(",", $_services);
        } else {
            $_services = array($_services);
        }

        $urls = array();

        $i = 0;

        foreach($_services as $_service):
            $urls[$i] = "http://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx";
            $urls[$i] .= "?nCdEmpresa=" . $_user;
            $urls[$i] .= "&sDsSenha=" . $_pass;
            $urls[$i] .= "&nCdServico=" . $_service;
            $urls[$i] .= "&sCepOrigem=" . $originPostcode;
            $urls[$i] .= "&sCepDestino=" . $destPostcode;
            $urls[$i] .= "&nVlPeso=" . $formatedWeight;
            $urls[$i] .= "&nCdFormato=1";
            $urls[$i] .= "&nVlComprimento=" . $dimensions['depth'];
            $urls[$i] .= "&nVlAltura=" . $dimensions['height'];
            $urls[$i] .= "&nVlLargura=" . $dimensions['width'];
            $urls[$i] .= "&sCdMaoPropria=n";
            $urls[$i] .= "&nVlValorDeclarado=0";
            $urls[$i] .= "&sCdAvisoRecebimento=n";
            $urls[$i] .= "&nVlDiametro=0";
            $urls[$i] .= "&StrRetorno=xml";
            $urls[$i] .= "&nIndicaCalculo=3";
            $i++;
        endforeach;

        return $urls;
    }

    public function getXml($urls) {
        
        $services = null;

        foreach($urls as $url):
            $content = file_get_contents($url);
            $xml = simplexml_load_string($content);

            foreach ($xml->cServico as $cServico):
                
                if ((strval($cServico->MsgErro) != "") && (intval($cServico->Erro) != 9) && (intval($cServico->Erro) != 10) && (intval($cServico->Erro) != 11)) {
                    continue;
                }

                $services[] = array (
                    "code" => intval($cServico->Codigo),
                    "days" => intval($cServico->PrazoEntrega),
                    "price" => floatval(str_replace(",", ".", str_replace(".", "", $cServico->Valor)))
                );

            endforeach;
        endforeach;

        if (is_array($services)) {
            return $services;
        }

        return null;
    }

    private function addRateResult($result, $shippingPrice, $shippingCode, $shippingDays, $shippingTitle) {
        $method = Mage::getModel("shipping/rate_result_method");
        $method->setCarrier("multicarrier");
        $method->setCarrierTitle(Mage::getStoreConfig('carriers/multicarrier_tablerate/tablerate_cubic_coefficient'));
        // $method->setMethod("multicarrier");
        $method->setMethod("multicarrier_" . strtolower($shippingTitle));
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
