<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Reads Smarty Address Validation module configuration from the store scope.
 */
class ModuleConfig
{
    private const XML_PATH_ENABLED = 'smarty_address_validation/general/enabled';
    private const XML_PATH_AUTOCOMPLETE_ENABLED = 'smarty_address_validation/general/autocomplete_enabled';
    private const XML_PATH_AUTH_ID = 'smarty_address_validation/general/auth_id';
    private const XML_PATH_AUTH_TOKEN = 'smarty_address_validation/general/auth_token';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    /**
     * Returns whether the module is enabled and fully configured.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId)) {
            return false;
        }

        $authId = $this->getAuthId($storeId);
        $authToken = $this->getAuthToken($storeId);

        return $authId !== null && $authId !== '' && $authToken !== null && $authToken !== '';
    }

    /**
     * Returns whether the autocomplete dropdown is enabled.
     *
     * Autocomplete uses Smarty's US Autocomplete Pro API, which is a separate subscription from the
     * US Street Address API that validation uses. This flag lets a merchant whose plan does not include
     * Autocomplete Pro switch the dropdown off while keeping address validation.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isAutocompleteEnabled(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId)
            && $this->scopeConfig->isSetFlag(
                self::XML_PATH_AUTOCOMPLETE_ENABLED,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
    }

    /**
     * Returns the configured Smarty Auth ID.
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getAuthId(?int $storeId = null): ?string
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_AUTH_ID, ScopeInterface::SCOPE_STORE, $storeId);

        return $value !== null ? (string) $value : null;
    }

    /**
     * Returns the decrypted Smarty Auth Token.
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getAuthToken(?int $storeId = null): ?string
    {
        $encryptedValue = $this->scopeConfig->getValue(
            self::XML_PATH_AUTH_TOKEN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($encryptedValue === null || $encryptedValue === '') {
            return null;
        }

        return $this->encryptor->decrypt((string) $encryptedValue);
    }
}
