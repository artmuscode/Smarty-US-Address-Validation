<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Test\Unit\Model\Smarty;

use Custom\SmartyAddressValidation\Api\Data\AddressValidationRequestInterface;
use Custom\SmartyAddressValidation\Model\Data\AddressValidationRequest;
use Custom\SmartyAddressValidation\Model\Smarty\ClientFactoryInterface;
use Custom\SmartyAddressValidation\Model\Smarty\ValidationGateway;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SmartyStreets\PhpSdk\NativeSerializer;
use SmartyStreets\PhpSdk\Request;
use SmartyStreets\PhpSdk\Response;
use SmartyStreets\PhpSdk\Sender;
use SmartyStreets\PhpSdk\US_Street\Client as StreetClient;

class ValidationGatewayTest extends TestCase
{
    /**
     * @var ClientFactoryInterface&MockObject
     */
    private $clientFactory;

    /**
     * @var ValidationGateway
     */
    private ValidationGateway $gateway;

    /**
     * @var Sender
     */
    private $fakeSender;

    protected function setUp(): void
    {
        $this->clientFactory = $this->createMock(ClientFactoryInterface::class);
        $this->gateway = new ValidationGateway($this->clientFactory);
    }

    /**
     * Builds a fake Sender that captures the last Request it was asked to send and returns the given
     * JSON-encoded payload (or throws the given exception instead, if provided).
     *
     * @param string $responseJson
     * @param \Throwable|null $throws
     * @return Sender
     */
    private function fakeSender(string $responseJson = '[]', ?\Throwable $throws = null): Sender
    {
        $this->fakeSender = new class ($responseJson, $throws) implements Sender {
            /**
             * @var string
             */
            private string $responseJson;

            /**
             * @var \Throwable|null
             */
            private ?\Throwable $throws;

            /**
             * @var Request|null
             */
            public ?Request $lastRequest = null;

            public function __construct(string $responseJson, ?\Throwable $throws)
            {
                $this->responseJson = $responseJson;
                $this->throws = $throws;
            }

            public function send(Request $request)
            {
                $this->lastRequest = $request;

                if ($this->throws !== null) {
                    throw $this->throws;
                }

                return new Response(200, $this->responseJson, []);
            }
        };

        return $this->fakeSender;
    }

    /**
     * Configures the injected ClientFactoryInterface mock to return a StreetClient built over the given
     * fake Sender.
     *
     * @param Sender $sender
     * @return void
     */
    private function useSender(Sender $sender): void
    {
        $this->clientFactory->method('createStreetClient')
            ->willReturn(new StreetClient($sender, new NativeSerializer()));
    }

    /**
     * Builds an AddressValidationRequestInterface populated with fixture data for the gateway tests.
     *
     * @return AddressValidationRequestInterface
     */
    private function addressRequest(): AddressValidationRequestInterface
    {
        return (new AddressValidationRequest())
            ->setStreet('123 Main St')
            ->setStreet2('Apt 4')
            ->setCity('Anytown')
            ->setRegionCode('CA')
            ->setPostcode('90210')
            ->setCountryId('US');
    }

    public function testItBuildsASmartyUsStreetAddressLookupRequestingAtMost1CandidateFromTheGivenAddressRequest(): void
    {
        $sender = $this->fakeSender();
        $this->useSender($sender);

        $this->gateway->lookup($this->addressRequest());

        $parameters = $sender->lastRequest->getParameters();

        $this->assertSame('123 Main St', $parameters['street']);
        $this->assertSame('Apt 4', $parameters['street2']);
        $this->assertSame('Anytown', $parameters['city']);
        $this->assertSame('CA', $parameters['state']);
        $this->assertSame('90210', $parameters['zipcode']);
        $this->assertSame(1, $parameters['candidates']);
    }

    public function testItReturnsNullWhenSmartyReturnsNoCandidateMatch(): void
    {
        $this->useSender($this->fakeSender('[]'));

        $this->assertNull($this->gateway->lookup($this->addressRequest()));
    }

    /**
     * It normalizes the SDK candidate object into the documented array shape, including the DPV match
     * code, when Smarty returns a candidate.
     */
    public function testItNormalizesTheCandidateIntoTheDocumentedArrayShapeWithDpvMatchCode(): void
    {
        $responseJson = json_encode([
            [
                'input_index' => 0,
                'candidate_index' => 0,
                'delivery_line_1' => '123 Main St',
                'delivery_line_2' => 'Apt 4',
                'components' => [
                    'city_name' => 'Anytown',
                    'state_abbreviation' => 'CA',
                    'zipcode' => '90210',
                    'plus4_code' => '1234',
                ],
                'analysis' => [
                    'dpv_match_code' => 'Y',
                    'dpv_footnotes' => 'AABB',
                    'footnotes' => 'N#',
                ],
                'metadata' => [
                    'rdi' => 'Residential',
                    'latitude' => 37.4224764,
                    'longitude' => -122.0842499,
                    'precision' => 'Rooftop',
                ],
            ],
        ]);

        $this->useSender($this->fakeSender($responseJson));

        $result = $this->gateway->lookup($this->addressRequest());

        $this->assertSame([
            'rdi' => 'Residential',
            'latitude' => 37.4224764,
            'longitude' => -122.0842499,
            'precision' => 'Rooftop',
            'delivery_line_1' => '123 Main St',
            'delivery_line_2' => 'Apt 4',
            'city_name' => 'Anytown',
            'state_abbreviation' => 'CA',
            'zipcode' => '90210',
            'plus4_code' => '1234',
            'dpv_match_code' => 'Y',
            'dpv_footnotes' => 'AABB',
            'footnotes' => 'N#',
        ], $result);
    }

    public function testItThrowsWhenTheUnderlyingSmartyClientThrows(): void
    {
        $this->useSender($this->fakeSender('[]', new \RuntimeException('Smarty is unreachable')));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Smarty is unreachable');

        $this->gateway->lookup($this->addressRequest());
    }
}
