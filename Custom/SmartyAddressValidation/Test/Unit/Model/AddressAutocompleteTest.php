<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Test\Unit\Model;

use Custom\SmartyAddressValidation\Api\Data\AddressSuggestionInterface;
use Custom\SmartyAddressValidation\Api\Data\AddressSuggestionInterfaceFactory;
use Custom\SmartyAddressValidation\Model\AddressAutocomplete;
use Custom\SmartyAddressValidation\Model\Config\ModuleConfig;
use Custom\SmartyAddressValidation\Model\Data\AddressSuggestion;
use Custom\SmartyAddressValidation\Model\RegionResolver;
use Custom\SmartyAddressValidation\Model\Smarty\AutocompleteGatewayInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AddressAutocompleteTest extends TestCase
{
    /**
     * @var AutocompleteGatewayInterface&MockObject
     */
    private $gateway;

    /**
     * @var ModuleConfig&MockObject
     */
    private $moduleConfig;

    /**
     * @var RegionResolver&MockObject
     */
    private $regionResolver;

    /**
     * @var AddressSuggestionInterfaceFactory&MockObject
     */
    private $suggestionFactory;

    /**
     * @var CacheInterface&MockObject
     */
    private $cache;

    /**
     * @var SerializerInterface&MockObject
     */
    private $serializer;

    /**
     * @var LoggerInterface&MockObject
     */
    private $logger;

    /**
     * @var StoreManagerInterface&MockObject
     */
    private $storeManager;

    /**
     * @var AddressAutocomplete
     */
    private AddressAutocomplete $addressAutocomplete;

    protected function setUp(): void
    {
        $this->gateway = $this->createMock(AutocompleteGatewayInterface::class);
        $this->moduleConfig = $this->createMock(ModuleConfig::class);
        $this->regionResolver = $this->createMock(RegionResolver::class);
        $this->suggestionFactory = $this->createMock(AddressSuggestionInterfaceFactory::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->moduleConfig->method('isAutocompleteEnabled')->willReturn(true);
        $this->cache->method('load')->willReturn(false);

        $this->addressAutocomplete = new AddressAutocomplete(
            $this->gateway,
            $this->moduleConfig,
            $this->regionResolver,
            $this->suggestionFactory,
            $this->cache,
            $this->serializer,
            $this->logger,
            $this->storeManager
        );
    }

    /**
     * Configures the suggestion factory mock to return a fresh AddressSuggestion instance on each create() call.
     *
     * @return void
     */
    private function useRealSuggestionFactory(): void
    {
        $this->suggestionFactory->method('create')->willReturnCallback(
            static fn (): AddressSuggestionInterface => new AddressSuggestion()
        );
    }

    /**
     * Wires an in-memory fake CacheInterface (backed by a plain array) and a real JSON serializer, so
     * save()/load() round-trip for real instead of being individually mocked.
     *
     * @return void
     */
    private function useInMemoryCache(): void
    {
        $this->cache = new class implements CacheInterface {
            /**
             * @var array<string, string>
             */
            private array $storage = [];

            public function getFrontend()
            {
                return null;
            }

            public function load($identifier)
            {
                return $this->storage[$identifier] ?? false;
            }

            public function save($data, $identifier, $tags = [], $lifeTime = null)
            {
                $this->storage[$identifier] = $data;

                return true;
            }

            public function remove($identifier)
            {
                unset($this->storage[$identifier]);

                return true;
            }

            public function clean($tags = [])
            {
                $this->storage = [];

                return true;
            }
        };
        $this->serializer = new Json();

        $this->addressAutocomplete = new AddressAutocomplete(
            $this->gateway,
            $this->moduleConfig,
            $this->regionResolver,
            $this->suggestionFactory,
            $this->cache,
            $this->serializer,
            $this->logger,
            $this->storeManager
        );
    }

    public function testItReturnsMappedAddressSuggestionObjectsIncludingResolvedRegionIdForAGivenSearchString(): void
    {
        $this->useRealSuggestionFactory();

        $this->gateway->method('lookup')->with('123 Main St')->willReturn([
            [
                'street_line' => '123 Main St',
                'secondary' => 'Apt 4',
                'city' => 'Anytown',
                'state' => 'CA',
                'zipcode' => '90210',
                'entries' => 1,
            ],
        ]);

        $this->regionResolver->method('resolve')->with('CA', 'US')->willReturn(12);

        $result = $this->addressAutocomplete->suggest('123 Main St');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(AddressSuggestionInterface::class, $result[0]);
        $this->assertSame('123 Main St', $result[0]->getStreetLine());
        $this->assertSame('Apt 4', $result[0]->getSecondary());
        $this->assertSame('Anytown', $result[0]->getCity());
        $this->assertSame('CA', $result[0]->getStateAbbreviation());
        $this->assertSame(12, $result[0]->getRegionId());
        $this->assertSame('90210', $result[0]->getZipcode());
    }

    public function testItReturnsAnEmptyArrayWithoutCallingTheGatewayWhenTheModuleIsDisabled(): void
    {
        $this->moduleConfig = $this->createMock(ModuleConfig::class);
        $this->moduleConfig->method('isAutocompleteEnabled')->with(1)->willReturn(false);

        $this->addressAutocomplete = new AddressAutocomplete(
            $this->gateway,
            $this->moduleConfig,
            $this->regionResolver,
            $this->suggestionFactory,
            $this->cache,
            $this->serializer,
            $this->logger,
            $this->storeManager
        );

        $this->gateway->expects($this->never())->method('lookup');

        $this->assertSame([], $this->addressAutocomplete->suggest('123 Main St'));
    }

    public function testItReturnsAnEmptyArrayWithoutCallingTheGatewayWhenAutocompleteIsOffButTheModuleIsEnabled(): void
    {
        $this->moduleConfig = $this->createMock(ModuleConfig::class);
        $this->moduleConfig->method('isEnabled')->willReturn(true);
        $this->moduleConfig->method('isAutocompleteEnabled')->with(1)->willReturn(false);

        $this->addressAutocomplete = new AddressAutocomplete(
            $this->gateway,
            $this->moduleConfig,
            $this->regionResolver,
            $this->suggestionFactory,
            $this->cache,
            $this->serializer,
            $this->logger,
            $this->storeManager
        );

        $this->gateway->expects($this->never())->method('lookup');
        $this->logger->expects($this->never())->method('error');

        $this->assertSame([], $this->addressAutocomplete->suggest('123 Main St'));
    }

    public function testItReturnsAnEmptyArrayWithoutCallingTheGatewayWhenTheCountryIdIsProvidedAndIsNotUs(): void
    {
        $this->gateway->expects($this->never())->method('lookup');

        $this->assertSame([], $this->addressAutocomplete->suggest('123 Main St', 'CA'));
    }

    public function testItReturnsAnEmptyArrayWithoutCallingTheGatewayWhenTheSearchIsUnderThreeChars(): void
    {
        $this->gateway->expects($this->never())->method('lookup');

        $this->assertSame([], $this->addressAutocomplete->suggest('12'));
    }

    public function testItReturnsAnEmptyArrayWithoutCallingTheGatewayWhenTheSearchStringIsEmptyOrWhitespace(): void
    {
        $this->gateway->expects($this->never())->method('lookup');

        $this->assertSame([], $this->addressAutocomplete->suggest('   '));
    }

    public function testItReturnsACachedResultForARepeatedIdenticalSearchWithoutCallingTheGatewayAgain(): void
    {
        $this->useRealSuggestionFactory();
        $this->useInMemoryCache();

        $this->gateway->expects($this->once())->method('lookup')->with('123 Main St')->willReturn([
            [
                'street_line' => '123 Main St',
                'secondary' => '',
                'city' => 'Anytown',
                'state' => 'CA',
                'zipcode' => '90210',
                'entries' => 1,
            ],
        ]);

        $this->regionResolver->method('resolve')->with('CA', 'US')->willReturn(12);

        $first = $this->addressAutocomplete->suggest('123 Main St');
        $second = $this->addressAutocomplete->suggest('123 Main St');

        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        $this->assertSame($first[0]->getStreetLine(), $second[0]->getStreetLine());
        $this->assertSame($first[0]->getRegionId(), $second[0]->getRegionId());
    }

    public function testItReturnsAnEmptyArrayAndLogsAnErrorWithoutTheSearchStringWhenTheGatewayThrows(): void
    {
        $exception = new \RuntimeException('Smarty is unreachable');

        $this->gateway->method('lookup')->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->callback(static function (string $message): bool {
                    return str_contains($message, 'RuntimeException')
                        && str_contains($message, 'Smarty is unreachable')
                        && !str_contains($message, '123 Main St');
                }),
                $this->callback(static function (array $context) use ($exception): bool {
                    return $context === ['store_id' => 1, 'exception' => $exception];
                })
            );

        $this->assertSame([], $this->addressAutocomplete->suggest('123 Main St'));
    }

    public function testItStopsCallingTheGatewayForSubsequentSearchesOnceALookupHasFailed(): void
    {
        $this->useInMemoryCache();

        $this->gateway->expects($this->once())
            ->method('lookup')
            ->willThrowException(new \RuntimeException('Smarty is unreachable'));

        $this->assertSame([], $this->addressAutocomplete->suggest('123 Main St'));
        $this->assertSame([], $this->addressAutocomplete->suggest('456 Oak Ave'));
        $this->assertSame([], $this->addressAutocomplete->suggest('789 Pine Rd'));
    }

    public function testItLogsOnlyOnceWhileTheFailureMarkerIsHeldRatherThanOncePerSearch(): void
    {
        $this->useInMemoryCache();

        $this->gateway->method('lookup')->willThrowException(new \RuntimeException('Smarty is unreachable'));

        $this->logger->expects($this->once())->method('error');

        $this->addressAutocomplete->suggest('123 Main St');
        $this->addressAutocomplete->suggest('456 Oak Ave');
        $this->addressAutocomplete->suggest('789 Pine Rd');
    }

    public function testItCallsTheGatewayAgainOnceTheFailureMarkerHasExpired(): void
    {
        // The default cache mock returns false from every load(), standing in for an expired marker.
        $this->useRealSuggestionFactory();
        $this->regionResolver->method('resolve')->willReturn(null);

        $this->gateway->expects($this->exactly(2))
            ->method('lookup')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new \RuntimeException('Smarty is unreachable')),
                [
                    [
                        'street_line' => '123 Main St',
                        'secondary' => '',
                        'city' => 'Anytown',
                        'state' => 'CA',
                        'zipcode' => '90210',
                        'entries' => 1,
                    ],
                ]
            );

        $this->assertSame([], $this->addressAutocomplete->suggest('123 Main St'));
        $this->assertCount(1, $this->addressAutocomplete->suggest('123 Main St'));
    }

    public function testItLimitsResultsToAMaximumOfTenSuggestions(): void
    {
        $this->useRealSuggestionFactory();
        $this->regionResolver->method('resolve')->willReturn(null);

        $rawSuggestions = [];
        for ($i = 0; $i < 15; $i++) {
            $rawSuggestions[] = [
                'street_line' => "123 Main St Apt $i",
                'secondary' => '',
                'city' => 'Anytown',
                'state' => 'CA',
                'zipcode' => '90210',
                'entries' => 1,
            ];
        }

        $this->gateway->method('lookup')->willReturn($rawSuggestions);

        $result = $this->addressAutocomplete->suggest('123 Main St');

        $this->assertCount(10, $result);
    }
}
