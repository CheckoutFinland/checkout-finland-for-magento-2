<?php
namespace Op\Checkout\Model\Api;

use Magento\Framework\DataObject;
use Op\Checkout\Helper\ApiData;
use Op\Checkout\Helper\Data as opHelper;
use Op\Checkout\Helper\Signature;

class Checkout extends DataObject
{
    protected $apiData;
    protected $opHelper;
    protected $signature;

    public function __construct(
        ApiData $apiData,
        opHelper $opHelper,
        Signature $signature
    ) {
        $this->opHelper = $opHelper;
        $this->apiData = $apiData;
        $this->signature = $signature;
    }

    public function getResponseData($order)
    {
        $uri = '/payments';
        $merchantId = $this->opHelper->getMerchantId();
        $merchantSecret = $this->opHelper->getMerchantSecret();
        $method = 'post';

        $response = $this->apiData->getResponse($uri, $order, $merchantId, $merchantSecret, $method);

        return $response['data'];
    }

    public function getFormAction($responseData, $paymentMethodId = null)
    {
        $returnUrl = '';

        foreach ($responseData->providers as $provider) {
            if ($provider->id == $paymentMethodId) {
                $returnUrl = $provider->url;
            }
        }

        return $returnUrl;
    }

    public function getFormFields($responseData, $paymentMethodId = null)
    {
        $formFields = [];

        foreach ($responseData->providers as $provider) {
            if ($provider->id == $paymentMethodId) {
                foreach ($provider->parameters as $parameter) {
                    $formFields[$parameter->name] = $parameter->value;
                }
            }
        }

        return $formFields;
    }


    public function verifyPayment($signature, $status, $params)
    {
        $hmac = $this->signature->calculateHmac($params, '', $this->opHelper->getMerchantSecret());

        if ($signature === $hmac && ($status === 'ok' || $status === 'pending')) {
            return $status;
        } else {
            return false;
        }
    }

}