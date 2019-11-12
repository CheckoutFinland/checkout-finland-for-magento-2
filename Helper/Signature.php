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

    public function calculateHmac(array $params = [], $body = null, $secretKey = null)
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


    public function validateHmac(
        array $params = [],
        $body = null,
        $signature = null,
        $secretKey = null
    ) {
        $hmac = static::calculateHmac($params, $body, $secretKey);
        if ($hmac !== $signature) {
            $this->log->critical('Response HMAC signature mismatch!');
        }
    }
}