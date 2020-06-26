<?php
namespace Op\Checkout\Gateway\Http\Client;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Op\Checkout\Helper\ApiData;
use OpMerchantServices\SDK\Response\RefundResponse;
use Psr\Log\LoggerInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransactionRefund implements ClientInterface
{
    /**
     * @var ApiData
     */
    private $apiData;

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * TransactionRefund constructor.
     *
     * @param ApiData $apiData
     * @param LoggerInterface $log
     */
    public function __construct(
        ApiData $apiData,
        LoggerInterface $log
    ) {
        $this->apiData = $apiData;
        $this->log = $log;
    }

    /**
     * @param TransferInterface $transferObject
     * @return array|void
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $request = $transferObject->getBody();

        $data = [
            'status' => false
        ];

        /** @var RefundResponse $response */
        $response = $this->postRefundRequest($request);

        if ($response) {
            $data['status'] = $response->getStatus();
        }
        return $data;
    }

    /**
     * @param $request
     * @return bool
     */
    protected function postRefundRequest($request)
    {
        $response = $this->apiData->processApiRequest(
            'refund',
            $request['order'],
            $request['amount'],
            $request['parent_transaction_id']
        );
        $error = $response["error"];

        if (isset($error)) {
            $this->log->error(
                'Error occurred during refund: '
                . $error
                . ', Falling back to to email refund.'
            );
            $emailResponse = $this->apiData->processApiRequest(
                'email_refund',
                $request['order'],
                $request['amount'],
                $request['parent_transaction_id']
            );
            $emailError = $emailResponse["error"];
            if (isset($emailError)) {
                $this->log->error(
                    'Error occurred during email refund: '
                    . $emailError
                );
                return false;
            }
            return $response["data"];
        }
        return $response["data"];
    }
}
