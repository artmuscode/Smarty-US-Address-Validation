<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Api;

use Custom\SmartyAddressValidation\Api\Data\AddressValidationRequestInterface;
use Custom\SmartyAddressValidation\Api\Data\AddressValidationResultInterface;

/**
 * Validates a US address against the Smarty API, mapping the result into a match/deliverability outcome.
 *
 * Owns the enabled-check, US-only country gate, incomplete-input gate, response caching, and
 * fail-open error handling around the Smarty validation Gateway.
 *
 * @api
 */
interface AddressValidatorInterface
{
    /**
     * Validate an address against the Smarty API
     *
     * @param \Custom\SmartyAddressValidation\Api\Data\AddressValidationRequestInterface $address
     * @return \Custom\SmartyAddressValidation\Api\Data\AddressValidationResultInterface
     */
    public function validate(AddressValidationRequestInterface $address): AddressValidationResultInterface;
}
