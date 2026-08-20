<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Model\Smarty;

/**
 * Adapts the Smarty US Autocomplete Pro SDK client to a plain-array response shape.
 *
 * Internal only (not `@api`) — exists so the Smarty SDK boundary is mockable in unit tests.
 */
interface AutocompleteGatewayInterface
{
    /**
     * Look up address suggestions for a partial search string.
     *
     * Returns a list of normalized arrays, each shaped:
     * ['street_line' => string, 'secondary' => string, 'city' => string, 'state' => string,
     *  'zipcode' => string, 'entries' => int]
     *
     * @param string $search
     * @return array[]
     */
    public function lookup(string $search): array;
}
