<?php
namespace Op\Checkout\Helper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Setup\Exception;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\TransactionFactory;

/**
 * Class ActivateOrder
 * @package Op\Checkout\Helper
 */
class ActivateOrder
{
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order
     */
    protected $orderResourceModel;
    /**
     * @var TransactionRepositoryInterface
     */
    private $transactionRepository;
    /**
     * @var InvoiceService
     */
    private $invoiceService;
    /**
     * @var TransactionFactory
     */
    private $transactionFactory;

    /**
     * ActivateOrder constructor.
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param TransactionRepositoryInterface $transactionRepository
     * @param InvoiceService $invoiceService
     * @param TransactionFactory $transactionFactory
     * @param \Magento\Sales\Model\ResourceModel\Order $orderResourceModel
     */
    public function __construct(
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        TransactionRepositoryInterface $transactionRepository,
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        \Magento\Sales\Model\ResourceModel\Order $orderResourceModel
    ) {
        $this->orderResourceModel = $orderResourceModel;
        $this->orderRepository = $orderRepository;
        $this->transactionRepository = $transactionRepository;
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
    }

    /**
     * @param $orderId
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function activateOrder($orderId)
    {
        $order = $this->orderRepository->get($orderId);

        /**
         * Loop through order items and set canceled items as ordered
         */
        foreach ($order->getItems() as $item) {
            $item->setQtyCanceled(0);
        }

        $this->orderResourceModel->save($order);
        $this->processInvoice($order);
    }

    /**
     * @param $orderId
     * @return int
     */
    public function isCanceled($orderId)
    {
        $order = $this->orderRepository->get($orderId);
        $i = 0;

        foreach ($order->getItems() as $item) {
            if ($item->getQtyCanceled() > 0) {
                $i++;
            }
        }

        $transactionId = $this->getCaptureTransaction($order);

        if ($i > 0 && $transactionId) {
            return true;
        } else {
            return false;
        }
    }


    protected function getCaptureTransaction($order)
    {
        $transactionId = false;
        $paymentId =  $order->getPayment()->getId();
        /* For backwards compatibility, e.g. Magento 2.2.9 requires 3 parameters. */
        $transaction = $this->transactionRepository->getByTransactionType('capture', $paymentId, $order->getId());
        if ($transaction) {
            $transactionId = $transaction->getTransactionId();
        }
        return $transactionId;
    }

    protected function processInvoice($order)
    {
        $transactionId = $this->getCaptureTransaction($order);

        if ($order->canInvoice()) {
            try {
                $invoice = $this->invoiceService->prepareInvoice($order);
                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                $invoice->setTransactionId($transactionId);
                $invoice->register();
                $transactionSave = $this->transactionFactory->create();
                $transactionSave->addObject(
                    $invoice
                )->addObject(
                    $order
                )->save();
            } catch (LocalizedException $exception) {
                $invoiceFailException = $exception->getMessage();
            }

            if (isset($invoiceFailException)) {
                $this->processError($invoiceFailException);
            }
        }
    }

}
