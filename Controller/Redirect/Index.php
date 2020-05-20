<?php

namespace Op\Checkout\Controller\Redirect;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Op\Checkout\Helper\ApiData;
use Op\Checkout\Helper\Data as opHelper;
use Psr\Log\LoggerInterface;
use Op\Checkout\Gateway\Config;

/**
 * Class Index
 */
class Index extends \Magento\Framework\App\Action\Action
{
    protected $urlBuilder;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var JsonFactory
     */
    protected $jsonFactory;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepositoryInterface;

    /**
     * @var OrderManagementInterface
     */
    protected $orderManagementInterface;

    /**
     * @var PageFactory
     */
    protected $pageFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ApiData
     */
    protected $apiData;

    /**
     * @var opHelper
     */
    protected $opHelper;

    /**
     * @var Config
     */
    protected $gatewayConfig;

    protected $errorMsg = null;



    /**
     * Index constructor.
     *
     * @param Context $context
     * @param Session $checkoutSession
     * @param OrderFactory $orderFactory
     * @param JsonFactory $jsonFactory
     * @param OrderRepositoryInterface $orderRepositoryInterface
     * @param OrderManagementInterface $orderManagementInterface
     * @param PageFactory  $pageFactory
     * @param LoggerInterface $logger
     * @param ApiData $apiData
     * @param opHelper $opHelper
     * @param Config $gatewayConfig
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        JsonFactory $jsonFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface,
        OrderManagementInterface $orderManagementInterface,
        PageFactory $pageFactory,
        LoggerInterface $logger,
        ApiData $apiData,
        opHelper $opHelper,
        Config $gatewayConfig
    ) {
        $this->urlBuilder = $context->getUrl();
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->jsonFactory = $jsonFactory;
        $this->orderRepositoryInterface = $orderRepositoryInterface;
        $this->orderManagementInterface = $orderManagementInterface;
        $this->pageFactory = $pageFactory;
        $this->logger = $logger;
        $this->apiData = $apiData;
        $this->opHelper = $opHelper;
        $this->gatewayConfig = $gatewayConfig;
        parent::__construct($context);
    }

    /**
     * @return mixed
     */
    public function execute()
    {
        /** @var Json $resultJson */
        $resultJson = $this->jsonFactory->create();

        $order = null;
        try {
            if ($this->getRequest()->getParam('is_ajax')) {
                $selectedPaymentMethodRaw
                    = $this->getRequest()->getParam(
                    'preselected_payment_method_id'
                );
                $selectedPaymentMethodId = preg_replace(
                    '/[0-9]{1,2}$/',
                    '',
                    $selectedPaymentMethodRaw
                );

                if (empty($selectedPaymentMethodId)) {
                    $this->errorMsg = __('No payment method selected');
                    throw new LocalizedException(__('No payment method selected'));
                }

                /** @var Order $order */
                $order = $this->orderFactory->create();
                $order = $order->loadByIncrementId(
                    $this->checkoutSession->getLastRealOrderId()
                );
                $responseData = $this->getResponseData($order);
                $formData = $this->getFormFields(
                    $responseData,
                    $selectedPaymentMethodId
                );
                $formAction = $this->getFormAction(
                    $responseData,
                    $selectedPaymentMethodId
                );

                if ($this->gatewayConfig->getSkipBankSelection()) {
                    $redirect_url = $responseData->href;

                    return $resultJson->setData(
                        [
                            'success' => true,
                            'data' => 'redirect',
                            'redirect' => $redirect_url
                        ]
                    );
                }

                $block = $this->pageFactory
                    ->create()
                    ->getLayout()
                    ->createBlock('Op\Checkout\Block\Redirect\Checkout')
                    ->setUrl($formAction)
                    ->setParams($formData);

                return $resultJson->setData(
                    [
                        'success' => true,
                        'data' => $block->toHtml(),
                    ]
                );
            }
        } catch (\Exception $e) {
            // Error will be handled below
            $this->logger->debug($e->getMessage());
        }

        if ($order) {
            $this->orderManagementInterface->cancel($order->getId());
            $order->addCommentToStatusHistory(
                __('Order canceled. Failed to redirect to OP Payment Service.')
            );
            $this->orderRepositoryInterface->save($order);
        }

        $this->checkoutSession->restoreQuote();
        $resultJson = $this->jsonFactory->create();

        return $resultJson->setData(
            [
                'success' => false,
                'message' => $this->errorMsg
            ]
        );
    }

    /**
     * @param array $responseData
     * @param $paymentMethodId
     * @return array
     */
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

    /**
     * @param array $responseData
     * @param $paymentMethodId
     * @return string
     */
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

    /**
     * @param Order $order
     * @return mixed
     * @throws LocalizedException
     */
    protected function getResponseData($order)
    {
        $uri = '/payments';
        $merchantId = $this->opHelper->getMerchantId();
        $merchantSecret = $this->opHelper->getMerchantSecret();
        $method = 'post';

        $response = $this->apiData->getResponse(
            $uri,
            $order,
            $merchantId,
            $merchantSecret,
            $method
        );

        $status = $response['status'];

        if (!isset($status)) {
            $this->errorMsg = __(
                'There was a problem processing your order contents'
            );
            throw new LocalizedException(
                __('There was a problem processing your order contents')
            );
        }
        if ($status === 422 || $status === 400 || $status === 404) {
            $this->errorMsg = __(
                'Couldn\'t successfully establish connection to payment provider'
            );
            throw new LocalizedException(
                __('Couldn\'t successfully establish connection to payment provider')
            );
        }
        return $response['data'];
    }
}
