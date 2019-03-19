<?php

namespace Op\Checkout\Model;

use Op\Checkout\Api\RedirectInterface;
use \Magento\Checkout\Model\Session;

class Redirect implements RedirectInterface
{
    protected $_checkoutSession;

    public function __construct(
        Session $checkoutSession
    ) {
        $this->_checkoutSession = $checkoutSession;
    }

    public function redirect()
    {
        $url = $this->_checkoutSession->getCheckoutRedirectUrl();

        if ($url) {
            return $url;
        }

        return false;
    }
}