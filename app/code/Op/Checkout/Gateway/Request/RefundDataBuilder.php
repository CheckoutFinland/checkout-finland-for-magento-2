<?php
namespace Op\Checkout\Gateway\Request;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Braintree\Gateway\SubjectReader;
use Magento\Store\Model\StoreManagerInterface;
use Op\Checkout\Helper\ApiData;
use Op\Checkout\Helper\Data;
use \Magento\Framework\UrlInterface;
use Magento\Framework\Serialize\Serializer\Json;

class RefundDataBuilder implements BuilderInterface
{
    private $subjectReader;

    /**
     * @var Data
     */
    private $opHelper;
    protected $currentOrder;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var ApiData
     */
    private $apiData;
    /**
     * @var Json
     */
    private $json;

    protected $itemArgs;
    protected $orderItems;
    protected $urlInterface;

    /**
     * RefundDataBuilder constructor.
     * @param SubjectReader $subjectReader
     * @param StoreManagerInterface $storeManager
     * @param ApiData $apiData
     * @param UrlInterface $urlInterface
     * @param Json $json
     * @param Data $opHelper
     */
    public function __construct(
        SubjectReader $subjectReader,
        StoreManagerInterface $storeManager,
        ApiData $apiData,
        UrlInterface $urlInterface,
        Json $json,
        Data $opHelper
    ) {
        $this->opHelper = $opHelper;
        $this->subjectReader = $subjectReader;
        $this->storeManager = $storeManager;
        $this->apiData = $apiData;
        $this->json = $json;
        $this->urlInterface = $urlInterface;
    }

    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $amount = \Magento\Payment\Gateway\Helper\SubjectReader::readAmount($buildSubject);

        $this->currentOrder = $paymentDataObject->getOrder();
        $this->orderItems = $this->currentOrder->getItems();
        $payment = $paymentDataObject->getPayment();

        // TODO: move checks to validator
        if ($amount <= 0) {
            throw new LocalizedException(__('Invalid amount for refund.'));
        }

        if (!$payment->getTransactionId()) {
            throw new LocalizedException(__('Invalid transaction ID.'));
        }

        if (count($this->getTaxRates()) !== 1) {
            throw new LocalizedException(__('Cannot refund order with multiple tax rates. Please refund offline.'));
        }

        $body = $this->buildRefundRequest($amount);

        if ($this->postRefundRequest($payment, $body)) {
            return [
                'trnsaction_id' => $payment->getTransactionId(),
                'amount' => $amount
            ];
        }
        throw new LocalizedException(__('Error refunding payment. Please try again or refund offline.'));
    }

    /**
     * @param $payment
     * @param $body
     * @return bool
     */
    protected function postRefundRequest($payment, $body)
    {
        $transactionId = $payment->getParentTransactionId();

        $uri = '/payments/' . $transactionId . '/refund';

        //$bodyJson = json_encode($body);
        $bodyJson = $this->json->serialize($body);

        $response = $this->apiData->getResponse(
            $uri,
            '',
            $this->opHelper->getMerchantId(),
            $this->opHelper->getMerchantSecret(),
            'post',
            $transactionId,
            $bodyJson
        );

        $status = $response['status'];
        $data = $response['data'];

        if ($status === 201) {
            return true;
        } elseif (($status === 422 || $status === 400) && $this->postRefundRequestEmail($payment, $body)) {
            // TODO: 422 replaced with 400 ? should we add 4xx check here ?
            return true;
        } else {
            //TODO: DEAL WITH ERROR ! DON'T JUST LOG IT !
            $this->log->critical($data->status . ': ' . $data->message);
            return false;
        }
    }

    /**
     * @param $payment
     * @param $body
     * @return bool
     */
    protected function postRefundRequestEmail($payment, $body)
    {

        $transactionId = $payment->getParentTransactionId();

        $uri = '/payments/' . $transactionId . '/refund/email';
        $body['email'] = $this->currentOrder->getBillingAddress()->getEmail();
        $body = $this->json->serialize($body);

        $response = $this->apiData->getResponse(
            $uri,
            '',
            $this->opHelper->getMerchantId(),
            $this->opHelper->getMerchantSecret(),
            'post',
            $transactionId,
            $body
        );
        $status = $response['status'];
        $data = $response['data'];

        if ($status === 201) {
            return true;
        } else {
            //TODO: FIX LOGGER
            $this->log->critical($data->status . ': ' . $data->message);
            return false;
        }
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

    /**
     * @param $amount
     * @return array
     */
    protected function buildRefundRequest($amount)
    {
        try {
            $storeUrl = $this->storeManager
                ->getStore()
                ->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);
        } catch (NoSuchEntityException $e) {
            $storeUrl = $this->urlInterface->getBaseUrl();
        }

        $body = [
            'amount' => $amount * 100,
            'callbackUrls' => [
                'success' => $storeUrl,
                'cancel' => $storeUrl,
            ],
        ];

        return $body;
    }
}
