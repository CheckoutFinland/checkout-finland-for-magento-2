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
        $version = 'v' . $this->versionHelper->getVersion();
        try {
            $githubContent = $this->versionHelper->getDecodedContentFromGithub();
            $githubContent['tag_name'];

            if ($version != $githubContent['tag_name']) {
                $html = '<strong style="color: red">' . $version . " - Newer version (" . $githubContent['tag_name'] .
                    ") available. " . "<a href= \"" . $githubContent['html_url'] . "\" target='_blank'> " .
                    "More details</a></strong>";
            } else {
                $html = '<strong style="color: green">' . $version . " - Latest version" . '</strong>';
            }
        } catch (\Exception $e) {
            return '<strong>' .  $version . " - Can't check for updates now"  . '</strong>';
        }
        return $html;
    }
}
