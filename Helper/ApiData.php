<?php

namespace Op\Checkout\Helper;

use GuzzleHttp\Exception\RequestException;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Tax\Helper\Data as TaxHelper;
use Op\Checkout\Model\Adapter\Adapter;
use Op\Checkout\Gateway\Config\Config as GatewayConfig;
use Op\Checkout\Helper\Data as CheckoutHelper;
use Op\Checkout\Logger\Request\Logger as RequestLogger;
use Op\Checkout\Logger\Response\Logger as ResponseLogger;
use OpMerchantServices\SDK\Exception\HmacException;
use OpMerchantServices\SDK\Exception\ValidationException;
use OpMerchantServices\SDK\Request\PaymentRequest;
use OpMerchantServices\SDK\Model\Customer;
use OpMerchantServices\SDK\Model\Address;
use OpMerchantServices\SDK\Model\Item;
use OpMerchantServices\SDK\Model\CallbackUrl;
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
     * @var Signature
     */
    private $signature;

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
     * ApiData constructor.
     * @param LoggerInterface $log
     * @param Signature $signature
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
     * @param GatewayConfig $gatewayConfig
     * @param Adapter $opAdapter
     * @param PaymentRequest $paymentRequest
     */
    public function __construct(
        LoggerInterface $log,
        Signature $signature,
        UrlInterface $urlBuilder,
        RequestInterface $request,
        Json $json,
        CountryInformationAcquirerInterface $countryInformationAcquirer,
        TaxHelper $taxHelper,
        Order $order,
        RequestLogger $requestLogger,
        ResponseLogger $responseLogger,
        CheckoutHelper $helper,
        Config $resourceConfig,
        GatewayConfig $gatewayConfig,
        Adapter $opAdapter,
        PaymentRequest $paymentRequest
    ) {
        $this->log = $log;
        $this->signature = $signature;
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
    }

    /**
     * Process payment creation
     *
     * @param Order $order
     * @return PaymentRequest|string
     */
    public function processPayment($order)
    {
        // Initialize checkout log variables
        $responseLogEnabled = $this->helper->getResponseLog();
        $requestLogEnabled = $this->helper->getRequestLog();

        $response = null;

        try {
            $opPayment = $this->paymentRequest;
            $this->setPaymentRequestData($opPayment, $order);

            $opClient = $this->opAdapter->initOpMerchantClient();
            if ($requestLogEnabled) {
                $this->requestLogger
                    ->debug(
                        'Creating Request to OP Payment Service API. Order Id: '
                        . $order->getId()
                    );
            }
            $response["data"] = $opClient->createPayment($opPayment);
            $response["error"] = null;

            if ($responseLogEnabled) {
                $this->responseLogger
                    ->debug(
                        'Successful response from OP Payment Service API. Order Id: '
                        . $order->getId()
                    );
            }
        } catch (RequestException $e){
            if ($e->hasResponse()) {
                if ($requestLogEnabled) {
                    $this->requestLogger->debug('Connection error to OP Payment Service API: '
                        . $e->getMessage()
                        . 'Error Code: '
                        . $e->getCode());
                }
                $response["error"] = $e->getMessage();
                return $response;
            }
        } catch (\Exception $e) {
            if ($requestLogEnabled) {
                $this->requestLogger->debug('A problem occurred during payment creation: '
                    . $e->getMessage());
            }
            $response["error"] = $e->getMessage();
            return $response;
        }

        if ($responseLogEnabled) {
            // Gather and log relevant data
            $loggedData = json_encode(
                [
                    'transactionId' => $response["data"]->getTransactionId(),
                    'href' => $response["data"]->getHref()
                ],
                JSON_UNESCAPED_SLASHES
            );
            $this->responseLogger->debug('Response data for Order Id ' . $order->getId() . ': ' . $loggedData);
        }

        return $response;
    }

    /**
     * Set data for Payment request
     *
     * @param PaymentRequest $opPayment
     * @param Order $order
     * @return mixed
     * @throws \Exception
     */
    protected function setPaymentRequestData($opPayment, $order)
    {
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();

        $opPayment->setStamp(hash('sha256', time() . $order->getIncrementId()));

        $reference = $this->gatewayConfig->getGenerateReferenceForOrder()
            ? $this->helper->calculateOrderReferenceNumber($order->getIncrementId())
            : $order->getIncrementId();
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

        $items = $this->getOrderItemLines($order);

        $opPayment->setItems($items);

        $opPayment->setRedirectUrls($this->createRedirectUrl());

        $opPayment->setCallbackUrls($this->createCallbackUrl());

        return $opPayment;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderAddressInterface $billingAddress
     * @return Customer
     */
    protected function createCustomer($billingAddress)
    {
        $customer = new Customer;

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
            $address->getCountryId())
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
     * Create order items
     *
     * @param Order $order
     * @return array
     * @throws \Exception
     */
    protected function getOrderItemLines($order)
    {
        $orderItems = $this->itemArgs($order);
        $orderTotal = round($order->getGrandTotal() * 100);

        $items = array_map(
            function ($item) use ($order) {
                return $this->createOrderItems($item);
            }, $orderItems
        );

        $itemSum = 0;
        $itemQty = 0;

        /** @var Item $orderItem */
        foreach ($items as $orderItem)
        {
            $itemSum += floatval($orderItem->getUnitPrice() * $orderItem->getUnits());
            $itemQty += $orderItem->getUnits();
        }

        // Handle rounding issues by creating rounding row for order
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
     * @param string $urlParam
     * @return string
     */
    protected function getCallbackUrl($urlParam)
    {
        $successUrl = $this->urlBuilder->getUrl('opcheckout/' . $urlParam, [
            '_secure' => $this->request->isSecure()
        ]);

        return $successUrl;
    }

    /**
     * @param $order
     * @return array|null
     */
    protected function itemArgs($order)
    {
        $items = [];

        # Add line items
        /** @var $item OrderItem */
        foreach ($order->getAllItems() as $key => $item) {
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
                    'price' => floatval($item->getPriceInclTax()),
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
        if (abs($order->getDiscountAmount()) > 0) {
            $discountData = $this->_getDiscountData($order);
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
     * @param \Magento\Sales\Model\Order $order
     * @return mixed
     */
    private function _getDiscountData(\Magento\Sales\Model\Order $order)
    {
        $discountIncl = 0;
        $discountExcl = 0;

        // Get product discount amounts
        foreach ($order->getAllItems() as $item) {
            if (!$this->taxHelper->priceIncludesTax()) {
                $discountExcl += $item->getDiscountAmount();
                $discountIncl += $item->getDiscountAmount() * (($item->getTaxPercent() / 100) + 1);
            } else {
                $discountExcl += $item->getDiscountAmount() / (($item->getTaxPercent() / 100) + 1);
                $discountIncl += $item->getDiscountAmount();
            }
        }

        // Get shipping tax rate
        if ((float)$order->getShippingInclTax() && (float)$order->getShippingAmount()) {
            $shippingTaxRate = $order->getShippingInclTax() / $order->getShippingAmount();
        } else {
            $shippingTaxRate = 1;
        }

        // Add / exclude shipping tax
        $shippingDiscount = (float)$order->getShippingDiscountAmount();
        if (!$this->taxHelper->priceIncludesTax()) {
            $discountIncl += $shippingDiscount * $shippingTaxRate;
            $discountExcl += $shippingDiscount;
        } else {
            $discountIncl += $shippingDiscount;
            $discountExcl += $shippingDiscount / $shippingTaxRate;
        }

        $return = new \Magento\Framework\DataObject;
        return $return->setDiscountInclTax($discountIncl)->setDiscountExclTax($discountExcl);
    }
}
