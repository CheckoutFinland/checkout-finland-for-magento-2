<?php
namespace Op\Checkout\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;

class TransactionRefund implements ClientInterface
{
    public function placeRequest(\Magento\Payment\Gateway\Http\TransferInterface $transferObject)
    {
        $requests = $transferObject->getBody();
        $responses = [];
        return $responses;
    }
}
