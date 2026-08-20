<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Model\Smarty;

use Custom\SmartyAddressValidation\Api\Data\AddressValidationRequestInterface;

/**
 * Adapts the Smarty US Street Address SDK client to a plain-array response shape.
 *
 * Internal only (not `@api`) — exists so the Smarty SDK boundary is mockable in unit tests.
 */
interface ValidationGatewayInterface
{
    /**
     * Look up the single best-matching candidate for the given address.
     *
     * Returns the normalized best candidate, shaped:
     * ['delivery_line_1' => string, 'delivery_line_2' => string, 'city_name' => string,
     *  'state_abbreviation' => string, 'zipcode' => string, 'plus4_code' => string,
     *  'dpv_match_code' => string, 'dpv_footnotes' => string, 'footnotes' => string]
     *
     * Returns null when Smarty returns no candidate match at all.
     *
     * @param AddressValidationRequestInterface $address
     * @return array|null
     */
    public function lookup(AddressValidationRequestInterface $address): ?array;
}
