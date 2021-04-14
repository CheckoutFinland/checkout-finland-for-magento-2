<?php

namespace Op\Checkout\Plugin\Model;

use Magento\Tax\Model\Config;
use Op\Checkout\Gateway\Config\Config as GatewayConfig;

class TaxConfigPlugin
{

    const UNIT_CALCULATION = 'UNIT_BASE_CALCULATION';

    /**
     * @var GatewayConfig
     */
    private $gatewayConfig;

    /**
     * TaxConfigPlugin constructor.
     *
     * @param GatewayConfig $gatewayConfig
     */
    public function __construct
    (
        GatewayConfig $gatewayConfig
    )
    {
        $this->gatewayConfig = $gatewayConfig;
    }

    /**
     * @param Config $subject
     * @param $result
     * @return mixed
     */
    public function afterGetAlgorithm(\Magento\Tax\Model\Config $subject, $result)
    {
        if ($result !== self::UNIT_CALCULATION && $this->gatewayConfig->getUseRecommendedTaxAlgorithm() == true){
            return self::UNIT_CALCULATION;
        }
        return $result;
    }

}
