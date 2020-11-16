<?php
namespace Op\Checkout\Model;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\Builder as transactionBuilder;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface as transactionBuilderInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Op\Checkout\Exceptions\CheckoutException;
use Op\Checkout\Gateway\Config\Config;
use Op\Checkout\Gateway\Validator\ResponseValidator;
use Op\Checkout\Helper\ApiData;
use Op\Checkout\Helper\Data as opHelper;
use Op\Checkout\Setup\Recurring;

/**
 * Class ReceiptDataProvider
 */
class ReceiptDataProvider
{
    const RECEIPT_PROCESSING_CACHE_PREFIX = "receipt_processing_";

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * @var OrderSender
     */
    protected $orderSender;

    /**
     * @var TransportBuilder
     */
    protected $transportBuilder;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var OrderManagementInterface
     */
    protected $orderManagementInterface;

    /**
     * @var ResponseValidator
     */
    protected $responseValidator;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepositoryInterface;

    /**
     * @var transactionBuilderInterface
     */
    protected $transactionBuilderInterface;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var OrderStatusHistoryRepositoryInterface
     */
    protected $orderStatusHistoryRepository;

    /**
     * @var TransactionFactory
     */
    protected $transactionFactory;

    /**
     * @var opHelper
     */
    protected $opHelper;

    /**
     * @var OrderInterface
     */
    protected $orderInterface;

    /**
     * @var transactionBuilder
     */
    protected $transactionBuilder;

    /**
     * @var |Magento\Framework\App\CacheInterface
     */
    private $cache;

    /**
     * @var /Magento/Sales/Model/Order
     */
    protected $currentOrder;

    /**
     * @var \Magento\Sales\Model\Order\Payment
     */
    protected $currentOrderPayment;

    /**
     * @var null|int
     */
    protected $orderId;

    /**
     * @var null|string
     */
    protected $orderIncrementalId;

    /**
     * @var null|string
     */
    protected $transactionId;

    /**
     * @var null|string
     */
    protected $paramsStamp;

    /**
     * @var null|string
     */
    protected $paramsMethod;
    /**
     * @var Config
     */
    private $gatewayConfig;
    /**
     * @var ApiData
     */
    private $apiData;

    /**
     * ReceiptDataProvider constructor.
     * @param Context $context
     * @param Session $session
     * @param TransactionRepositoryInterface $transactionRepository
     * @param OrderSender $orderSender
     * @param TransportBuilder $transportBuilder
     * @param ScopeConfigInterface $scopeConfig
     * @param OrderManagementInterface $orderManagementInterface
     * @param ResponseValidator $responseValidator
     * @param OrderRepositoryInterface $orderRepositoryInterface
     * @param transactionBuilderInterface $transactionBuilderInterface
     * @param CacheInterface $cache
     * @param InvoiceService $invoiceService
     * @param OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository
     * @param TransactionFactory $transactionFactory
     * @param opHelper $opHelper
     * @param OrderInterface $orderInterface
     * @param transactionBuilder $transactionBuilder
     * @param Config $gatewayConfig
     * @param ApiData $apiData
     */
    public function __construct(
        Context $context,
        Session $session,
        TransactionRepositoryInterface $transactionRepository,
        OrderSender $orderSender,
        TransportBuilder $transportBuilder,
        ScopeConfigInterface $scopeConfig,
        OrderManagementInterface $orderManagementInterface,
        ResponseValidator $responseValidator,
        OrderRepositoryInterface $orderRepositoryInterface,
        transactionBuilderInterface $transactionBuilderInterface,
        CacheInterface $cache,
        InvoiceService $invoiceService,
        OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository,
        TransactionFactory $transactionFactory,
        opHelper $opHelper,
        OrderInterface $orderInterface,
        transactionBuilder $transactionBuilder,
        Config $gatewayConfig,
        ApiData $apiData
    ) {
        $this->urlBuilder = $context->getUrl();
        $this->cache = $cache;
        $this->context = $context;
        $this->session = $session;
        $this->transactionRepository = $transactionRepository;
        $this->orderSender = $orderSender;
        $this->transportBuilder = $transportBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->orderManagementInterface = $orderManagementInterface;
        $this->responseValidator = $responseValidator;
        $this->orderRepositoryInterface = $orderRepositoryInterface;
        $this->transactionBuilderInterface = $transactionBuilderInterface;
        $this->invoiceService = $invoiceService;
        $this->orderStatusHistoryRepository = $orderStatusHistoryRepository;
        $this->transactionFactory = $transactionFactory;
        $this->opHelper = $opHelper;
        $this->orderInterface = $orderInterface;
        $this->transactionBuilder = $transactionBuilder;
        $this->gatewayConfig = $gatewayConfig;
        $this->apiData = $apiData;
    }

    /**
     * @param array $params
     * @throws CheckoutException
     * @throws LocalizedException
     */
    public function execute(array $params)
    {
        if ($this->gatewayConfig->getGenerateReferenceForOrder()) {
            $this->orderIncrementalId
                = $this->opHelper->getIdFromOrderReferenceNumber(
                $params["checkout-reference"]
            );
        } else {
            $this->orderIncrementalId
                = $params["checkout-reference"];
        }
        $this->transactionId        =   $params["checkout-transaction-id"];
        $this->paramsStamp          =   $params['checkout-stamp'];
        $this->paramsMethod         =   $params['checkout-provider'];

        $this->session->unsCheckoutRedirectUrl();

        $this->currentOrder = $this->loadOrder();
        $this->orderId = $this->currentOrder->getId();

        /** @var int $count */
        $count = 0;

        while ($this->isOrderLocked($this->orderId) && $count < 3) {
            sleep(1);
            $count++;
        }

        $this->lockProcessingOrder($this->orderId);

        $this->currentOrderPayment = $this->currentOrder->getPayment();

        /** @var bool $paymentVerified */
        $paymentVerified = $this->verifyPaymentData($params);

        $this->processTransaction();
        $this->processPayment($paymentVerified);
        $this->processInvoice();
        $this->processOrder($paymentVerified);

        $this->unlockProcessingOrder($this->orderId);
    }

    /**
     * @param int $orderId
     */
    protected function lockProcessingOrder($orderId)
    {
        /** @var string $identifier */
        $identifier = self::RECEIPT_PROCESSING_CACHE_PREFIX . $orderId;

        $this->cache->save("locked", $identifier);
    }

    /**
     * @param int $orderId
     */
    protected function unlockProcessingOrder($orderId)
    {
        /** @var string $identifier */
        $identifier = self::RECEIPT_PROCESSING_CACHE_PREFIX . $orderId;

        $this->cache->remove($identifier);
    }

    /**
     * @param int $orderId
     * @return bool
     */
    protected function isOrderLocked($orderId)
    {
        /** @var string $identifier */
        $identifier = self::RECEIPT_PROCESSING_CACHE_PREFIX . $orderId;

        return $this->cache->load($identifier) ? true : false;
    }

    /**
     * @param $paymentVerified
     */
    protected function processOrder($paymentVerified)
    {
        $orderState = $this->gatewayConfig->getDefaultOrderStatus();

        if ($paymentVerified === 'pending') {
            $this->currentOrder->setState(Recurring::ORDER_STATE_CUSTOM_CODE);
            $this->currentOrder->setStatus(Recurring::ORDER_STATUS_CUSTOM_CODE);
            $this->currentOrder->addCommentToStatusHistory(__('Pending payment from OP Payment Service'));
        } else {
            $this->currentOrder->setState($orderState)->setStatus($orderState);
            $this->currentOrder->addCommentToStatusHistory(__('Payment has been completed'));
        }

        $this->orderRepositoryInterface->save($this->currentOrder);

        try {
            $this->orderSender->send($this->currentOrder);
        } catch (\Exception $e) {
            // TODO: log or not email sending issues ? atleast no need to break on this error.
        }
    }

    /**
     * process invoice
     * @throws LocalizedException
     * @throws CheckoutException
     */
    protected function processInvoice()
    {
        if ($this->currentOrder->canInvoice()) {
            try {
                /** @var /Magento/Sales/Api/Data/InvoiceInterface|/Magento/Sales/Model/Order/Invoice $invoice */
                $invoice = $this->invoiceService->prepareInvoice($this->currentOrder); //TODO: catch \InvalidArgumentException which extends \Exception
                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                $invoice->setTransactionId($this->currentOrderPayment->getLastTransId());
                $invoice->register();
                /** @var /Magento/Framework/DB/Transaction $transactionSave */
                $transactionSave = $this->transactionFactory->create();
                $transactionSave->addObject(
                    $invoice
                )->addObject(
                    $this->currentOrder
                )->save();
            } catch (LocalizedException $exception) {
                $invoiceFailException = $exception->getMessage(); //TODO: log errors
            }

            if (isset($invoiceFailException)) {
                $this->opHelper->processError($invoiceFailException);
            }
        }
    }

    /**
     * @param bool|string $paymentVerified
     */
    protected function processPayment($paymentVerified = false)
    {
        if ($paymentVerified === 'ok') {

            /** @var \Magento\Sales\Model\Order\Payment\Transaction\Builder|null $transaction */
            $transaction = $this->addPaymentTransaction($this->currentOrder, $this->transactionId, $this->getDetails());

            $this->currentOrderPayment->addTransactionCommentsToOrder($transaction, '');
            $this->currentOrderPayment->setLastTransId($this->transactionId);

            if ($this->currentOrder->getStatus() == 'canceled') {
                $this->notifyCanceledOrder();
            }
        }
    }

    /**
     * notify canceled order
     */
    protected function notifyCanceledOrder()
    {
        if (filter_var($this->gatewayConfig->getNotificationEmail(), FILTER_VALIDATE_EMAIL)) {
            $postObject = new \Magento\Framework\DataObject();
            $postObject->setData(['order_id' => $this->orderIncrementalId]);
            $transport = $this->transportBuilder
                ->setTemplateIdentifier('restore_order_notification')
                ->setTemplateOptions(['area' => \Magento\Framework\App\Area::AREA_FRONTEND, 'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID])
                ->setTemplateVars(['data' => $postObject])
                ->setFrom([
                    'name' => $this->opHelper->getConfig('general/store_information/name') . ' - Magento',
                    'email' => $this->opHelper->getConfig('trans_email/ident_general/email')
                ])->addTo([
                    $this->gatewayConfig->getNotificationEmail()
                ])->getTransport();
            $transport->sendMessage();
        }
    }

    /**
     * @return array
     */
    protected function getDetails()
    {
        return [
            'orderNo'   => $this->orderIncrementalId,
            'stamp'     => $this->paramsStamp,
            'method'    => $this->paramsMethod
        ];
    }

    /**
     * @return mixed
     * @throws CheckoutException
     */
    protected function loadOrder()
    {
        $order = $this->orderInterface->loadByIncrementId($this->orderIncrementalId);
        if (!$order->getId()) {
            $this->opHelper->processError('Order not found');
        }
        return $order;
    }

    /**
     * @param string[] $params
     * @throws LocalizedException
     * @throws CheckoutException
     * @return string|void
     */
    protected function verifyPaymentData($params)
    {
        $status = $params['checkout-status'];
        $verifiedPayment = $this->apiData->validateHmac($params, $params['signature']);

        if ($verifiedPayment && ($status === 'ok' || $status == 'pending')) {
            return $status;
        } else {
            $this->currentOrder->addCommentToStatusHistory(__('Order canceled. Failed to complete the payment.'));
            $this->orderRepositoryInterface->save($this->currentOrder);
            $this->orderManagementInterface->cancel($this->currentOrder->getId());
            $this->opHelper->processError('Failed to complete the payment. Please try again or contact the customer service.');
        }
    }

    /**
     * @return bool|mixed
     */
    protected function loadTransaction()
    {
        /** @var bool|mixed $transaction */
        $transaction = $this->transactionRepository->getByTransactionId(
            $this->transactionId,
            $this->currentOrder->getPayment()->getId(),
            $this->orderId
        );

        return $transaction;
    }

    /**
     * @param $transaction
     */
    protected function processExistingTransaction($transaction)
    {
        $details = $transaction->getAdditionalInformation(Transaction::RAW_DETAILS);
        if (is_array($details)) {
            $this->opHelper->processSuccess();
        }
    }

    /**
     * @return bool
     * @throws CheckoutException
     */
    protected function processTransaction()
    {
        $transaction = $this->loadTransaction();
        if ($transaction) {
            $this->processExistingTransaction($transaction);
            $this->opHelper->processError('Payment failed');
        }
        return true;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param $transactionId
     * @param array $details
     * @return transactionBuilder|null
     */
    protected function addPaymentTransaction(\Magento\Sales\Model\Order $order, $transactionId, array $details = [])
    {
        /** @var null|\Magento\Sales\Model\Order\Payment\Transaction\Builder $transaction */
        $transaction = null;
        /** @var \Magento\Framework\DataObject|\Magento\Sales\Api\Data\OrderPaymentInterface |mixed|null $payment */
        $payment = $order->getPayment();

        $transaction = $this->transactionBuilder
            ->setPayment($payment)->setOrder($order)
            ->setTransactionId($transactionId)
            ->setAdditionalInformation([Transaction::RAW_DETAILS => (array) $details])
            ->setFailSafe(true)
            ->build(Transaction::TYPE_CAPTURE);
        $transaction->setIsClosed(false);
        return $transaction;
    }
}
