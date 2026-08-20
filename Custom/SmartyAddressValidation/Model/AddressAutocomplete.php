<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Model;

use Custom\SmartyAddressValidation\Api\AddressAutocompleteInterface;
use Custom\SmartyAddressValidation\Api\Data\AddressSuggestionInterface;
use Custom\SmartyAddressValidation\Api\Data\AddressSuggestionInterfaceFactory;
use Custom\SmartyAddressValidation\Model\Config\ModuleConfig;
use Custom\SmartyAddressValidation\Model\Smarty\AutocompleteGatewayInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Maps raw Smarty autocomplete Gateway results into address suggestion DTOs.
 *
 * Owns the enabled-check, server-side US-only gate, minimum-input gating, short-TTL response
 * caching, and fail-open error handling around the Gateway.
 */
class AddressAutocomplete implements AddressAutocompleteInterface
{
    private const US_COUNTRY_ID = 'US';
    private const MIN_SEARCH_LENGTH = 3;
    private const MAX_RESULTS = 10;
    private const CACHE_TTL = 300;
    private const CACHE_ID_PREFIX = 'smarty_address_validation_autocomplete_';
    private const FAILURE_CACHE_TTL = 300;
    private const FAILURE_CACHE_ID_PREFIX = self::CACHE_ID_PREFIX . 'failure_';

    /**
     * @var AutocompleteGatewayInterface
     */
    private AutocompleteGatewayInterface $gateway;

    /**
     * @var ModuleConfig
     */
    private ModuleConfig $moduleConfig;

    /**
     * @var RegionResolver
     */
    private RegionResolver $regionResolver;

    /**
     * @var AddressSuggestionInterfaceFactory
     */
    private AddressSuggestionInterfaceFactory $suggestionFactory;

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
     * @param AutocompleteGatewayInterface $gateway
     * @param ModuleConfig $moduleConfig
     * @param RegionResolver $regionResolver
     * @param AddressSuggestionInterfaceFactory $suggestionFactory
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        AutocompleteGatewayInterface $gateway,
        ModuleConfig $moduleConfig,
        RegionResolver $regionResolver,
        AddressSuggestionInterfaceFactory $suggestionFactory,
        CacheInterface $cache,
        SerializerInterface $serializer,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager
    ) {
        $this->gateway = $gateway;
        $this->moduleConfig = $moduleConfig;
        $this->regionResolver = $regionResolver;
        $this->suggestionFactory = $suggestionFactory;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritDoc
     */
    public function suggest(string $search, ?string $countryId = null): array
    {
        $storeId = (int) $this->storeManager->getStore()->getId();

        if (!$this->moduleConfig->isAutocompleteEnabled($storeId)) {
            return [];
        }

        if ($countryId !== null && $countryId !== self::US_COUNTRY_ID) {
            return [];
        }

        $normalizedSearch = trim($search);

        if (mb_strlen($normalizedSearch) < self::MIN_SEARCH_LENGTH) {
            return [];
        }

        $cacheId = $this->buildCacheId($normalizedSearch, $countryId ?? self::US_COUNTRY_ID);
        $cached = $this->cache->load($cacheId);

        if ($cached !== false) {
            return $this->buildSuggestions((array) $this->serializer->unserialize($cached));
        }

        $failureCacheId = self::FAILURE_CACHE_ID_PREFIX . $storeId;

        // A failing account (most often one whose plan does not include US Autocomplete Pro) would
        // otherwise be retried on every keystroke. Back off for the whole store until the marker expires.
        if ($this->cache->load($failureCacheId) !== false) {
            return [];
        }

        try {
            $rawSuggestions = $this->gateway->lookup($normalizedSearch);
        } catch (\Throwable $exception) {
            // Logged at error with the exception attached: this catch is deliberately broad so a
            // Smarty outage cannot break checkout, which also means it swallows programming errors.
            // Logged only when the marker is first set, so a sustained outage costs one line per TTL
            // rather than one per search.
            $this->cache->save('1', $failureCacheId, [], self::FAILURE_CACHE_TTL);

            $this->logger->error(
                sprintf('%s: %s', get_class($exception), $exception->getMessage()),
                ['store_id' => $storeId, 'exception' => $exception]
            );

            return [];
        }

        $rawSuggestions = array_slice($rawSuggestions, 0, self::MAX_RESULTS);
        $suggestionData = array_map([$this, 'mapRawSuggestion'], $rawSuggestions);

        $this->cache->save($this->serializer->serialize($suggestionData), $cacheId, [], self::CACHE_TTL);

        return $this->buildSuggestions($suggestionData);
    }

    /**
     * Build the cache identifier for a normalized search string and country id.
     *
     * @param string $normalizedSearch
     * @param string $countryId
     * @return string
     */
    private function buildCacheId(string $normalizedSearch, string $countryId): string
    {
        return self::CACHE_ID_PREFIX . hash('sha256', mb_strtolower($normalizedSearch) . '|' . $countryId);
    }

    /**
     * Map a single raw Gateway suggestion array into a cache-friendly plain array, resolving the region id.
     *
     * @param array $rawSuggestion
     * @return array
     */
    private function mapRawSuggestion(array $rawSuggestion): array
    {
        $stateAbbreviation = (string) ($rawSuggestion['state'] ?? '');

        return [
            'street_line' => (string) ($rawSuggestion['street_line'] ?? ''),
            'secondary' => (string) ($rawSuggestion['secondary'] ?? ''),
            'city' => (string) ($rawSuggestion['city'] ?? ''),
            'state_abbreviation' => $stateAbbreviation,
            'region_id' => $this->regionResolver->resolve($stateAbbreviation, self::US_COUNTRY_ID),
            'zipcode' => (string) ($rawSuggestion['zipcode'] ?? ''),
        ];
    }

    /**
     * Build AddressSuggestionInterface DTOs from mapped plain-array suggestion data.
     *
     * @param array[] $suggestionData
     * @return AddressSuggestionInterface[]
     */
    private function buildSuggestions(array $suggestionData): array
    {
        return array_map(function (array $data): AddressSuggestionInterface {
            $suggestion = $this->suggestionFactory->create();
            $suggestion->setStreetLine($data['street_line']);
            $suggestion->setSecondary($data['secondary']);
            $suggestion->setCity($data['city']);
            $suggestion->setStateAbbreviation($data['state_abbreviation']);
            $suggestion->setRegionId($data['region_id']);
            $suggestion->setZipcode($data['zipcode']);

            return $suggestion;
        }, $suggestionData);
    }
}
