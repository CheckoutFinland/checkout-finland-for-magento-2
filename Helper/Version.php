<?php

namespace Op\Checkout\Helper;

class Version
{
    /**
     * @var string
     */
    const GIT_URL = 'https://api.github.com/repos/CheckoutFinland/checkout-finland-for-magento-2/releases/latest';

    /**
     * For extension version
     *
     * @var \Magento\Framework\Module\ModuleListInterface
     */
    protected $moduleList;

    /**
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    protected $curlClient;

    public function __construct(
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\HTTP\Client\Curl $curlClient
    ) {
        $this->moduleList = $moduleList;
        $this->curlClient = $curlClient;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        if ($moduleInfo = $this->moduleList->getOne('Op_Checkout')) {
            return $moduleInfo['setup_version'];
        }
        return '-';
    }

    /**
     * @return mixed
     */
    public function getDecodedContentFromGithub()
    {
        $options = [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_USERAGENT => 'magento'
        ];
        $this->curlClient->setOptions($options);
        $this->curlClient->get(self::GIT_URL);
        return json_decode($this->curlClient->getBody(), true);
    }
}
