<?php
declare(strict_types=1);

namespace Op\Checkout\Api;

interface OpPaymentMethodsManagementInterface
{

    /**
     * GET for OpPaymentMethods api
     * @return string
     */
    public function getOpPaymentMethods();
}

