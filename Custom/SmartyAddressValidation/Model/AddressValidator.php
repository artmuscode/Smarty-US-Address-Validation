<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Model;

use Custom\SmartyAddressValidation\Api\AddressValidatorInterface;
use Custom\SmartyAddressValidation\Api\Data\AddressGeoDataInterface;
use Custom\SmartyAddressValidation\Api\Data\AddressGeoDataInterfaceFactory;
use Custom\SmartyAddressValidation\Api\Data\AddressValidationRequestInterface;
use Custom\SmartyAddressValidation\Api\Data\AddressValidationRequestInterfaceFactory;
use Custom\SmartyAddressValidation\Api\Data\AddressValidationResultInterface;
use Custom\SmartyAddressValidation\Api\Data\AddressValidationResultInterfaceFactory;
use Custom\SmartyAddressValidation\Model\Config\ModuleConfig;
use Custom\SmartyAddressValidation\Model\Smarty\ValidationGatewayInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Validates a US address against the Smarty API, mapping the result into a match/deliverability outcome.
 *
 * Owns the enabled-check, US-only country gate, incomplete-input gate, short-TTL response
 * caching, and fail-open error handling around the Gateway.
 */
class AddressValidator implements AddressValidatorInterface
{
    private const US_COUNTRY_ID = 'US';
    private const DPV_MATCH_CODE_DELIVERABLE = 'Y';
    private const CACHE_TTL = 300;
    private const CACHE_ID_PREFIX = 'smarty_address_validation_validate_';

    /**
     * @var ValidationGatewayInterface
     */
    private ValidationGatewayInterface $gateway;

    /**
     * @var ModuleConfig
     */
    private ModuleConfig $moduleConfig;

    /**
     * @var RegionResolver
     */
    private RegionResolver $regionResolver;

    /**
     * @var AddressValidationRequestInterfaceFactory
     */
    private AddressValidationRequestInterfaceFactory $requestFactory;

    /**
     * @var AddressValidationResultInterfaceFactory
     */
    private AddressValidationResultInterfaceFactory $resultFactory;

    /**
     * @var AddressGeoDataInterfaceFactory
     */
    private AddressGeoDataInterfaceFactory $geoDataFactory;

    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param ValidationGatewayInterface $gateway
     * @param ModuleConfig $moduleConfig
     * @param RegionResolver $regionResolver
     * @param AddressValidationRequestInterfaceFactory $requestFactory
     * @param AddressValidationResultInterfaceFactory $resultFactory
     * @param AddressGeoDataInterfaceFactory $geoDataFactory
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ValidationGatewayInterface $gateway,
        ModuleConfig $moduleConfig,
        RegionResolver $regionResolver,
        AddressValidationRequestInterfaceFactory $requestFactory,
        AddressValidationResultInterfaceFactory $resultFactory,
        AddressGeoDataInterfaceFactory $geoDataFactory,
        CacheInterface $cache,
        SerializerInterface $serializer,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager
    ) {
        $this->gateway = $gateway;
        $this->moduleConfig = $moduleConfig;
        $this->regionResolver = $regionResolver;
        $this->requestFactory = $requestFactory;
        $this->resultFactory = $resultFactory;
        $this->geoDataFactory = $geoDataFactory;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritDoc
     */
    public function validate(AddressValidationRequestInterface $address): AddressValidationResultInterface
    {
        $storeId = (int) $this->storeManager->getStore()->getId();

        if (!$this->moduleConfig->isEnabled($storeId)) {
            return $this->buildResult($address, null);
        }

        if ($address->getCountryId() !== self::US_COUNTRY_ID) {
            return $this->buildResult($address, null);
        }

        if ($this->isIncomplete($address)) {
            return $this->buildResult($address, null);
        }

        $cacheId = $this->buildCacheId($address);
        $cached = $this->cache->load($cacheId);

        if ($cached !== false) {
            return $this->buildResult($address, $this->serializer->unserialize($cached));
        }

        try {
            $raw = $this->gateway->lookup($address);
        } catch (\Throwable $exception) {
            // Logged at error with the exception attached: this catch is deliberately broad so a
            // Smarty outage cannot break checkout, which also means it swallows programming errors.
            $this->logger->error(
                sprintf('%s: %s', get_class($exception), $exception->getMessage()),
                ['store_id' => $storeId, 'exception' => $exception]
            );

            return $this->buildResult($address, null);
        }

        $this->cache->save($this->serializer->serialize($raw), $cacheId, [], self::CACHE_TTL);

        return $this->buildResult($address, $raw);
    }

    /**
     * Build the cache identifier for the normalized address fields.
     *
     * @param AddressValidationRequestInterface $address
     * @return string
     */
    private function buildCacheId(AddressValidationRequestInterface $address): string
    {
        $normalized = implode('|', [
            mb_strtolower(trim($address->getStreet())),
            mb_strtolower(trim((string) $address->getStreet2())),
            mb_strtolower(trim($address->getCity())),
            mb_strtolower(trim($address->getRegionCode())),
            trim($address->getPostcode()),
            mb_strtolower(trim($address->getCountryId())),
        ]);

        return self::CACHE_ID_PREFIX . hash('sha256', $normalized);
    }

    /**
     * Whether the address lacks enough information for a Smarty lookup to be worthwhile.
     *
     * @param AddressValidationRequestInterface $address
     * @return bool
     */
    private function isIncomplete(AddressValidationRequestInterface $address): bool
    {
        if (trim($address->getStreet()) === '') {
            return true;
        }

        if (trim($address->getPostcode()) === ''
            && (trim($address->getCity()) === '' || trim($address->getRegionCode()) === '')
        ) {
            return true;
        }

        return false;
    }

    /**
     * Build the validation result from the original address and a raw Gateway candidate.
     *
     * @param AddressValidationRequestInterface $address
     * @param array|null $raw
     * @return AddressValidationResultInterface
     */
    private function buildResult(
        AddressValidationRequestInterface $address,
        ?array $raw
    ): AddressValidationResultInterface {
        $result = $this->resultFactory->create();
        $result->setOriginal($address);

        if ($raw === null) {
            $result->setSuggested(null);
            $result->setMatched(false);
            $result->setDeliverable(false);
            $result->setGeoData(null);

            return $result;
        }

        $stateAbbreviation = (string) ($raw['state_abbreviation'] ?? '');

        $suggested = $this->requestFactory->create();
        $suggested->setStreet((string) ($raw['delivery_line_1'] ?? ''));
        $street2 = (string) ($raw['delivery_line_2'] ?? '');
        $suggested->setStreet2($street2 !== '' ? $street2 : null);
        $suggested->setCity((string) ($raw['city_name'] ?? ''));
        $suggested->setRegionCode($stateAbbreviation);
        $suggested->setRegionId($this->regionResolver->resolve($stateAbbreviation, self::US_COUNTRY_ID));
        $suggested->setPostcode((string) ($raw['zipcode'] ?? ''));
        $suggested->setCountryId(self::US_COUNTRY_ID);

        $result->setSuggested($suggested);
        $result->setMatched(true);
        $result->setDeliverable(($raw['dpv_match_code'] ?? '') === self::DPV_MATCH_CODE_DELIVERABLE);
        $result->setGeoData($this->buildGeoData($raw));

        return $result;
    }

    /**
     * Build the delivery metadata (residential/commercial plus geocode) from a raw Gateway candidate.
     *
     * @param array $raw
     * @return AddressGeoDataInterface
     */
    private function buildGeoData(array $raw): AddressGeoDataInterface
    {
        $geoData = $this->geoDataFactory->create();
        $geoData->setRdi($this->normalizeRdi((string) ($raw['rdi'] ?? '')));
        $geoData->setLatitude(isset($raw['latitude']) ? (float) $raw['latitude'] : null);
        $geoData->setLongitude(isset($raw['longitude']) ? (float) $raw['longitude'] : null);

        $precision = (string) ($raw['precision'] ?? '');
        $geoData->setGeoPrecision($precision !== '' ? $precision : null);

        return $geoData;
    }

    /**
     * Map Smarty's RDI value onto a stable lowercase enum
     *
     * Downstream consumers do not have to cope with casing or vocabulary changes. Anything
     * unrecognised is reported as unknown (null).
     *
     * @param string $rdi
     * @return string|null
     */
    private function normalizeRdi(string $rdi): ?string
    {
        return match (strtolower(trim($rdi))) {
            'residential' => AddressGeoFields::RDI_RESIDENTIAL,
            'commercial' => AddressGeoFields::RDI_COMMERCIAL,
            default => null,
        };
    }
}
