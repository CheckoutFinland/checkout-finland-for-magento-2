<?php

namespace Op\Checkout\Logger\Request;

/**
 * Class Logger
 */
class RequestLogger extends \Monolog\Logger
{

    /**
     * Add request info log to op_payment_service_request.log
     *
     * @param $type
     * @param $data
     */
    public function requestInfoLog($type, $data)
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
     * Add request error log to op_payment_service_request.log
     *
     * @param $type
     * @param $data
     */
    public function requestErrorLog($type, $data)
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
