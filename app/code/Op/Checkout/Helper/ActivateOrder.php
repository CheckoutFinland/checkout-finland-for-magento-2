<?php
namespace Op\Checkout\Helper;

/**
 * Class ActivateOrder
 * @package Op\Checkout\Helper
 */
class ActivateOrder
{
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order
     */
    protected $orderResourceModel;

    /**
     * ActivateOrder constructor.
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Sales\Model\ResourceModel\Order $orderResourceModel
     */
    public function __construct(
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\ResourceModel\Order $orderResourceModel
    ) {
        $this->orderResourceModel = $orderResourceModel;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param $orderId
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function activateOrder($orderId)
    {
        $order = $this->orderRepository->get($orderId);

        /**
         * Loop through order items and set canceled items as ordered
         */
        foreach ($order->getItems() as $item) {
            $item->setQtyCanceled(0);
        }

        $this->orderResourceModel->save($order);
    }

    /**
     * @param $orderId
     * @return int
     */
    public function isCanceled($orderId)
    {
        $order = $this->orderRepository->get($orderId);
        $i = 0;

        foreach ($order->getItems() as $item) {
            if ($item->getQtyCanceled() > 0) {
                $i++;
            }
        }

        if ($i > 0) {
            return true;
        } else {
            return false;
        }
    }
}
