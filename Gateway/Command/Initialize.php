<?php

namespace Op\Checkout\Gateway\Command;

use Magento\Payment\Gateway\CommandInterface;

class Initialize implements CommandInterface
{
    protected $opHelper;
    public function __construct(\Op\Checkout\Helper\Data $opHelper)
    {
        $this->opHelper = $opHelper;
    }

    public function execute(array $commandSubject)
    {
        $payment = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($commandSubject);
        $stateObject = \Magento\Payment\Gateway\Helper\SubjectReader::readStateObject($commandSubject);

        $payment = $payment->getPayment();
        $payment->setIsTransactionPending(true);
        $payment->setIsTransactionIsClosed(false);
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);

        $stateObject->setIsNotified(false);

        $stateObject->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);

        $stateObject->setIsNotified(false);

        return $this;
    }
}
