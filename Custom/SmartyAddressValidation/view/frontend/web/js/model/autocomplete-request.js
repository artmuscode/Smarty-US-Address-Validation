/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */
define([], function () {
    'use strict';

    /**
     * Creates a stateful helper that builds autocomplete search request payloads from raw street
     * keystrokes and tracks the latest search term so stale suggestion responses can be discarded.
     *
     * @param {Object} config
     * @param {Number} config.minSearchLength - minimum number of characters before a request is built
     * @param {Array} config.allowedCountries - country ids for which autocomplete is enabled
     * @return {Object}
     */
    return function (config) {
        var options = config || {},
            latestSearchTerm = null;

        return {

            /**
             * Builds a search request payload for the given raw input and shipping country, or
             * returns null when the input is too short or the country is not allowed.
             *
             * @param {String} rawText
             * @param {String} countryId
             * @return {Object|null}
             */
            build: function (rawText, countryId) {
                var searchTerm = (rawText || '').trim(),
                    minLength = options.minSearchLength || 0,
                    allowedCountries = options.allowedCountries || [];

                if (searchTerm.length < minLength) {
                    return null;
                }

                if (allowedCountries.indexOf(countryId) === -1) {
                    return null;
                }

                latestSearchTerm = searchTerm;

                return {
                    search: searchTerm,
                    countryId: countryId
                };
            },

            /**
             * Determines whether the given search term still matches the most recently built
             * request, allowing callers to discard suggestion responses for superseded searches.
             *
             * @param {String} searchTerm
             * @return {Boolean}
             */
            isLatestSearch: function (searchTerm) {
                return searchTerm === latestSearchTerm;
            }
        };
    };
});
