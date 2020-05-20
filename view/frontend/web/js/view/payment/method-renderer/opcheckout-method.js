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
        var checkoutConfig = window.checkoutConfig.payment;

        return Component.extend(
            {
                defaults: {
                    template: checkoutConfig.opcheckout.payment_template

                },
                payMethod: 'opcheckout',
                redirectAfterPlaceOrder: false,
                selectedPaymentMethodId: ko.observable(0),
                selectedMethodGroup: ko.observable('mobile'),

                initialize: function () {
                    self = this;
                    this._super();
                    if (!self.getIsSuccess()) {
                        self.addErrorMessage($t('Op Payment Service API credentials are incorrect. Please contact support.'));
                    }
                    if (self.getSkipMethodSelection() == true) {
                        self.selectedPaymentMethodId(self.payMethod);

                    } else {
                        $("<style type='text/css'>" + self.getPaymentMethodStyles() + "</style>").appendTo("head");
                    }
                },
                setPaymentMethodId: function (paymentMethod) {
                    self.selectedPaymentMethodId(paymentMethod.id);
                    $.cookie('checkoutSelectedPaymentMethodId', paymentMethod.id);

                    return true;
                },
                getInstructions: function () {
                    return checkoutConfig[self.payMethod].instructions;
                },
                getIsSuccess: function () {
                    return checkoutConfig[self.payMethod].success;
                },
                //Get icon for payment group by group id
                getGroupIcon: function (group) {
                    return checkoutConfig[self.payMethod].image[group];
                },

                getSkipMethodSelection: function () {
                    return checkoutConfig[self.payMethod].skip_method_selection;
                },
                getPaymentMethodStyles: function () {
                    return checkoutConfig[self.payMethod].payment_method_styles;
                },
                getMethodGroups: function () {
                    return checkoutConfig[self.payMethod].method_groups;
                },
                getTerms: function () {
                    return checkoutConfig[self.payMethod].payment_terms;
                },
                selectPaymentMethod: function () {
                    selectPaymentMethodAction(self.getData());
                    checkoutData.setSelectedPaymentMethod(self.item.method);

                    return true;
                },
                addErrorMessage: function (msg) {
                    messageList.addErrorMessage(
                        {
                            message: msg
                        }
                    );
                },
                getBypassPaymentRedirectUrl: function () {
                    return checkoutConfig[self.payMethod].payment_redirect_url;
                },
                scrollTo: function () {
                    var errorElement_offset;
                    var scroll_top;
                    var scrollElement = $('html, body'),
                        windowHeight = $(window).height(),
                        errorElement_offset = $('.message').offset().top,
                        scroll_top = errorElement_offset - windowHeight / 3;

                    scrollElement.animate(
                        {
                            scrollTop: scroll_top
                        }
                    );
                },
                validate: function () {
                    if (self.selectedPaymentMethodId() == 0) {
                        return false;
                    } else {
                        return true;
                    }
                },
                // Redirect to Checkout
                placeOrder: function () {
                    if (self.getSkipMethodSelection() == false) {
                        if (self.validate() && additionalValidators.validate()) {
                            return self.placeOrderBypass();
                        } else {
                            self.addErrorMessage($t('No payment method selected. Please select one.'));
                            self.scrollTo();
                            return false;
                        }
                    } else {
                        return self.placeOrderBypass();
                    }
                },
                placeOrderBypass: function () {
                    placeOrderAction(self.getData(), self.messageContainer).done(
                        function () {
                            fullScreenLoader.startLoader();
                            $.ajax(
                                {
                                    url: mageUrlBuilder.build(self.getBypassPaymentRedirectUrl()),
                                    type: 'post',
                                    context: this,
                                    data: {'is_ajax': true, 'preselected_payment_method_id': self.selectedPaymentMethodId()}
                                }
                            ).done(
                                function (response) {
                                    if ($.type(response) === 'object' && response.success && response.data) {
                                        if (response.redirect) {
                                            window.location.href = response.redirect;
                                        }
                                        $('#checkout-form-wrapper').append(response.data);
                                        return false;
                                    }
                                    fullScreenLoader.stopLoader();

                                    self.addErrorMessage(response.message);
                                }
                            ).fail(
                                function (response) {
                                    fullScreenLoader.stopLoader();

                                    self.addErrorMessage(response.message);
                                }
                            ).always(
                                function () {
                                }
                            );
                        }
                    );
                }
            }
        );
    }
);
