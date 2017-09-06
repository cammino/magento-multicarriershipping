<?php

class Cammino_Multicarriershipping_Helper_Data extends Mage_Core_Helper_Abstract
{
	public function removeService($services){
		return $services;
	}
	
	public function shippingDays($days) {
		if(intval($days) == 1) {
			return "um dia útil";
		} else {
			return "$days dias úteis";
		}
	}
    // pega todas as dimensões do produto e se nao estiverem setadas, pega a StoreConfig (default) das mesmas
	public function getDimensions($product, $quantity) {
        $dimensions = array(
            'width' => ($product->getWidth() != null ? $product->getWidth() : Mage::getStoreConfig('carriers/multicarrier/default_width')),
            'height' => ($product->getHeight() != null ? $product->getHeight() : Mage::getStoreConfig('carriers/multicarrier/default_height')),
            'depth' => ($product->getDepth() != null ? $product->getDepth() : Mage::getStoreConfig('carriers/multicarrier/default_depth')) 
        );

        // multiplica o valor da menor dimensão pela quantidade de itens iguais do carrinho
        $minIndex = array_keys($dimensions, min($dimensions));
        $dimensions[$minIndex[0]] *= $quantity;

        return $dimensions;
    }

    // soma as dimensões dos produtos do carrinho
    public function getDimensionsSum(&$dimensionsSum, $dimensions) {
        $dimensionsSum = array(
            'width' => $dimensionsSum['width'] + $dimensions['width'],
            'height' => $dimensionsSum['height'] + $dimensions['height'],
            'depth' => $dimensionsSum['depth'] + $dimensions['depth']
        );
        
        return $dimensionsSum;
    }

    public function getWeightUsingDimensions($dimensions, $cartProduct) {
        //stored configs Tablerate
	    $cubicCoefficient = Mage::getStoreConfig('carriers/multicarrier_tablerate/tablerate_cubic_coefficient');
	    $cubicLimit = Mage::getStoreConfig('carriers/multicarrier_tablerate/tablerate_cubic_limit');
	    $cubicWeight = $dimensions['height'] * $dimensions['width'] * $dimensions['depth'] / $cubicCoefficient;
        

	     $maxWeight = Mage::getStoreConfig('carriers/multicarrier_tablerate/max_weight');
        
        // se (volume/coefficient) é maior que o limite, usa-o, do contrário, usa o preço do produto
        if ($cubicWeight > $cubicLimit) {
            return $this->getTablerateRoundsAndWeights($cubicWeight, $maxWeight);
        }
        else {
            $productsWeight = $cartProduct->getWeight() * $cartProduct->getQty();
            return $this->getTablerateRoundsAndWeights($productsWeight, $maxWeight);            
        }
    }


    public function getTablerateRoundsAndWeights($productWeight, $maxWeight) {
        // limitWeight: limite de peso especificado no painel administrativo
        // rounds: quantas vezes o peso é maior que o limite. Ex: 4 = 130 / 30
        // lastWeight: resto da divisão, peso extra. Ex: 10 = 130 % 30
        // No exemplo acima, o maxWeight (30) será usado 4x e o lastWeight (10) será usado 1x
        if ($productWeight > $maxWeight) {
            return array(
                'limitWeight' => $maxWeight,
                'rounds' => floor($productWeight / $maxWeight),
                'lastWeight' => $productWeight % $maxWeight
            );
        } else {
            return array(
                'limitWeight' => $maxWeight,
                'rounds' => 0,
                'lastWeight' => $productWeight
            ); 
        } 
    }
    public function shippingTitle($code)
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

    
    public function restrictWeightForWebservice($weight) {
        //peso formatado, não excedendo mais do que 30 kg e não nulo
        if ($weight == 0)
            $weight = 0.3;

        if ($weight > 30)
            $weight = 30;
        
        return (number_format($weight, 2, ',', ''));
    }

    public function restrictDimensionsForWebservice($dimensions) {
        // comprimento no intervalo -> 16 <= comprimento <= 105
        if ($dimensions['depth'] < 16)
            $dimensions['depth'] = 16;
        
       if ($dimensions['depth'] > 105)
            $dimensions['depth'] = 105;
        
        // altura no intervalo -> 2 <= altura <= 105
        if ($dimensions['height'] < 2)
            $dimensions['height'] = 2;

        if ($dimensions['height'] > 105)
            $dimensions['height'] = 105;

        // largura no intervalo -> 11 <= largura <= 105
        if ($dimensions['width'] < 11)
            $dimensions['width'] = 11;

        if ($dimensions['width'] > 105)
            $dimensions['width'] =  105;
        
        // se soma das dimensões for maior que 200, atribui 66 a todas as dimensões
        if (($dimensions['width']+$dimensions['depth']+$dimensions['height']) > 200) {
            $dimensions['width'] = 66;
            $dimensions['depth'] = 66;
            $dimensions['height'] = 66;
        }
        return $dimensions;
    }
}