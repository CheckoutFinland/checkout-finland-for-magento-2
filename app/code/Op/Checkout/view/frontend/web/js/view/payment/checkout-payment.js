/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'checkout',
                component: 'Op_Checkout/js/view/payment/method-renderer/checkout-method'
            }
        );
        return Component.extend({});
    }
);
