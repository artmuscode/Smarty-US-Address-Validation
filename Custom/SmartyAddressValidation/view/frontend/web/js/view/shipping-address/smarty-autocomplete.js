/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */
define([
    'jquery',
    'ko',
    'underscore',
    'uiComponent',
    'Magento_Ui/js/lib/registry/registry',
    'Magento_Checkout/js/model/url-builder',
    'Magento_Checkout/js/checkout-data',
    'mage/storage',
    'Custom_SmartyAddressValidation/js/model/autocomplete-request'
], function ($, ko, _, Component, registry, urlBuilder, checkoutData, storage, autocompleteRequestFactory) {
    'use strict';

    var REQUEST_TIMEOUT_MS = 5000,
        SERVICE_URL = '/smarty-address-validation/autocomplete',
        STREET_INPUT_SELECTOR = 'input[name="street[0]"]',
        DROPDOWN_SELECTOR = '.smarty-autocomplete-dropdown',
        EVENT_NAMESPACE = '.smartyAutocomplete';

    return Component.extend({
        /**
         * Declared flat rather than under a 'config' key: Magento_Ui/js/core/renderer/layout merges
         * a jsLayout node's 'config' onto the node itself and then deletes it, so values injected by
         * LayoutProcessorPlugin arrive as top-level properties.
         */
        defaults: {
            template: 'Custom_SmartyAddressValidation/smarty-autocomplete-dropdown',
            minSearchLength: 3,
            debounceMs: 300,
            allowedCountries: ['US']
        },

        /** @inheritdoc */
        initialize: function () {
            this._super();

            this.suggestions = ko.observableArray([]);
            this.isDropdownVisible = ko.observable(false);
            this.activeIndex = ko.observable(-1);
            this.appliedStreetLine = null;

            this.fieldsetName = this.name.substring(0, this.name.lastIndexOf('.'));
            this.requestBuilder = autocompleteRequestFactory({
                minSearchLength: this.minSearchLength,
                allowedCountries: this.allowedCountries
            });
            this.debouncedSearch = _.debounce(this.performSearch.bind(this), this.debounceMs);

            this.bindFieldComponents();
            this.bindDomEvents();

            return this;
        },

        /**
         * Resolves and caches the sibling field ui components this widget reads from and writes to.
         */
        bindFieldComponents: function () {
            var self = this;

            registry.async(this.fieldsetName + '.country_id')(function (countryField) {
                self.countryField = countryField;
            });

            registry.async(this.fieldsetName + '.street.0')(function (streetField) {
                self.streetField = streetField;

                streetField.value.subscribe(function (value) {
                    self.onStreetInput(value);
                });
            });
        },

        /**
         * Handles keystrokes in the street address field, building and dispatching a debounced
         * suggestion search, or closing the dropdown when the input no longer qualifies.
         *
         * @param {String} value
         */
        onStreetInput: function (value) {
            var request;

            // Applying a suggestion writes back into this same field. Swallow that echo so the
            // dropdown does not immediately reopen with the suggestion the shopper just picked.
            if (value === this.appliedStreetLine) {
                this.closeDropdown();

                return;
            }

            this.appliedStreetLine = null;
            request = this.requestBuilder.build(value, this.getCountryId());

            if (!request) {
                this.closeDropdown();

                return;
            }

            this.debouncedSearch(request);
        },

        /**
         * Returns the currently selected shipping country id.
         *
         * @return {String|null}
         */
        getCountryId: function () {
            return this.countryField ? this.countryField.value() : null;
        },

        /**
         * Calls this module's own autocomplete REST endpoint for the given search request.
         * Fails open (closes the dropdown) on error or timeout, and discards responses that
         * no longer correspond to the latest search term typed by the shopper.
         *
         * @param {Object} request
         */
        performSearch: function (request) {
            var self = this,
                serviceUrl = urlBuilder.createUrl(SERVICE_URL, {}),
                jqXHR = storage.post(serviceUrl, JSON.stringify(request), false),
                timeoutId = setTimeout(function () {
                    jqXHR.abort();
                }, REQUEST_TIMEOUT_MS);

            jqXHR.done(function (suggestions) {
                clearTimeout(timeoutId);

                if (!self.requestBuilder.isLatestSearch(request.search)) {
                    return;
                }

                self.showSuggestions(suggestions);
            }).fail(function () {
                clearTimeout(timeoutId);
                self.closeDropdown();
            });
        },

        /**
         * Populates the dropdown with the given suggestions, or closes it when there are none.
         *
         * @param {Array} suggestions
         */
        showSuggestions: function (suggestions) {
            if (!suggestions || !suggestions.length) {
                this.closeDropdown();

                return;
            }

            this.suggestions(suggestions);
            this.activeIndex(-1);
            this.isDropdownVisible(true);
        },

        /**
         * Hides the dropdown and clears any pending suggestions.
         */
        closeDropdown: function () {
            this.suggestions([]);
            this.activeIndex(-1);
            this.isDropdownVisible(false);
        },

        /**
         * Applies the chosen suggestion to the shipping address fields and persists the
         * selection so it survives a checkout step change.
         *
         * @param {Object} suggestion
         */
        selectSuggestion: function (suggestion) {
            this.appliedStreetLine = suggestion.street_line;
            this.debouncedSearch.cancel();

            this.applyToField('street.0', suggestion.street_line);
            this.applyToField('street.1', suggestion.secondary || '');
            this.applyToField('city', suggestion.city);
            this.applyToField('region_id', suggestion.region_id);
            this.applyToField('postcode', suggestion.zipcode);

            this.persistShippingAddress(suggestion);
            this.closeDropdown();
        },

        /**
         * Sets the value of a sibling shipping-address-fieldset field by its relative name.
         *
         * @param {String} fieldName
         * @param {String|Number} value
         */
        applyToField: function (fieldName, value) {
            registry.async(this.fieldsetName + '.' + fieldName)(function (field) {
                field.value(value);
            });
        },

        /**
         * Persists the applied suggestion to checkout-data so it survives navigating away
         * from and back to the shipping step.
         *
         * @param {Object} suggestion
         */
        persistShippingAddress: function (suggestion) {
            var addressData = checkoutData.getShippingAddressFromData() || {};

            addressData.street = [suggestion.street_line, suggestion.secondary || ''];
            addressData.city = suggestion.city;
            addressData.region_id = suggestion.region_id;
            addressData.postcode = suggestion.zipcode;

            checkoutData.setShippingAddressFromData(addressData);
        },

        /**
         * Closes the dropdown shortly after the street field loses focus, deferred so that a
         * click/keyboard selection on a suggestion is registered first.
         */
        onBlur: function () {
            var self = this;

            setTimeout(function () {
                self.closeDropdown();
            }, 150);
        },

        /**
         * Delegates keydown/blur handling from the street input and outside-click detection
         * from the document, since this component's own template is a sibling of that input
         * rather than its wrapper.
         */
        bindDomEvents: function () {
            var self = this;

            $(document).on('keydown' + EVENT_NAMESPACE, STREET_INPUT_SELECTOR, function (event) {
                self.onKeyDown(null, event);
            });

            $(document).on('blur' + EVENT_NAMESPACE, STREET_INPUT_SELECTOR, function () {
                self.onBlur();
            });

            $(document).on('click' + EVENT_NAMESPACE, function (event) {
                self.onDocumentClick(event);
            });
        },

        /**
         * Closes the dropdown when the shopper clicks outside of the street field and the
         * suggestion dropdown itself.
         *
         * @param {Event} event
         */
        onDocumentClick: function (event) {
            var $target = $(event.target);

            if (!$target.is(STREET_INPUT_SELECTOR) && !$target.closest(DROPDOWN_SELECTOR).length) {
                this.closeDropdown();
            }
        },

        /** @inheritdoc */
        destroy: function () {
            $(document).off(EVENT_NAMESPACE);

            this._super();
        },

        /**
         * Handles arrow-key navigation and Enter/Escape while the dropdown is open.
         *
         * @param {Object} data
         * @param {Event} event
         * @return {Boolean}
         */
        onKeyDown: function (data, event) {
            var suggestions = this.suggestions(),
                index = this.activeIndex();

            switch (event.key) {
                case 'ArrowDown':
                    event.preventDefault();
                    this.activeIndex(Math.min(index + 1, suggestions.length - 1));
                    break;

                case 'ArrowUp':
                    event.preventDefault();
                    this.activeIndex(Math.max(index - 1, 0));
                    break;

                case 'Enter':
                    if (index >= 0 && suggestions[index]) {
                        event.preventDefault();
                        this.selectSuggestion(suggestions[index]);
                    }
                    break;

                case 'Escape':
                    this.closeDropdown();
                    break;

                default:
                    break;
            }

            return true;
        },

        /**
         * Builds the single-line label shown for a suggestion.
         *
         * Assembled here rather than from adjacent spans in the template, where the markup
         * whitespace between them renders as stray spaces before the punctuation.
         *
         * @param {Object} suggestion
         * @return {String}
         */
        formatSuggestion: function (suggestion) {
            var streetLine = suggestion.street_line;

            if (suggestion.secondary) {
                streetLine += ' ' + suggestion.secondary;
            }

            return streetLine + ', ' + suggestion.city + ', ' +
                suggestion.state_abbreviation + ' ' + suggestion.zipcode;
        },

        /**
         * Whether the suggestion at the given index is the currently keyboard-highlighted one.
         *
         * @param {Number} index
         * @return {Boolean}
         */
        isActive: function (index) {
            return this.activeIndex() === index;
        }
    });
});
