<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Model\Smarty;

use Custom\SmartyAddressValidation\Model\Config\ModuleConfig;
use SmartyStreets\PhpSdk\ClientBuilder;
use SmartyStreets\PhpSdk\StaticCredentials;
use SmartyStreets\PhpSdk\US_Autocomplete_Pro\Client as AutocompleteClient;
use SmartyStreets\PhpSdk\US_Street\Client as StreetClient;

/**
 * Builds Smarty SDK clients using store-scoped credentials read at call time, bounded by a short timeout
 * and a single retry so a Smarty outage cannot turn into a long hang on checkout.
 */
class ClientFactory implements ClientFactoryInterface
{
    private const TIMEOUT_MS = 3000;
    private const MAX_RETRIES = 1;

    /**
     * @var ModuleConfig
     */
    private ModuleConfig $moduleConfig;

    /**
     * @param ModuleConfig $moduleConfig
     */
    public function __construct(ModuleConfig $moduleConfig)
    {
        $this->moduleConfig = $moduleConfig;
    }

    /**
     * @inheritDoc
     */
    public function createAutocompleteClient(): AutocompleteClient
    {
        return $this->clientBuilder()->buildUSAutocompleteProApiClient();
    }

    /**
     * @inheritDoc
     */
    public function createStreetClient(): StreetClient
    {
        return $this->clientBuilder()->buildUsStreetApiClient();
    }

    /**
     * Build a Smarty ClientBuilder configured with the current store's credentials, timeout, and retries.
     *
     * @return ClientBuilder
     */
    private function clientBuilder(): ClientBuilder
    {
        $credentials = new StaticCredentials(
            (string) $this->moduleConfig->getAuthId(),
            (string) $this->moduleConfig->getAuthToken()
        );

        return (new ClientBuilder($credentials))
            ->withMaxTimeout(self::TIMEOUT_MS)
            ->retryAtMost(self::MAX_RETRIES);
    }
}
