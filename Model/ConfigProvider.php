<?php

namespace Op\Checkout\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Store\Model\StoreManagerInterface;
use Op\Checkout\Gateway\Config\Config;
use Op\Checkout\Helper\ApiData as apiData;
use Op\Checkout\Helper\Data as opHelper;
use Op\Checkout\Model\Adapter\Adapter;
use Psr\Log\LoggerInterface;

/**
 * Class ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'opcheckout';
    const CREDITCARD_GROUP_ID = 'creditcard';

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
     * @var Resolver
     */
    private $localeResolver;
    /**
     * @var Adapter
     */
    private $opAdapter;
    /**
     * @var LoggerInterface
     */
    private $log;

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
     * @param Resolver $localeResolver
     * @param Adapter $opAdapter
     * @param LoggerInterface $log
     * @throws LocalizedException
     */
    public function __construct(
        opHelper $ophelper,
        apiData $apidata,
        PaymentHelper $paymentHelper,
        Session $checkoutSession,
        Config $gatewayConfig,
        AssetRepository $assetRepository,
        StoreManagerInterface $storeManager,
        Resolver $localeResolver,
        Adapter $opAdapter,
        LoggerInterface $log
    ) {
        $this->ophelper = $ophelper;
        $this->apidata = $apidata;
        $this->checkoutSession = $checkoutSession;
        $this->gatewayConfig = $gatewayConfig;
        $this->assetRepository = $assetRepository;
        $this->storeManager = $storeManager;
        $this->localeResolver = $localeResolver;
        $this->opAdapter = $opAdapter;
        $this->log = $log;
        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     */
    public function getConfig()
    {
        $storeId = $this->storeManager->getStore()->getId();
        $config = [];
        $status = $this->gatewayConfig->isActive($storeId);

        if (!$status) {
            return $config;
        }
        try {
            $groupData = $this->getAllPaymentMethods();

            $config = [
                'payment' => [
                    self::CODE => [
                        'instructions' => $this->gatewayConfig->getInstructions(),
                        'skip_method_selection' => $this->gatewayConfig->getSkipBankSelection(),
                        'payment_redirect_url' => $this->getPaymentRedirectUrl(),
                        'payment_template' => $this->gatewayConfig->getPaymentTemplate(),
                        'method_groups' => $this->handlePaymentProviderGroupData($groupData['groups']),
                        'payment_terms' => $groupData['terms'],
                        'payment_method_styles' => $this->wrapPaymentMethodStyles($storeId)
                    ]
                ]
            ];
            //Get images for payment groups
            foreach ($groupData['groups'] as $group) {
                $groupId = $group['id'];
                $groupImage = $group['icon'];
                $config['payment'][self::CODE]['image'][$groupId] = '';
                if ($groupImage) {
                    $config['payment'][self::CODE]['image'][$groupId] = $groupImage;
                }
            }
        } catch (\Exception $e) {
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
        $styles .= '.checkout-group-collapsible li{ color:' . $this->gatewayConfig->getPaymentGroupTextColor($storeId) . '}';
        $styles .= '.checkout-group-collapsible.active span{ color:' . $this->gatewayConfig->getPaymentGroupHighlightTextColor($storeId) . ';}';
        $styles .= '.checkout-group-collapsible.active li{ color:' . $this->gatewayConfig->getPaymentGroupHighlightTextColor($storeId) . '}';
        $styles .= '.checkout-group-collapsible:hover:not(.active) {background-color:' . $this->gatewayConfig->getPaymentGroupHoverColor() . '}';
        $styles .= '.checkout-payment-methods .checkout-payment-method.active{ border-color:' . $this->gatewayConfig->getPaymentMethodHighlightColor($storeId) . ';border-width:2px;}';
        $styles .= '.checkout-payment-methods .checkout-payment-method:hover, .checkout-payment-methods .checkout-payment-method:not(.active):hover { border-color:' . $this->gatewayConfig->getPaymentMethodHoverHighlight($storeId) . ';}';
        $styles .= $this->gatewayConfig->getAdditionalCss($storeId);
        return $styles;
    }

    /**
     * @return string
     */
    protected function getPaymentRedirectUrl()
    {
        return 'opcheckout/redirect';
    }

    /**
     * Get all payment methods and groups with order total value
     *
     * @return mixed|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function getAllPaymentMethods()
    {
        $orderValue = $this->checkoutSession->getQuote()->getGrandTotal();

        $response = $this->apidata->processApiRequest(
            'payment_providers',
            null,
            round($orderValue * 100)
        );

        $errorMsg = $response['error'];

        if (isset($errorMsg)) {
            $this->log->error(
                'Error occurred during email refund: '
                . $errorMsg
            );
            $this->ophelper->processError($errorMsg);
        }

        return $response["data"];
    }

    /**
     * Create array for payment providers and groups containing unique method id
     *
     * @param $responseData
     * @return array
     */
    protected function handlePaymentProviderGroupData($responseData)
    {
        $allMethods = [];
        $allGroups = [];
        foreach ($responseData as $group) {
            $allGroups[] = [
                'id' => $group['id'],
                'name' => $group['name'],
                'icon' => $group['icon']
            ];
            foreach ($group['providers'] as $provider) {
                $allMethods[] = $provider;
            }
        }
        foreach ($allGroups as $key => $group) {
            $allGroups[$key]['providers'] = $this->addProviderDataToGroup($allMethods, $group['id']);
        }
        return array_values($allGroups);
    }

    /**
     * Add payment method data to group
     *
     * @param $responseData
     * @param $groupId
     * @return array
     */
    protected function addProviderDataToGroup($responseData, $groupId)
    {
        $i = 1;

        foreach ($responseData as $key => $method) {
            if ($method->getGroup() == $groupId) {
                $id = $groupId === self::CREDITCARD_GROUP_ID ? $method->getId() . '-' . $i++ : $method->getId();
                $methods[] = [
                    'checkoutId' => $method->getId(),
                    'id' => $id,
                    'name' => $method->getName(),
                    'group' => $method->getGroup(),
                    'icon' => $method->getIcon(),
                    'svg' => $method->getSvg()
                ];
            }
        }
        return $methods;
    }
}
