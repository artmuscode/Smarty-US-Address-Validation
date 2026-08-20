<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Test\Unit\Model\Data;

use Custom\SmartyAddressValidation\Model\Data\AddressValidationRequest;
use Custom\SmartyAddressValidation\Model\Data\AddressValidationResult;
use PHPUnit\Framework\TestCase;

class AddressValidationResultTest extends TestCase
{
    public function testItReturnsNullFromGetSuggestedByDefaultOnANewAddressValidationResult(): void
    {
        $result = new AddressValidationResult();

        $this->assertNull($result->getSuggested());
    }

    public function testItStoresAndReturnsOriginalSuggestedMatchedAndDeliverableOnAddressValidationResult(): void
    {
        $result = new AddressValidationResult();
        $original = new AddressValidationRequest();
        $suggested = new AddressValidationRequest();

        $result->setOriginal($original);
        $result->setSuggested($suggested);
        $result->setMatched(true);
        $result->setDeliverable(true);

        $this->assertSame($original, $result->getOriginal());
        $this->assertSame($suggested, $result->getSuggested());
        $this->assertTrue($result->isMatched());
        $this->assertTrue($result->isDeliverable());
    }
}
