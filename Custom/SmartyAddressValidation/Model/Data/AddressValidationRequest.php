<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Model\Data;

use Custom\SmartyAddressValidation\Api\Data\AddressValidationRequestInterface;

class AddressValidationRequest implements AddressValidationRequestInterface
{
    /**
     * @var string
     */
    private string $street = '';

    /**
     * @var string|null
     */
    private ?string $street2 = null;

    /**
     * @var string
     */
    private string $city = '';

    /**
     * @var string
     */
    private string $regionCode = '';

    /**
     * @var int|null
     */
    private ?int $regionId = null;

    /**
     * @var string
     */
    private string $postcode = '';

    /**
     * @var string
     */
    private string $countryId = '';

    /**
     * @inheritDoc
     */
    public function getStreet(): string
    {
        return $this->street;
    }

    /**
     * @inheritDoc
     */
    public function setStreet(string $street): self
    {
        $this->street = $street;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getStreet2(): ?string
    {
        return $this->street2;
    }

    /**
     * @inheritDoc
     */
    public function setStreet2(?string $street2): self
    {
        $this->street2 = $street2;

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
    public function getRegionCode(): string
    {
        return $this->regionCode;
    }

    /**
     * @inheritDoc
     */
    public function setRegionCode(string $regionCode): self
    {
        $this->regionCode = $regionCode;

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
    public function getPostcode(): string
    {
        return $this->postcode;
    }

    /**
     * @inheritDoc
     */
    public function setPostcode(string $postcode): self
    {
        $this->postcode = $postcode;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getCountryId(): string
    {
        return $this->countryId;
    }

    /**
     * @inheritDoc
     */
    public function setCountryId(string $countryId): self
    {
        $this->countryId = $countryId;

        return $this;
    }
}
