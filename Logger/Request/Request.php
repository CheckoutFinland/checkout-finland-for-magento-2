<?php

namespace Op\Checkout\Logger\Request;

use Monolog\Logger;

/**
 * Class Request
 */
class Request extends \Magento\Framework\Logger\Handler\Base
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
    protected $fileName = 'var/log/op_payment_service_request.log';
}
