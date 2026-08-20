<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Model;

/**
 * Column and attribute names for the Smarty delivery metadata carried on quote and order addresses.
 *
 * Shared so the schema, the quote-to-order copy, and the REST extension attributes cannot drift apart.
 */
class AddressGeoFields
{
    public const RDI = 'smarty_rdi';
    public const LATITUDE = 'smarty_latitude';
    public const LONGITUDE = 'smarty_longitude';
    public const GEO_PRECISION = 'smarty_geo_precision';

    public const RDI_RESIDENTIAL = 'residential';
    public const RDI_COMMERCIAL = 'commercial';

    /**
     * All field names, in a stable order.
     *
     * @var string[]
     */
    public const ALL = [self::RDI, self::LATITUDE, self::LONGITUDE, self::GEO_PRECISION];
}
