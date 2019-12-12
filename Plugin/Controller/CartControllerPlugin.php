<?php

namespace Op\Checkout\Plugin\Controller;

use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\Response\Http;
use Magento\Framework\Message\ManagerInterface;
use Op\Checkout\Helper\Data as CheckoutHelper;
use Magento\Framework\Controller\ResultFactory;
use Op\Checkout\Logger\Request\Logger;
use Psr\Log\LoggerInterface;

class CartControllerPlugin
{

    protected $redirect;

    protected $response;

    protected $messageManager;

    protected $helper;

    protected $resultFactory;
    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * CartControllerPlugin constructor.
     * @param RedirectInterface $redirect
     * @param Http $response
     * @param ManagerInterface $messageManager
     * @param CheckoutHelper $helper
     * @param ResultFactory $resultFactory
     * @param LoggerInterface $log
     */
    public function __construct(
        RedirectInterface $redirect,
        Http $response,
        ManagerInterface $messageManager,
        CheckoutHelper $helper,
        ResultFactory $resultFactory,
        LoggerInterface $log
    )
    {
        $this->redirect = $redirect;
        $this->response = $response;
        $this->messageManager = $messageManager;
        $this->helper = $helper;
        $this->resultFactory = $resultFactory;
        $this->log = $log;
    }

    /**
     * Plugin that handles redirects to shopping cart when prescription order checkout is locked.
     *
     * @param \Magento\Checkout\Controller\Cart\Index $subject
     * @param \Closure $proceed
     * @return \Magento\Checkout\Controller\Cart\Index | \Magento\Framework\Controller\ResultInterface
     */
    public function aroundExecute(\Magento\Checkout\Controller\Cart\Index $subject, \Closure $proceed)
    {
        $merchantId = $this->helper->getMerchantId();
        $merchantSecret = $this->helper->getMerchantSecret();

        if (empty($merchantSecret) || empty($merchantId)) {
            $this->messageManager->addWarningMessage(_('Op Payment Service API credentials are missing. Please contact support.'));
            $this->log->critical('Op Payment Service API credentials missing');
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setRefererUrl();
            return $resultRedirect;
        }
        return $proceed($subject);
    }
}
