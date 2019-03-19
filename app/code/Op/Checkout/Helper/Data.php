<?php

namespace Op\Checkout\Helper;

use Magento\Framework\Escaper;
use Magento\Framework\App\Helper\Context;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    protected $_storeManager;
    protected $encryptor;
    protected $escaper;
    protected $methods = [];

    protected $methodCodes = [\Op\Checkout\Model\ConfigProvider::CODE];

    const MERCHANT_SECRET_PATH = 'payment/checkout/merchant_secret';
    const MERCHANT_ID_PATH = 'payment/checkout/merchant_id';
    const DEBUG_LOG = 'payment/checkout/debuglog';
    const DEFAULT_ORDER_STATUS = 'payment/checkout/order_status';
    const NOTIFICATION_EMAIL = 'payment/checkout/recipient_email';
    const BYPASS_PATH = 'Op_Checkout/payment/checkout-bypass';

    public function __construct(
        Context $context,
        Escaper $escaper,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        parent::__construct($context);
        $this->encryptor = $encryptor;
        $this->_storeManager = $storeManager;
        $this->escaper = $escaper;
    }

    public function getConfig($config_path)
    {
        return $this->scopeConfig->getValue(
            $config_path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getDefaultOrderStatus()
    {
        return $this->getConfig(self::DEFAULT_ORDER_STATUS);
    }

    public function getDebugLoggerStatus()
    {
        return $this->getConfig(self::DEBUG_LOG);
    }

    public function getMerchantId()
    {
        return $this->getConfig(self::MERCHANT_ID_PATH);
    }

    public function getNotificationEmail()
    {
        return $this->getConfig(self::NOTIFICATION_EMAIL);
    }

    public function getMerchantSecret()
    {
        //return $merchant_sercret;
        $merchant_sercret = $this->getConfig(self::MERCHANT_SECRET_PATH);
        return $this->encryptor->decrypt($merchant_sercret);
    }

    public function isJson($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    public function getPaymentTemplate()
    {
        return self::BYPASS_PATH;
    }

    public function getPaymentRedirectUrl($code)
    {
        return $this->methods[$code]->getPaymentRedirectUrl();
    }

    public function getUseBypass()
    {
        return true;
    }

    public function getEnabledPaymentMethodGroups($code)
    {
        return $this->methods[$code]->getEnabledPaymentMethodGroups();
    }

    public function getInstructions($code)
    {
        return 'Mega instructions'; //nl2br($this->escaper->escapeHtml($this->methods[$code]->getInstructions()));
    }

    public function getValidAlgorithms()
    {
        return ["sha256", "sha512"];
    }

}