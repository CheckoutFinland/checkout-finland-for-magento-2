/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'jquery',
        'underscore',
        'mage/storage',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/action/get-totals',
        'Magento_Checkout/js/model/url-builder',
        'mage/url',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/totals',
        'Magento_Ui/js/model/messageList',
        'mage/translate',
    ],
    function (ko, $, _, storage, Component, placeOrderAction, selectPaymentMethodAction, additionalValidators, quote, getTotalsAction, urlBuilder, mageUrlBuilder, fullScreenLoader, customer, checkoutData, totals, messageList, $t) {
        'use strict';
        var self;
        return Component.extend({
            defaults: {
                template: window.checkoutConfig.payment.payment_template.opcheckout
            },
            redirectAfterPlaceOrder: false,
            selectedPaymentMethodId: ko.observable(0),
            selectedMethodGroup: ko.observable('bank'),

            initialize: function () {
                self = this;

                this._super();

                if (this.getPaymentPageBypass()) {
                    this.initPaymentPageBypass();

                    this.selectedMethodGroup.subscribe(function (groupId) {
                        // Find group
                        var group = _.find(self.getMethodGroups(), function (group) {
                            return groupId == group.id;
                        });

                        // Set first payment method as selected
                        if (typeof group != 'undefined' && group != null) {
                            self.setPaymentMethodId(group.methods[0]);
                        }
                    });
                }
            },

            initPaymentPageBypass: function () {
                // Set default payment method group
                var cookieMethodGroup = $.cookie('checkoutSelectedPaymentMethodGroup');
                if (typeof cookieMethodGroup != 'undefined') {
                    this.selectedMethodGroup(cookieMethodGroup);
                }

                // Set default payment method
                var cookieMethodId = $.cookie('checkoutSelectedPaymentMethodId');
                if (cookieMethodId) {
                    this.selectedPaymentMethodId(cookieMethodId);
                }
            },

            setPaymentMethodId: function (paymentMethod) {
                self.selectedPaymentMethodId(paymentMethod.id);
                $.cookie('checkoutSelectedPaymentMethodId', paymentMethod.id);

                return true;
            },

            getInstructions: function () {
                return window.checkoutConfig.payment.instructions[this.item.method];
            },

            getPaymentPageBypass: function () {
                return window.checkoutConfig.payment.use_bypass[this.item.method];
            },

            getMethodGroups: function () {
                return window.checkoutConfig.payment.method_groups[this.item.method];
            },

            selectPaymentMethod: function () {
                if (typeof this.id != 'undefined') {
                    self.selectedMethodGroup(this.id);
                    $.cookie('checkoutSelectedPaymentMethodGroup', this.id);
                }

                selectPaymentMethodAction(self.getData());
                checkoutData.setSelectedPaymentMethod(self.item.method);

                return true;
            },

            addErrorMessage: function (msg) {
                messageList.addErrorMessage({
                    message: msg
                });
            },
            getBypassPaymentRedirectUrl: function () {
                return window.checkoutConfig.payment.payment_redirect_url[this.item.method];
            },
            scrollTo: function () {
                var errorElement_offset;
                var scroll_top;
                var scrollElement = $('html, body'),
                    windowHeight = $(window).height(),
                    errorElement_offset = $('.message').offset().top,
                    scroll_top = errorElement_offset - windowHeight / 3;

                scrollElement.animate({
                    scrollTop: scroll_top
                });
            },
            validate: function () {
                self.initPaymentPageBypass();
                if (self.selectedPaymentMethodId() == 0) {
                    return false;
                } else {
                    return true;
                }
            },
            // Redirect to Checkout
            placeOrder: function () {
                if (self.validate() && additionalValidators.validate()) {
                    return self.placeOrderBypass();
                } else {
                    self.addErrorMessage($t('No payment method selected. Please select one.'));
                    self.scrollTo();
                    return false;
                }
            },
            placeOrderBypass: function () {

                placeOrderAction(self.getData(), self.messageContainer).done(function () {
                    fullScreenLoader.startLoader();
                    console.log(self.selectedPaymentMethodId());
                    $.ajax({
                        url: mageUrlBuilder.build(self.getBypassPaymentRedirectUrl()),
                        type: 'post',
                        context: this,
                        data: {'is_ajax': true, 'preselected_payment_method_id': self.selectedPaymentMethodId()}
                    }).done(function (response) {
                        if ($.type(response) == 'object' && response.success && response.data) {
                            $('#checkout-form-wrapper').append(response.data);
                            return false;
                        }

                        fullScreenLoader.stopLoader();

                        self.addErrorMessage($t('An error occurred on the server. Please try to place the order again.'));
                    }).fail(function () {
                        fullScreenLoader.stopLoader();

                        self.addErrorMessage($t('An error occurred on the server. Please try to place the order again.'));
                    }).always(function () {
                    });
                });
            }
        });
    }
);
