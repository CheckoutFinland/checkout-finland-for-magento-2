<?php
namespace Op\Checkout\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Op\Checkout\Helper\Signature;
use Op\Checkout\Helper\Data as opHelper;

class ResponseValidator extends AbstractValidator
{

    /**
     * @var opHelper
     */
    private $opHelper;
    /**
     * @var Signature
     */
    private $signature;

    /**
     * ResponseValidator constructor.
     * @param opHelper $opHelper
     * @param Signature $signature
     * @param ResultInterfaceFactory $resultFactory
     */
    public function __construct(
        opHelper $opHelper,
        Signature $signature,
        ResultInterfaceFactory $resultFactory
    ) {
        parent::__construct($resultFactory);
        $this->opHelper = $opHelper;
        $this->signature = $signature;
    }

    public function validate(array $validationSubject)
    {
        $isValid = true;
        $fails = [];

        if ($this->validateOrderId($validationSubject["checkout-reference"]) == false) {
            $fails[] = "OrderId is invalid";
        }

        if ($this->isRequestMerchantIdEmpty($this->opHelper->getMerchantId())) {
            $fails[] = "Request MerchantId is empty";
        }

        if ($this->isResponseMerchantIdEmpty($validationSubject["checkout-account"])) {
            $fails[] = "Response MerchantId is empty";
        }

        if ($this->isMerchantIdValid($validationSubject["checkout-account"]) == false) {
            $fails[] = "Response and Request merchant ids does not match";
        }

        if ($this->validateResponse($validationSubject) == false) {
            $fails[] = "Invalid response data from Checkout";
        }

        if ($this->validateAlgorithm($validationSubject["checkout-algorithm"]) == false) {
            $fails[] = "Invalid response data from Checkout";
        }

        if (sizeof($fails) > 0) {
            $isValid = false;
        }
        return $this->createResult($isValid, $fails);
    }

    /**
     * @param $orderid
     * @return bool
     */
    public function validateOrderId($orderid)
    {
        return is_numeric($orderid);
    }

    /**
     * @param $responseMerchantId
     * @return bool
     */
    public function isMerchantIdValid($responseMerchantId)
    {
        $requestMerchantId = $this->opHelper->getMerchantId();
        if ($requestMerchantId == $responseMerchantId) {
            return true;
        }

        return false;
    }

    /**
     * @param $requestMerchantId
     * @return bool
     */
    public function isRequestMerchantIdEmpty($requestMerchantId)
    {
        return empty($requestMerchantId);
    }

    /**
     * @param $responseMerchantId
     * @return bool
     */
    public function isResponseMerchantIdEmpty($responseMerchantId)
    {
        return empty($responseMerchantId);
    }

    /**
     * @param $algorithm
     * @return bool
     */
    public function validateAlgorithm($algorithm)
    {
        return in_array($algorithm, $this->opHelper->getValidAlgorithms(), true);
    }

    /**
     * @param $params
     * @return bool
     */
    public function validateResponse($params)
    {
        $hmac = $this->signature->calculateHmac($params, '', $this->opHelper->getMerchantSecret());
        if ($params["signature"] !== $hmac) {
            return false;
        }
        return true;
    }
}
