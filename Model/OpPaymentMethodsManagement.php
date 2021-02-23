<?php
declare(strict_types=1);

namespace Op\Checkout\Model;

use Op\Checkout\Model\ConfigProvider;

class OpPaymentMethodsManagement implements \Op\Checkout\Api\OpPaymentMethodsManagementInterface
{
    /**
     * @var \Op\Checkout\Model\ConfigProvider
     */
    private $configProvider;

    /**
     * OpPaymentMethodsManagement constructor.
     * @param \Op\Checkout\Model\ConfigProvider $configProvider
     */
    public function __construct(
        ConfigProvider $configProvider
    ) {
        $this->configProvider = $configProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function getOpPaymentMethods()
    {
        return $this->configProvider->getConfig();
    }
}

