<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Model\Data;

use Custom\SmartyAddressValidation\Api\Data\AddressSuggestionInterface;

class AddressSuggestion implements AddressSuggestionInterface
{
    /**
     * @var string
     */
    private string $streetLine = '';

    /**
     * @var string|null
     */
    private ?string $secondary = null;

    /**
     * @var string
     */
    private string $city = '';

    /**
     * @var string
     */
    private string $stateAbbreviation = '';

    /**
     * @var int|null
     */
    private ?int $regionId = null;

    /**
     * @var string
     */
    private string $zipcode = '';

    /**
     * @inheritDoc
     */
    public function getStreetLine(): string
    {
        return $this->streetLine;
    }

    /**
     * @inheritDoc
     */
    public function setStreetLine(string $streetLine): self
    {
        $this->streetLine = $streetLine;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getSecondary(): ?string
    {
        return $this->secondary;
    }

    /**
     * @inheritDoc
     */
    public function setSecondary(?string $secondary): self
    {
        $this->secondary = $secondary;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getCity(): string
    {
        return $this->city;
    }

    /**
     * @inheritDoc
     */
    public function setCity(string $city): self
    {
        $this->city = $city;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getStateAbbreviation(): string
    {
        return $this->stateAbbreviation;
    }

    /**
     * @inheritDoc
     */
    public function setStateAbbreviation(string $stateAbbreviation): self
    {
        $this->stateAbbreviation = $stateAbbreviation;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getRegionId(): ?int
    {
        return $this->regionId;
    }

    /**
     * @inheritDoc
     */
    public function setRegionId(?int $regionId): self
    {
        $this->regionId = $regionId;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getZipcode(): string
    {
        return $this->zipcode;
    }

    /**
     * @inheritDoc
     */
    public function setZipcode(string $zipcode): self
    {
        $this->zipcode = $zipcode;

        return $this;
    }
}
