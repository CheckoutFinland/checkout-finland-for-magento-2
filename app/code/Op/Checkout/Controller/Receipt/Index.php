<?php

namespace Op\Checkout\Controller\Receipt;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Quote\Model\QuoteRepository;
use Op\Checkout\Gateway\Validator\ResponseValidator;
use Op\Checkout\Model\CheckoutException;
use Op\Checkout\Model\TransactionSuccessException;
use Op\Checkout\Model\ReceiptDataProvider;

class Index extends \Magento\Framework\App\Action\Action
{
    protected $session;
    protected $responseValidator;
    protected $receiptDataProvider;
    protected $quoteRepository;

    public function __construct(
        Context $context,
        Session $session,
        ResponseValidator $responseValidator,
        QuoteRepository $quoteRepository,
        ReceiptDataProvider $receiptDataProvider
    ) {
        parent::__construct($context);
        $this->session = $session;
        $this->responseValidator = $responseValidator;
        $this->receiptDataProvider = $receiptDataProvider;
        $this->quoteRepository = $quoteRepository;
    }

    public function execute()
    {
        //exit;
        $isValid = true;
        $failMessage = null;
        $validationResponse = $this->responseValidator->validate($this->getRequest()->getParams());

        if (!$validationResponse->isValid()) {
            foreach ($validationResponse->getFailsDescription() as $failMessage) {
                $this->messageManager->addErrorMessage($failMessage);
            }
            $this->session->restoreQuote();
            $this->_redirect('checkout/cart');
            return;
        }

        $orderNo = $this->getRequest()->getParam('checkout-reference');
        if (empty($orderNo)) {
            $this->session->restoreQuote();
            $this->messageManager->addErrorMessage(__('Order number is empty'));
            $this->_redirect('checkout/cart');
            return;
        }

        try {
            $this->receiptDataProvider->execute($this->getRequest()->getParams());
        } catch (CheckoutException $exception) {
            $isValid = false;
            $failMessage = $exception->getMessage();
        } catch (TransactionSuccessException $successException) {
            $isValid = true;
        }

        if ($isValid == false) {
            $this->session->restoreQuote();
            $this->messageManager->addErrorMessage(__($failMessage));
            $this->_redirect('checkout/cart');
        } else {
            $quote = $this->session->getQuote();
            $quote->setIsActive(false);
            $this->quoteRepository->save($quote);
            $this->_redirect('checkout/onepage/success');
        }
        return;
    }
}
