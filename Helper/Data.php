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
	//get all the dimensions of the product. If it isn`t filled, get store config in admin
    public function getDimensions($product, $quantity) {
        $dimensions = [
            'width' => ($product->getWidth() != null ? $product->getWidth() : Mage::getStoreConfig('carriers/multicarrier/default_width')) * $quantity,
            'height' => ($product->getHeight() != null ? $product->getHeight() : Mage::getStoreConfig('carriers/multicarrier/default_height')) * $quantity,
            'depth' => ($product->getDepth() != null ? $product->getDepth() : Mage::getStoreConfig('carriers/multicarrier/default_depth')) * $quantity
        ];
        return $dimensions;
    }

    public function getDimensionsSum($dimensionsSum, $dimensions) {
        $dimensionsSum = [
            'width' => $dimensionsSum['width'] + $dimensions['width'],
            'height' => $dimensionsSum['height'] + $dimensions['height'],
            'depth' => $dimensionsSum['depth'] + $dimensions['depth']
        ];
        return $dimensionsSum;
    }

    public function getWeightUsingDimensions($dimensions, $cartProduct) {
	    //stored configs Tablerate
	    $cubicCoefficient = Mage::getStoreConfig('carriers/multicarrier_tablerate/tablerate_cubic_coefficient');
	    $cubicLimit = Mage::getStoreConfig('carriers/multicarrier_tablerate/tablerate_cubic_limit');
	    $cubicWeight = $dimensions['height'] * $dimensions['width'] * $dimensions['depth'] / $cubicCoefficient;

	    // return weight of product multiplied by quantity of this item. If volume/coefficient is bigger than the limit, return it. if it is  smaller, return the product weight
	     return ((($cubicWeight > $cubicLimit) ? $cubicWeight : $cartProduct->getWeight()) * $cartProduct->getQty());
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

    

}