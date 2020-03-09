<?php

namespace Op\Checkout\Controller\Redirect;

use Magento\Framework\Exception\LocalizedException;
use Op\Checkout\Helper\ApiData;
use Op\Checkout\Helper\Data as opHelper;
use \Psr\Log\LoggerInterface;

/**
 * Class Index
 */
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
    protected $apiData;
    protected $opHelper;
    protected $logger;

    /**
     * Index constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface
     * @param \Magento\Sales\Api\OrderManagementInterface $orderManagementInterface
     * @param \Magento\Framework\View\Result\PageFactory $pageFactory
     * @param LoggerInterface $logger
     * @param ApiData $apiData
     * @param opHelper $opHelper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface,
        \Magento\Sales\Api\OrderManagementInterface $orderManagementInterface,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        LoggerInterface $logger,
        ApiData $apiData,
        opHelper $opHelper
    ) {
        $this->urlBuilder = $context->getUrl();
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->jsonFactory = $jsonFactory;
        $this->pageFactory = $pageFactory;
        $this->orderManagementInterface = $orderManagementInterface;
        $this->orderRepositoryInterface = $orderRepositoryInterface;
        $this->apiData = $apiData;
        $this->opHelper = $opHelper;
        $this->logger = $logger;
        parent::__construct($context);
    }

    public function execute()
    {

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->jsonFactory->create();

        $order = null;
        try {
            if ($this->getRequest()->getParam('is_ajax')) {
                $selectedPaymentMethodRaw = $this->getRequest()->getParam('preselected_payment_method_id');
                $selectedPaymentMethodId = preg_replace('/[0-9]{1,2}$/', '', $selectedPaymentMethodRaw);

                if (empty($selectedPaymentMethodId)) {
                    throw new LocalizedException(__('no payment method selected'));
                }

                $order = $this->orderFactory->create();
                $order = $order->loadByIncrementId($this->checkoutSession->getLastRealOrderId());
                $responseData = $this->getResponseData($order);
                $formData = $this->getFormFields($responseData, $selectedPaymentMethodId);
                $formAction = $this->getFormAction($responseData, $selectedPaymentMethodId);

                if ($this->opHelper->getSkipBankSelection()) {
                    $redirect_url = $responseData->href;

                    return $resultJson->setData([
                        'success' => true,
                        'data' => 'redirect',
                        'redirect' => $redirect_url
                    ]);
                }

                $block = $this->pageFactory
                    ->create()
                    ->getLayout()
                    ->createBlock('Op\Checkout\Block\Redirect\Checkout')
                    ->setUrl($formAction)
                    ->setParams($formData);

                return $resultJson->setData([
                    'success' => true,
                    'data' => $block->toHtml(),
                ]);
            }
        } catch (\Exception $e) {
            // Error will be handled below
            $this->logger->debug($e->getMessage());
        }

        if ($order) {
            $this->orderManagementInterface->cancel($order->getId());
            $order->addCommentToStatusHistory(__('Order canceled. Failed to redirect to OP Payment Service.'));
            $this->orderRepositoryInterface->save($order);
        }

        $this->checkoutSession->restoreQuote();
        $resultJson = $this->jsonFactory->create();

        return $resultJson->setData([
            'success' => false,
        ]);
    }

    protected function getFormFields($responseData, $paymentMethodId = null)
    {
        $formFields = [];

        foreach ($responseData->providers as $provider) {
            if ($provider->id == $paymentMethodId) {
                foreach ($provider->parameters as $parameter) {
                    $formFields[$parameter->name] = $parameter->value;
                }
            }
        }

        return $formFields;
    }

    protected function getFormAction($responseData, $paymentMethodId = null)
    {
        $returnUrl = '';

        foreach ($responseData->providers as $provider) {
            if ($provider->id == $paymentMethodId) {
                $returnUrl = $provider->url;
            }
        }

        return $returnUrl;
    }

    protected function getResponseData($order)
    {
        $uri = '/payments';
        $merchantId = $this->opHelper->getMerchantId();
        $merchantSecret = $this->opHelper->getMerchantSecret();
        $method = 'post';

        $response = $this->apiData->getResponse($uri, $order, $merchantId, $merchantSecret, $method);

        return $response['data'];
    }
}
