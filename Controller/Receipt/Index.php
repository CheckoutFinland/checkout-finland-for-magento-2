<?php

namespace Op\Checkout\Controller\Receipt;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Api\Data\OrderInterface;
use Op\Checkout\Gateway\Validator\ResponseValidator;
use Op\Checkout\Helper\ProcessPayment;
use Op\Checkout\Model\CheckoutException;
use Op\Checkout\Model\TransactionSuccessException;
use Op\Checkout\Model\ReceiptDataProvider;
use Op\Checkout\Gateway\Config\Config;
use Op\Checkout\Helper\Data;

/**
 * Class Index
 */
class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var Session
     */
    protected $session;

    /**
     * @var ResponseValidator
     */
    protected $responseValidator;

    /**
     * @var ReceiptDataProvider
     */
    protected $receiptDataProvider;

    /**
     * @var QuoteRepository
     */
    protected $quoteRepository;

    /**
     * @var OrderInterface
     */
    private $orderInterface;

    /**
     * @var ProcessPayment
     */
    private $processPayment;
    /**
     * @var Config
     */
    private $gatewayConfig;
    /**
     * @var Data
     */
    private $opHelper;

    /**
     * Index constructor.
     * @param Context $context
     * @param Session $session
     * @param ResponseValidator $responseValidator
     * @param QuoteRepository $quoteRepository
     * @param ReceiptDataProvider $receiptDataProvider
     * @param OrderInterface $orderInterface
     * @param ProcessPayment $processPayment
     * @param Config $gatewayConfig
     * @param Data $opHelper
     */
    public function __construct(
        Context $context,
        Session $session,
        ResponseValidator $responseValidator,
        QuoteRepository $quoteRepository,
        ReceiptDataProvider $receiptDataProvider,
        OrderInterface $orderInterface,
        ProcessPayment $processPayment,
        Config $gatewayConfig,
        Data $opHelper
    ) {
        parent::__construct($context);
        $this->session = $session;
        $this->responseValidator = $responseValidator;
        $this->receiptDataProvider = $receiptDataProvider;
        $this->quoteRepository = $quoteRepository;
        $this->orderInterface = $orderInterface;
        $this->processPayment = $processPayment;
        $this->gatewayConfig = $gatewayConfig;
        $this->opHelper = $opHelper;
    }

    /**
     * execute method
     */
    public function execute() // there is also other call which changes order status
    {

        /** @var array $successStatuses */
        $successStatuses = ["processing", "pending_opcheckout", "pending"];

        /** @var array $cancelStatuses */
        $cancelStatuses = ["canceled"];

        /** @var string $reference */
        $reference = $this->getRequest()->getParam('checkout-reference');

        /** @var string $orderNo */
        $orderNo = $this->gatewayConfig->getGenerateReferenceForOrder()
            ? $this->opHelper->getIdFromOrderReferenceNumber($reference)
            : $reference;

        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->orderInterface->loadByIncrementId($orderNo);

        sleep(2); //giving callback time to get processed

        /** @var string $status */
        $status = $order->getStatus();

        /** @var array $failMessages */
        $failMessages = [];

        if ($status == 'pending_payment' || in_array($status, $cancelStatuses)) {
            // order status could be changed by callback, if not, status change needs to be forced by processing the payment
            $failMessages = $this->processPayment->process($this->getRequest()->getParams(),$this->session);
        }

        if ($status == 'pending_payment') { // status could be changed by callback, if not, it needs to be forced
            $order = $this->orderInterface->loadByIncrementId($orderNo); // refreshing order
            $status = $order->getStatus(); // getting current status
        }

        if (in_array($status,$successStatuses)) {
            $this->_redirect('checkout/onepage/success');
        } else if (in_array($status,$cancelStatuses)) {

            /** @var string $failMessage */
            foreach ($failMessages as $failMessage) {
                $this->messageManager->addErrorMessage($failMessage);
            }

            $this->_redirect('checkout/cart');
        }

        return; //TODO: error log
    }
}
