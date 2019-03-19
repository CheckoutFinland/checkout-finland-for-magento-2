<?php

namespace Op\Checkout\Helper;

use Magento\Sales\Model\Order\Item;

class OrderItems
{
    protected $log;
    protected $_taxHelper;

    public function __construct(
        \Psr\Log\LoggerInterface $log,
        \Magento\Tax\Helper\Data $taxHelper
    ) {
        $this->log = $log;
        $this->_taxHelper = $taxHelper;
    }

    public function getOrderItems($order)
    {
        $items = [];

        foreach ($this->_itemArgs($order) as $i => $item) {
            $items[] = array(
                'unitPrice' => $item['price'] * 100,
                'units' => $item['amount'],
                'vatPercentage' => $item['vat'],
                'description' => $item['title'],
                'productCode' => $item['code'],
                'deliveryDate' => date('Y-m-d'),
            );
        }

        return $items;
    }

    public function _itemArgs($order)
    {
        $items = array();

        # Add line items
        /** @var $item Item */
        foreach ($order->getAllItems() as $key => $item) {
            // When in grouped or bundle product price is dynamic (product_calculations = 0)
            // then also the child products has prices so we set
            if ($item->getChildrenItems() && !$item->getProductOptions()['product_calculations']) {
                $items[] = array(
                    'title' => $item->getName(),
                    'code' => $item->getSku(),
                    'amount' => floatval($item->getQtyOrdered()),
                    'price' => 0,
                    'vat' => 0,
                    'discount' => 0,
                    'type' => 1,
                );
            } else {
                $items[] = array(
                    'title' => $item->getName(),
                    'code' => $item->getSku(),
                    'amount' => floatval($item->getQtyOrdered()),
                    'price' => floatval($item->getPriceInclTax()),
                    'vat' => round(floatval($item->getTaxPercent())),
                    'discount' => 0,
                    'type' => 1,
                );
            }
        }

        # Add shipping
        if (!$order->getIsVirtual()) {
            $shippingExclTax = $order->getShippingAmount();
            $shippingInclTax = $order->getShippingInclTax();
            $shippingTaxPct = 0;
            if ($shippingExclTax > 0) {
                $shippingTaxPct = ($shippingInclTax - $shippingExclTax) / $shippingExclTax * 100;
            }

            if ($order->getShippingDescription()) {
                $shippingLabel = $order->getShippingDescription();
            } else {
                $shippingLabel = __('Shipping');
            }

            $items[] = array(
                'title' => $shippingLabel,
                'code' => '',
                'amount' => 1,
                'price' => floatval($order->getShippingInclTax()),
                'vat' => round(floatval($shippingTaxPct)),
                'discount' => 0,
                'type' => 2,
            );
        }

        # Add discount
        if (abs($order->getDiscountAmount()) > 0) {
            $discountData = $this->_getDiscountData($order);
            $discountInclTax = $discountData->getDiscountInclTax();
            $discountExclTax = $discountData->getDiscountExclTax();
            $discountTaxPct = 0;
            if ($discountExclTax > 0) {
                $discountTaxPct = ($discountInclTax - $discountExclTax) / $discountExclTax * 100;
            }

            if ($order->getDiscountDescription()) {
                $discountLabel = $order->getDiscountDescription();
            } else {
                $discountLabel = __('Discount');
            }

            $items[] = array(
                'title' => (string) $discountLabel,
                'code' => '',
                'amount' => 1,
                'price' => floatval($discountData->getDiscountInclTax()) * -1,
                'vat' => round(floatval($discountTaxPct)),
                'discount' => 0,
                'type' => 3
            );
        }

        return $items;
    }

    private function _getDiscountData(\Magento\Sales\Model\Order $order)
    {
        $discountIncl = 0;
        $discountExcl = 0;

        // Get product discount amounts
        foreach ($order->getAllItems() as $item) {
            if (!$this->_taxHelper->priceIncludesTax()) {
                $discountExcl += $item->getDiscountAmount();
                $discountIncl += $item->getDiscountAmount() * (($item->getTaxPercent() / 100) + 1);
            } else {
                $discountExcl += $item->getDiscountAmount() / (($item->getTaxPercent() / 100) + 1);
                $discountIncl += $item->getDiscountAmount();
            }
        }

        // Get shipping tax rate
        if ((float) $order->getShippingInclTax() && (float) $order->getShippingAmount()) {
            $shippingTaxRate = $order->getShippingInclTax() / $order->getShippingAmount();
        } else {
            $shippingTaxRate = 1;
        }

        // Add / exclude shipping tax
        $shippingDiscount = (float) $order->getShippingDiscountAmount();
        if (!$this->_taxHelper->priceIncludesTax()) {
            $discountIncl += $shippingDiscount * $shippingTaxRate;
            $discountExcl += $shippingDiscount;
        } else {
            $discountIncl += $shippingDiscount;
            $discountExcl += $shippingDiscount / $shippingTaxRate;
        }

        $return = new \Magento\Framework\DataObject;
        return $return->setDiscountInclTax($discountIncl)->setDiscountExclTax($discountExcl);
    }
}