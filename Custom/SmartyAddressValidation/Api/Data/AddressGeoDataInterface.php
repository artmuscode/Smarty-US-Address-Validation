<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Api\Data;

/**
 * Delivery metadata Smarty returns alongside a validated address.
 *
 * Carries the residential/commercial classification and the geocode of the delivery point, for
 * downstream consumers such as an ERP. Geocodes are only as good as the precision reported with
 * them, so precision is exposed rather than assumed.
 *
 * @api
 */
interface AddressGeoDataInterface
{
    /**
     * Get the residential delivery indicator: "residential", "commercial", or null when unknown
     *
     * @return string|null
     */
    public function getRdi(): ?string;

    /**
     * Set the residential delivery indicator
     *
     * @param string|null $rdi
     * @return $this
     */
    public function setRdi(?string $rdi): self;

    /**
     * Get the latitude of the delivery point
     *
     * @return float|null
     */
    public function getLatitude(): ?float;

    /**
     * Set the latitude of the delivery point
     *
     * @param float|null $latitude
     * @return $this
     */
    public function setLatitude(?float $latitude): self;

    /**
     * Get the longitude of the delivery point
     *
     * @return float|null
     */
    public function getLongitude(): ?float;

    /**
     * Set the longitude of the delivery point
     *
     * @param float|null $longitude
     * @return $this
     */
    public function setLongitude(?float $longitude): self;

    /**
     * Get the coordinate precision reported by Smarty, e.g. Zip5, Zip9, Rooftop
     *
     * @return string|null
     */
    public function getGeoPrecision(): ?string;

    /**
     * Set the coordinate precision reported by Smarty
     *
     * @param string|null $geoPrecision
     * @return $this
     */
    public function setGeoPrecision(?string $geoPrecision): self;
}
