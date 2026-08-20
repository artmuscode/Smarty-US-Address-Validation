<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Test\Unit\Plugin;

use Custom\SmartyAddressValidation\Model\AddressGeoFields;
use Custom\SmartyAddressValidation\Plugin\OrderAddressGeoDataExtensionPlugin;
use Magento\Sales\Api\Data\OrderAddressExtensionFactory;
use Magento\Sales\Api\Data\OrderAddressExtensionInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderAddressGeoDataExtensionPluginTest extends TestCase
{
    /**
     * @var OrderRepositoryInterface&MockObject
     */
    private $subject;

    /**
     * @var OrderAddressGeoDataExtensionPlugin
     */
    private OrderAddressGeoDataExtensionPlugin $plugin;

    protected function setUp(): void
    {
        $this->subject = $this->createMock(OrderRepositoryInterface::class);

        $extensionFactory = $this->createMock(OrderAddressExtensionFactory::class);
        $extensionFactory->method('create')->willReturnCallback(
            fn (): OrderAddressExtensionInterface => $this->createMock(OrderAddressExtensionInterface::class)
        );

        $this->plugin = new OrderAddressGeoDataExtensionPlugin($extensionFactory);
    }

    /**
     * Builds an order whose single address carries the given stored column data.
     *
     * @param array $data
     * @param OrderAddressExtensionInterface|null $extension
     * @return Order&MockObject
     */
    private function orderWithAddressData(array $data, ?OrderAddressExtensionInterface $extension = null)
    {
        $address = $this->createMock(OrderAddress::class);
        $address->method('getData')->willReturnCallback(static fn (string $key) => $data[$key] ?? null);
        $address->method('getExtensionAttributes')->willReturn($extension);

        $order = $this->createMock(Order::class);
        $order->method('getAddresses')->willReturn([$address]);

        return $order;
    }

    public function testItPublishesTheStoredColumnsOntoTheAddressExtensionAttributes(): void
    {
        $extension = $this->createMock(OrderAddressExtensionInterface::class);
        $extension->expects($this->once())->method('setSmartyRdi')->with('residential');
        $extension->expects($this->once())->method('setSmartyLatitude')->with(37.4224764);
        $extension->expects($this->once())->method('setSmartyLongitude')->with(-122.0842499);
        $extension->expects($this->once())->method('setSmartyGeoPrecision')->with('Rooftop');

        $order = $this->orderWithAddressData([
            AddressGeoFields::RDI => 'residential',
            AddressGeoFields::LATITUDE => '37.4224764',
            AddressGeoFields::LONGITUDE => '-122.0842499',
            AddressGeoFields::GEO_PRECISION => 'Rooftop',
        ], $extension);

        $this->assertSame($order, $this->plugin->afterGet($this->subject, $order));
    }

    public function testItPublishesNullsRatherThanEmptyStringsForAnOrderPlacedBeforeCaptureExisted(): void
    {
        $extension = $this->createMock(OrderAddressExtensionInterface::class);
        $extension->expects($this->once())->method('setSmartyRdi')->with(null);
        $extension->expects($this->once())->method('setSmartyLatitude')->with(null);
        $extension->expects($this->once())->method('setSmartyLongitude')->with(null);
        $extension->expects($this->once())->method('setSmartyGeoPrecision')->with(null);

        $order = $this->orderWithAddressData([], $extension);

        $this->plugin->afterGet($this->subject, $order);
    }

    public function testItCreatesExtensionAttributesWhenTheAddressHasNoneYet(): void
    {
        $order = $this->orderWithAddressData([AddressGeoFields::RDI => 'commercial'], null);

        $this->assertSame($order, $this->plugin->afterGet($this->subject, $order));
    }

    public function testItAppliesToEveryOrderInAListSoBulkErpPollsAreCoveredToo(): void
    {
        $orders = [
            $this->orderWithAddressData([AddressGeoFields::RDI => 'residential']),
            $this->orderWithAddressData([AddressGeoFields::RDI => 'commercial']),
        ];

        $searchResult = $this->createMock(OrderSearchResultInterface::class);
        $searchResult->expects($this->once())->method('getItems')->willReturn($orders);

        $this->assertSame($searchResult, $this->plugin->afterGetList($this->subject, $searchResult));
    }
}
