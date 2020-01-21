<?php
namespace Op\Checkout\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\Builder as transactionBuilder;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface as transactionBuilderInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Sales\Api\OrderManagementInterface;
use Op\Checkout\Helper\Data as opHelper;
use Op\Checkout\Helper\Signature;
use Magento\Sales\Api\OrderRepositoryInterface;
use Op\Checkout\Gateway\Validator\ResponseValidator;
use Op\Checkout\Gateway\Request\Capture as opCapture;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\Data\OrderInterface;

class ReceiptDataProvider
{
    protected $urlBuilder;
    protected $session;
    protected $transactionRepository;
    protected $orderFactory;
    protected $orderSender;
    protected $scopeConfig;
    protected $orderManagementInterface;
    protected $orderRepositoryInterface;
    protected $responseValidator;
    protected $transactionBuilderInterface;
    protected $signature;
    /**
     * @var opCapture
     */
    protected $opCapture;
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
     * @var /Magento/Sales/Model/Order
     */
    protected $currentOrder;
    protected $currentOrderPayment;
    protected $orderId;
    protected $orderIncrementalId;
    protected $transactionId;
    protected $paramsStamp;
    protected $paramsMethod;
    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;


    /**
     * ReceiptDataProvider constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param TransportBuilder $transportBuilder
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param OrderManagementInterface $orderManagementInterface
     * @param ResponseValidator $responseValidator
     * @param OrderRepositoryInterface $orderRepositoryInterface
     * @param transactionBuilderInterface $transactionBuilderInterface
     * @param opCapture $opCapture
     * @param Signature $signature
     * @param InvoiceService $invoiceService
     * @param OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository
     * @param TransactionFactory $transactionFactory
     * @param opHelper $opHelper
     * @param OrderInterface $orderInterface
     * @param transactionBuilder $transactionBuilder
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $session,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        TransportBuilder $transportBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        OrderManagementInterface $orderManagementInterface,
        ResponseValidator $responseValidator,
        OrderRepositoryInterface $orderRepositoryInterface,
        transactionBuilderInterface $transactionBuilderInterface,
        opCapture $opCapture,
        Signature $signature,
        InvoiceService $invoiceService,
        OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository,
        TransactionFactory $transactionFactory,
        opHelper $opHelper,
        OrderInterface $orderInterface,
        transactionBuilder $transactionBuilder
    ) {
        $this->urlBuilder = $context->getUrl();
        $this->session = $session;
        $this->transactionRepository = $transactionRepository;
        $this->orderSender = $orderSender;
        $this->transportBuilder = $transportBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->signature = $signature;
        $this->orderManagementInterface = $orderManagementInterface;
        $this->orderRepositoryInterface = $orderRepositoryInterface;
        $this->responseValidator = $responseValidator;
        $this->transactionBuilder = $transactionBuilder;
        $this->transactionBuilderInterface = $transactionBuilderInterface;
        $this->opCapture = $opCapture;
        $this->invoiceService = $invoiceService;
        $this->orderStatusHistoryRepository = $orderStatusHistoryRepository;
        $this->transactionFactory = $transactionFactory;
        $this->opHelper = $opHelper;
        $this->context = $context;
        $this->orderInterface = $orderInterface;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /* TODO: MOST OF THE LOGIC GOES HERE! */

    public function execute(array $params)
    {
        $this->orderIncrementalId   =   $params["checkout-reference"];
        $this->transactionId        =   $params["checkout-transaction-id"];
        $this->paramsStamp          =   $params['checkout-stamp'];
        $this->paramsMethod         =   $params['checkout-provider'];


        $this->session->unsCheckoutRedirectUrl();

        $this->currentOrder = $this->loadOrder();

        $this->searchCriteriaBuilder->addFilter('txn_id', $this->transactionId);

        /** @var \Magento\Sales\Api\Data\TransactionInterface[] $conflictingTransactions */
        $conflictingTransactions = $this->transactionRepository->getList(
            $this->searchCriteriaBuilder->create()
        )->getItems();

        if (sizeof($conflictingTransactions) > 0) { //handling multiple calls with the same transaction id
            return;
        }

        $this->orderId = $this->currentOrder->getId();
        $this->currentOrderPayment = $this->currentOrder->getPayment();


        $paymentVerified = $this->verifyPaymentData($params);

        $this->processTransaction();
        $this->processPayment($paymentVerified);
        $this->processInvoice();
        $this->processOrder($paymentVerified);
    }


    protected function processOrder($paymentVerified)
    {
        $orderState = $this->opHelper->getDefaultOrderStatus();

        if ($paymentVerified === 'pending') {
            $this->currentOrder->setState('pending_opcheckout');
            $this->currentOrder->setStatus('pending_opcheckout');
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

    protected function processInvoice()
    {
        if ($this->currentOrder->canInvoice()) {
            try {
                $invoice = $this->invoiceService->prepareInvoice($this->currentOrder);
                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                $invoice->setTransactionId($this->currentOrderPayment->getLastTransId());
                $invoice->register();
                $transactionSave = $this->transactionFactory->create();
                $transactionSave->addObject(
                    $invoice
                )->addObject(
                    $this->currentOrder
                )->save();
            } catch (LocalizedException $exception) {
                $invoiceFailException = $exception->getMessage();
            }

            if (isset($invoiceFailException)) {
                $this->processError($invoiceFailException);
            }
        }
    }

    protected function processPayment($paymentVerified = false)
    {
        if ($paymentVerified === 'ok') {
            $transaction = $this->addPaymentTransaction($this->currentOrder, $this->transactionId, $this->getDetails());
            $this->currentOrderPayment->addTransactionCommentsToOrder($transaction, '');
            $this->currentOrderPayment->setLastTransId($this->transactionId);

            if ($this->currentOrder->getStatus() == 'canceled') {
                $this->notifyCanceledOrder();
            }
        }
    }

    protected function notifyCanceledOrder()
    {
        if (filter_var($this->opHelper->getNotificationEmail(), FILTER_VALIDATE_EMAIL)) {
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
                    $this->opHelper->getNotificationEmail()
                ])->getTransport();
            $transport->sendMessage();
        }
    }

    protected function getDetails()
    {
        return [
            'orderNo'   => $this->orderIncrementalId,
            'stamp'     => $this->paramsStamp,
            'method'    => $this->paramsMethod
        ];
    }

    protected function loadOrder()
    {
        $order = $this->orderInterface->loadByIncrementId($this->orderIncrementalId);
        if (!$order->getId()) {
            $this->processError('Order not found');
        }
        return $order;
    }

    protected function verifyPaymentData($params)
    {
        $verifiedPayment = $this->verifyPayment($params['signature'], $params['checkout-status'], $params);
        if (!$verifiedPayment) {
            $this->currentOrder->addCommentToStatusHistory(__('Order canceled. Failed to complete the payment.'));
            $this->orderRepositoryInterface->save($this->currentOrder);
            $this->orderManagementInterface->cancel($this->currentOrder->getId());
            $this->processError('Failed to complete the payment. Please try again or contact the customer service.');
        }
        return $verifiedPayment;
    }

    protected function loadTransaction()
    {
        return $transaction = $this->transactionRepository->getByTransactionId(
            $this->transactionId,
            $this->currentOrder->getPayment()->getId(),
            $this->orderId
        );
    }

    protected function processExistingTransaction($transaction)
    {
        $details = $transaction->getAdditionalInformation(Transaction::RAW_DETAILS);
        if (is_array($details)) {
            $this->processSuccess();
        }
    }

    protected function processTransaction()
    {
        $transaction = $this->loadTransaction();
        if ($transaction) {
            $this->processExistingTransaction($transaction);
            $this->processError('Payment failed');
        }
        return true;
    }

    protected function addPaymentTransaction(\Magento\Sales\Model\Order $order, $transactionId, array $details = [])
    {
        $transaction = null;
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

    /**
     * @param $errorMessage
     * @throws CheckoutException
     */
    protected function processError($errorMessage)
    {
        throw new CheckoutException(__($errorMessage));
    }

    /**
     * @throws TransactionSuccessException
     */
    protected function processSuccess()
    {
        throw new TransactionSuccessException(__('All fine'));
    }

    protected function verifyPayment($signature, $status, $params)
    {
        $hmac = $this->signature->calculateHmac($params, '', $this->opHelper->getMerchantSecret());

        if ($signature === $hmac && ($status === 'ok' || $status === 'pending')) {
            return $status;
        } else {
            return false;
        }
    }

}
