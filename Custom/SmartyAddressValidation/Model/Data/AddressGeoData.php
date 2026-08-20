<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Model\Data;

use Custom\SmartyAddressValidation\Api\Data\AddressGeoDataInterface;

class AddressGeoData implements AddressGeoDataInterface
{
    /**
     * @var string|null
     */
    private ?string $rdi = null;

    /**
     * @var float|null
     */
    private ?float $latitude = null;

    /**
     * @var float|null
     */
    private ?float $longitude = null;

    /**
     * @var string|null
     */
    private ?string $geoPrecision = null;

    /**
     * @inheritDoc
     */
    public function getRdi(): ?string
    {
        return $this->rdi;
    }

    /**
     * @inheritDoc
     */
    public function setRdi(?string $rdi): self
    {
        $this->rdi = $rdi;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    /**
     * @inheritDoc
     */
    public function setLatitude(?float $latitude): self
    {
        $this->latitude = $latitude;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    /**
     * @inheritDoc
     */
    public function setLongitude(?float $longitude): self
    {
        $this->longitude = $longitude;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getGeoPrecision(): ?string
    {
        return $this->geoPrecision;
    }

    /**
     * @inheritDoc
     */
    public function setGeoPrecision(?string $geoPrecision): self
    {
        $this->geoPrecision = $geoPrecision;

        return $this;
    }
}
