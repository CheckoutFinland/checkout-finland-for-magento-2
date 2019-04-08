<?php

namespace Op\Checkout\Test\Unit\Controller;

use Op\Checkout\Controller\Redirect\Index;
use PHPUnit\Framework\TestCase;

use Magento\Framework\Exception\LocalizedException;
use Op\Checkout\Helper\ApiData;
use Op\Checkout\Helper\Data as opHelper;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Sales\Model\Order;
use \InvalidArgumentException;

class RedirectIndexUnitTest extends TestCase
{

    /** @var  ApiData | \PHPUnit_Framework_MockObject_MockObject */
    private $apiDataMock;

    /** @var opHelper | \PHPUnit_Framework_MockObject_MockObject */
    private $opHelperMock;

    /** @var LoggerInterface | \PHPUnit_Framework_MockObject_MockObject */
    private $loggerInterfaceMock;

    /** @var  Context | \PHPUnit_Framework_MockObject_MockObject */
    private $contextMock;

    /** @var  Session | \PHPUnit_Framework_MockObject_MockObject */
    private $sessionMock;

    /** @var  OrderFactory | \PHPUnit_Framework_MockObject_MockObject */
    private $orderFactoryMock;

    /** @var  JsonFactory | \PHPUnit_Framework_MockObject_MockObject */
    private $jsonFactoryMock;

    /** @var  OrderRepositoryInterface | \PHPUnit_Framework_MockObject_MockObject */
    private $orderRepositoryInterfaceMock;

    /** @var  OrderManagementInterface | \PHPUnit_Framework_MockObject_MockObject */
    private $orderManagementInterfaceMock;

    /** @var  PageFactory | \PHPUnit_Framework_MockObject_MockObject */
    private $pageFactoryMock;

    /** @var  LocalizedException | \PHPUnit_Framework_MockObject_MockObject */
    private $localizedExceptionMock;

    /** @var  Action | \PHPUnit_Framework_MockObject_MockObject */
    private $actionMock;

    /** @var  RequestInterface | \PHPUnit_Framework_MockObject_MockObject */
    private $requestInterfaceMock;

    /** @var  Index */
    private $redirectIndex;

    /** @var  Json | \PHPUnit_Framework_MockObject_MockObject */
    private $jsonMock;

    /** @var  Order | \PHPUnit_Framework_MockObject_MockObject */
    private $orderMock;

    /** @var  InvalidArgumentException | \PHPUnit_Framework_MockObject_MockObject */
    private $invalidArgumentMock;


    private function getSimpleMock($originalClassName)
    {
        return $this->getMockBuilder($originalClassName)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function setUp()
    {
        $this->apiDataMock = $this->getSimpleMock(ApiData::class);
        $this->opHelperMock = $this->getSimpleMock(opHelper::class);
        $this->loggerInterfaceMock = $this->getSimpleMock(LoggerInterface::class);
        $this->orderFactoryMock = $this->getSimpleMock(OrderFactory::class);
        $this->jsonFactoryMock = $this->getSimpleMock(JsonFactory::class);
        $this->orderRepositoryInterfaceMock = $this->getSimpleMock(OrderRepositoryInterface::class);
        $this->orderManagementInterfaceMock = $this->getSimpleMock(OrderManagementInterface::class);
        $this->localizedExceptionMock = $this->getSimpleMock(LocalizedException::class);
        $this->pageFactoryMock = $this->getSimpleMock(PageFactory::class);
        $this->jsonMock = $this->getSimpleMock(Json::class);
        $this->orderMock = $this->getSimpleMock(Order::class);
        $this->invalidArgumentMock = $this->getSimpleMock(InvalidArgumentException::class);
        $this->actionMock = $this->getSimpleMock(Action::class);

        $this->contextMock = $this->createPartialMock(
            \Magento\Backend\App\Action\Context::class,
            ['getRequest', 'getSession', 'getRedirect']
        );

        $this->sessionMock = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->setMethods(['getLastRealOrderId', 'restoreQuote'])->getMockForAbstractClass();
        $this->requestInterfaceMock = $this->getMockBuilder(RequestInterface::class)->disableOriginalConstructor()->setMethods(['getParams'])->getMockForAbstractClass();

        $this->contextMock->expects($this->any())->method('getRequest')->will($this->returnValue($this->requestInterfaceMock));
        $this->jsonFactoryMock->expects($this->any())->method('create')->willReturn($this->jsonMock);

        $this->redirectIndex = new Index(
            $this->contextMock,
            $this->sessionMock,
            $this->orderFactoryMock,
            $this->jsonFactoryMock,
            $this->orderRepositoryInterfaceMock,
            $this->orderManagementInterfaceMock,
            $this->pageFactoryMock,
            $this->loggerInterfaceMock,
            $this->apiDataMock,
            $this->opHelperMock
        );
    }

    public function testAjaxRequestParameterNotSet()
    {
        $this->requestInterfaceMock->method('getParam')
            ->with('is_ajax')
            ->willReturn(false);

        $this->orderFactoryMock->expects($this->never())
            ->method('create');

        $this->loggerInterfaceMock->expects($this->never())
            ->method('debug');

        $this->jsonMock->expects($this->once())
            ->method('setData')
            ->with([
                'success' => false,
            ]);

        $this->redirectIndex->execute();
    }

    public function testApiResponseThrowsError()
    {
        $this->requestInterfaceMock->expects($this->at(0))
            ->method('getParam')
            ->with('is_ajax')
            ->will($this->returnValue(true));

        $this->requestInterfaceMock->expects($this->at(1))
            ->method('getParam')
            ->with('preselected_payment_method_id')
            ->will($this->returnValue('test-payment-method'));

        $this->apiDataMock->method('getResponse')
            ->willThrowException($this->invalidArgumentMock);

        $this->orderFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($this->orderMock);

        $this->orderMock->expects($this->once())
            ->method('loadByIncrementId')
            ->willReturn($this->orderMock);

        $this->sessionMock->expects($this->once())
            ->method('getLastRealOrderId')
            ->willReturn(1);

        $this->loggerInterfaceMock->expects($this->once())
            ->method('debug');

        $this->jsonMock->expects($this->once())
            ->method('setData')
            ->with([
                'success' => false,
            ]);

        $this->redirectIndex->execute();
    }

    public function testPaymentMethodParameterFails()
    {
        $this->requestInterfaceMock->expects($this->at(0))
            ->method('getParam')
            ->with('is_ajax')
            ->will($this->returnValue(true));

        $this->requestInterfaceMock->expects($this->at(1))
            ->method('getParam')
            ->with('preselected_payment_method_id')
            ->will($this->returnValue(null));

        $this->orderFactoryMock->expects($this->never())
            ->method('create');

        $this->loggerInterfaceMock->expects($this->once())
            ->method('debug');

        $this->jsonMock->expects($this->once())
            ->method('setData')
            ->with([
                'success' => false,
            ]);

        $this->redirectIndex->execute();
    }

}
