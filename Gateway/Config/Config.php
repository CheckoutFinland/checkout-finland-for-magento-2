<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Op\Checkout\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config extends \Magento\Payment\Gateway\Config\Config
{
    const DEFAULT_PATH_PATTERN = 'payment/%s/%s';
    const CODE = 'opcheckout';
    const KEY_ACTIVE = 'active';
    const KEY_PAYMENTGROUP_BG_COLOR = 'op_personalization/payment_group_bg';
    const KEY_PAYMENTGROUP_HIGHLIGHT_BG_COLOR = 'op_personalization/payment_group_highlight_bg';
    const KEY_PAYMENTGROUP_TEXT_COLOR = 'op_personalization/payment_group_text';
    const KEY_PAYMENTGROUP_HIGHLIGHT_TEXT_COLOR = 'op_personalization/payment_group_highlight_text';
    const KEY_PAYMENTGROUP_HOVER_COLOR = 'op_personalization/payment_group_hover';
    const KEY_PAYMENTMETHOD_HIGHLIGHT_COLOR = 'op_personalization/payment_method_highlight';
    const KEY_PAYMENTMETHOD_HIGHLIGHT_HOVER = 'op_personalization/payment_method_hover';
    const KEY_PAYMENTMETHOD_ADDITIONAL = 'op_personalization/advanced_op_personalization/additional_css';

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        $methodCode = self::CODE,
        $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
    }

    /**
     * Gets Payment configuration status.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isActive($storeId = null)
    {
        return (bool) $this->getValue(self::KEY_ACTIVE, $storeId);
    }

    public function getPaymentGroupBgColor($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTGROUP_BG_COLOR, $storeId);
    }

    public function getPaymentGroupHighlightBgColor($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTGROUP_HIGHLIGHT_BG_COLOR, $storeId);
    }

    public function getPaymentGroupTextColor($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTGROUP_TEXT_COLOR, $storeId);
    }

    public function getPaymentGroupHighlightTextColor($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTGROUP_HIGHLIGHT_TEXT_COLOR, $storeId);
    }

    public function getPaymentGroupHoverColor($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTGROUP_HOVER_COLOR, $storeId);
    }

    public function getPaymentMethodHighlightColor($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTMETHOD_HIGHLIGHT_COLOR, $storeId);
    }

    public function getPaymentMethodHoverHighlight($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTMETHOD_HIGHLIGHT_HOVER, $storeId);
    }

    public function getAdditionalCss($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTMETHOD_ADDITIONAL, $storeId);
    }
}
