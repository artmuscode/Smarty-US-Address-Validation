<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Api\Data;

/**
 * Address validation request interface.
 *
 * Carries the address data submitted for validation against the Smarty API.
 *
 * @api
 */
interface AddressValidationRequestInterface
{
    /**
     * Get street line 1
     *
     * @return string
     */
    public function getStreet(): string;

    /**
     * Set street line 1
     *
     * @param string $street
     * @return $this
     */
    public function setStreet(string $street): self;

    /**
     * Get street line 2
     *
     * @return string|null
     */
    public function getStreet2(): ?string;

    /**
     * Set street line 2
     *
     * @param string|null $street2
     * @return $this
     */
    public function setStreet2(?string $street2): self;

    /**
     * Get city
     *
     * @return string
     */
    public function getCity(): string;

    /**
     * Set city
     *
     * @param string $city
     * @return $this
     */
    public function setCity(string $city): self;

    /**
     * Get region code (e.g. two-letter state abbreviation)
     *
     * @return string
     */
    public function getRegionCode(): string;

    /**
     * Set region code (e.g. two-letter state abbreviation)
     *
     * @param string $regionCode
     * @return $this
     */
    public function setRegionCode(string $regionCode): self;

    /**
     * Get region id
     *
     * @return int|null
     */
    public function getRegionId(): ?int;

    /**
     * Set region id
     *
     * @param int|null $regionId
     * @return $this
     */
    public function setRegionId(?int $regionId): self;

    /**
     * Get postcode
     *
     * @return string
     */
    public function getPostcode(): string;

    /**
     * Set postcode
     *
     * @param string $postcode
     * @return $this
     */
    public function setPostcode(string $postcode): self;

    /**
     * Get country id
     *
     * @return string
     */
    public function getCountryId(): string;

    /**
     * Set country id
     *
     * @param string $countryId
     * @return $this
     */
    public function setCountryId(string $countryId): self;
}
