<?php

namespace Op\Checkout\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Op\Checkout\Helper\Data as opHelper;
use Op\Checkout\Helper\ApiData as apiData;
use Magento\Payment\Helper\Data as PaymentHelper;

class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'checkout';

    protected $methodCodes = [
        self::CODE,
    ];
    protected $ophelper;
    protected $apidata;


    public function __construct(
        opHelper $ophelper,
        apiData $apidata,
        PaymentHelper $paymentHelper
    ) {
        $this->ophelper = $ophelper;
        $this->apidata = $apidata;


        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }
    }

    public function getConfig()
    {
        $config = [];
        $config['payment']['instructions'][self::CODE] = $this->ophelper->getInstructions(self::CODE);
        $config['payment']['use_bypass'][self::CODE] = true; //$this->getUseBypass($code);
        $config['payment']['method_groups'][self::CODE] = $this->getEnabledPaymentMethodGroups();
        $config['payment']['payment_redirect_url'][self::CODE] = $this->getPaymentRedirectUrl();
        $config['payment']['payment_template'][self::CODE] = $this->ophelper->getPaymentTemplate();
        return $config;
    }

    public function getPaymentRedirectUrl()
    {
        return 'checkout/redirect';
    }

    public function getAllPaymentMethods($orderValue = 25)
    {
        $uri = '/merchants/payment-providers?amount=' . $orderValue * 100;
        $merchantId = $this->ophelper->getMerchantId();
        $merchantSecret = $this->ophelper->getMerchantSecret();
        $method = 'get';

        $response = $this->apidata->getResponse($uri, '', $merchantId, $merchantSecret, $method);

        return $response['data'];
    }

    public function getEnabledPaymentMethodGroups()
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

        // Add methods to groups
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
                    'icon'  => $method['icon']
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
