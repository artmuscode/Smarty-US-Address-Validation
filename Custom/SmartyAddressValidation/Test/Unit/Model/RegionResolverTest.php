<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Test\Unit\Model;

use Custom\SmartyAddressValidation\Model\RegionResolver;
use Magento\Directory\Model\Region;
use Magento\Directory\Model\RegionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RegionResolverTest extends TestCase
{
    /**
     * @var RegionFactory|MockObject
     */
    private RegionFactory $regionFactory;

    /**
     * @var RegionResolver
     */
    private RegionResolver $regionResolver;

    protected function setUp(): void
    {
        $this->regionFactory = $this->createMock(RegionFactory::class);
        $this->regionResolver = new RegionResolver($this->regionFactory);
    }

    public function testItResolvesAUsStateAbbreviationToAMagentoRegionId(): void
    {
        $region = $this->createMock(Region::class);
        $region->method('getId')->willReturn('14');
        $region->expects($this->once())
            ->method('loadByCode')
            ->with('IL', 'US')
            ->willReturn($region);

        $this->regionFactory->method('create')->willReturn($region);

        $this->assertSame(14, $this->regionResolver->resolve('IL', 'US'));
    }

    public function testItReturnsNullFromRegionResolverForAnUnknownStateAbbreviation(): void
    {
        $region = $this->createMock(Region::class);
        $region->method('getId')->willReturn(null);
        $region->method('loadByCode')->with('ZZ', 'US')->willReturn($region);

        $this->regionFactory->method('create')->willReturn($region);

        $this->assertNull($this->regionResolver->resolve('ZZ', 'US'));
    }

    public function testItDoesNotReloadTheSameRegionCodeTwice(): void
    {
        $region = $this->createMock(Region::class);
        $region->method('getId')->willReturn('14');
        $region->method('loadByCode')->with('IL', 'US')->willReturn($region);

        $this->regionFactory->expects($this->once())
            ->method('create')
            ->willReturn($region);

        $this->assertSame(14, $this->regionResolver->resolve('IL', 'US'));
        $this->assertSame(14, $this->regionResolver->resolve('IL', 'US'));
    }
}
