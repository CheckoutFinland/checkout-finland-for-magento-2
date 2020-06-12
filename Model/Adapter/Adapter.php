<?php

namespace Op\Checkout\Model\Adapter;

use Magento\Framework\Exception\LocalizedException;
use Op\Checkout\Helper\Data;
use OpMerchantServices\SDK\Client;
use Magento\Framework\Module\ModuleListInterface;

class Adapter
{
    /**
     * @var string MODULE_CODE
     */
    const MODULE_CODE = 'Op_Checkout';
    /**
     * @var int
     */
    protected $merchantId;
    /**
     * @var string
     */
    protected $merchantSecret;
    /**
     * @var Data
     */
    private $opHelper;
    /**
     * @var ModuleListInterface
     */
    private $moduleList;

    public function __construct(
        Data $opHelper,
        ModuleListInterface $moduleList
    )
    {
        $this->opHelper = $opHelper;
        $this->moduleList = $moduleList;
        $this->merchantId = $opHelper->getMerchantId();
        $this->merchantSecret = $opHelper->getMerchantSecret();
    }

    /**
     * Create Instance of the Op Merchant Services SDK Api Client
     * @return Client
     * @throws LocalizedException
     */
    public function initOpMerchantClient()
    {
        if (class_exists('OpMerchantServices\SDK\Client')) {
            $opClient = new Client(
                $this->merchantId,
                $this->merchantSecret,
                'op-payment-service-for-magento-2-' . $this->getExtensionVersion()
            );
            return $opClient;
        } else {
            throw new LocalizedException(__('OpMerchantServices\SDK\Client does not exist'));
        }
    }

    /**
     * @return string module version in format x.x.x
     */
    protected function getExtensionVersion()
    {
        return $this->moduleList->getOne(self::MODULE_CODE)['setup_version'];
    }

}
