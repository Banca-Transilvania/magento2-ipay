/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/action/place-order',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/url-builder',
    'mage/storage',
    'mage/cookies'
],
function (
    $,
    Component,
    placeOrderAction,
    additionalValidators,
    fullScreenLoader,
    urlBuilder,
    storage
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'BTRL_Ipay/payment/ipay'
        },

        placeOrder: async function() {
            let self = this;

            if (this.validate() &&
                additionalValidators.validate() &&
                this.isPlaceOrderActionAllowed() === true
            ) {
                // Place Order but use our own redirect url after
                fullScreenLoader.startLoader();
                self.isPlaceOrderActionAllowed(false);

                await $.when(
                    placeOrderAction(self.getData(), self.messageContainer)
                ).fail(
                    function (response) {
                        self.isPlaceOrderActionAllowed(true);
                        fullScreenLoader.stopLoader();
                    }
                ).done(
                    function (orderId) {
                        self.afterPlaceOrder();
                        self.getOrderRedirectUrl(orderId).done(
                            function(response) {
                                window.location.href = response;
                            }
                        );
                    }
                );
            }
        },

        getOrderRedirectUrl: function(orderId) {
            return storage.get(urlBuilder.createUrl('/btrl/ipay/get-redirect-url/' + orderId, {}));
        }
    });
});
