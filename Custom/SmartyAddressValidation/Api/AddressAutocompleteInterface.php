<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Api;

/**
 * Provides US address autocomplete suggestions backed by the Smarty US Autocomplete Pro API.
 *
 * @api
 */
interface AddressAutocompleteInterface
{
    /**
     * Suggest address completions for a partial search string.
     *
     * @param string $search
     * @param string|null $countryId
     * @return \Custom\SmartyAddressValidation\Api\Data\AddressSuggestionInterface[]
     */
    public function suggest(string $search, ?string $countryId = null): array;
}
