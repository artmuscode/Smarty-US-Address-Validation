<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Model\Smarty;

use SmartyStreets\PhpSdk\US_Autocomplete_Pro\Client as AutocompleteClient;
use SmartyStreets\PhpSdk\US_Street\Client as StreetClient;

/**
 * Builds credentialed Smarty SDK clients at call time.
 *
 * Implementations must read store-scoped credentials fresh on every call (not cache a client built at
 * DI-construction time) so that admin configuration changes take effect without a recompile.
 */
interface ClientFactoryInterface
{
    /**
     * Build a Smarty US Autocomplete Pro API client.
     *
     * @return \SmartyStreets\PhpSdk\US_Autocomplete_Pro\Client
     */
    public function createAutocompleteClient(): AutocompleteClient;

    /**
     * Build a Smarty US Street Address API client.
     *
     * @return \SmartyStreets\PhpSdk\US_Street\Client
     */
    public function createStreetClient(): StreetClient;
}
