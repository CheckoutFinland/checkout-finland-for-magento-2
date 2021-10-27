# Checkout Finland Magento 2 Module

## DEPRECATED

**This extension has been deprecated**.  All of its functionality now exists as the rebranded 
[`paytrail/paytrail-for-adobe-commerce`](https://github.com/paytrail/paytrail-for-adobe-commerce) module. 
It is advised to upgrade to the new module.

Checkout Finland's payment methods for your Magento 2 web store

***Always perform a backup of your database and source code before installing any Magento 2 extensions.***

This module has been tested on Magento 2.4.2, 2.4.1 and 2.3.X {community|commerce} versions. Support for 2.2.X has ended and compatibility with older versions cannot be guaranteed.

Magento system requirements per tested version can be found on https://devdocs.magento.com/guides/v2.4/install-gde/system-requirements.html

## Features
This payment module has the following features:
- Payment methods provided by Checkout Finland payment service
- The ability to restore and ship a cancelled order
- Support for delayed payments (Collector etc.)
- Support for multiple stores within a single Magento 2 instance

## Updating

<b>With the release of 1.3.0 the module has been rebranded. If updating from older versions this requires manually uninstalling the old version with the following command before updating:</b><br/>
```composer remove op-merchant-services/op-payment-service-for-magento-2```

## Installation

#### Via Composer

<b>After introducing Checkout Finland PHP-SDK in version 1.2.0, the module only supports installation via composer.</b>

Steps:
1. Make sure that you have Magento file system owner rights.
2. Navigate to your Magento root folder on the command line and enter: <br/>```composer require checkout-finland/checkout-finland-for-magento-2:<latest_version> --no-update```
3. If your credentials are asked, enter your Magento marketplace access keys.
4. Enter command: <br/> ```composer update checkout-finland/checkout-finland-for-magento-2```
5. Run the following commands: <br/> ``` php bin/magento module:enable Op_Checkout ``` <br/> ```php bin/magento setup:upgrade``` <br/>```php bin/magento setup:di:compile``` <br/>```bin/magento setup:static-content:deploy``` 
6. Navigate to Magento admin interface and select __Stores -> Store Configuration -> Sales -> Payment Methods -> Checkout Finland__
7. Enter your credentials and enable the module (Test credentials: _375917 - SAIPPUAKAUPPIAS_)
8. Clear the cache 

## Usage
The module settings can be found from:
__Stores -> Configuration -> Sales -> Payment Methods -> Checkout Finland__

The module has the following settings:
- __Enable__: Defines whether the payment method is enabled or not *(Input: Yes / No)*
- __Skip bank selection__: Defines whether the bank selection will be skipped *(Input: Yes / No)*
- __Merchant ID__: Your Checkout Finland merchant ID *(Input: Text)*
- __Merchant Secret__: Your Checkout Finland merchant secret *(Input: Secret)*
- __New Order Status__: A custom status for a new order paid for with Checkout Finland *(Input: Selection)*
- __Email Address For Notifications__: If a payment has been processed after the order has been cancelled, a notification will be sent to the merchant so that they can reactivate and ship the order *(Input: Email address)* 
- __Payment from Applicable Countries__: Allow payments from all countries or specific countries *(Input: All / Specific)*
- __Payment from Specific Countries__: If the previous setting has been set to specific countries, this list can define the allowed countries *(Input: Selection)*

## Refunds
This payment module supports online refunds.

Steps:
1. Navigate to __Sales -> Orders__ and select the order you need to fully or partially refund
2. Select Invoices from Order View side bar
3. Select the invoice
4. Select Credit Memo
5. Define the items you want to refund and optionally define an adjustment fee
6. Click Refund

## Canceled order payment email notification
If the customer closes the browser window right after completing the payment BUT before returning to the store, Magento is left with a “Pending payment” status for the order. This status has a timeout, so if the payment confirmation does not arrive within 8 hours of the purchase, Magento automatically cancels the order. Checkout Finland informs Magento of a payment that has gone through, but it may take over 8 hours.

When the confirmation is finally made, Magento registers the transaction to the order and changes the order status to Processing. But since the stock may have changed in the interim, the items are still cancelled. The merchant will receive an email informing about the payment that has gone through, but they have to manually go to said order, make sure the items are still available, and click “Restore order” to be able to ship it.

__Adjust the timeout__<br/>
The timeout period of 8 hours can be adjusted in Magento configuration. A longer period may allow for Checkout Finland to confirm the order before it gets canceled, but it also reserves the stock for that exact time.
1. Go to __Stores -> Configuration -> Sales -> Sales -> Orders Cron Settings__
2. Adjust the __Pending Payment Order Lifetime (minutes)__ value to your liking.

## Order status
__Pending Payment__<br/>
Assigned to an order when customer is redirected to the payment provider of their choosing.

__Pending Checkout__<br/>
Assigned to an order if Checkout Finland is still waiting for a confirmation of payment. Applies to invoices, such as Collector.

__Processing__<br/>
Assigned to an order once payment is completed and items are ready for shipping.

__Canceled__<br/>
Assigned to an order if Pending Payment status has been active for over 8 hours.

Available statuses:
- Processing
- Suspected Fraud
- Pending Payment
- Payment Review
- Pending
- On Hold
- Complete
- Closed
- Canceled
- Pending Checkout

## Multiple stores
If you have multiple stores, you can set up the payment module differently depending on the selected store. In configuration settings, there is a selection for Store View.

By changing the Store View, you can define different settings for each store within the Magento 2 instance.

## Rounding problems with certain providers (Collector)

In some cases, this module might send a so-called "rounding-row" item in the order data, which might result in an error if this value is negative (this has been observed with Collector method). This is related to how Magento calculates and rounds taxes and how this module compensates for possible mismatches between the total and sum of individual items.

There are three algorithms for tax calculation in Magento which can be set in __Stores -> Configuration -> Sales -> Tax -> Tax Calculation Method Based On__
- Unit Price based
- Row Total based
- Total based

If the described error occurs when the calculation algorithm is Total based, changing it to Unit Price based might result in tax calculation with no mismatches. 

__Note:__ Changing the setting does not mean that the Unit Price based algorithm is better than Total or Row Total based, all three can have rounding issues in certain situations that can be resolved by choosing one of the other algorithms. The algorithms end up with the same calculations majority of the time.
