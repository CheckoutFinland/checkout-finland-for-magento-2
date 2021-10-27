<?php

namespace Op\Checkout\Notification\Model\Message;

class VersionNotification implements \Magento\Framework\Notification\MessageInterface
{
    /**
     * @var \Magento\Backend\Model\Auth\Session
     */
    private $authSession;
    /**
     * @var \Magento\AdminNotification\Model\InboxFactory
     */
    private $inboxFactory;
    /**
     * @var \Magento\Framework\Component\ComponentRegistrarInterface
     */
    private $componentRegistrar;
    /**
     * @var \Magento\Framework\Notification\NotifierInterface
     */
    private $notifierPool;
    /**
     * @var \Op\Checkout\Helper\Version
     */
    private $versionHelper;

    public function __construct(
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\AdminNotification\Model\InboxFactory $inboxFactory,
        \Magento\Framework\Component\ComponentRegistrarInterface $componentRegistrar,
        \Magento\Framework\Notification\NotifierInterface $notifierPool,
        \Op\Checkout\Helper\Version $versionHelper
    ) {
        $this->authSession = $authSession;
        $this->inboxFactory = $inboxFactory;
        $this->componentRegistrar = $componentRegistrar;
        $this->notifierPool = $notifierPool;
        $this->versionHelper = $versionHelper;
    }

    const MESSAGE_IDENTITY = 'Checkout Finland Version Control message';

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
        try {
            $url = "https://github.com/paytrail/paytrail-for-adobe-commerce";
            $versionData[] = [
                'severity' => self::SEVERITY_CRITICAL,
                'date_added' => date('Y-m-d H:i:s'),
                'title' => __("Checkout Finland module is now DEPRECATED"),
                'description' => __("The module has been rebranded and is now available at ") . $url,
                'url' => $url,
            ];
            /*
             * The parse function checks if the $versionData message exists in the inbox,
             * otherwise it will create it and add it to the inbox.
             */
            $this->inboxFactory->create()->parse(array_reverse($versionData));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Retrieve system message text
     *
     * @return \Magento\Framework\Phrase
     */
    public function getText()
    {
        $url = "https://github.com/paytrail/paytrail-for-adobe-commerce";
        $message = __("Checkout Finland module is now DEPRECATED. New module is available as paytrail/paytrail-for-adobe-commerce. ")
            . " <a href= \"" . $url . "\" target='_blank'> " . __("Click here for more info");
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

    /**
     * Set the current value for the backend session
     * @param $key
     * @param $value
     * @return mixed
     */
    private function setSessionData($key, $value)
    {
        return $this->authSession->setData($key, $value);
    }

    /**
     * Retrieve the session value
     * @param $key
     * @param bool $remove
     * @return mixed
     */
    private function getSessionData($key, $remove = false)
    {
        return $this->authSession->getData($key, $remove);
    }
}
