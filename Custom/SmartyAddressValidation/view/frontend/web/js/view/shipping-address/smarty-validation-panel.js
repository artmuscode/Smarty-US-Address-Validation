/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'ko',
    'uiComponent'
], function ($, ko, Component) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Custom_SmartyAddressValidation/smarty-address-comparison'
        },

        /**
         * @inheritdoc
         */
        initialize: function () {
            this._super();

            this.isVisible = ko.observable(false);
            this.enteredAddress = ko.observable(null);
            this.suggestedAddress = ko.observable(null);
            this.resolved = ko.observable(null);
            this.deferred = null;

            return this;
        },

        /**
         * Show the comparison panel and pause until the customer picks the address to use.
         *
         * Called by the shipping mixin when Smarty proposes a materially different address.
         *
         * @param {Object} enteredAddress
         * @param {Object} suggestedAddress
         * @return {Object} jQuery promise resolving with 'entered' or 'suggested'
         */
        show: function (enteredAddress, suggestedAddress) {
            this.deferred = $.Deferred();
            this.enteredAddress(enteredAddress);
            this.suggestedAddress(suggestedAddress);
            this.resolved(null);
            this.isVisible(true);

            return this.deferred.promise();
        },

        /**
         * Resolve the pending choice, hide the panel, and notify the caller of the outcome.
         *
         * @param {String} choice - 'entered' or 'suggested'
         */
        resolve: function (choice) {
            var deferred = this.deferred;

            this.isVisible(false);
            this.resolved(choice);
            this.deferred = null;

            if (deferred) {
                deferred.resolve(choice);
            }
        },

        /**
         * Customer chose to keep the address exactly as they entered it.
         */
        chooseEntered: function () {
            this.resolve('entered');
        },

        /**
         * Customer chose the Smarty-suggested address.
         */
        chooseSuggested: function () {
            this.resolve('suggested');
        }
    });
});
