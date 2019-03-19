<?php

namespace Op\Checkout\Helper;

class Signature
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $log;

    /**
     * Signature constructor.
     * @param \Psr\Log\LoggerInterface $log
     */
    public function __construct(
        \Psr\Log\LoggerInterface $log
    ) {
        $this->log = $log;
    }

    /**
     * Calculate Checkout HMAC
     *
     * For more information about the signature headers:
     * @see https://checkoutfinland.github.io/psp-api/#/?id=headers-and-request-signing
     * @see https://checkoutfinland.github.io/psp-api/#/?id=redirect-and-callback-url-parameters
     *
     * @param array[string]  $params    HTTP headers in an associative array.
     *
     * @param string                $body      HTTP request body, empty string for GET requests
     * @param string                $secretKey The merchant secret key.
     * @return string SHA-256 HMAC
     */
    public function calculateHmac(array $params = [], $body = NULL, $secretKey = NULL)
    {
        // Keep only checkout- params, more relevant for response validation.
        $includedKeys = array_filter(array_keys($params), function ($key) {
            return preg_match('/^checkout-/', $key);
        });
        // Keys must be sorted alphabetically
        sort($includedKeys, SORT_STRING);
        $hmacPayload = array_map(
            function ($key) use ($params) {
                // Responses have headers in an array.
                $param = is_array($params[ $key ]) ? $params[ $key ][0] : $params[ $key ];
                return join(':', [ $key, $param ]);
            },
            $includedKeys
        );
        array_push($hmacPayload, $body);
        return hash_hmac('sha256', join("\n", $hmacPayload), $secretKey);
    }
    /**
     * Evaluate a response signature validity.
     *
     * For more information about the signature headers:
     * @see https://checkoutfinland.github.io/psp-api/#/?id=headers-and-request-signing
     * @see https://checkoutfinland.github.io/psp-api/#/?id=redirect-and-callback-url-parameters
     *
     * @param array  $params    The response parameters.
     * @param string $body      The response body.
     * @param string $signature The response signature key.
     * @param string $secretKey The merchant secret key.
     *
     * @throws HmacException
     */
    public function validateHmac(
        array $params = [],
        $body = NULL,
        $signature = NULL,
        $secretKey = NULL
    ) {
        $hmac = static::calculateHmac($params, $body, $secretKey);
        if ($hmac !== $signature) {
            $this->log->critical('Response HMAC signature mismatch!');
        }
    }
}