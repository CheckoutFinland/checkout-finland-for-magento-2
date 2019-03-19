<?php

namespace Op\Checkout\Helper;

class ApiData
{
    const API_ENDPOINT = 'https://api.checkout.fi';

    protected $log;
    protected $signature;
    protected $orderItems;
    protected $_urlBuilder;
    protected $_request;
    protected $json;
    protected $resourceConfig;

    /**
     * ApiData constructor.
     * @param \Psr\Log\LoggerInterface $log
     * @param Signature $signature
     * @param OrderItems $orderItems
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\Serialize\Serializer\Json $json
     * @param \Magento\Config\Model\ResourceModel\Config $resourceConfig
     */
    public function __construct(
        \Psr\Log\LoggerInterface $log,
        Signature $signature,
        OrderItems $orderItems,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\Serialize\Serializer\Json $json,
        \Magento\Config\Model\ResourceModel\Config $resourceConfig
    ) {
        $this->log = $log;
        $this->signature = $signature;
        $this->orderItems = $orderItems;
        $this->_urlBuilder = $urlBuilder;
        $this->_request = $request;
        $this->resourceConfig = $resourceConfig;
        $this->json = $json;
    }

    public function getResponse($uri, $order, $merchantId, $merchantSecret, $method, $refundId = null, $refundBody = null)
    {
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

    public function getResponseHeaders($account, $method)
    {
        return $headers = [
            'checkout-account' => $account,
            'checkout-algorithm' => 'sha256',
            'checkout-method' => strtoupper($method),
            'checkout-nonce' => uniqid(true),
            'checkout-timestamp' => date('Y-m-d\TH:i:s.000\Z', time()),
            'content-type' => 'application/json; charset=utf-8',
        ];
    }

    public function getResponseBody($order)
    {
        $billingAddress = $order->getBillingAddress();

        // using json_encode for option.
        $body = json_encode(
            [
                'stamp' => hash('sha256', time() . $order->getIncrementId()),
                'reference' => $order->getIncrementId(),
                'amount' => $order->getGrandTotal() * 100,
                'currency' => $order->getOrderCurrencyCode(),
                'language' => 'FI',
                'items' => $this->orderItems->getOrderItems($order),
                'customer' => [
                    'firstName' => $billingAddress->getFirstName(),
                    'lastName' => $billingAddress->getLastName(),
                    'phone' => $billingAddress->getTelephone(),
                    'email' => $billingAddress->getEmail(),
                ],
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

        return $body;
    }

    public function getReceiptUrl()
    {
        $receiptUrl = $this->_urlBuilder->getUrl('checkout/receipt', [
            '_secure' => $this->_request->isSecure()
        ]);

        return $receiptUrl;
    }
}
