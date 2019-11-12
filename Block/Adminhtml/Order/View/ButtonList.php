<?php
namespace Op\Checkout\Block\Adminhtml\Order\View;

/**
 * Class ButtonList
 * @package Op\Checkout\Block\Adminhtml\Order\View
 */
class ButtonList extends \Magento\Backend\Block\Widget\Button\ButtonList
{
    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @var \Op\Checkout\Helper\ActivateOrder
     */
    protected $activateOrder;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * ButtonList constructor.
     * @param \Magento\Backend\Block\Widget\Button\ItemFactory $itemFactory
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Op\Checkout\Helper\ActivateOrder $activateOrder
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function __construct(
        \Magento\Backend\Block\Widget\Button\ItemFactory $itemFactory,
        \Magento\Framework\App\RequestInterface $request,
        \Op\Checkout\Helper\ActivateOrder $activateOrder,
        \Magento\Framework\UrlInterface $urlBuilder
    ) {
        parent::__construct($itemFactory);
        $this->itemFactory = $itemFactory;
        $this->request = $request;
        $this->activateOrder = $activateOrder;
        $this->urlBuilder = $urlBuilder;

        /**
         * Get order ID and current page URL
         */
        $orderId = $this->request->getParam('order_id');
        $url = $this->urlBuilder->getCurrentUrl();

        /**
         * Create rescue action button on order page if items are canceled
         */
        if ($this->activateOrder->isCanceled($orderId)) {
            $this->add('rescueOrder', [
                'label' => __('Restore Order'),
                'onclick' => '
                    if (confirm(\'' . __('Are you sure you want to make changes to this order?') . '\')) { 
                        document.cookie="rescue=' . $orderId . '";
                        window.location="' . $url . '"
                    }',
            ]);
        }

        /**
         * Check if the rescue button has been clicked,
         * verify that the cookie matches the current order, and rescue the items
         */
        //TODO: restore order using cookie. need to refactor !
        if (isset($_COOKIE['rescue']) && $_COOKIE['rescue'] == $orderId) {
            $this->activateOrder->activateOrder($orderId); // Rescue items
            setcookie('rescue', ''); // Reset cookie
            header('Location:' . $url);
        }
    }
}
