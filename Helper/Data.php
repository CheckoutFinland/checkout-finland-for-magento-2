<?php

namespace Op\Checkout\Helper;

use Magento\Framework\Escaper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Locale\Resolver;

/**
 * Class Data
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    protected $_storeManager;
    protected $encryptor;
    protected $escaper;
    protected $methods = [];
    /**
     * @var Resolver
     */
    private $localeResolver;

    protected $methodCodes = [\Op\Checkout\Model\ConfigProvider::CODE];

    const MERCHANT_SECRET_PATH = 'payment/opcheckout/merchant_secret';
    const MERCHANT_ID_PATH = 'payment/opcheckout/merchant_id';
    const DEBUG_LOG = 'payment/opcheckout/debuglog';
    const RESPONSE_LOG = 'payment/opcheckout/response_log';
    const REQUEST_LOG = 'payment/opcheckout/request_log';
    const DEFAULT_ORDER_STATUS = 'payment/opcheckout/order_status';
    const NOTIFICATION_EMAIL = 'payment/opcheckout/recipient_email';
    const LOGO = 'payment/opcheckout/logo';

    /**
     * Helper class constructor.
     *
     * @param Context $context
     * @param Escaper $escaper
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param Resolver $localeResolver
     */
    public function __construct(
        Context $context,
        Escaper $escaper,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        Resolver $localeResolver
    )
    {
        parent::__construct($context);
        $this->encryptor = $encryptor;
        $this->_storeManager = $storeManager;
        $this->escaper = $escaper;
        $this->localeResolver = $localeResolver;
    }

    /**
     * @param $config_path
     * @return mixed
     */
    public function getConfig($config_path)
    {
        return $this->scopeConfig->getValue(
            $config_path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return mixed
     */
    public function getDefaultOrderStatus()
    {
        return $this->getConfig(self::DEFAULT_ORDER_STATUS);
    }

    /**
     * @return mixed
     */
    public function getDebugLoggerStatus()
    {
        return $this->getConfig(self::DEBUG_LOG);
    }

    /**
     * @return mixed
     */
    public function getResponseLog()
    {
        return $this->getConfig(self::RESPONSE_LOG);
    }

    /**
     * @return mixed
     */
    public function getRequestLog()
    {
        return $this->getConfig(self::REQUEST_LOG);
    }

    /**
     * @return mixed
     */
    public function getMerchantId()
    {
        return $this->getConfig(self::MERCHANT_ID_PATH);
    }


    /**
     * @return mixed
     */
    public function getNotificationEmail()
    {
        return $this->getConfig(self::NOTIFICATION_EMAIL);
    }

    /**
     * @return mixed
     */
    public function getMerchantSecret()
    {
        //return $merchant_sercret;
        $merchant_sercret = $this->getConfig(self::MERCHANT_SECRET_PATH);
        return $this->encryptor->decrypt($merchant_sercret);
    }

    /**
     * @param $string
     * @return bool
     */
    public function isJson($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * @param $code
     * @return mixed
     */
    public function getPaymentRedirectUrl($code)
    {
        return $this->methods[$code]->getPaymentRedirectUrl();
    }

    /**
     * @param $code
     * @return mixed
     */
    public function getEnabledPaymentMethodGroups($code)
    {
        return $this->methods[$code]->getEnabledPaymentMethodGroups();
    }


    /**
     * @return array
     */
    public function getValidAlgorithms()
    {
        return ["sha256", "sha512"];
    }

    /**
     * @return string
     */
    public function getStoreLocaleForPaymentProvider()
    {
        $locale = 'EN';
        if ($this->localeResolver->getLocale() === 'fi_FI') {
            $locale = 'FI';
        }
        if ($this->localeResolver->getLocale() === 'sv_SE') {
            $locale = 'SV';
        }
        return $locale;
    }

    /**
     * Calculate Finnish reference number from order increment id
     * @param string $incrementId
     * @return string
     */
    public function calculateOrderReferenceNumber($incrementId)
    {
        $prefix = '1';
        $sum = 0;
        $length = strlen($incrementId);

        for ($i = 0; $i < $length; ++$i) {
            $sum += substr($incrementId, -1 - $i, 1) * [7, 3, 1][$i % 3];
        }
        $num = (10 - $sum % 10) % 10;
        $referenceNum = $prefix . $incrementId . $num;

        return trim(chunk_split($referenceNum, 5, ' '));
    }

    /**
     * Get order increment id from checkout reference number
     * @param string $reference
     * @return string|null
     */
    public function getIdFromOrderReferenceNumber($reference)
    {
        return preg_replace('/\s+/', '', substr($reference, 1, -1));
    }
}
