<?php

namespace Op\Checkout\Helper;

use Magento\Checkout\Model\Session;
use Magento\Quote\Model\QuoteRepository;
use Op\Checkout\Gateway\Validator\ResponseValidator;
use Op\Checkout\Model\CheckoutException;
use Op\Checkout\Model\TransactionSuccessException;
use Op\Checkout\Model\ReceiptDataProvider;
use Magento\Framework\App\CacheInterface;


/**
 * Class ProcessPayment
 */
class ProcessPayment
{
    const PAYMENT_PROCESSING_CACHE_PREFIX = "op-processing-payment-";

    /**
     * @var ResponseValidator
     */
    private $responseValidator;

    /**
     * @var ReceiptDataProvider
     */
    private $receiptDataProvider;

    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * ProcessPayment constructor.
     * @param ResponseValidator $responseValidator
     * @param ReceiptDataProvider $receiptDataProvider
     * @param QuoteRepository $quoteRepository
     * @param CacheInterface $cache
     */
    public function __construct(
        ResponseValidator $responseValidator,
        ReceiptDataProvider $receiptDataProvider,
        QuoteRepository $quoteRepository,
        CacheInterface $cache
    ) {
        $this->responseValidator = $responseValidator;
        $this->receiptDataProvider = $receiptDataProvider;
        $this->quoteRepository = $quoteRepository;
        $this->cache = $cache;
    }

    /**
     * @param array $params
     * @param Session $session
     * @return array
     */
    public function process($params, $session)
    {
        /** @var array $errors */
        $errors = [];

        /** @var \Magento\Payment\Gateway\Validator\Result $validationResponse */
        $validationResponse = $this->responseValidator->validate($params);

        if (!$validationResponse->isValid()) { // if response params are not valid, redirect back to the cart

            /** @var string $failMessage */
            foreach ($validationResponse->getFailsDescription() as $failMessage) {
                array_push($errors, $failMessage);
            }

            $session->restoreQuote(); // should it be restored?

            return $errors;
        }

        /** @var int|string|null $orderNo */
        $orderNo = $params['checkout-reference'];

        /** @var int $count */
        $count = 0;
        while ($this->isPaymentLocked($orderNo) && $count < 5) {
            $count ++;
            sleep($count);
        }

        $this->lockProcessingPayment($orderNo);

        /** @var array $ret */
        $ret = $this->processPayment($params, $session, $orderNo);

        $this->unlockProcessingPayment($orderNo);

        return array_merge($ret, $errors);
    }

    /**
     * @param array $params
     * @param Session $session
     * @param $orderNo
     * @return array
     */
    protected function processPayment($params, $session, $orderNo)
    {
        /** @var array $errors */
        $errors = [];

        /** @var bool $isValid */
        $isValid = true;

        /** @var null|string $failMessage */
        $failMessage = null;

        if (empty($orderNo)) {
            $session->restoreQuote();

            return $errors;
        }

        try {
            /*
            there are 2 calls called from OP Checkout.
            One call is when a customer is redirected back to the magento store.
            There is also the second, parallel, call from OP Checkout to make sure the payment is confirmed (if for any reason customer was not redirected back to the store).
            Sometimes, the calls are called with too small time difference between them that Magento cannot handle them. The second call must be ignored or slowed down.
            */
            $this->receiptDataProvider->execute($params);
        } catch (CheckoutException $exception) {
            $isValid = false;
            $failMessage = $exception->getMessage();
            array_push($errors, $failMessage);
        } catch (TransactionSuccessException $successException) {
            $isValid = true;
        }

        if ($isValid == false) {
            $session->restoreQuote();
        } else {
            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $session->getQuote();
            $quote->setIsActive(false);
            $this->quoteRepository->save($quote);
        }

        return $errors;
    }

    /**
     * @param int $orderId
     */
    protected function lockProcessingPayment($orderId)
    {
        /** @var string $identifier */
        $identifier = self::PAYMENT_PROCESSING_CACHE_PREFIX . $orderId;

        $this->cache->save("locked", $identifier);
    }

    /**
     * @param int $orderId
     */
    protected function unlockProcessingPayment($orderId)
    {
        /** @var string $identifier */
        $identifier = self::PAYMENT_PROCESSING_CACHE_PREFIX . $orderId;

        $this->cache->remove($identifier);
    }

    /**
     * @param int $orderId
     * @return bool
     */
    protected function isPaymentLocked($orderId) {
        /** @var string $identifier */
        $identifier = self::PAYMENT_PROCESSING_CACHE_PREFIX . $orderId;

        return $this->cache->load($identifier)?true:false;
    }
}