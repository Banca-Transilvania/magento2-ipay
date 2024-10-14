/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
],
function (Component, rendererList) {
    'use strict';

    rendererList.push(
        {
            type: 'btrl_ipay',
            component: 'BTRL_Ipay/js/view/payment/method-renderer/ipay'
        }
    );

    return Component.extend({});
});
