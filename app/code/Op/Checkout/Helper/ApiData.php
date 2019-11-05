<?php
namespace Op\Checkout\Helper;

use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Psr\Log\LoggerInterface;
use Op\Checkout\Helper\Data as DataHelper;

class ApiData
{
    /**
     * @var string API_ENDPOINT
     */
    const API_ENDPOINT = 'https://api.checkout.fi';

    /**
     * @var string MODULE_CODE
     */
    const MODULE_CODE = 'Op_Checkout';

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
     * @var ModuleListInterface
     */
    private $moduleList;

    /**
     * @var DataHelper
     */
    private $dataHelper;

    /**
     * ApiData constructor.
     * @param LoggerInterface $log
     * @param Signature $signature
     * @param UrlInterface $urlBuilder
     * @param RequestInterface $request
     * @param Json $json
     * @param CountryInformationAcquirerInterface $countryInformationAcquirer
     * @param TaxHelper $taxHelper
     * @param Config $resourceConfig
     * @param ModuleListInterface $moduleList
     * @param DataHelper $dataHelper
     */
    public function __construct(
        LoggerInterface $log,
        Signature $signature,
        UrlInterface $urlBuilder,
        RequestInterface $request,
        Json $json,
        CountryInformationAcquirerInterface $countryInformationAcquirer,
        TaxHelper $taxHelper,
        Config $resourceConfig,
        ModuleListInterface $moduleList,
        DataHelper $dataHelper
    ) {
        $this->log = $log;
        $this->signature = $signature;
        $this->urlBuilder = $urlBuilder;
        $this->request = $request;
        $this->json = $json;
        $this->countryInfo = $countryInformationAcquirer;
        $this->taxHelper = $taxHelper;
        $this->resourceConfig = $resourceConfig;
        $this->moduleList = $moduleList;
        $this->dataHelper = $dataHelper;
    }

    /**
     * @param $uri
     * @param $order
     * @param $merchantId
     * @param $merchantSecret
     * @param $method
     * @param null $refundId
     * @param null $refundBody
     * @return array|null
     */
    public function getResponse(
        $uri,
        $order,
        $merchantId,
        $merchantSecret,
        $method,
        $refundId = null,
        $refundBody = null
    ) {
        $method = strtoupper($method);
        $headers = $this->getResponseHeaders($merchantId, $method);
        $body = '';

        if ($method == 'POST' && !empty($order)) {
            $body = $this->getResponseBody($order);
        }

        if ($refundId) {
            $headers['checkout-transaction-id'] = $refundId;
            $body = $refundBody;
        }

        $headers['signature'] = $this->signature->calculateHmac($headers, $body, $merchantSecret);

        $client = new \GuzzleHttp\Client(['headers' => $headers]);
        $response = null;


        try {
            if ($method == 'POST') {
                $response = $client->post(self::API_ENDPOINT . $uri, ['body' => $body]);
            } else {
                $response = $client->get(self::API_ENDPOINT . $uri, ['body' => '']);
            }

            // FIXME: response log
            //$this->log->debug('test');
            //$this->log->debug(json_encode($response->getBody()->getContents()));
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // TODO: should we check here for the error code ?
            if ($e->hasResponse()) {
                $this->log->critical('Connection error to Checkout API: ' . $e->getMessage());
                $response["data"] = $e->getMessage();
                $response["status"] = $e->getCode();
            }
            return $response;
        }

        $responseBody = $response->getBody()->getContents();

        $responseHeaders = array_column(array_map(function ($key, $value) {
            return [$key, $value[0]];
        }, array_keys($response->getHeaders()), array_values($response->getHeaders())), 1, 0);

        $responseHmac = $this->signature->calculateHmac($responseHeaders, $responseBody, $merchantSecret);
        $responseSignature = $response->getHeader('signature')[0];

        if ($responseHmac == $responseSignature) {
            $data = array(
                'status' => $response->getStatusCode(),
                'data' => json_decode($responseBody)
            );

            return $data;
        }
    }

    /**
     * @param $account
     * @param $method
     * @return array
     */
    protected function getResponseHeaders($account, $method)
    {
        return [
            'cof-plugin-version' => 'op-payment-service-for-magento-2-'.$this->getExtensionVersion(),
            'checkout-account' => $account,
            'checkout-algorithm' => 'sha256',
            'checkout-method' => strtoupper($method),
            'checkout-nonce' => uniqid('', true),
            'checkout-timestamp' => date('Y-m-d\TH:i:s.000\Z', time()),
            'content-type' => 'application/json; charset=utf-8',
        ];
    }

    /**
     * @param $order
     * @return string
     */
    protected function getResponseBody($order)
    {
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();

        // using json_encode for option.
        $body = json_encode(
            [
                'stamp' => hash('sha256', time() . $order->getIncrementId()),
                'reference' => $order->getIncrementId(),
                'amount' => $order->getGrandTotal() * 100,
                'currency' => $order->getOrderCurrencyCode(),
                'language' => 'FI',
                'items' => $this->getOrderItems($order),
                'customer' => [
                    'firstName' => $billingAddress->getFirstName(),
                    'lastName' => $billingAddress->getLastName(),
                    'phone' => $billingAddress->getTelephone(),
                    'email' => $billingAddress->getEmail(),
                ],
                'invoicingAddress' => $this->formatAddress($billingAddress),
                'deliveryAddress' => $this->formatAddress($shippingAddress),
                'redirectUrls' => [
                    'success' => $this->getReceiptUrl(),
                    'cancel' => $this->getReceiptUrl(),
                ],
                'callbackUrls' => [
                    'success' => $this->getReceiptUrl(),
                    'cancel' => $this->getReceiptUrl(),
                ],
            ],
            JSON_UNESCAPED_SLASHES
        );

        if ($this->dataHelper->getDebugLoggerStatus()) {
            $this->log->debug($body);
        }


        return $body;
    }

    /**
     * @param $address
     * @return array
     */
    protected function formatAddress($address)
    {
        $country = $this->countryInfo->getCountryInfo($address->getCountryId())->getFullNameLocale();
        $streetAddressRows = $address->getStreet();
        $streetAddress = $streetAddressRows[0];
        if (mb_strlen($streetAddress, 'utf-8') > 50) {
            $streetAddress = mb_substr($streetAddress, 0, 50, 'utf-8');
        }

        $result = [
            'streetAddress' => $streetAddress,
            'postalCode' => $address->getPostcode(),
            'city' => $address->getCity(),
            'country' => $country
        ];

        if (!empty($address->getRegion())) {
            $result["county"] = $address->getRegion();
        }

        return $result;
    }

    protected function getReceiptUrl()
    {
        $receiptUrl = $this->urlBuilder->getUrl('opcheckout/receipt', [
            '_secure' => $this->request->isSecure()
        ]);

        return $receiptUrl;
    }


    /**
     * @param $order
     * @return array
     */
    protected function getOrderItems($order)
    {
        $items = [];

        foreach ($this->itemArgs($order) as $i => $item) {
            $items[] = array(
                'unitPrice' => $item['price'] * 100,
                'units' => $item['amount'],
                'vatPercentage' => $item['vat'],
                'description' => $item['title'],
                'productCode' => $item['code'],
                'deliveryDate' => date('Y-m-d'),
            );
        }

        return $items;
    }

    /**
     * @param $order
     * @return array
     */
    protected function itemArgs($order)
    {
        $items = array();

        # Add line items
        /** @var $item Item */
        foreach ($order->getAllItems() as $key => $item) {
            // When in grouped or bundle product price is dynamic (product_calculations = 0)
            // then also the child products has prices so we set
            if ($item->getChildrenItems() && !$item->getProductOptions()['product_calculations']) {
                $items[] = array(
                    'title' => $item->getName(),
                    'code' => $item->getSku(),
                    'amount' => floatval($item->getQtyOrdered()),
                    'price' => 0,
                    'vat' => 0,
                    'discount' => 0,
                    'type' => 1,
                );
            } else {
                $items[] = array(
                    'title' => $item->getName(),
                    'code' => $item->getSku(),
                    'amount' => floatval($item->getQtyOrdered()),
                    'price' => floatval($item->getPriceInclTax()),
                    'vat' => round(floatval($item->getTaxPercent())),
                    'discount' => 0,
                    'type' => 1,
                );
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

            $items[] = array(
                'title' => $shippingLabel,
                'code' => '',
                'amount' => 1,
                'price' => floatval($order->getShippingInclTax()),
                'vat' => round(floatval($shippingTaxPct)),
                'discount' => 0,
                'type' => 2,
            );
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

            $items[] = array(
                'title' => (string) $discountLabel,
                'code' => '',
                'amount' => -1,
                'price' => floatval($discountData->getDiscountInclTax()),
                'vat' => round(floatval($discountTaxPct)),
                'discount' => 0,
                'type' => 3
            );
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
        if ((float) $order->getShippingInclTax() && (float) $order->getShippingAmount()) {
            $shippingTaxRate = $order->getShippingInclTax() / $order->getShippingAmount();
        } else {
            $shippingTaxRate = 1;
        }

        // Add / exclude shipping tax
        $shippingDiscount = (float) $order->getShippingDiscountAmount();
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

    /**
     * @return string module version in format x.x.x
     */
    protected function getExtensionVersion() {
        return $this->moduleList->getOne(self::MODULE_CODE)['setup_version'];
    }
}
