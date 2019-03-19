<?php

namespace Op\Checkout\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;

class RefundResponseValidator extends AbstractValidator
{
    protected $resultFactory;
    public function __construct(
        \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory
    ) {
        parent::__construct($resultFactory);
        $this->resultFactory = $resultFactory;
    }

    public function validate(array $validationSubject)
    {
        $responses = \Magento\Payment\Gateway\Helper\SubjectReader::readResponse($validationSubject);

        $isValid = true;
        $errorMessages = [];

        return $this->createResult($isValid, $errorMessages);
    }
}
