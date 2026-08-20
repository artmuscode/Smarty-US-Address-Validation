<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Test\Unit\Model;

use Custom\SmartyAddressValidation\Api\Data\AddressGeoDataInterface;
use Custom\SmartyAddressValidation\Api\Data\AddressGeoDataInterfaceFactory;
use Custom\SmartyAddressValidation\Api\Data\AddressValidationRequestInterface;
use Custom\SmartyAddressValidation\Api\Data\AddressValidationRequestInterfaceFactory;
use Custom\SmartyAddressValidation\Api\Data\AddressValidationResultInterface;
use Custom\SmartyAddressValidation\Api\Data\AddressValidationResultInterfaceFactory;
use Custom\SmartyAddressValidation\Model\AddressValidator;
use Custom\SmartyAddressValidation\Model\Config\ModuleConfig;
use Custom\SmartyAddressValidation\Model\Data\AddressGeoData;
use Custom\SmartyAddressValidation\Model\Data\AddressValidationRequest;
use Custom\SmartyAddressValidation\Model\Data\AddressValidationResult;
use Custom\SmartyAddressValidation\Model\RegionResolver;
use Custom\SmartyAddressValidation\Model\Smarty\ValidationGatewayInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AddressValidatorTest extends TestCase
{
    /**
     * @var ValidationGatewayInterface&MockObject
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
     * @var AddressValidationRequestInterfaceFactory&MockObject
     */
    private $requestFactory;

    /**
     * @var AddressValidationResultInterfaceFactory&MockObject
     */
    private $resultFactory;

    /**
     * @var AddressGeoDataInterfaceFactory&MockObject
     */
    private $geoDataFactory;

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
     * @var AddressValidator
     */
    private AddressValidator $addressValidator;

    protected function setUp(): void
    {
        $this->gateway = $this->createMock(ValidationGatewayInterface::class);
        $this->moduleConfig = $this->createMock(ModuleConfig::class);
        $this->regionResolver = $this->createMock(RegionResolver::class);
        $this->requestFactory = $this->createMock(AddressValidationRequestInterfaceFactory::class);
        $this->resultFactory = $this->createMock(AddressValidationResultInterfaceFactory::class);
        $this->geoDataFactory = $this->createMock(AddressGeoDataInterfaceFactory::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->moduleConfig->method('isEnabled')->willReturn(true);
        $this->cache->method('load')->willReturn(false);

        $this->requestFactory->method('create')->willReturnCallback(
            static fn (): AddressValidationRequestInterface => new AddressValidationRequest()
        );
        $this->resultFactory->method('create')->willReturnCallback(
            static fn (): AddressValidationResultInterface => new AddressValidationResult()
        );
        $this->geoDataFactory->method('create')->willReturnCallback(
            static fn (): AddressGeoDataInterface => new AddressGeoData()
        );

        $this->addressValidator = $this->createAddressValidator();
    }

    /**
     * @return AddressValidator
     */
    private function createAddressValidator(): AddressValidator
    {
        return new AddressValidator(
            $this->gateway,
            $this->moduleConfig,
            $this->regionResolver,
            $this->requestFactory,
            $this->resultFactory,
            $this->geoDataFactory,
            $this->cache,
            $this->serializer,
            $this->logger,
            $this->storeManager
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

        $this->addressValidator = $this->createAddressValidator();
    }

    /**
     * @return AddressValidationRequestInterface
     */
    private function createValidAddress(): AddressValidationRequestInterface
    {
        $address = new AddressValidationRequest();
        $address->setStreet('123 Main St');
        $address->setStreet2(null);
        $address->setCity('Anytown');
        $address->setRegionCode('CA');
        $address->setPostcode('90210');
        $address->setCountryId('US');

        return $address;
    }

    public function testItReturnsAMatchedDeliverableResultWhenSmartyConfirmsTheAddressAsEntered(): void
    {
        $address = $this->createValidAddress();

        $this->gateway->method('lookup')->with($address)->willReturn([
            'delivery_line_1' => '123 Main St',
            'delivery_line_2' => '',
            'city_name' => 'Anytown',
            'state_abbreviation' => 'CA',
            'zipcode' => '90210',
            'plus4_code' => '1234',
            'dpv_match_code' => 'Y',
            'dpv_footnotes' => 'AABB',
            'footnotes' => '',
        ]);

        $this->regionResolver->method('resolve')->with('CA', 'US')->willReturn(12);

        $result = $this->addressValidator->validate($address);

        $this->assertTrue($result->isMatched());
        $this->assertTrue($result->isDeliverable());
    }

    public function testItReturnsAMatchedResultWithASuggestedAddressAndResolvedRegionIdOnACorrection(): void
    {
        $address = $this->createValidAddress();
        $address->setStreet('123 main street');

        $this->gateway->method('lookup')->with($address)->willReturn([
            'delivery_line_1' => '123 Main St',
            'delivery_line_2' => 'Apt 4',
            'city_name' => 'Anytown',
            'state_abbreviation' => 'CA',
            'zipcode' => '90210',
            'plus4_code' => '1234',
            'dpv_match_code' => 'Y',
            'dpv_footnotes' => 'AABB',
            'footnotes' => '',
        ]);

        $this->regionResolver->method('resolve')->with('CA', 'US')->willReturn(12);

        $result = $this->addressValidator->validate($address);

        $this->assertTrue($result->isMatched());
        $this->assertNotNull($result->getSuggested());
        $this->assertSame('123 Main St', $result->getSuggested()->getStreet());
        $this->assertSame('Apt 4', $result->getSuggested()->getStreet2());
        $this->assertSame('Anytown', $result->getSuggested()->getCity());
        $this->assertSame('CA', $result->getSuggested()->getRegionCode());
        $this->assertSame(12, $result->getSuggested()->getRegionId());
        $this->assertSame('90210', $result->getSuggested()->getPostcode());
        $this->assertSame('US', $result->getSuggested()->getCountryId());
    }

    /**
     * @dataProvider notDeliverableDpvMatchCodeProvider
     */
    public function testItMarksTheResultMatchedButNotDeliverableWhenTheDpvCodeFlagsABadSecondaryUnit(
        string $dpvMatchCode
    ): void {
        $address = $this->createValidAddress();

        $this->gateway->method('lookup')->with($address)->willReturn([
            'delivery_line_1' => '123 Main St',
            'delivery_line_2' => '',
            'city_name' => 'Anytown',
            'state_abbreviation' => 'CA',
            'zipcode' => '90210',
            'plus4_code' => '1234',
            'dpv_match_code' => $dpvMatchCode,
            'dpv_footnotes' => 'AABB',
            'footnotes' => '',
        ]);

        $this->regionResolver->method('resolve')->with('CA', 'US')->willReturn(12);

        $result = $this->addressValidator->validate($address);

        $this->assertTrue($result->isMatched());
        $this->assertFalse($result->isDeliverable());
        $this->assertNotNull($result->getSuggested());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function notDeliverableDpvMatchCodeProvider(): array
    {
        return [
            'missing secondary/apartment number' => ['D'],
            'secondary present but invalid' => ['S'],
        ];
    }

    public function testItReturnsAnUnmatchedResultWithoutCallingTheGatewayWhenTheCountryIdIsNotUs(): void
    {
        $address = $this->createValidAddress();
        $address->setCountryId('CA');

        $this->gateway->expects($this->never())->method('lookup');

        $result = $this->addressValidator->validate($address);

        $this->assertFalse($result->isMatched());
        $this->assertNull($result->getSuggested());
    }

    /**
     * @dataProvider incompleteAddressProvider
     */
    public function testItReturnsAnUnmatchedResultWithoutCallingTheGatewayWhenRequiredFieldsAreMissing(
        string $street,
        string $city,
        string $regionCode,
        string $postcode
    ): void {
        $address = $this->createValidAddress();
        $address->setStreet($street);
        $address->setCity($city);
        $address->setRegionCode($regionCode);
        $address->setPostcode($postcode);

        $this->gateway->expects($this->never())->method('lookup');

        $result = $this->addressValidator->validate($address);

        $this->assertFalse($result->isMatched());
        $this->assertNull($result->getSuggested());
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function incompleteAddressProvider(): array
    {
        return [
            'empty street' => ['', 'Anytown', 'CA', '90210'],
            'empty postcode and empty city' => ['123 Main St', '', 'CA', ''],
            'empty postcode and empty region' => ['123 Main St', 'Anytown', '', ''],
        ];
    }

    public function testItReturnsACachedResultForARepeatedIdenticalAddressWithoutCallingTheGatewayAgain(): void
    {
        $this->useInMemoryCache();

        $address = $this->createValidAddress();

        $this->gateway->expects($this->once())->method('lookup')->with($address)->willReturn([
            'delivery_line_1' => '123 Main St',
            'delivery_line_2' => '',
            'city_name' => 'Anytown',
            'state_abbreviation' => 'CA',
            'zipcode' => '90210',
            'plus4_code' => '1234',
            'dpv_match_code' => 'Y',
            'dpv_footnotes' => 'AABB',
            'footnotes' => '',
        ]);

        $this->regionResolver->method('resolve')->with('CA', 'US')->willReturn(12);

        $first = $this->addressValidator->validate($address);
        $second = $this->addressValidator->validate($address);

        $this->assertTrue($first->isMatched());
        $this->assertTrue($second->isMatched());
        $this->assertTrue($second->isDeliverable());
        $this->assertSame('123 Main St', $second->getSuggested()->getStreet());
        $this->assertSame(12, $second->getSuggested()->getRegionId());
    }

    public function testItReturnsAnUnmatchedResultAndLogsAnErrorWithoutAddressDataWhenTheGatewayThrows(): void
    {
        $address = $this->createValidAddress();
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

        $result = $this->addressValidator->validate($address);

        $this->assertFalse($result->isMatched());
        $this->assertNull($result->getSuggested());
    }

    public function testItReturnsAnUnmatchedResultWithoutCallingTheGatewayWhenTheModuleIsDisabled(): void
    {
        $this->moduleConfig = $this->createMock(ModuleConfig::class);
        $this->moduleConfig->method('isEnabled')->with(1)->willReturn(false);

        $this->addressValidator = $this->createAddressValidator();

        $address = $this->createValidAddress();

        $this->gateway->expects($this->never())->method('lookup');

        $result = $this->addressValidator->validate($address);

        $this->assertFalse($result->isMatched());
        $this->assertNull($result->getSuggested());
    }

    /**
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function rdiProvider(): array
    {
        return [
            'residential' => ['Residential', 'residential'],
            'commercial' => ['Commercial', 'commercial'],
            'lowercase from Smarty' => ['residential', 'residential'],
            'unexpected vocabulary' => ['Mixed', null],
            'not reported' => ['', null],
        ];
    }

    /**
     * @dataProvider rdiProvider
     */
    public function testItNormalizesSmartyRdiOntoAStableLowercaseEnum(string $raw, ?string $expected): void
    {
        $address = $this->createValidAddress();

        $this->gateway->method('lookup')->willReturn([
            'delivery_line_1' => '123 Main St',
            'city_name' => 'Anytown',
            'state_abbreviation' => 'CA',
            'zipcode' => '90210',
            'dpv_match_code' => 'Y',
            'rdi' => $raw,
        ]);

        $geoData = $this->addressValidator->validate($address)->getGeoData();

        $this->assertNotNull($geoData);
        $this->assertSame($expected, $geoData->getRdi());
    }

    public function testItExposesTheGeocodeAndItsPrecisionSoConsumersCanJudgeCoordinateQuality(): void
    {
        $address = $this->createValidAddress();

        $this->gateway->method('lookup')->willReturn([
            'delivery_line_1' => '123 Main St',
            'city_name' => 'Anytown',
            'state_abbreviation' => 'CA',
            'zipcode' => '90210',
            'dpv_match_code' => 'Y',
            'rdi' => 'Commercial',
            'latitude' => 37.4224764,
            'longitude' => -122.0842499,
            'precision' => 'Rooftop',
        ]);

        $geoData = $this->addressValidator->validate($address)->getGeoData();

        $this->assertSame(37.4224764, $geoData->getLatitude());
        $this->assertSame(-122.0842499, $geoData->getLongitude());
        $this->assertSame('Rooftop', $geoData->getGeoPrecision());
    }

    public function testItReportsNullCoordinatesRatherThanZeroWhenSmartyOmitsAGeocode(): void
    {
        $address = $this->createValidAddress();

        $this->gateway->method('lookup')->willReturn([
            'delivery_line_1' => '123 Main St',
            'city_name' => 'Anytown',
            'state_abbreviation' => 'CA',
            'zipcode' => '90210',
            'dpv_match_code' => 'Y',
            'latitude' => null,
            'longitude' => null,
            'precision' => '',
        ]);

        $geoData = $this->addressValidator->validate($address)->getGeoData();

        $this->assertNull($geoData->getLatitude());
        $this->assertNull($geoData->getLongitude());
        $this->assertNull($geoData->getGeoPrecision());
    }

    public function testItCarriesNoGeoDataWhenSmartyReturnsNoCandidate(): void
    {
        $address = $this->createValidAddress();

        $this->gateway->method('lookup')->willReturn(null);

        $this->assertNull($this->addressValidator->validate($address)->getGeoData());
    }
}
