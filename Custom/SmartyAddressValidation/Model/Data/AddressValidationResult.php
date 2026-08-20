<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Model\Data;

use Custom\SmartyAddressValidation\Api\Data\AddressGeoDataInterface;
use Custom\SmartyAddressValidation\Api\Data\AddressValidationRequestInterface;
use Custom\SmartyAddressValidation\Api\Data\AddressValidationResultInterface;

class AddressValidationResult implements AddressValidationResultInterface
{
    /**
     * @var \Custom\SmartyAddressValidation\Api\Data\AddressGeoDataInterface|null
     */
    private ?AddressGeoDataInterface $geoData = null;

    /**
     * @var \Custom\SmartyAddressValidation\Api\Data\AddressValidationRequestInterface
     */
    private AddressValidationRequestInterface $original;

    /**
     * @var \Custom\SmartyAddressValidation\Api\Data\AddressValidationRequestInterface|null
     */
    private ?AddressValidationRequestInterface $suggested = null;

    /**
     * @var bool
     */
    private bool $matched = false;

    /**
     * @var bool
     */
    private bool $deliverable = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->original = new AddressValidationRequest();
    }

    /**
     * @inheritDoc
     */
    public function getOriginal(): AddressValidationRequestInterface
    {
        return $this->original;
    }

    /**
     * @inheritDoc
     */
    public function setOriginal(AddressValidationRequestInterface $original): self
    {
        $this->original = $original;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getSuggested(): ?AddressValidationRequestInterface
    {
        return $this->suggested;
    }

    /**
     * @inheritDoc
     */
    public function setSuggested(?AddressValidationRequestInterface $suggested): self
    {
        $this->suggested = $suggested;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isMatched(): bool
    {
        return $this->matched;
    }

    /**
     * @inheritDoc
     */
    public function setMatched(bool $matched): self
    {
        $this->matched = $matched;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isDeliverable(): bool
    {
        return $this->deliverable;
    }

    /**
     * @inheritDoc
     */
    public function setDeliverable(bool $deliverable): self
    {
        $this->deliverable = $deliverable;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getGeoData(): ?AddressGeoDataInterface
    {
        return $this->geoData;
    }

    /**
     * @inheritDoc
     */
    public function setGeoData(?AddressGeoDataInterface $geoData): self
    {
        $this->geoData = $geoData;

        return $this;
    }
}
