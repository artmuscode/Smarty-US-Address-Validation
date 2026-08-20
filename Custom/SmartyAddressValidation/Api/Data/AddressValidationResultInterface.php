<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Api\Data;

/**
 * Address validation result interface.
 *
 * Carries the outcome of validating an address against the Smarty API,
 * including the original address, an optional suggested correction, and
 * match/deliverability status.
 *
 * @api
 */
interface AddressValidationResultInterface
{
    /**
     * Get the original address that was submitted for validation
     *
     * @return \Custom\SmartyAddressValidation\Api\Data\AddressValidationRequestInterface
     */
    public function getOriginal(): AddressValidationRequestInterface;

    /**
     * Set the original address that was submitted for validation
     *
     * @param \Custom\SmartyAddressValidation\Api\Data\AddressValidationRequestInterface $original
     * @return $this
     */
    public function setOriginal(AddressValidationRequestInterface $original): self;

    /**
     * Get the suggested/corrected address, if any
     *
     * @return \Custom\SmartyAddressValidation\Api\Data\AddressValidationRequestInterface|null
     */
    public function getSuggested(): ?AddressValidationRequestInterface;

    /**
     * Set the suggested/corrected address
     *
     * @param \Custom\SmartyAddressValidation\Api\Data\AddressValidationRequestInterface|null $suggested
     * @return $this
     */
    public function setSuggested(?AddressValidationRequestInterface $suggested): self;

    /**
     * Whether the original address matched a known address
     *
     * @return bool
     */
    public function isMatched(): bool;

    /**
     * Set whether the original address matched a known address
     *
     * @param bool $matched
     * @return $this
     */
    public function setMatched(bool $matched): self;

    /**
     * Whether the address is deliverable
     *
     * @return bool
     */
    public function isDeliverable(): bool;

    /**
     * Set whether the address is deliverable
     *
     * @param bool $deliverable
     * @return $this
     */
    public function setDeliverable(bool $deliverable): self;

    /**
     * Get the delivery metadata (residential/commercial and geocode) for the matched address
     *
     * @return \Custom\SmartyAddressValidation\Api\Data\AddressGeoDataInterface|null
     */
    public function getGeoData(): ?AddressGeoDataInterface;

    /**
     * Set the delivery metadata for the matched address
     *
     * @param \Custom\SmartyAddressValidation\Api\Data\AddressGeoDataInterface|null $geoData
     * @return $this
     */
    public function setGeoData(?AddressGeoDataInterface $geoData): self;
}
