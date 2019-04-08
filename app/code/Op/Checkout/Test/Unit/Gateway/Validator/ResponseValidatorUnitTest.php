<?php

namespace Op\Checkout\Tests\Gateway\Validator;

use Op\Checkout\Gateway\Validator\ResponseValidator;
use PHPUnit\Framework\TestCase;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Op\Checkout\Helper\Signature;
use Op\Checkout\Helper\Data as opHelper;

class ResponseValidatorUnitTest extends TestCase
{
    /** @var  Signature | \PHPUnit_Framework_MockObject_MockObject */
    private $signatureMock;

    /** @var  opHelper | \PHPUnit_Framework_MockObject_MockObject */
    private $opHelperMock;

    /** @var  AbstractValidator | \PHPUnit_Framework_MockObject_MockObject */
    private $abstractValidatorMock;

    /** @var  ResultInterfaceFactory | \PHPUnit_Framework_MockObject_MockObject */
    private $resultInterfaceFactoryMock;

    /** @var  ResponseValidator */
    private $responseValidator;

    private $shouldPass = [
        'checkout-account' => 'test-merchant-id',
        'checkout-reference' => 123123123123123,
        'checkout-algorithm' => 'sha256',
        'signature' => 'test-signature'
    ];

    private $shouldFail = [
        'checkout-account' => 'invalid-checkout-accountid',
        'checkout-reference' => 'invalid-checkout-reference',
        'checkout-algorithm' => 'sha257',
        'signature' => 'failing-test-signature'
    ];

    private function getSimpleMock($originalClassName)
    {
        return $this->getMockBuilder($originalClassName)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function setUp()
    {
        $this->signatureMock = $this->getSimpleMock(Signature::class);
        $this->opHelperMock = $this->getSimpleMock(opHelper::class);
        $this->resultInterfaceFactoryMock = $this->getSimpleMock(ResultInterfaceFactory::class);
        $this->abstractValidatorMock = $this->getSimpleMock(AbstractValidator::class);

        $this->responseValidator = new ResponseValidator(
            $this->opHelperMock,
            $this->signatureMock,
            $this->resultInterfaceFactoryMock
        );

        $this->opHelperMock->method('getValidAlgorithms')->willReturn(array('sha256', 'sha512'));

        $this->opHelperMock->method('getMerchantId')->willReturn('test-merchant-id');

        $this->signatureMock->method('calculateHmac')->willReturn('test-signature');
    }

    public function testValidateCreatesValidResult()
    {
        $this->resultInterfaceFactoryMock->expects($this->once())->method('create')->with([
            'isValid' => true,
            'failsDescription' => []
        ]);

        $this->responseValidator->validate($this->shouldPass);
    }

    public function testValidateCreatesInValidResult()
    {
        $this->resultInterfaceFactoryMock->expects($this->once())->method('create')->with([
            'isValid' => false,
            'failsDescription' => [
                0 => 'OrderId is invalid',
                1 => 'Response and Request merchant ids does not match',
                2 => 'Invalid response data from Checkout',
                3 => 'Invalid response data from Checkout'
            ]]);

        $this->responseValidator->validate($this->shouldFail);
    }

    public function testValidateOrderId()
    {
        $trueResult = $this->responseValidator->validateOrderId($this->shouldPass['checkout-reference']);

        self::assertTrue($trueResult);

        $falseResult = $this->responseValidator->validateOrderId(null);

        self::assertFalse($falseResult);

        $falseResult = $this->responseValidator->validateOrderId($this->shouldFail['checkout-reference']);

        self::assertFalse($falseResult);
    }

    public function testIsRequestMerchantIdEmpty()
    {
        $trueResult = $this->responseValidator->isRequestMerchantIdEmpty(null);

        self::assertTrue($trueResult);

        $trueResult = $this->responseValidator->isRequestMerchantIdEmpty('');

        self::assertTrue($trueResult);

        $falseResult = $this->responseValidator->isRequestMerchantIdEmpty(123);

        self::assertFalse($falseResult);
    }

    public function testIsResponseMerchantIdEmpty()
    {
        $trueResult = $this->responseValidator->isResponseMerchantIdEmpty(null);

        self::assertTrue($trueResult);

        $falseResult = $this->responseValidator->isResponseMerchantIdEmpty($this->shouldPass['checkout-account']);

        self::assertFalse($falseResult);
    }

    public function testMerchantIdMatches()
    {
        $trueResult = $this->responseValidator->isMerchantIdValid($this->shouldPass['checkout-account']);

        self::assertTrue($trueResult);
    }

    public function testMerchantIdDoesNotMatch()
    {
        $falseResult = $this->responseValidator->isMerchantIdValid($this->shouldFail['checkout-account']);

        self::assertFalse($falseResult);
    }

    public function testValidateAlgorithm()
    {
        $falseResult = $this->responseValidator->validateAlgorithm($this->shouldFail['checkout-algorithm']);

        self::assertFalse($falseResult);

        $trueResult = $this->responseValidator->validateAlgorithm($this->shouldPass['checkout-algorithm']);

        self::assertTrue($trueResult);
    }

    public function testValidateResponse()
    {
        $trueResult = $this->responseValidator->validateResponse($this->shouldPass);

        self::assertTrue($trueResult);

        $falseResult = $this->responseValidator->validateResponse($this->shouldFail);

        self::assertFalse($falseResult);

    }

}
