<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Model\Smarty;

use SmartyStreets\PhpSdk\US_Autocomplete_Pro\Lookup;
use SmartyStreets\PhpSdk\US_Autocomplete_Pro\Suggestion;

/**
 * Thin adapter over the Smarty US Autocomplete Pro SDK client.
 */
class AutocompleteGateway implements AutocompleteGatewayInterface
{
    /**
     * @var ClientFactoryInterface
     */
    private ClientFactoryInterface $clientFactory;

    /**
     * @param ClientFactoryInterface $clientFactory
     */
    public function __construct(ClientFactoryInterface $clientFactory)
    {
        $this->clientFactory = $clientFactory;
    }

    /**
     * @inheritDoc
     */
    public function lookup(string $search): array
    {
        $lookup = new Lookup($search);

        $client = $this->clientFactory->createAutocompleteClient();
        $client->sendLookup($lookup);

        $suggestions = $lookup->getResult() ?? [];

        return array_map([$this, 'normalize'], $suggestions);
    }

    /**
     * Normalize an SDK Suggestion object into the documented plain-array shape.
     *
     * @param Suggestion $suggestion
     * @return array{street_line: string, secondary: string, city: string, state: string, zipcode: string,
     *     entries: int}
     */
    private function normalize(Suggestion $suggestion): array
    {
        return [
            'street_line' => (string) $suggestion->getStreetLine(),
            'secondary' => (string) $suggestion->getSecondary(),
            'city' => (string) $suggestion->getCity(),
            'state' => (string) $suggestion->getState(),
            'zipcode' => (string) $suggestion->getZIPCode(),
            'entries' => (int) $suggestion->getEntries(),
        ];
    }
}
