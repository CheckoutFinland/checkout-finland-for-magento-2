<?php
namespace Op\Checkout\Gateway\Http\Client;

class TransactionRefund extends AbstractTransaction
{
    protected function process(array $data)
    {
        $response['object'] = [];
        return $response;
    }
}
