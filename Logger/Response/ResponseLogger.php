<?php

namespace Op\Checkout\Logger\Response;

/**
 * Class Logger
 */
class ResponseLogger extends \Monolog\Logger
{

    /**
     * Add response info log to op_payment_service_response.log
     *
     * @param $type
     * @param $data
     */
    public function responseInfoLog($type, $data)
    {
        if (is_array($data)) {
            $this->addInfo($type . ': ' . json_encode($data));
        } elseif (is_object($data)) {
            $this->addInfo($type . ': ' . json_encode($data));
        } else {
            $this->addInfo($type . ': ' . $data);
        }
    }

    /**
     * Add response error log to op_payment_service_response.log
     *
     * @param $type
     * @param $data
     */
    public function responseErrorLog($type, $data)
    {
        if (is_array($data)) {
            $this->addError($type . ': ' . json_encode($data));
        } elseif (is_object($data)) {
            $this->addError($type . ': ' . json_encode($data));
        } else {
            $this->addError($type . ': ' . $data);
        }
    }
}
