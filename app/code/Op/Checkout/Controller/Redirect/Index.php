<?php

namespace Op\Checkout\Controller\Redirect;

use Magento\Framework\Exception\LocalizedException;
use \Op\Checkout\Model\Api\Checkout as opCheckout;

class Index extends \Magento\Framework\App\Action\Action
{
    protected $urlBuilder;
    protected $checkoutSession;
    protected $orderFactory;
    protected $jsonFactory;
    protected $pageFactory;
    protected $checkout;
    protected $orderManagementInterface;
    protected $orderRepositoryInterface;

    /**
     * Index constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface
     * @param \Magento\Sales\Api\OrderManagementInterface $orderManagementInterface
     * @param \Magento\Framework\View\Result\PageFactory $pageFactory
     * @param opCheckout $checkout
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface,
        \Magento\Sales\Api\OrderManagementInterface $orderManagementInterface,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        opCheckout $checkout
    ) {
        $this->urlBuilder = $context->getUrl();
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->jsonFactory = $jsonFactory;
        $this->pageFactory = $pageFactory;
        $this->checkout = $checkout;
        $this->orderManagementInterface = $orderManagementInterface;
        $this->orderRepositoryInterface = $orderRepositoryInterface;
        parent::__construct($context);
    }

    public function execute()
    {
        $order = null;
        try {
            if ($this->getRequest()->getParam('is_ajax')) {
                $selectedPaymentMethodRaw = $this->getRequest()->getParam('preselected_payment_method_id');
                $selectedPaymentMethodId = preg_replace('/[0-9]{1,2}$/', '', $selectedPaymentMethodRaw);

                if (empty($selectedPaymentMethodId)) {
                    throw new LocalizedException('no payment method selected');
                }

                $order = $this->orderFactory->create();
                $order = $order->loadByIncrementId($this->checkoutSession->getLastRealOrderId());
                $responseData = $this->checkout->getResponseData($order);
                $formData = $this->checkout->getFormFields($responseData, $selectedPaymentMethodId);
                $formAction = $this->checkout->getFormAction($responseData, $selectedPaymentMethodId);

                // Create block containing form data
                $block = $this->pageFactory
                    ->create()
                    ->getLayout()
                    ->createBlock('Op\Checkout\Block\Redirect\Checkout')
                    ->setUrl($formAction)
                    ->setParams($formData);

                $resultJson = $this->jsonFactory->create();

                return $resultJson->setData([
                    'success' => true,
                    'data' => $block->toHtml(),
                ]);
            }
        } catch (\Exception $e) {
            // Error will be handled below
        }

        if ($order) {
            $this->orderManagementInterface->cancel($order->getId());
            $order->addCommentToStatusHistory(__('Order canceled. Failed to redirect to OP Checkout.'));
            $this->orderRepositoryInterface->save($order);
        }

        $this->checkoutSession->restoreQuote();
        $resultJson = $this->jsonFactory->create();

        return $resultJson->setData([
            'success' => false,
        ]);
    }
}
