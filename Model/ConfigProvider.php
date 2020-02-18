<?php

namespace Op\Checkout\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Helper\Data as PaymentHelper;
use Op\Checkout\Helper\ApiData as apiData;
use Op\Checkout\Helper\Data as opHelper;
use Op\Checkout\Gateway\Config\Config;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Store\Model\StoreManagerInterface;

class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'opcheckout';

    protected $methodCodes = [
        self::CODE,
    ];
    protected $ophelper;
    protected $apidata;
    /**
     * @var Session
     */
    protected $checkoutSession;
    /**
     * @var Config
     */
    private $gatewayConfig;
    /**
     * @var AssetRepository
     */
    private $assetRepository;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * ConfigProvider constructor
     *
     * @param opHelper $ophelper
     * @param apiData $apidata
     * @param PaymentHelper $paymentHelper
     * @param Session $checkoutSession
     * @param Config $gatewayConfig
     * @param AssetRepository $assetRepository
     * @param StoreManagerInterface $storeManager
     * @throws LocalizedException
     */
    public function __construct(
        opHelper $ophelper,
        apiData $apidata,
        PaymentHelper $paymentHelper,
        Session $checkoutSession,
        Config $gatewayConfig,
        AssetRepository $assetRepository,
        StoreManagerInterface $storeManager
    )
    {
        $this->ophelper = $ophelper;
        $this->apidata = $apidata;
        $this->checkoutSession = $checkoutSession;
        $this->gatewayConfig = $gatewayConfig;
        $this->assetRepository = $assetRepository;
        $this->storeManager = $storeManager;
        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }
    }

    public function getConfig()
    {
        $storeId = $this->storeManager->getStore()->getId();
        $config = [];
        $status = $this->gatewayConfig->isActive($storeId);
        if (!$status) {
            return $config;
        }
        try {
            $config = ['payment' => [
                self::CODE => [
                    'instructions' => $this->ophelper->getInstructions(),
                    'use_bypass' => true,
                    'payment_redirect_url' => $this->getPaymentRedirectUrl(),
                    'payment_template' => $this->ophelper->getPaymentTemplate(),
                    'method_groups' => $this->getEnabledPaymentMethodGroups(),
                    'payment_method_styles' => $this->wrapPaymentMethodStyles($storeId)
                ]
            ]
            ];
            foreach ($this->getEnabledPaymentMethodGroups() as $group) {

                $groupId = $group['id'];
                $config['payment'][self::CODE]['image'][$groupId] = '';

                $url = $this->assetRepository->getUrl('Op_Checkout::images/icon_' . $groupId . '.svg');
                if ($url) {
                    $config['payment'][self::CODE]['image'][$groupId] = $url;
                }
            }

        } catch (CheckoutException $e) {
            $config['payment'][self::CODE]['success'] = 0;
            return $config;
        }
        $config['payment'][self::CODE]['success'] = 1;
        return $config;
    }

    /**
     * Create payment page styles from the values entered in Op configuration.
     *
     * @param $storeId
     * @return string
     */
    protected function wrapPaymentMethodStyles($storeId)
    {
        $styles = '.checkout-group-collapsible{ background-color:' . $this->gatewayConfig->getPaymentGroupBgColor($storeId) . '; margin-top:1%; margin-bottom:2%;}';
        $styles .= '.checkout-group-collapsible.active{ background-color:' . $this->gatewayConfig->getPaymentGroupHighlightBgColor($storeId) . ';}';
        $styles .= '.checkout-group-collapsible span{ color:' . $this->gatewayConfig->getPaymentGroupTextColor($storeId) . ';}';
        $styles .= '.checkout-group-collapsible.active span{ color:' . $this->gatewayConfig->getPaymentGroupHighlightTextColor($storeId) . ';}';
        $styles .= '.checkout-group-collapsible.active li{ color:' . $this->gatewayConfig->getPaymentGroupHighlightTextColor($storeId) . '}';
        $styles .= '.checkout-payment-methods .checkout-payment-method.active{ border-color:' . $this->gatewayConfig->getPaymentMethodHighlightColor() . ';border-width:2px;}';
        $styles .= '.checkout-payment-methods .checkout-payment-method:hover, .checkout-payment-methods .checkout-payment-method:not(.active):hover { border-color:' . $this->gatewayConfig->getPaymentMethodHoverHighlight($storeId) . ';}';
        $styles .= $this->gatewayConfig->getAdditionalCss($storeId);
        return $styles;
    }

    protected function getPaymentRedirectUrl()
    {
        return 'opcheckout/redirect';
    }

    /**
     * Get all payment methods with order total value
     *
     * @return mixed
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function getAllPaymentMethods()
    {
        $orderValue = $this->checkoutSession->getQuote()->getGrandTotal();
        $uri = '/merchants/payment-providers?amount=' . $orderValue * 100;
        $merchantId = $this->ophelper->getMerchantId();
        $merchantSecret = $this->ophelper->getMerchantSecret();
        $method = 'get';

        $response = $this->apidata->getResponse($uri, '', $merchantId, $merchantSecret, $method);

        return $response['data'];
    }

    protected function getEnabledPaymentMethodGroups()
    {
        $responseData = $this->getAllPaymentMethods();

        $groupData = $this->getEnabledPaymentGroups($responseData);
        $groups = [];

        foreach ($groupData as $group) {
            $groups[] = [
                'id' => $group,
                'title' => __($group)
            ];
        }

        return $this->addMethodsToGroups($groups, $responseData);
    }

    protected function addMethodsToGroups($groups, $responseData)
    {
        foreach ($groups as $key => $group) {
            $groups[$key]['methods'] = $this->getEnabledPaymentMethodsByGroup($responseData, $group['id']);

            // Remove empty groups
            if (empty($groups[$key]['methods'])) {
                unset($groups[$key]);
            }
        }
        return array_values($groups);
    }

    protected function getEnabledPaymentMethodsByGroup($responseData, $groupId)
    {
        $allMethods = [];

        foreach ($responseData as $provider) {
            $allMethods[] = [
                'value' => $provider->id,
                'label' => $provider->id,
                'group' => $provider->group,
                'icon' => $provider->svg
            ];
        }

        $i = 1;

        foreach ($allMethods as $key => $method) {
            if ($method['group'] == $groupId) {
                $methods[] = [
                    'checkoutId' => $method['value'],
                    'id' => $method['value'] . $i++,
                    'title' => $method['label'],
                    'group' => $method['group'],
                    'icon' => $method['icon']
                ];
            }
        }

        return $methods;
    }

    protected function getEnabledPaymentGroups($responseData)
    {
        $allGroups = [];

        foreach ($responseData as $provider) {
            $allGroups[] = $provider->group;
        }

        return array_unique($allGroups);
    }
}
