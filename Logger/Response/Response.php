<?php

namespace Op\Checkout\Logger\Response;

use Monolog\Logger;

class Response extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::DEBUG;

    /**
     * File name
     * @var string
     */
    protected $fileName = 'var/log/op_payment_service_response.log';
}
