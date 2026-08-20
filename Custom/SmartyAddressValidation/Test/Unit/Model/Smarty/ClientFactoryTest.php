<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Test\Unit\Model\Smarty;

use Custom\SmartyAddressValidation\Model\Config\ModuleConfig;
use Custom\SmartyAddressValidation\Model\Smarty\ClientFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SmartyStreets\PhpSdk\NativeSender;
use SmartyStreets\PhpSdk\RetrySender;
use SmartyStreets\PhpSdk\Sender;
use SmartyStreets\PhpSdk\US_Autocomplete_Pro\Client as AutocompleteClient;
use SmartyStreets\PhpSdk\US_Street\Client as StreetClient;

class ClientFactoryTest extends TestCase
{
    /**
     * @var ScopeConfigInterface&MockObject
     */
    private $scopeConfig;

    /**
     * @var EncryptorInterface&MockObject
     */
    private $encryptor;

    /**
     * @var ClientFactory
     */
    private ClientFactory $clientFactory;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->scopeConfig->method('getValue')->willReturn('some-value');
        $this->encryptor->method('decrypt')->willReturn('some-decrypted-value');

        $moduleConfig = new ModuleConfig($this->scopeConfig, $this->encryptor);
        $this->clientFactory = new ClientFactory($moduleConfig);
    }

    public function testItConfiguresTheSmartyClientWithA3000msTimeoutAndOneRetry(): void
    {
        $client = $this->clientFactory->createAutocompleteClient();

        $this->assertInstanceOf(AutocompleteClient::class, $client);
        $this->assertClientConfiguredWithTimeoutAndRetries($this->readPrivateProperty($client, 'sender'));
    }

    public function testItCreatesAStreetClientConfiguredWithA3000msTimeoutAndOneRetry(): void
    {
        $client = $this->clientFactory->createStreetClient();

        $this->assertInstanceOf(StreetClient::class, $client);
        $this->assertClientConfiguredWithTimeoutAndRetries($this->readPrivateProperty($client, 'sender'));
    }

    /**
     * Assert that the given Sender decorator chain is configured with a 3000ms timeout and 1 retry.
     *
     * @param Sender $senderChain
     * @return void
     */
    private function assertClientConfiguredWithTimeoutAndRetries(Sender $senderChain): void
    {
        $retrySender = $this->findSenderInChain($senderChain, RetrySender::class);
        $this->assertInstanceOf(RetrySender::class, $retrySender);
        $this->assertSame(1, $retrySender->getMaxRetries());

        $nativeSender = $this->findSenderInChain($senderChain, NativeSender::class);
        $this->assertInstanceOf(NativeSender::class, $nativeSender);
        $this->assertSame(3000, $this->readPrivateProperty($nativeSender, 'maxTimeOut'));
    }

    /**
     * Walk a Sender decorator chain looking for an instance of the given class.
     *
     * @param Sender $sender
     * @param string $class
     * @return Sender|null
     */
    private function findSenderInChain(Sender $sender, string $class): ?Sender
    {
        if ($sender instanceof $class) {
            return $sender;
        }

        $reflection = new \ReflectionClass($sender);
        if (!$reflection->hasProperty('inner')) {
            return null;
        }

        return $this->findSenderInChain($this->readPrivateProperty($sender, 'inner'), $class);
    }

    /**
     * Read a private property's value via reflection.
     *
     * @param object $object
     * @param string $property
     * @return mixed
     */
    private function readPrivateProperty(object $object, string $property)
    {
        $reflectionProperty = new \ReflectionProperty($object, $property);
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($object);
    }
}
