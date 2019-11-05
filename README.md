# OP Payment Service Magento 2 Module
Checkout Finland's payment methods for your Magento 2 web store

***Always perform a backup of your database and source code before installing any Magento 2 extensions.***

This module works on Magento 2.2.7< and 2.3.0< {community|commerce} versions.

## Features
This payment module has the following features:
- Payment methods provided by OP Payment Service
- The ability to restore and ship a cancelled order
- Support for delayed payments (Collector etc.)
- Support for multiple stores within a single Magento 2 instance

## Installation
Steps:
1. Download module from GitHub
2. Place the module files into the app/code directory within the magento installation
3. Run the following commands: <br/>```php bin/magento setup:upgrade``` <br/>```php bin/magento setup:di:compile``` <br/>```bin/magento setup:static-content:deploy``` 
4. Navigate to Magento admin interface and select __Stores -> Store Configuration -> Sales -> Payment Methods -> OP Payment Service__
5. Enter your credentials and enable the module (Checkout test credentials: _375917 - SAIPPUAKAUPPIAS_)
6. Clear the cache 

## Usage
The module settings can be found from:
__Stores -> Configuration -> Sales -> Payment Methods -> OP Payment Service__

The module has the following settings:
- __Enable__: Defines whether the payment method is enabled or not *(Input: Yes / No)*
- __Skip bank selection__: Defines whether the bank selection will be skipped *(Input: Yes / No)*
- __Merchant ID__: Your merchant ID from OP Payment Service *(Input: Text)*
- __Merchant Secret__: Your merchant secret from OP Payment Service *(Input: Secret)*
- __New Order Status__: A custom status for a new order paid for with OP Payment Service *(Input: Selection)*
- __Email Address For Notifications__: If a payment has been processed after the order has been cancelled, a notification will be sent to the merchant so that they can reactivate and ship the order *(Input: Email address)* 
- __Payment from Applicable Countries__: Allow payments from all countries or specific countries *(Input: All / Specific)*
- __Payment from Specific Countries__: If the previous setting has been set to specific countries, this list can define the allowed countries *(Input: Selection)*

## Refunds
This payment module supports online refunds.

_Note: payments made through the old Checkout Finland module cannot be refunded through this module. Old payments can still be refunded through Checkout’s Extranet._

Steps:
1. Navigate to __Sales -> Orders__ and select the order you need to fully or partially refund
2. Select Invoices from Order View side bar
3. Select the invoice
4. Select Credit Memo
5. Define the items you want to refund and optionally define an adjustment fee
6. Click Refund

## Canceled order payment email notification
If the customer closes the browser window right after completing the payment BUT before returning to the store, Magento is left with a “Pending payment” status for the order. This status has a timeout, so if the payment confirmation does not arrive within 8 hours of the purchase, Magento automatically cancels the order. OP Payment Service informs Magento of a payment that has gone through, but it may take over 8 hours.

When the confirmation is finally made, Magento registers the transaction to the order and changes the order status to Processing. But since the stock may have changed in the interim, the items are still cancelled. The merchant will receive an email informing about the payment that has gone through, but they have to manually go to said order, make sure the items are still available, and click “Restore order” to be able to ship it.

__Adjust the timeout__<br/>
The timeout period of 8 hours can be adjusted in Magento configuration. A longer period may allow for OP Payment Service to confirm the order before it gets canceled, but it also reserves the stock for that exact time.
1. Go to __Stores -> Configuration -> Sales -> Sales -> Orders Cron Settings__
2. Adjust the __Pending Payment Order Lifetime (minutes)__ value to your liking.

## Order status
__Pending Payment__<br/>
Assigned to an order when customer is redirected to the payment provider of their choosing.

__Pending Checkout__<br/>
Assigned to an order if OP Payment Service is still waiting for a confirmation of payment. Applies to invoices, such as Collector.

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