<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Test\Unit\Model\Data;

use Custom\SmartyAddressValidation\Model\Data\AddressValidationRequest;
use PHPUnit\Framework\TestCase;

class AddressValidationRequestTest extends TestCase
{
    public function testItStoresAndReturnsStreetStreet2CityRegionCodeRegionIdPostcodeAndCountryId(): void
    {
        $request = new AddressValidationRequest();

        $request->setStreet('123 Main St');
        $request->setStreet2('Apt 4');
        $request->setCity('Springfield');
        $request->setRegionCode('IL');
        $request->setRegionId(14);
        $request->setPostcode('62701');
        $request->setCountryId('US');

        $this->assertSame('123 Main St', $request->getStreet());
        $this->assertSame('Apt 4', $request->getStreet2());
        $this->assertSame('Springfield', $request->getCity());
        $this->assertSame('IL', $request->getRegionCode());
        $this->assertSame(14, $request->getRegionId());
        $this->assertSame('62701', $request->getPostcode());
        $this->assertSame('US', $request->getCountryId());
    }
}
