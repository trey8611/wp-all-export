<?php

namespace Wpae\VariationOptions;

use Wpae\Pro\VariationOptions\VariationOptions;
use Wpae\VariationOptions\VariationOptions as BasicVariationOptions;

class VariationOptionsFactory
{
    public function createVariationOptions($pmxeEdition)
    {
        switch ($pmxeEdition){
            case 'free':
            case 'paid':
                return new VariationOptions();
                break;
            default:
                throw new \Exception('Unknown PMXE edition');
        }
    }
}