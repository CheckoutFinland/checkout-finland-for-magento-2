<?php
namespace Op\Checkout\Gateway\Request;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Op\Checkout\Helper\ApiData;
use Op\Checkout\Helper\Data;
use Psr\Log\LoggerInterface;

class RefundDataBuilder implements BuilderInterface
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var ApiData
     */
    private $apiData;
    /**
     * @var UrlInterface
     */
    protected $urlInterface;
    /**
     * @var Json
     */
    private $json;
    /**
     * @var Data
     */
    private $opHelper;

    protected $currentOrder;
    protected $orderItems;
    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * RefundDataBuilder constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param ApiData $apiData
     * @param UrlInterface $urlInterface
     * @param Json $json
     * @param Data $opHelper
     * @param LoggerInterface $log
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ApiData $apiData,
        UrlInterface $urlInterface,
        Json $json,
        Data $opHelper,
        LoggerInterface $log
    ) {
        $this->opHelper = $opHelper;
        $this->storeManager = $storeManager;
        $this->apiData = $apiData;
        $this->json = $json;
        $this->urlInterface = $urlInterface;
        $this->log = $log;
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws LocalizedException
     */
    public function build(array $buildSubject)
    {
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $amount = \Magento\Payment\Gateway\Helper\SubjectReader::readAmount($buildSubject);

        $this->currentOrder = $paymentDataObject->getOrder();
        $this->orderItems = $this->currentOrder->getItems();
        $payment = $paymentDataObject->getPayment();

        if (count($this->getTaxRates()) !== 1) {
            throw new LocalizedException(__('Cannot refund order with multiple tax rates. Please refund offline.'));
        }

        if ($this->postRefundRequest($amount, $payment)) {
            return [
                'trnsaction_id' => $payment->getTransactionId(),
                'amount' => $amount
            ];
        }

        throw new LocalizedException(__('Error refunding payment. Please try again or refund offline.'));
    }

    /**
     * @param $amount
     * @param $payment
     * @return bool
     */
    protected function postRefundRequest($amount, $payment)
    {
        $response = $this->apiData->processApiRequest(
            'refund',
            $this->currentOrder,
            $amount,
            $payment
        );
        $error = $response["error"];

        if (isset($error)) {
            $this->log->error(
                'Error occurred during refund: '
                . $error
                . ', Falling back to to email refund.'
            );
            $emailResponse = $this->apiData->processApiRequest(
                'email_refund',
                $this->currentOrder,
                $amount,
                $payment
            );
            $emailError = $emailResponse["error"];
            if (isset($emailError)) {
                $this->log->error(
                    'Error occurred during email refund: '
                    . $emailError
                );
                return false;
            }
            return true;
        }
        return true;
    }

    /**
     * @return array
     */
    protected function getTaxRates()
    {
        $rates = [];
        foreach ($this->orderItems as $item) {
            if ($item['price'] > 0) {
                $rates[] = round($item['vat'] * 100);
            }
        }

        return array_unique($rates, SORT_NUMERIC);
    }
}
