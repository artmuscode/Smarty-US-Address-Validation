<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Test\Unit\Model\Smarty;

use Custom\SmartyAddressValidation\Model\Smarty\AutocompleteGateway;
use Custom\SmartyAddressValidation\Model\Smarty\ClientFactoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SmartyStreets\PhpSdk\NativeSerializer;
use SmartyStreets\PhpSdk\Request;
use SmartyStreets\PhpSdk\Response;
use SmartyStreets\PhpSdk\Sender;
use SmartyStreets\PhpSdk\US_Autocomplete_Pro\Client as AutocompleteClient;

class AutocompleteGatewayTest extends TestCase
{
    /**
     * @var ClientFactoryInterface&MockObject
     */
    private $clientFactory;

    /**
     * @var AutocompleteGateway
     */
    private AutocompleteGateway $gateway;

    /**
     * @var Sender
     */
    private $fakeSender;

    protected function setUp(): void
    {
        $this->clientFactory = $this->createMock(ClientFactoryInterface::class);
        $this->gateway = new AutocompleteGateway($this->clientFactory);
    }

    /**
     * Builds a fake Sender that captures the last Request it was asked to send and returns the given
     * JSON-encoded payload (or throws the given exception instead, if provided).
     *
     * @param string $responseJson
     * @param \Throwable|null $throws
     * @return Sender
     */
    private function fakeSender(string $responseJson = '{"suggestions":[]}', ?\Throwable $throws = null): Sender
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
     * Configures the injected ClientFactoryInterface mock to return an AutocompleteClient built over the
     * given fake Sender.
     *
     * @param Sender $sender
     * @return void
     */
    private function useSender(Sender $sender): void
    {
        $this->clientFactory->method('createAutocompleteClient')
            ->willReturn(new AutocompleteClient($sender, new NativeSerializer()));
    }

    public function testItBuildsASmartyUsAutocompleteProRequestFromTheSearchString(): void
    {
        $sender = $this->fakeSender();
        $this->useSender($sender);

        $this->gateway->lookup('123 Main St');

        $this->assertSame('123 Main St', $sender->lastRequest->getParameters()['search']);
    }

    public function testItReturnsAnEmptyArrayWhenSmartyReturnsNoCandidates(): void
    {
        $this->useSender($this->fakeSender('{"suggestions":[]}'));

        $this->assertSame([], $this->gateway->lookup('123 Main St'));
    }

    public function testItNormalizesSmartysResponseEntriesIntoTheDocumentedArrayShapeWhenSmartyReturnsCandidates(): void
    {
        $responseJson = json_encode([
            'suggestions' => [
                [
                    'street_line' => '123 Main St',
                    'secondary' => 'Apt 4',
                    'city' => 'Anytown',
                    'state' => 'CA',
                    'zipcode' => '90210',
                    'entries' => 3,
                ],
            ],
        ]);

        $this->useSender($this->fakeSender($responseJson));

        $result = $this->gateway->lookup('123 Main St');

        $this->assertSame([
            [
                'street_line' => '123 Main St',
                'secondary' => 'Apt 4',
                'city' => 'Anytown',
                'state' => 'CA',
                'zipcode' => '90210',
                'entries' => 3,
            ],
        ], $result);
    }

    public function testItThrowsWhenTheUnderlyingSmartyClientThrows(): void
    {
        $this->useSender($this->fakeSender('{"suggestions":[]}', new \RuntimeException('Smarty is unreachable')));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Smarty is unreachable');

        $this->gateway->lookup('123 Main St');
    }
}
