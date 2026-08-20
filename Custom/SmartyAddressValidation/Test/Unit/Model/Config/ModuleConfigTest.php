<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Test\Unit\Model\Config;

use Custom\SmartyAddressValidation\Model\Config\ModuleConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ModuleConfigTest extends TestCase
{
    private const ENABLED_PATH = 'smarty_address_validation/general/enabled';
    private const AUTOCOMPLETE_ENABLED_PATH = 'smarty_address_validation/general/autocomplete_enabled';
    private const AUTH_ID_PATH = 'smarty_address_validation/general/auth_id';
    private const AUTH_TOKEN_PATH = 'smarty_address_validation/general/auth_token';

    /**
     * @var ScopeConfigInterface&MockObject
     */
    private $scopeConfig;

    /**
     * @var EncryptorInterface&MockObject
     */
    private $encryptor;

    /**
     * @var ModuleConfig
     */
    private ModuleConfig $moduleConfig;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->encryptor = $this->createMock(EncryptorInterface::class);

        $this->moduleConfig = new ModuleConfig($this->scopeConfig, $this->encryptor);
    }

    public function testItReturnsFalseFromIsEnabledWhenSmartyAddressValidationGeneralEnabledIsZero(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with(self::ENABLED_PATH, ScopeInterface::SCOPE_STORE, null)
            ->willReturn(false);

        $this->assertFalse($this->moduleConfig->isEnabled());
    }

    public function testItReturnsTrueFromIsEnabledWhenSmartyAddressValidationGeneralEnabledIsOne(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with(self::ENABLED_PATH, ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);

        $this->scopeConfig->method('getValue')
            ->willReturnMap([
                [self::AUTH_ID_PATH, ScopeInterface::SCOPE_STORE, null, 'my-auth-id'],
                [self::AUTH_TOKEN_PATH, ScopeInterface::SCOPE_STORE, null, 'encrypted-token'],
            ]);

        $this->encryptor->method('decrypt')->willReturn('decrypted-token');

        $this->assertTrue($this->moduleConfig->isEnabled());
    }

    public function testItReturnsTheConfiguredValueFromGetAuthId(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(self::AUTH_ID_PATH, ScopeInterface::SCOPE_STORE, null)
            ->willReturn('my-auth-id');

        $this->assertSame('my-auth-id', $this->moduleConfig->getAuthId());
    }

    public function testItReturnsTheDecryptedValueFromGetAuthToken(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(self::AUTH_TOKEN_PATH, ScopeInterface::SCOPE_STORE, null)
            ->willReturn('encrypted-token');

        $this->encryptor->expects($this->once())
            ->method('decrypt')
            ->with('encrypted-token')
            ->willReturn('decrypted-token');

        $this->assertSame('decrypted-token', $this->moduleConfig->getAuthToken());
    }

    public function testItReturnsNullFromGetAuthTokenWhenNoTokenIsConfigured(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(self::AUTH_TOKEN_PATH, ScopeInterface::SCOPE_STORE, null)
            ->willReturn(null);

        $this->encryptor->expects($this->never())->method('decrypt');

        $this->assertNull($this->moduleConfig->getAuthToken());
    }

    public function testItReturnsFalseFromIsEnabledWhenEnabledIsOneButAuthIdOrAuthTokenIsMissing(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with(self::ENABLED_PATH, ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);

        $this->scopeConfig->method('getValue')
            ->willReturnMap([
                [self::AUTH_ID_PATH, ScopeInterface::SCOPE_STORE, null, ''],
                [self::AUTH_TOKEN_PATH, ScopeInterface::SCOPE_STORE, null, 'encrypted-token'],
            ]);

        $this->encryptor->method('decrypt')->willReturn('decrypted-token');

        $this->assertFalse($this->moduleConfig->isEnabled());
    }

    public function testItReturnsFalseFromIsEnabledWhenEnabledIsOneButTheAuthTokenIsMissing(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with(self::ENABLED_PATH, ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);

        $this->scopeConfig->method('getValue')
            ->willReturnMap([
                [self::AUTH_ID_PATH, ScopeInterface::SCOPE_STORE, null, 'my-auth-id'],
                [self::AUTH_TOKEN_PATH, ScopeInterface::SCOPE_STORE, null, ''],
            ]);

        $this->encryptor->expects($this->never())->method('decrypt');

        $this->assertFalse($this->moduleConfig->isEnabled());
    }

    /**
     * Stubs the credential lookups so isEnabled()'s composite check passes.
     *
     * @return void
     */
    private function stubValidCredentials(): void
    {
        $this->scopeConfig->method('getValue')
            ->willReturnMap([
                [self::AUTH_ID_PATH, ScopeInterface::SCOPE_STORE, null, 'my-auth-id'],
                [self::AUTH_TOKEN_PATH, ScopeInterface::SCOPE_STORE, null, 'encrypted-token'],
            ]);

        $this->encryptor->method('decrypt')->willReturn('decrypted-token');
    }

    public function testItReturnsTrueFromIsAutocompleteEnabledWhenBothTheModuleAndAutocompleteFlagsAreOn(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->willReturnMap([
                [self::ENABLED_PATH, ScopeInterface::SCOPE_STORE, null, true],
                [self::AUTOCOMPLETE_ENABLED_PATH, ScopeInterface::SCOPE_STORE, null, true],
            ]);

        $this->stubValidCredentials();

        $this->assertTrue($this->moduleConfig->isAutocompleteEnabled());
    }

    public function testItReturnsFalseFromIsAutocompleteEnabledWhenTheAutocompleteFlagIsOffButTheModuleIsOn(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->willReturnMap([
                [self::ENABLED_PATH, ScopeInterface::SCOPE_STORE, null, true],
                [self::AUTOCOMPLETE_ENABLED_PATH, ScopeInterface::SCOPE_STORE, null, false],
            ]);

        $this->stubValidCredentials();

        $this->assertFalse($this->moduleConfig->isAutocompleteEnabled());
        $this->assertTrue($this->moduleConfig->isEnabled());
    }

    public function testItReturnsFalseFromIsAutocompleteEnabledWhenTheModuleIsDisabledDespiteTheFlagBeingOn(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->willReturnMap([
                [self::ENABLED_PATH, ScopeInterface::SCOPE_STORE, null, false],
                [self::AUTOCOMPLETE_ENABLED_PATH, ScopeInterface::SCOPE_STORE, null, true],
            ]);

        $this->assertFalse($this->moduleConfig->isAutocompleteEnabled());
    }

    public function testItReadsTheAutocompleteFlagFromTheStoreScope(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->willReturnMap([
                [self::ENABLED_PATH, ScopeInterface::SCOPE_STORE, 5, true],
                [self::AUTOCOMPLETE_ENABLED_PATH, ScopeInterface::SCOPE_STORE, 5, true],
            ]);

        $this->scopeConfig->method('getValue')
            ->willReturnMap([
                [self::AUTH_ID_PATH, ScopeInterface::SCOPE_STORE, 5, 'my-auth-id'],
                [self::AUTH_TOKEN_PATH, ScopeInterface::SCOPE_STORE, 5, 'encrypted-token'],
            ]);

        $this->encryptor->method('decrypt')->willReturn('decrypted-token');

        $this->assertTrue($this->moduleConfig->isAutocompleteEnabled(5));
    }

    public function testItReadsConfigValuesFromTheStoreScope(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(self::AUTH_ID_PATH, ScopeInterface::SCOPE_STORE, 5)
            ->willReturn('my-auth-id');

        $this->moduleConfig->getAuthId(5);
    }
}
