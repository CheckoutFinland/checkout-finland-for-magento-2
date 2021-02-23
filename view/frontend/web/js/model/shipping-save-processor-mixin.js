define([
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/url-builder',
    'Magento_Checkout/js/model/error-processor',
    'mage/storage',
], function (wrapper, urlBuilder, errorProcessor, storage) {
    'use strict';

    return function (shippingSaveProcessor) {
        shippingSaveProcessor.saveShippingInformation
            = wrapper.wrapSuper(shippingSaveProcessor.saveShippingInformation, function (type) {
            this._super(type);

            var serviceUrl = urlBuilder.createUrl('/op-checkout/oppaymentmethods', {});

            return storage.get(
                serviceUrl, false
            ).done(function (response) {
                window.checkoutConfig.payment.opcheckout = response.find(element => element.opcheckout).opcheckout;
            }).fail(function (response) {
                errorProcessor.process(response);
            });
        });

        return shippingSaveProcessor;
    };
});
