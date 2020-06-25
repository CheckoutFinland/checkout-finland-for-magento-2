<?php

namespace Op\Checkout\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\Locale\Resolver;
use Op\Checkout\Exceptions\CheckoutException;
use Op\Checkout\Exceptions\TransactionSuccessException;

/**
 * Class Data
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const DEFAULT_ORDER_STATUS = 'payment/opcheckout/order_status';
    const NOTIFICATION_EMAIL = 'payment/opcheckout/recipient_email';
    const LOGO = 'payment/opcheckout/logo';

    /**
     * @var Resolver
     */
    private $localeResolver;

    /**
     * Helper class constructor.
     *
     * @param Context $context
     * @param Resolver $localeResolver
     */
    public function __construct(
        Context $context,
        Resolver $localeResolver
    ) {
        parent::__construct($context);
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
    public function getNotificationEmail()
    {
        return $this->getConfig(self::NOTIFICATION_EMAIL);
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

    /**
     * @param $errorMessage
     * @throws CheckoutException
     */
    public function processError($errorMessage)
    {
        throw new CheckoutException(__($errorMessage));
    }

    /**
     * @throws TransactionSuccessException
     */
    public function processSuccess()
    {
        throw new TransactionSuccessException(__('Success'));
    }
}
