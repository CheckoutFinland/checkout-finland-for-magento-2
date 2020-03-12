<?php

namespace Op\Checkout\Controller\Callback;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Op\Checkout\Helper\ProcessPayment;

/**
 * Class Index
 */
class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var Session
     */
    protected $session;

    /**
     * @var ProcessPayment
     */
    private $processPayment;

    /**
     * Index constructor.
     * @param Context $context
     * @param Session $session
     * @param ProcessPayment $processPayment
     */
    public function __construct(
        Context $context,
        Session $session,
        ProcessPayment $processPayment
    ) {
        parent::__construct($context);
        $this->session = $session;
        $this->processPayment = $processPayment;
    }

    /**
     * execute method
     */
    public function execute()
    {
        $this->processPayment->process($this->getRequest()->getParams(), $this->session);

        return;
    }
}