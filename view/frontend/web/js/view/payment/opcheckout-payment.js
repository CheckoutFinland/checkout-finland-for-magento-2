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
                type: 'opcheckout',
                component: 'Op_Checkout/js/view/payment/method-renderer/opcheckout-method'
            }
        );
        return Component.extend({});
    }
);
