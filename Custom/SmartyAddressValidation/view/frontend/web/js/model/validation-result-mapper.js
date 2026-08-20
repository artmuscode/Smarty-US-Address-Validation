/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

define([], function () {
    'use strict';

    /**
     * Common street-suffix synonyms, canonicalized so Smarty's routine abbreviation normalization
     * (e.g. "Street" -> "St") does not register as a material difference from the entered address.
     */
    var STREET_SUFFIX_MAP = {
        street: 'st',
        avenue: 'ave',
        boulevard: 'blvd',
        drive: 'dr',
        lane: 'ln',
        road: 'rd',
        court: 'ct',
        place: 'pl',
        circle: 'cir',
        terrace: 'ter',
        parkway: 'pkwy',
        highway: 'hwy',
        square: 'sq',
        apartment: 'apt',
        suite: 'ste'
    };

    /**
     * Canonicalize a single street token: lowercase words already match map keys/values.
     *
     * @param {String} token
     * @return {String}
     */
    function canonicalizeToken(token) {
        return STREET_SUFFIX_MAP[token] || token;
    }

    /**
     * Normalize a street line for comparison: case-fold, strip punctuation, canonicalize suffixes.
     *
     * @param {String} street
     * @return {String}
     */
    function normalizeStreet(street) {
        return String(street || '')
            .toLowerCase()
            .replace(/[.,#]/g, '')
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .map(canonicalizeToken)
            .join(' ');
    }

    /**
     * Normalize a simple text field for comparison: case-fold and trim.
     *
     * @param {String} value
     * @return {String}
     */
    function normalizeText(value) {
        return String(value || '').toLowerCase().trim();
    }

    /**
     * Normalize a postal code for comparison: ZIP+4 and ZIP5-only are treated as equivalent.
     *
     * @param {String} postcode
     * @return {String}
     */
    function normalizeZip(postcode) {
        return String(postcode || '').trim().split('-')[0];
    }

    /**
     * Build a normalized, comparable JSON signature for an address.
     *
     * Address objects are expected in REST payload shape: street, street2, city, region_code,
     * postcode, country_id.
     *
     * @param {Object} address
     * @return {String}
     */
    function normalizedSignature(address) {
        address = address || {};

        return JSON.stringify({
            street: normalizeStreet(address.street),
            street2: normalizeStreet(address.street2),
            city: normalizeText(address.city),
            regionCode: normalizeText(address.region_code),
            postcode: normalizeZip(address.postcode),
            countryId: normalizeText(address.country_id)
        });
    }

    return {
        /**
         * Decide whether the validate endpoint's suggested address materially differs from the
         * entered address and should be surfaced to the customer for review.
         *
         * @param {Object} validationResult - parsed response of /V1/smarty-address-validation/validate
         * @param {Object} enteredAddress - the address as submitted for validation
         * @return {{differs: Boolean, suggested: (Object|null)}}
         */
        map: function (validationResult, enteredAddress) {
            var suggested = validationResult && validationResult.suggested;

            if (!suggested) {
                return {
                    differs: false,
                    suggested: null
                };
            }

            return {
                differs: normalizedSignature(enteredAddress) !== normalizedSignature(suggested),
                suggested: suggested
            };
        },

        /**
         * Build a normalized signature for an address, usable as a loop-guard key by callers that
         * need to detect whether the same address has already been resolved by the customer.
         *
         * @param {Object} address
         * @return {String}
         */
        signature: function (address) {
            return normalizedSignature(address);
        }
    };
});
