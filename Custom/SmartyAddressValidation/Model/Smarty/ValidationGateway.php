<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Model\Smarty;

use Custom\SmartyAddressValidation\Api\Data\AddressValidationRequestInterface;
use SmartyStreets\PhpSdk\US_Street\Candidate;
use SmartyStreets\PhpSdk\US_Street\Lookup;

/**
 * Thin adapter over the Smarty US Street Address SDK client.
 */
class ValidationGateway implements ValidationGatewayInterface
{
    /**
     * Request only the single best match — task 007's business logic only needs one candidate.
     */
    private const MAX_CANDIDATES = 1;

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
    public function lookup(AddressValidationRequestInterface $address): ?array
    {
        $lookup = new Lookup(
            $address->getStreet(),
            $address->getStreet2(),
            null,
            $address->getCity(),
            $address->getRegionCode(),
            $address->getPostcode()
        );
        $lookup->setMaxCandidates(self::MAX_CANDIDATES);

        $client = $this->clientFactory->createStreetClient();
        $client->sendLookup($lookup);

        $candidates = $lookup->getResult() ?? [];

        if (empty($candidates)) {
            return null;
        }

        return $this->normalize($candidates[0]);
    }

    /**
     * Normalize an SDK Candidate object into the documented plain-array shape.
     *
     * @param Candidate $candidate
     * @return array{delivery_line_1: string, delivery_line_2: string, city_name: string,
     *     state_abbreviation: string, zipcode: string, plus4_code: string, dpv_match_code: string,
     *     dpv_footnotes: string, footnotes: string, rdi: string, latitude: float|null,
     *     longitude: float|null, precision: string}
     */
    private function normalize(Candidate $candidate): array
    {
        $components = $candidate->getComponents();
        $analysis = $candidate->getAnalysis();
        $metadata = $candidate->getMetadata();
        $latitude = $metadata->getLatitude();
        $longitude = $metadata->getLongitude();

        return [
            'rdi' => (string) $metadata->getRdi(),
            // Smarty omits coordinates entirely for some ZIP-only matches, so keep null distinct from 0.0.
            'latitude' => $latitude !== null && $latitude !== '' ? (float) $latitude : null,
            'longitude' => $longitude !== null && $longitude !== '' ? (float) $longitude : null,
            'precision' => (string) $metadata->getPrecision(),
            'delivery_line_1' => (string) $candidate->getDeliveryLine1(),
            'delivery_line_2' => (string) $candidate->getDeliveryLine2(),
            'city_name' => (string) $components->getCityName(),
            'state_abbreviation' => (string) $components->getStateAbbreviation(),
            'zipcode' => (string) $components->getZipcode(),
            'plus4_code' => (string) $components->getPlus4Code(),
            'dpv_match_code' => (string) $analysis->getDpvMatchCode(),
            'dpv_footnotes' => (string) $analysis->getDpvFootnotes(),
            'footnotes' => (string) $analysis->getFootnotes(),
        ];
    }
}
