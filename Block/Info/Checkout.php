<?php
namespace Op\Checkout\Block\Info;

use Op\Checkout\Helper\Data;

/**
 * Class Checkout
 */
class Checkout extends \Magento\Payment\Block\Info
{
    /**
     * @var string
     */
    protected $_template = 'Op_Checkout::info/checkout.phtml';

    /**
     * @return mixed
     */
    public function getOpCheckoutLogo()
    {
        return $this->_scopeConfig->getValue(
            Data::LOGO,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}
