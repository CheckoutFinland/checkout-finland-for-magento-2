<?php
namespace Op\Checkout\Gateway\Response;

use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class RefundHandler implements HandlerInterface
{
    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * RefundHandler constructor.
     *
     * @param ManagerInterface $messageManager
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        ManagerInterface $messageManager,
        SubjectReader $subjectReader
    ) {
        $this->messageManager = $messageManager;
        $this->subjectReader = $subjectReader;
    }

    /**
     * @param array $handlingSubject
     * @param array $response
     */
    public function handle(array $handlingSubject, array $response)
    {
        $payment = $this->subjectReader->readPayment($handlingSubject);

        $payment = $payment->getPayment();
        $transactionId = $payment->getTransactionId() . "-" . time();
        $payment->setIsTransactionClosed(true);
        $payment->setTransactionId($transactionId);
        $payment->setShouldCloseParentTransaction(false);

        $this->messageManager->addSuccessMessage(__('Op Payment Service refund successful.'));
    }
}
