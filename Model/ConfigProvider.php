<?php

namespace Op\Checkout\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Helper\Data as PaymentHelper;
use Op\Checkout\Helper\ApiData as apiData;
use Op\Checkout\Helper\Data as opHelper;

/**
 * Class ConfigProvider
 */
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
     * ConfigProvider constructor.
     * @param opHelper $ophelper
     * @param apiData $apidata
     * @param PaymentHelper $paymentHelper
     * @param Session $checkoutSession
     */
    public function __construct(
        opHelper $ophelper,
        apiData $apidata,
        PaymentHelper $paymentHelper,
        Session $checkoutSession
    ) {
        $this->ophelper = $ophelper;
        $this->apidata = $apidata;
        $this->checkoutSession = $checkoutSession;

        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        $config = [];
        $status = $this->ophelper->getMethodStatus();
        if (!$status) {
            return $config;
        }
        try {
            $config['payment']['instructions'][self::CODE] = $this->ophelper->getInstructions(self::CODE);
            $config['payment']['use_bypass'][self::CODE] = true;
            $config['payment']['payment_redirect_url'][self::CODE] = $this->getPaymentRedirectUrl();
            $config['payment']['payment_template'][self::CODE] = $this->ophelper->getPaymentTemplate();
            $config['payment']['method_groups'][self::CODE] = $this->getEnabledPaymentMethodGroups();
        } catch (CheckoutException $e) {
            $config['payment']['success'][self::CODE] = 0;
            return $config;
        }
        $config['payment']['success'][self::CODE] = 1;
        return $config;
    }

    /**
     * @return string
     */
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

    /**
     * @return array
     */
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

    /**
     * @param $groups
     * @param $responseData
     * @return array
     */
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

    /**
     * @param $responseData
     * @param $groupId
     * @return array
     */
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

    /**
     * @param $responseData
     * @return array
     */
    protected function getEnabledPaymentGroups($responseData)
    {
        $allGroups = [];

        foreach ($responseData as $provider) {
            $allGroups[] = $provider->group;
        }

        return array_unique($allGroups);
    }
}
