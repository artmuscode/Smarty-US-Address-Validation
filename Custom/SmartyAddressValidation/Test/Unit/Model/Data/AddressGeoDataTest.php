<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Test\Unit\Model\Data;

use Custom\SmartyAddressValidation\Model\Data\AddressGeoData;
use PHPUnit\Framework\TestCase;

class AddressGeoDataTest extends TestCase
{
    public function testItDefaultsEveryFieldToNullSoUncapturedDataIsDistinguishableFromZero(): void
    {
        $geoData = new AddressGeoData();

        $this->assertNull($geoData->getRdi());
        $this->assertNull($geoData->getLatitude());
        $this->assertNull($geoData->getLongitude());
        $this->assertNull($geoData->getGeoPrecision());
    }

    public function testItRoundTripsEveryField(): void
    {
        $geoData = new AddressGeoData();

        $geoData->setRdi('residential')
            ->setLatitude(37.4224764)
            ->setLongitude(-122.0842499)
            ->setGeoPrecision('Rooftop');

        $this->assertSame('residential', $geoData->getRdi());
        $this->assertSame(37.4224764, $geoData->getLatitude());
        $this->assertSame(-122.0842499, $geoData->getLongitude());
        $this->assertSame('Rooftop', $geoData->getGeoPrecision());
    }

    public function testItKeepsAZeroCoordinateDistinctFromAnUncapturedOne(): void
    {
        $geoData = new AddressGeoData();

        $geoData->setLatitude(0.0)->setLongitude(0.0);

        $this->assertSame(0.0, $geoData->getLatitude());
        $this->assertSame(0.0, $geoData->getLongitude());
    }
}
