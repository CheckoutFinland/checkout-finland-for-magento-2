<?php
namespace Op\Checkout\Block\Info;

use Magento\Framework\View\Element\Template;
use Op\Checkout\Helper\Data;
use Op\Checkout\Gateway\Config\Config;
use Magento\Store\Model\StoreManagerInterface;

class Checkout extends \Magento\Payment\Block\Info
{
    protected $_template = 'Op_Checkout::info/checkout.phtml';
    /**
     * @var Config
     */
    private $gatewayConfig;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Checkout block constructor
     *
     * @param Config $gatewayConfig
     * @param StoreManagerInterface $storeManager
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct
    (
        Config $gatewayConfig,
        StoreManagerInterface $storeManager,
        Template\Context $context,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->gatewayConfig = $gatewayConfig;
        $this->storeManager = $storeManager;
    }

    public function getOpCheckoutLogo()
    {
        return $this->_scopeConfig->getValue(
            Data::LOGO,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getPaymentMethodTitle()
    {
        return $this->gatewayConfig->getTitle($this->storeManager->getStore()->getId());
    }
}
