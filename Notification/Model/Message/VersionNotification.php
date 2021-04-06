<?php

namespace Op\Checkout\Notification\Model\Message;

class VersionNotification implements \Magento\Framework\Notification\MessageInterface
{
    private $authSession;
    private $inboxFactory;
    private $componentRegistrar;
    private $notifierPool;

    public function __construct(
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\AdminNotification\Model\InboxFactory $inboxFactory,
        \Magento\Framework\Component\ComponentRegistrarInterface $componentRegistrar,
        \Magento\Framework\Notification\NotifierInterface $notifierPool
    ) {
        $this->authSession = $authSession;
        $this->inboxFactory = $inboxFactory;
        $this->componentRegistrar = $componentRegistrar;
        $this->notifierPool = $notifierPool;
    }

    const MESSAGE_IDENTITY = 'OP Checkout Version Control message';

    /**
     * Retrieve unique system message identity
     *
     * @return string
     */
    public function getIdentity()
    {
        return self::MESSAGE_IDENTITY;
    }

    /**
     * Check whether the system message should be shown
     *
     * @return bool
     */
    public function isDisplayed()
    {
        // Only execute the query the first time you access the Admin page
//        if ($this->authSession->isFirstPageAfterLogin()) {
        //$this->addNotification();
        try {
            $dateModel = $this->dateTimeFactory->create();
            $githubContent = $this->getDecodedContentFromGithub();
            $githubContent['tag_name'] = $this->getVersionFrom($githubContent['tag_name']);
            $this->setSessionData("OPCheckoutGithubVersion", $githubContent);
            $title = "New OP Checkout extension version " . $githubContent['tag_name'] . " available!";
            $versionData[] = [
                'severity' => self::SEVERITY_CRITICAL,
                'date_added' => date('Y-m-d H:i:s', strtotime("2021-04-05")),
                'title' => $title,
                'description' => $githubContent['body'],
                'url' => $githubContent['html_url'],
            ];

            /*
             * The parse function checks if the $versionData message exists in the inbox,
             * otherwise it will create it and add it to the inbox.
             */
            $this->inboxFactory->create()->parse(array_reverse($versionData));
            return true;
            /*
             * This will compare the currently installed version with the latest available one.
             * A message will appear after the login if the two are not matching.
             */
            if ($this->getModuleVersion() != $githubContent['tag_name']) {
                return true;
            }
        } catch (\Exception $e) {
            return false;
//            }
        }
        return false;
    }

    /**
     * Retrieve system message text
     *
     * @return \Magento\Framework\Phrase
     */
    public function getText()
    {
        $githubContent = $this->getSessionData("OPCheckoutGithubVersion");
        $message = __("A new Op Checkout extension version is now available: ");
        $message .= __(
            "<a href= \"" . $githubContent['html_url'] . "\" target='_blank'> " . $githubContent['tag_name'] . "!</a>"
        );
        $message .= __(
            " You are running the " . $this->getModuleVersion(
            ) . " version. We advise to update your extension."
        );
        return __($message);
    }

    /**
     * Retrieve system message severity
     *
     * @return int
     */
    public function getSeverity()
    {
        return self::SEVERITY_CRITICAL;
    }

    public function addNotification()
    {
        $this->notifierPool->addNotice(
            'New version of OP checkout out!',
            'Message description text.'
        );
    }

    private function getDecodedContentFromGithub()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.github.com/repos/OPMerchantServices/op-payment-service-for-magento-2/releases/latest');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'magento');
        $content = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($content, true);
        return $json;
    }

    /**
     * Set the current value for the backend session
     */
    private function setSessionData($key, $value)
    {
        return $this->authSession->setData($key, $value);
    }

    /**
     * Retrieve the session value
     */
    private function getSessionData($key, $remove = false)
    {
        return $this->authSession->getData($key, $remove);
    }

    /**
     * Get the current module version from composer.json
     * @return mixed|string
     */
    private function getModuleVersion()
    {
        $moduleDir = $this->componentRegistrar->getPath(
            \Magento\Framework\Component\ComponentRegistrar::MODULE,
            'Op_Checkout'
        );

        $composerJson = file_get_contents($moduleDir . '/composer.json');
        $composerJson = json_decode($composerJson, true);

        if (empty($composerJson['version'])) {
            return "Version is not available in composer.json";
        }

        return $composerJson['version'];
    }

    /**
     * Extract a version number from the tag name
     * @param $tagName
     * @return false|string
     */
    private function getVersionFrom($tagName)
    {
        $vpos = strpos($tagName, 'v');
        return $vpos !== false ? substr($tagName, $vpos + 1) : $tagName;
    }
}
