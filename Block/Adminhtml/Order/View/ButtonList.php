<?php
namespace Op\Checkout\Block\Adminhtml\Order\View;

use Magento\Backend\Block\Widget\Button\ItemFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\UrlInterface;
use Op\Checkout\Helper\ActivateOrder;

/**
 * Class ButtonList
 */
class ButtonList extends \Magento\Backend\Block\Widget\Button\ButtonList
{
    /**
     * @var string COOKIE_NAME_RESCUE
     */
    CONST RESCUE_COOKIE_NAME = "rescue";

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var ActivateOrder
     */
    protected $activateOrder;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * ButtonList constructor.
     * @param ItemFactory $itemFactory
     * @param RequestInterface $request
     * @param ActivateOrder $activateOrder
     * @param UrlInterface $urlBuilder
     * @param CookieManagerInterface $cookieManager
     */
    public function __construct(
        ItemFactory $itemFactory,
        RequestInterface $request,
        ActivateOrder $activateOrder,
        UrlInterface $urlBuilder,
        CookieManagerInterface $cookieManager
    ) {
        parent::__construct($itemFactory);
        $this->itemFactory = $itemFactory;
        $this->request = $request;
        $this->activateOrder = $activateOrder;
        $this->urlBuilder = $urlBuilder;
        $this->cookieManager = $cookieManager;

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
                        document.cookie="' . self::RESCUE_COOKIE_NAME . '=' . $orderId . '";
                        window.location="' . $url . '"
                    }',
            ]);
        }

        /** @var null|string $cookie */
        $cookie = $this->cookieManager->getCookie(self::RESCUE_COOKIE_NAME);

        /**
         * Check if the rescue button has been clicked,
         * verify that the cookie matches the current order, and rescue the items
         */
        if (!is_null($cookie) && $cookie == $orderId) {
            $this->activateOrder->activateOrder($orderId); // Rescue items
            $this->cookieManager->deleteCookie(self::RESCUE_COOKIE_NAME);

            header('Location:' . $url);
        }
    }
}
