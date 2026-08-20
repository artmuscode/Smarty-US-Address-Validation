<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Model;

use Magento\Directory\Model\RegionFactory;

/**
 * Resolves a region code (e.g. two-letter state abbreviation) to a Magento region id.
 */
class RegionResolver
{
    /**
     * @var \Magento\Directory\Model\RegionFactory
     */
    private RegionFactory $regionFactory;

    /**
     * Resolved region ids, keyed by "{countryId}_{regionCode}", memoized for the life of the request.
     *
     * @var array<string, int|null>
     */
    private array $resolved = [];

    /**
     * @param \Magento\Directory\Model\RegionFactory $regionFactory
     */
    public function __construct(RegionFactory $regionFactory)
    {
        $this->regionFactory = $regionFactory;
    }

    /**
     * Resolve a region code to a Magento region id
     *
     * @param string $regionCode
     * @param string $countryId
     * @return int|null
     */
    public function resolve(string $regionCode, string $countryId = 'US'): ?int
    {
        $cacheKey = $countryId . '_' . $regionCode;

        if (array_key_exists($cacheKey, $this->resolved)) {
            return $this->resolved[$cacheKey];
        }

        $region = $this->regionFactory->create();
        $region->loadByCode($regionCode, $countryId);

        $regionId = $region->getId();

        return $this->resolved[$cacheKey] = $regionId !== null ? (int) $regionId : null;
    }
}
