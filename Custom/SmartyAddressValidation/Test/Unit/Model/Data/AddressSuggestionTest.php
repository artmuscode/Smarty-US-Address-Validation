<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Test\Unit\Model\Data;

use Custom\SmartyAddressValidation\Model\Data\AddressSuggestion;
use PHPUnit\Framework\TestCase;

class AddressSuggestionTest extends TestCase
{
    public function testItStoresAndReturnsStreetLineSecondaryCityStateAbbreviationRegionIdAndZipcode(): void
    {
        $suggestion = new AddressSuggestion();

        $suggestion->setStreetLine('123 Main St');
        $suggestion->setSecondary('Apt 4');
        $suggestion->setCity('Springfield');
        $suggestion->setStateAbbreviation('IL');
        $suggestion->setRegionId(14);
        $suggestion->setZipcode('62701');

        $this->assertSame('123 Main St', $suggestion->getStreetLine());
        $this->assertSame('Apt 4', $suggestion->getSecondary());
        $this->assertSame('Springfield', $suggestion->getCity());
        $this->assertSame('IL', $suggestion->getStateAbbreviation());
        $this->assertSame(14, $suggestion->getRegionId());
        $this->assertSame('62701', $suggestion->getZipcode());
    }
}
