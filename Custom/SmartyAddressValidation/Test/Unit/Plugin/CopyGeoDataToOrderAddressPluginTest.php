<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Test\Unit\Plugin;

use Custom\SmartyAddressValidation\Model\AddressGeoFields;
use Custom\SmartyAddressValidation\Plugin\CopyGeoDataToOrderAddressPlugin;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote\Address\ToOrderAddress;
use Magento\Sales\Model\Order\Address as OrderAddress;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CopyGeoDataToOrderAddressPluginTest extends TestCase
{
    /**
     * @var ToOrderAddress&MockObject
     */
    private $subject;

    /**
     * @var CopyGeoDataToOrderAddressPlugin
     */
    private CopyGeoDataToOrderAddressPlugin $plugin;

    protected function setUp(): void
    {
        $this->subject = $this->createMock(ToOrderAddress::class);
        $this->plugin = new CopyGeoDataToOrderAddressPlugin();
    }

    /**
     * Builds a quote address carrying the given data, without touching the database.
     *
     * @param array $data
     * @return QuoteAddress&MockObject
     */
    private function quoteAddressWith(array $data)
    {
        $quoteAddress = $this->createMock(QuoteAddress::class);
        $quoteAddress->method('getData')->willReturnCallback(
            static fn (string $key) => $data[$key] ?? null
        );

        return $quoteAddress;
    }

    public function testItCopiesEveryCapturedFieldOntoTheOrderAddress(): void
    {
        $quoteAddress = $this->quoteAddressWith([
            AddressGeoFields::RDI => 'residential',
            AddressGeoFields::LATITUDE => '37.4224764',
            AddressGeoFields::LONGITUDE => '-122.0842499',
            AddressGeoFields::GEO_PRECISION => 'Rooftop',
        ]);
        $orderAddress = $this->createPartialMock(OrderAddress::class, []);

        $result = $this->plugin->afterConvert($this->subject, $orderAddress, $quoteAddress);

        $this->assertSame('residential', $result->getData(AddressGeoFields::RDI));
        $this->assertSame('37.4224764', $result->getData(AddressGeoFields::LATITUDE));
        $this->assertSame('-122.0842499', $result->getData(AddressGeoFields::LONGITUDE));
        $this->assertSame('Rooftop', $result->getData(AddressGeoFields::GEO_PRECISION));
    }

    public function testItLeavesTheOrderAddressUntouchedForABillingAddressThatWasNeverValidated(): void
    {
        $quoteAddress = $this->quoteAddressWith([]);
        $orderAddress = $this->createPartialMock(OrderAddress::class, []);

        $result = $this->plugin->afterConvert($this->subject, $orderAddress, $quoteAddress);

        foreach (AddressGeoFields::ALL as $field) {
            $this->assertNull($result->getData($field));
        }
    }

    public function testItSkipsEmptyStringsSoAnUnknownClassificationIsNotStoredAsBlank(): void
    {
        $quoteAddress = $this->quoteAddressWith([
            AddressGeoFields::RDI => '',
            AddressGeoFields::LATITUDE => '37.4224764',
        ]);
        $orderAddress = $this->createPartialMock(OrderAddress::class, []);

        $result = $this->plugin->afterConvert($this->subject, $orderAddress, $quoteAddress);

        $this->assertNull($result->getData(AddressGeoFields::RDI));
        $this->assertSame('37.4224764', $result->getData(AddressGeoFields::LATITUDE));
    }
}
