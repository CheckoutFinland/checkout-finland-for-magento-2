<?php

namespace Op\Checkout\Block\System\Config\Form\Field;

class Version extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @var \Op\Checkout\Helper\Version
     */
    private $versionHelper;

    public function __construct(
        \Op\Checkout\Helper\Version $versionHelper,
        \Magento\Backend\Block\Template\Context $context
    ) {
        $this->versionHelper = $versionHelper;
        parent::__construct($context);
    }

    /**
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(
        \Magento\Framework\Data\Form\Element\AbstractElement $element
    ) {
        $url = "https://github.com/paytrail/paytrail-for-adobe-commerce";
        return '<strong style="color: red">' . __("This module is now deprecated. ") .
            "</strong>" . __("New module is available as paytrail/paytrail-for-adobe-commerce. ") . " <a href= \"" . $url . "\" target='_blank'> " .
            __("Click here for more info") . "</a>";
    }
}
