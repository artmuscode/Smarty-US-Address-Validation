/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

/*eslint strict: ["error", "global"]*/

'use strict';

var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/view/shipping': {
                'Custom_SmartyAddressValidation/js/mixin/shipping-mixin': true
            }
        }
    }
};
