<?php

namespace Op\Checkout\Helper;

use GuzzleHttp\Exception\RequestException;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Store\Model\StoreManagerInterface;
use Op\Checkout\Exceptions\CheckoutException;
use Magento\Tax\Helper\Data as TaxHelper;
use Op\Checkout\Gateway\Config\Config as GatewayConfig;
use Op\Checkout\Helper\Data as CheckoutHelper;
use Op\Checkout\Logger\Request\RequestLogger as RequestLogger;
use Op\Checkout\Logger\Response\ResponseLogger as ResponseLogger;
use Op\Checkout\Model\Adapter\Adapter;
use OpMerchantServices\SDK\Model\Address;
use OpMerchantServices\SDK\Model\CallbackUrl;
use OpMerchantServices\SDK\Model\Customer;
use OpMerchantServices\SDK\Model\Item;
use OpMerchantServices\SDK\Request\EmailRefundRequest;
use OpMerchantServices\SDK\Request\PaymentRequest;
use OpMerchantServices\SDK\Request\RefundRequest;
use Psr\Log\LoggerInterface;

/**
 * Class ApiData
 */
class ApiData
{
    /**
     * @var Order
     */
    private $order;

    /**
     * @var RequestLogger
     */
    private $requestLogger;

    /**
     * @var ResponseLogger
     */
    private $responseLogger;

    /**
     * @var CheckoutHelper
     */
    private $helper;

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var CountryInformationAcquirerInterface
     */
    private $countryInfo;

    /**
     * @var TaxHelper
     */
    private $taxHelper;

    /**
     * @var Config
     */
    private $resourceConfig;

    /**
     * @var GatewayConfig
     */
    private $gatewayConfig;

    /**
     * @var Adapter
     */
    private $opAdapter;
    /**
     * @var PaymentRequest
     */
    private $paymentRequest;
    /**
     * @var RefundRequest
     */
    private $refundRequest;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var EmailRefundRequest
     */
    private $emailRefundRequest;

    /**
     * Temporary fix for discount handling in Collector payments.
     * @var $collectorMethods
     */
    private $collectorMethods = ['collectorb2c','collectorb2b'];

    /**
     * ApiData constructor.
     * @param LoggerInterface $log
     * @param UrlInterface $urlBuilder
     * @param RequestInterface $request
     * @param Json $json
     * @param CountryInformationAcquirerInterface $countryInformationAcquirer
     * @param TaxHelper $taxHelper
     * @param Order $order
     * @param RequestLogger $requestLogger
     * @param ResponseLogger $responseLogger
     * @param CheckoutHelper $helper
     * @param Config $resourceConfig
     * @param StoreManagerInterface $storeManager
     * @param GatewayConfig $gatewayConfig
     * @param Adapter $opAdapter
     * @param PaymentRequest $paymentRequest
     * @param RefundRequest $refundRequest
     * @param EmailRefundRequest $emailRefundRequest
     */
    public function __construct(
        LoggerInterface $log,
        UrlInterface $urlBuilder,
        RequestInterface $request,
        Json $json,
        CountryInformationAcquirerInterface $countryInformationAcquirer,
        Order $order,
        RequestLogger $requestLogger,
        ResponseLogger $responseLogger,
        TaxHelper $taxHelper,
        CheckoutHelper $helper,
        Config $resourceConfig,
        StoreManagerInterface $storeManager,
        GatewayConfig $gatewayConfig,
        Adapter $opAdapter,
        PaymentRequest $paymentRequest,
        RefundRequest $refundRequest,
        EmailRefundRequest $emailRefundRequest
    ) {
        $this->log = $log;
        $this->urlBuilder = $urlBuilder;
        $this->request = $request;
        $this->json = $json;
        $this->countryInfo = $countryInformationAcquirer;
        $this->taxHelper = $taxHelper;
        $this->order = $order;
        $this->requestLogger = $requestLogger;
        $this->responseLogger = $responseLogger;
        $this->helper = $helper;
        $this->resourceConfig = $resourceConfig;
        $this->gatewayConfig = $gatewayConfig;
        $this->opAdapter = $opAdapter;
        $this->paymentRequest = $paymentRequest;
        $this->refundRequest = $refundRequest;
        $this->storeManager = $storeManager;
        $this->emailRefundRequest = $emailRefundRequest;
    }

    /**
     * Process Api request
     *
     * @param string $requestType
     * @param Order|null $order
     * @param $amount
     * @param $transactionId
     * @param $methodId
     * @return mixed
     */
    public function processApiRequest(
        $requestType,
        $order = null,
        $amount = null,
        $transactionId = null,
        $methodId = null
    ) {
        $response["data"] = null;
        $response["error"] = null;

        try {
            $opClient = $this->opAdapter->initOpMerchantClient();

            $this->helper->logCheckoutData(
                'request',
                'info',
                'Creating '
                . $requestType
                . ' request to OP Payment Service API. '
                . $orderLog = isset($order) ? 'Order Id: ' . $order->getId() : ''
            );
            // Handle payment requests
            if ($requestType === 'payment') {
                $opPayment = $this->paymentRequest;
                $this->setPaymentRequestData($opPayment, $order, $methodId);

                $response["data"] = $opClient->createPayment($opPayment);

                $loggedData = json_encode(
                    [
                        'transactionId' => $response["data"]->getTransactionId(),
                        'href' => $response["data"]->getHref()
                    ],
                    JSON_UNESCAPED_SLASHES
                );
                $this->helper->logCheckoutData(
                    'response',
                    'success',
                    'Successful response for order Id '
                    . $order->getId()
                    . '. Order data: '
                    . $loggedData
                );
                // Handle refund requests
            } elseif ($requestType === 'refund') {
                $opRefund = $this->refundRequest;
                $this->setRefundRequestData($opRefund, $amount);

                $response["data"] = $opClient->refund($opRefund, $transactionId);

                $this->helper->logCheckoutData(
                    'response',
                    'success',
                    'Successful response for refund. Transaction Id: '
                    . $response["data"]->getTransactionId()
                );
                // Handle email refund requests
            } elseif ($requestType === 'email_refund') {
                $opEmailRefund = $this->emailRefundRequest;
                $this->setEmailRefundRequestData($opEmailRefund, $amount, $order);

                $response["data"] = $opClient->emailRefund($opEmailRefund, $transactionId);

                $this->helper->logCheckoutData(
                    'response',
                    'success',
                    'Successful response for email refund. Transaction Id: '
                    . $response["data"]->getTransactionId()
                );
            } elseif ($requestType === 'payment_providers') {
                $response["data"] = $opClient->getGroupedPaymentProviders(
                    $amount,
                    $this->helper->getStoreLocaleForPaymentProvider()
                );
                $this->helper->logCheckoutData(
                    'response',
                    'success',
                    'Successful response for payment providers.'
                );
            }
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $this->helper->logCheckoutData(
                    'request',
                    'error',
                    'Connection error to OP Payment Service API: '
                    . $e->getMessage()
                    . 'Error Code: '
                    . $e->getCode()
                );
                $response["error"] = $e->getMessage();
                return $response;
            }
        } catch (\Exception $e) {
            $this->helper->logCheckoutData(
                'response',
                'error',
                'A problem occurred: '
                . $e->getMessage()
            );
            $response["error"] = $e->getMessage();
            return $response;
        }

        return $response;
    }

    /**
     * @param PaymentRequest $opPayment
     * @param Order $order
     * @param string $methodId
     * @return mixed
     * @throws \Exception
     */
    protected function setPaymentRequestData($opPayment, $order, $methodId)
    {
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();

        $opPayment->setStamp(hash('sha256', time() . $order->getIncrementId()));

        $reference = $this->helper->getReference($order);

        $opPayment->setReference($reference);

        $opPayment->setCurrency($order->getOrderCurrencyCode())->setAmount(round($order->getGrandTotal() * 100));

        $customer = $this->createCustomer($billingAddress);
        $opPayment->setCustomer($customer);

        $invoicingAddress = $this->createAddress($order, $billingAddress);
        $opPayment->setInvoicingAddress($invoicingAddress);

        if (!is_null($shippingAddress)) {
            $deliveryAddress = $this->createAddress($order, $shippingAddress);
            $opPayment->setDeliveryAddress($deliveryAddress);
        }

        $opPayment->setLanguage($this->helper->getStoreLocaleForPaymentProvider());

        $items = $this->getOrderItemLines($order, $methodId);

        $opPayment->setItems($items);

        $opPayment->setRedirectUrls($this->createRedirectUrl());

        $opPayment->setCallbackUrls($this->createCallbackUrl());

        // Log payment data
        $this->helper->logCheckoutData('request', 'info', $opPayment);

        return $opPayment;
    }

    /**
     * @param RefundRequest $opRefund
     * @param $amount
     * @throws CheckoutException
     */
    protected function setRefundRequestData($opRefund, $amount)
    {
        if ($amount <= 0) {
            $this->helper->processError('Refund amount must be above 0');
        }

        $opRefund->setAmount(round($amount * 100));

        $callback = $this->createRefundCallback();
        $opRefund->setCallbackUrls($callback);
    }

    /**
     * @param EmailRefundRequest $opEmailRefund
     * @param $amount
     * @param $order
     */
    protected function setEmailRefundRequestData($opEmailRefund, $amount, $order)
    {
        $opEmailRefund->setEmail($order->getBillingAddress()->getEmail());

        $opEmailRefund->setAmount(round($amount * 100));

        $callback = $this->createRefundCallback();
        $opEmailRefund->setCallbackUrls($callback);
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderAddressInterface $billingAddress
     * @return Customer
     */
    protected function createCustomer($billingAddress)
    {
        $customer = new Customer();

        $customer->setEmail($billingAddress->getEmail())
            ->setFirstName($billingAddress->getFirstName())
            ->setLastName($billingAddress->getLastname())
            ->setPhone($billingAddress->getTelephone());

        return $customer;
    }

    /**
     * @param Order $order
     * @param $address
     * @throws NoSuchEntityException
     * @return Address
     */
    protected function createAddress($order, $address)
    {
        $opAddress = new Address();

        $country = $this->countryInfo->getCountryInfo(
            $address->getCountryId()
        )
            ->getTwoLetterAbbreviation();
        $streetAddressRows = $address->getStreet();
        $streetAddress = $streetAddressRows[0];
        if (mb_strlen($streetAddress, 'utf-8') > 50) {
            $streetAddress = mb_substr($streetAddress, 0, 50, 'utf-8');
        }

        $opAddress->setStreetAddress($streetAddress)
            ->setPostalCode($address->getPostcode())
            ->setCity($address->getCity())
            ->setCountry($country);

        if (!empty($address->getRegion())) {
            $opAddress->setCounty($address->getRegion());
        }

        return $opAddress;
    }

    /**
     * @param Order $order
     * @param string $methodId
     * @return array
     * @throws \Exception
     */
    protected function getOrderItemLines($order, $methodId)
    {
        $orderItems = $this->itemArgs($order, $methodId);
        $orderTotal = round($order->getGrandTotal() * 100);

        $items = array_map(
            function ($item) use ($order) {
                return $this->createOrderItems($item);
            },
            $orderItems
        );

        $itemSum = 0;
        $itemQty = 0;

        /** @var Item $orderItem */
        foreach ($items as $orderItem) {
            $itemSum += floatval($orderItem->getUnitPrice() * $orderItem->getUnits());
            $itemQty += $orderItem->getUnits();
        }

        if ($itemSum != $orderTotal) {
            $diffValue = abs($itemSum - $orderTotal);

            if ($diffValue > $itemQty) {
                throw new \Exception(__('Difference in rounding the prices is too big'));
            }

            $roundingItem = new Item();
            $roundingItem->setDescription(__('Rounding', 'op-payment-service-magento-2'));
            $roundingItem->setDeliveryDate(date('Y-m-d'));
            $roundingItem->setVatPercentage(0);
            $roundingItem->setUnits(($orderTotal - $itemSum > 0) ? 1 : -1);
            $roundingItem->setUnitPrice($diffValue);
            $roundingItem->setProductCode('rounding-row');

            $items[] = $roundingItem;
        }
        return $items;
    }

    /**
     * @param OrderItem $item
     * @return Item
     */
    protected function createOrderItems($item)
    {
        $opItem = new Item();

        $opItem->setUnitPrice(round($item['price'] * 100))
            ->setUnits($item['amount'])
            ->setVatPercentage($item['vat'])
            ->setProductCode($item['code'])
            ->setDeliveryDate(date('Y-m-d'))
            ->setDescription($item['title']);

        return $opItem;
    }

    /**
     * @return CallbackUrl
     */
    protected function createRefundCallback()
    {
        $callback = new CallbackUrl();

        try {
            $storeUrl = $this->storeManager
                ->getStore()
                ->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);
        } catch (NoSuchEntityException $e) {
            $storeUrl = $this->urlBuilder->getBaseUrl();
        }

        $callback->setSuccess($storeUrl);
        $callback->setCancel($storeUrl);

        return $callback;
    }

    /**
     * @return CallbackUrl
     */
    protected function createRedirectUrl()
    {
        $callback = new CallbackUrl();

        $callback->setSuccess($this->getCallbackUrl('receipt'));
        $callback->setCancel($this->getCallbackUrl('receipt'));

        return $callback;
    }

    /**
     * @return CallbackUrl
     */
    protected function createCallbackUrl()
    {
        $callback = new CallbackUrl();

        $callback->setSuccess($this->getCallbackUrl('callback'));
        $callback->setCancel($this->getCallbackUrl('callback'));

        return $callback;
    }

    /**
     * @param $param
     * @return string
     */
    protected function getCallbackUrl($param)
    {
        $successUrl = $this->urlBuilder->getUrl('opcheckout/' . $param, [
            '_secure' => $this->request->isSecure()
        ]);

        return $successUrl;
    }

    /**
     * @param Order $order
     * @param string $methodId
     * @return array|null
     */
    protected function itemArgs($order, $methodId)
    {
        $items = [];

        # Add line items
        /** @var $item OrderItem */
        foreach ($order->getAllItems() as $key => $item) {

            //Temporary fix for Collector payment methods discount calculation
            $discountIncl = 0;
            if (!$this->taxHelper->priceIncludesTax()) {
                $discountIncl += $item->getDiscountAmount() * (($item->getTaxPercent() / 100) + 1);
            } else {
                $discountIncl += $item->getDiscountAmount();
            }

            // When in grouped or bundle product price is dynamic (product_calculations = 0)
            // then also the child products has prices so we set
            if ($item->getChildrenItems() && !$item->getProductOptions()['product_calculations']) {
                $items[] = [
                    'title' => $item->getName(),
                    'code' => $item->getSku(),
                    'amount' => floatval($item->getQtyOrdered()),
                    'price' => 0,
                    'vat' => 0
                ];
            } else {
                $items[] = [
                    'title' => $item->getName(),
                    'code' => $item->getSku(),
                    'amount' => floatval($item->getQtyOrdered()),
                    'price' => in_array($methodId, $this->collectorMethods) ? floatval($item->getPriceInclTax()) - ($discountIncl / $item->getQtyOrdered()) : floatval($item->getPriceInclTax()),
                    'vat' => round(floatval($item->getTaxPercent()))
                ];
            }
        }

        // Add shipping
        if (!$order->getIsVirtual()) {
            $shippingExclTax = $order->getShippingAmount();
            $shippingInclTax = $order->getShippingInclTax();
            $shippingTaxPct = 0;
            if ($shippingExclTax > 0) {
                $shippingTaxPct = ($shippingInclTax - $shippingExclTax) / $shippingExclTax * 100;
            }

            if ($order->getShippingDescription()) {
                $shippingLabel = $order->getShippingDescription();
            } else {
                $shippingLabel = __('Shipping');
            }

            $items[] = [
                'title' => $shippingLabel,
                'code' => 'shipping-row',
                'amount' => 1,
                'price' => floatval($order->getShippingInclTax()),
                'vat' => round(floatval($shippingTaxPct))
            ];
        }

        // Add discount row
        if (abs($order->getDiscountAmount()) > 0 && !in_array($methodId, $this->collectorMethods)) {
            $discountData = $this->helper->getDiscountData($order);
            $discountInclTax = $discountData->getDiscountInclTax();
            $discountExclTax = $discountData->getDiscountExclTax();
            $discountTaxPct = 0;
            if ($discountExclTax > 0) {
                $discountTaxPct = ($discountInclTax - $discountExclTax) / $discountExclTax * 100;
            }

            if ($order->getDiscountDescription()) {
                $discountLabel = $order->getDiscountDescription();
            } else {
                $discountLabel = __('Discount');
            }

            $items[] = [
                'title' => (string)$discountLabel,
                'code' => 'discount-row',
                'amount' => -1,
                'price' => floatval($discountData->getDiscountInclTax()),
                'vat' => round(floatval($discountTaxPct))
            ];
        }

        return $items;
    }

    /**
     * @param $params
     * @param $signature
     * @return bool
     */
    public function validateHmac($params, $signature)
    {
        try {
            $this->helper->logCheckoutData(
                'request',
                'info',
                'Validating Hmac for transaction: '
                . $params["checkout-transaction-id"]
            );
            $opClient = $this->opAdapter->initOpMerchantClient();

            $opClient->validateHmac($params, '', $signature);
        } catch (\Exception $e) {
            $this->helper->logCheckoutData(
                'request',
                'error',
                'Hmac validation failed for transaction: '
                . $params["checkout-transaction-id"]
            );
            return false;
        }
        $this->helper->logCheckoutData(
            'response',
            'info',
            'Hmac validation successful for transaction: '
            . $params["checkout-transaction-id"]
        );
        return true;
    }
}
