<?php

namespace Op\Checkout\Tests\Helper;

use Op\Checkout\Controller\Receipt\Index;
use PHPUnit\Framework\TestCase;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Quote\Model\QuoteRepository;
use Op\Checkout\Gateway\Validator\ResponseValidator;
use Op\Checkout\Model\CheckoutException;
use Op\Checkout\Model\ReceiptDataProvider;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Action\Action;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Quote\Model\Quote;


class ReceiptIndexUnitTest extends TestCase
{
    /** @var Context | \PHPUnit_Framework_MockObject_MockObject * */
    private $contextMock;

    /** @var Session | \PHPUnit_Framework_MockObject_MockObject */
    private $sessionMock;

    /** @var  QuoteRepository | \PHPUnit_Framework_MockObject_MockObject */
    private $quoteRepositoryMock;

    /** @var  ResponseValidator | \PHPUnit_Framework_MockObject_MockObject */
    private $responseValidatorMock;

    /** @var  CheckoutException | \PHPUnit_Framework_MockObject_MockObject */
    private $checkoutExceptionMock;

    /** @var  ReceiptDataProvider | \PHPUnit_Framework_MockObject_MockObject */
    private $receiptDataProviderMock;

    /** @var  RequestInterface | \PHPUnit_Framework_MockObject_MockObject*/
    private $requestInterfaceMock;

    /** @var Index */
    private $controller;

    /** @var ResultInterface | \PHPUnit_Framework_MockObject_MockObject */
    private $resultInterfaceMock;

    /** @var  Action | \PHPUnit_Framework_MockObject_MockObject */
    private $actionMock;

    /** @var  ManagerInterface | \PHPUnit_Framework_MockObject_MockObject */
    private $messageManagerMock;
    
    /** @var  RedirectInterface | \PHPUnit_Framework_MockObject_MockObject */
    private $redirectInterfaceMock;

    /** @var  ResponseInterface | \PHPUnit_Framework_MockObject_MockObject */
    private $responseInterfaceMock;

    /** @var  Quote | \PHPUnit_Framework_MockObject_MockObject */
    private $quoteMock;

    private function getSimpleMock($originalClassName)
    {
        return $this->getMockBuilder($originalClassName)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function setUp()
    {
        $this->resultInterfaceMock = $this->getSimpleMock(ResultInterface::class);
        $this->messageManagerMock = $this->getSimpleMock(ManagerInterface::class);
        $this->responseValidatorMock = $this->getSimpleMock(ResponseValidator::class);
        $this->checkoutExceptionMock = $this->getSimpleMock(CheckoutException::class);
        $this->receiptDataProviderMock = $this->getSimpleMock(ReceiptDataProvider::class);
        $this->quoteRepositoryMock = $this->getSimpleMock(QuoteRepository::class);
        $this->responseInterfaceMock = $this->getSimpleMock(ResponseInterface::class);
        $this->sessionMock = $this->getSimpleMock(Session::class);

        $this->contextMock = $this->createPartialMock(
            \Magento\Backend\App\Action\Context::class,
            ['getRequest', 'getSession', 'getMessageManager', 'getRedirect', 'getResponse']
        );

        $this->redirectInterfaceMock = $this->getMockBuilder(RedirectInterface::class)->disableOriginalConstructor()->setMethods(['redirect'])->getMockForAbstractClass();;
        $this->requestInterfaceMock = $this->getMockBuilder(RequestInterface::class)->disableOriginalConstructor()->setMethods(['getParams'])->getMockForAbstractClass();
        $this->actionMock = $this->getMockBuilder(Action::class)->disableOriginalConstructor()->setMethods(['getResponse'])->getMockForAbstractClass();
        $this->quoteMock = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->setMethods(['setIsActive'])->getMock();

        $this->contextMock->expects($this->any())->method('getRequest')->will($this->returnValue($this->requestInterfaceMock));
        $this->contextMock->expects($this->any())->method('getMessageManager')->willReturn($this->messageManagerMock);
        $this->contextMock->expects($this->any())->method('getRedirect')->willReturn($this->redirectInterfaceMock);
        $this->contextMock->expects($this->any())->method('getResponse')->willReturn($this->responseInterfaceMock);

        $this->requestInterfaceMock->method('getParams')->willReturn(['checkout-reference' => 'test reference']);
        $this->requestInterfaceMock->method('getParam')->with('checkout-reference')->willReturn('test reference');

        $this->responseValidatorMock->method('validate')->willReturn($this->resultInterfaceMock);

        $this->controller = new Index(
            $this->contextMock,
            $this->sessionMock,
            $this->responseValidatorMock,
            $this->quoteRepositoryMock,
            $this->receiptDataProviderMock
        );
    }

    public function testValidateReturnsFail()
    {
        $this->resultInterfaceMock->method('isValid')
            ->willReturn(false);

        $this->resultInterfaceMock->method('getFailsDescription')
            ->willReturn(['Test fail description']);

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with('Test fail description');

        $this->sessionMock->expects($this->once())
            ->method('restoreQuote');
        
        $this->redirectInterfaceMock->expects($this->once())
            ->method('redirect');

        $this->controller->execute();
    }

    public function testValidateSucceeds()
    {
        $this->resultInterfaceMock->method('isValid')
            ->willReturn(true);

        $this->resultInterfaceMock->method('getFailsDescription')
            ->willReturn(['']);

        $this->sessionMock->expects($this->never())
            ->method('restoreQuote');

        $this->sessionMock->expects($this->once())
            ->method('getQuote')
            ->willReturn($this->quoteMock);

        $this->quoteMock->expects($this->once())
            ->method('setIsActive');
        
        $this->quoteRepositoryMock->expects($this->once())
            ->method('save')
            ->with($this->quoteMock);

        $this->controller->execute();
    }

    public function testDataProviderThrowsCheckoutException()
    {
        $this->resultInterfaceMock->method('isValid')
            ->willReturn(true);

        $this->resultInterfaceMock->method('getFailsDescription')
            ->willReturn(['']);
        
        $this->receiptDataProviderMock->method('execute')->willThrowException($this->checkoutExceptionMock);

        $this->sessionMock->expects($this->once())
            ->method('restoreQuote');

        $this->redirectInterfaceMock->expects($this->once())
            ->method('redirect');

        $this->controller->execute();

    }

}
