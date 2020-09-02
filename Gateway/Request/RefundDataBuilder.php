<?php
namespace Op\Checkout\Gateway\Request;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Op\Checkout\Helper\Data;
use Psr\Log\LoggerInterface;

class RefundDataBuilder implements BuilderInterface
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Data
     */
    private $opHelper;

    /**
     * @var LoggerInterface
     */
    private $log;
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * RefundDataBuilder constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param Data $opHelper
     * @param SubjectReader $subjectReader
     * @param LoggerInterface $log
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Data $opHelper,
        SubjectReader $subjectReader,
        LoggerInterface $log
    ) {
        $this->opHelper = $opHelper;
        $this->storeManager = $storeManager;
        $this->log = $log;
        $this->subjectReader = $subjectReader;
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws LocalizedException
     */
    public function build(array $buildSubject)
    {
        $paymentDataObject = $this->subjectReader->readPayment($buildSubject);
        $amount = $this->subjectReader->readAmount($buildSubject);

        $order = $paymentDataObject->getOrder();
        $orderItems = $order->getItems();
        $payment = $paymentDataObject->getPayment();

        $errMsg = null;

        if ($amount <= 0) {
            $errMsg = 'Invalid amount for refund.';
        }

        if (!$payment->getTransactionId()) {
            $errMsg = 'Invalid transaction ID.';
        }

        if (count($this->getTaxRates($orderItems)) !== 1) {
            $errMsg = 'Cannot refund order with multiple tax rates. Please refund offline.';
        }

        if (isset($errMsg)) {
            $this->log->error($errMsg);
            $this->opHelper->processError($errMsg);
        }

        return [
            'transaction_id' => $payment->getTransactionId(),
            'parent_transaction_id' => $payment->getParentTransactionId(),
            'amount' => $amount,
            'order' => $order
        ];
    }

    /**
     * @param $items
     * @return array
     */
    protected function getTaxRates($items)
    {
        $rates = [];
        foreach ($items as $item) {
            if ($item['price'] > 0) {
                $rates[] = round($item['vat'] * 100);
            }
        }

        return array_unique($rates, SORT_NUMERIC);
    }
}
