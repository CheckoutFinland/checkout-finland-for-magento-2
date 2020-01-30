<?php

namespace Op\Checkout\Controller\Receipt;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Quote\Model\QuoteRepository;
use Op\Checkout\Gateway\Validator\ResponseValidator;
use Op\Checkout\Model\CheckoutException;
use Op\Checkout\Model\TransactionSuccessException;
use Op\Checkout\Model\ReceiptDataProvider;

/**
 * Class Index
 * @package Op\Checkout\Controller\Receipt
 */
class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var Session
     */
    protected $session;

    /**
     * @var ResponseValidator
     */
    protected $responseValidator;

    /**
     * @var ReceiptDataProvider
     */
    protected $receiptDataProvider;

    /**
     * @var QuoteRepository
     */
    protected $quoteRepository;

    /**
     * Index constructor.
     * @param Context $context
     * @param Session $session
     * @param ResponseValidator $responseValidator
     * @param QuoteRepository $quoteRepository
     * @param ReceiptDataProvider $receiptDataProvider
     */
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

    /**
     *  execute function
     */
    public function execute()
    {
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
            if (!$this->receiptDataProvider->execute($this->getRequest()->getParams())) {
                $this->_redirect('checkout/onepage/success'); // the second call, just in case the customer's call is the second one

                return; // avoiding conflict if a callback url is called and processed twice at the same time
            }
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
