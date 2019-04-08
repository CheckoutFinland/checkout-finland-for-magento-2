<?php
namespace Op\Checkout\Block\Info;

use Op\Checkout\Helper\Data;

class Checkout extends \Magento\Payment\Block\Info
{
    protected $_template = 'Op_Checkout::info/checkout.phtml';

    public function getOpCheckoutLogo()
    {
        return $this->_scopeConfig->getValue(
            Data::LOGO,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}
