<?php
namespace Op\Checkout\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;

/**
 * Class TransactionPayment
 * @package Op\Checkout\Gateway\Http\Client
 */
class TransactionPayment implements ClientInterface
{
    private $opHelper;

    public function __construct(
        \Op\Checkout\Helper\Data $opHelper
    ) {
        $this->opHelper = $opHelper;
    }

    /**
     * @param \Magento\Payment\Gateway\Http\TransferInterface $transferObject
     * @return array|bool
     */
    public function placeRequest(\Magento\Payment\Gateway\Http\TransferInterface $transferObject)
    {
        return true;
    }
}
