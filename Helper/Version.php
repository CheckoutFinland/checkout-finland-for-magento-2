<?php

namespace Op\Checkout\Helper;

class Version
{
    /**
     * @var string
     */
    const GIT_URL = 'https://api.github.com/repos/OPMerchantServices/op-payment-service-for-magento-2/releases/latest';

    /**
     * For extension version
     *
     * @var \Magento\Framework\Module\ModuleListInterface
     */
    protected $moduleList;

    public function __construct(
        \Magento\Framework\Module\ModuleListInterface $moduleList
    ) {
        $this->moduleList = $moduleList;
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
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::GIT_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'magento');
        $content = curl_exec($ch);
        curl_close($ch);
        return json_decode($content, true);
    }
}
