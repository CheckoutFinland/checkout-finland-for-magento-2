<?php
namespace Op\Checkout\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Op\Checkout\Helper\Data as opHelper;
use Op\Checkout\Helper\ApiData;

class ResponseValidator extends AbstractValidator
{

    /**
     * @var opHelper
     */
    private $opHelper;

    /**
     * @var ApiData
     */
    private $apiData;

    /**
     * ResponseValidator constructor.
     * @param opHelper $opHelper
     * @param ResultInterfaceFactory $resultFactory
     * @param ApiData $apiData
     */
    public function __construct(
        opHelper $opHelper,
        ResultInterfaceFactory $resultFactory,
        ApiData $apiData
    ) {
        parent::__construct($resultFactory);
        $this->opHelper = $opHelper;
        $this->apiData = $apiData;
    }

    /**
     * @param array $validationSubject
     * @return \Magento\Payment\Gateway\Validator\ResultInterface
     */
    public function validate(array $validationSubject)
    {
        $isValid = true;
        $fails = [];

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
        return $this->apiData->validateHmac($params, $params["signature"]);
    }
}
