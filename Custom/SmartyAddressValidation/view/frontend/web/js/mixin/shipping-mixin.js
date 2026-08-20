/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'uiRegistry',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/url-builder',
    'Magento_Checkout/js/checkout-data',
    'mage/storage',
    'Custom_SmartyAddressValidation/js/model/validation-result-mapper'
], function ($, registry, quote, urlBuilder, checkoutData, storage, mapper) {
    'use strict';

    var NEW_ADDRESS_TYPE = 'new-customer-address',
        VALIDATE_TIMEOUT_MS = 5000,
        VALIDATE_SERVICE_ROUTE = '/smarty-address-validation/validate',
        PANEL_COMPONENT_PATH = 'checkout.steps.shipping-step.shippingAddress.smarty-validation-panel',
        FIELDSET_PATH = 'checkout.steps.shipping-step.shippingAddress.shipping-address-fieldset',
        FAIL_OPEN_RESULT = { suggested: null },
        lastResolvedSignature = null,
        validationInProgress = false;

    /**
     * Whether the customer is submitting a newly-typed address rather than a saved address-book entry.
     *
     * Per product decision, Smarty validation only runs for a newly-typed shipping address.
     *
     * @return {Boolean}
     */
    function isNewAddress() {
        var shippingAddress = quote.shippingAddress();

        return Boolean(shippingAddress)
            && typeof shippingAddress.getType === 'function'
            && shippingAddress.getType() === NEW_ADDRESS_TYPE;
    }

    /**
     * Build the address payload sent to the validate endpoint from the quote's current shipping address.
     *
     * @param {Object} shippingAddress
     * @return {Object}
     */
    function buildAddressPayload(shippingAddress) {
        var street = shippingAddress.street || [];

        return {
            street: street[0] || '',
            street2: street[1] || '',
            city: shippingAddress.city || '',
            region_code: shippingAddress.regionCode || '',
            region_id: shippingAddress.regionId || null,
            postcode: shippingAddress.postcode || '',
            country_id: shippingAddress.countryId || ''
        };
    }

    /**
     * Call the validate endpoint. Always resolves - fails open (no suggestion) on error or timeout,
     * matching the backend's own fail-open behavior.
     *
     * @param {Object} addressPayload
     * @return {Object} jQuery promise
     */
    function callValidateEndpoint(addressPayload) {
        var deferred = $.Deferred(),
            settled = false,
            timeoutId;

        /**
         * Resolve the deferred exactly once, whichever of the timeout/done/fail paths wins.
         *
         * @param {Object} result
         */
        function settle(result) {
            if (!settled) {
                settled = true;
                clearTimeout(timeoutId);
                deferred.resolve(result);
            }
        }

        timeoutId = setTimeout(function () {
            settle(FAIL_OPEN_RESULT);
        }, VALIDATE_TIMEOUT_MS);

        storage.post(
            urlBuilder.createUrl(VALIDATE_SERVICE_ROUTE, {}),
            JSON.stringify({ address: addressPayload }),
            false
        ).done(settle).fail(function () {
            settle(FAIL_OPEN_RESULT);
        });

        return deferred.promise();
    }

    /**
     * Apply a Smarty-suggested address to the shipping address fieldset fields (region by id) and
     * to the persisted checkout form data, so the choice survives a step change.
     *
     * @param {Object} suggested
     */
    function applySuggestedAddress(suggested) {
        registry.get(FIELDSET_PATH + '.street.0').value(suggested.street);
        registry.get(FIELDSET_PATH + '.street.1').value(suggested.street2 || '');
        registry.get(FIELDSET_PATH + '.city').value(suggested.city);
        registry.get(FIELDSET_PATH + '.region_id').value(suggested.region_id);
        registry.get(FIELDSET_PATH + '.postcode').value(suggested.postcode);

        checkoutData.setShippingAddressFromData($.extend(true, {}, checkoutData.getShippingAddressFromData(), {
            street: [suggested.street, suggested.street2 || ''],
            city: suggested.city,
            region_id: suggested.region_id,
            postcode: suggested.postcode
        }));
    }

    return function (Component) {
        return Component.extend({
            /**
             * Validate the entered shipping address against Smarty before proceeding to the next
             * checkout step, pausing on a comparison panel when Smarty proposes a materially
             * different address.
             *
             * The parent method is captured synchronously as 'proceed': Magento's '_super' is a
             * temporary property that mage/utils/wrapper restores as soon as this function returns,
             * so it is already gone by the time any of the async callbacks below run.
             */
            setShippingInformation: function () {
                var proceed = this._super.bind(this),
                    addressPayload,
                    signature;

                // Gate on form validity so an incomplete address never reaches the Smarty lookup.
                // The parent re-runs this check and owns rendering the validation errors.
                if (!this.validateShippingInformation()) {
                    proceed();
                    return;
                }

                if (!isNewAddress()) {
                    proceed();
                    return;
                }

                addressPayload = buildAddressPayload(quote.shippingAddress());
                signature = mapper.signature(addressPayload);

                if (validationInProgress || signature === lastResolvedSignature) {
                    proceed();
                    return;
                }

                validationInProgress = true;

                callValidateEndpoint(addressPayload).always(function () {
                    validationInProgress = false;
                }).done(function (result) {
                    var mapped = mapper.map(result, addressPayload),
                        panel;

                    if (!mapped.differs) {
                        lastResolvedSignature = signature;
                        proceed();
                        return;
                    }

                    panel = registry.get(PANEL_COMPONENT_PATH);

                    if (!panel) {
                        // The panel failed to render; never strand the customer on the shipping step.
                        lastResolvedSignature = signature;
                        proceed();
                        return;
                    }

                    panel.show(addressPayload, mapped.suggested).then(function (choice) {
                        var resolvedAddress = addressPayload;

                        if (choice === 'suggested') {
                            applySuggestedAddress(mapped.suggested);
                            resolvedAddress = mapped.suggested;
                        }

                        lastResolvedSignature = mapper.signature(resolvedAddress);
                        proceed();
                    });
                });
            }
        });
    };
});
