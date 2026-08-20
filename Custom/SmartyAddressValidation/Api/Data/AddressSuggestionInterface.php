<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Api\Data;

/**
 * Address suggestion interface.
 *
 * Carries a single autocomplete suggestion returned by the Smarty API.
 *
 * @api
 */
interface AddressSuggestionInterface
{
    /**
     * Get street line
     *
     * @return string
     */
    public function getStreetLine(): string;

    /**
     * Set street line
     *
     * @param string $streetLine
     * @return $this
     */
    public function setStreetLine(string $streetLine): self;

    /**
     * Get secondary address line (e.g. unit/apartment)
     *
     * @return string|null
     */
    public function getSecondary(): ?string;

    /**
     * Set secondary address line (e.g. unit/apartment)
     *
     * @param string|null $secondary
     * @return $this
     */
    public function setSecondary(?string $secondary): self;

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
     * Get state abbreviation
     *
     * @return string
     */
    public function getStateAbbreviation(): string;

    /**
     * Set state abbreviation
     *
     * @param string $stateAbbreviation
     * @return $this
     */
    public function setStateAbbreviation(string $stateAbbreviation): self;

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
     * Get zipcode
     *
     * @return string
     */
    public function getZipcode(): string;

    /**
     * Set zipcode
     *
     * @param string $zipcode
     * @return $this
     */
    public function setZipcode(string $zipcode): self;
}
