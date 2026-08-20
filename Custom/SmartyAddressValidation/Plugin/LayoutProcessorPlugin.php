<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Plugin;

use Custom\SmartyAddressValidation\Model\Config\ModuleConfig;
use Magento\Checkout\Block\Checkout\LayoutProcessor;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Injects the Smarty autocomplete and validation-panel jsLayout component nodes into checkout shipping step.
 */
class LayoutProcessorPlugin
{
    private const MIN_SEARCH_LENGTH = 3;
    private const DEBOUNCE_MS = 300;
    private const ALLOWED_COUNTRIES = ['US'];

    /**
     * Region of Magento_Checkout/js/view/shipping the comparison panel is rendered into.
     *
     * The shipping template only renders children that declare one of its named regions, so a node
     * without a displayArea is instantiated but never output. This one sits directly above the
     * shipping-method block, next to the Next button that triggers the panel.
     */
    private const PANEL_DISPLAY_AREA = 'before-shipping-method-form';

    /**
     * Knockout valueUpdate mode forced onto the first street line.
     *
     * Magento_Ui/js/form/element/abstract defaults valueUpdate to false, which makes the value
     * observable update on change (blur) only. Autocomplete needs per-keystroke updates.
     */
    private const STREET_VALUE_UPDATE = 'keyup';

    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param ModuleConfig $moduleConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(ModuleConfig $moduleConfig, StoreManagerInterface $storeManager)
    {
        $this->moduleConfig = $moduleConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * Injects Smarty jsLayout components into the checkout shipping step when the module is enabled.
     *
     * @param LayoutProcessor $subject
     * @param array $jsLayout
     * @return array
     */
    public function afterProcess(LayoutProcessor $subject, array $jsLayout): array
    {
        $storeId = $this->getStoreId();

        if (!$this->moduleConfig->isEnabled($storeId)) {
            return $jsLayout;
        }

        // The dropdown is gated separately: it needs a US Autocomplete Pro subscription, which
        // validation does not. Skipping it also skips the keyup valueUpdate only it requires.
        if ($this->moduleConfig->isAutocompleteEnabled($storeId)) {
            $jsLayout = $this->addAutocompleteComponent($jsLayout);
        }

        $jsLayout = $this->addValidationPanelComponent($jsLayout);

        return $jsLayout;
    }

    /**
     * Returns the current store id, or null when the store cannot be resolved.
     *
     * @return int|null
     */
    private function getStoreId(): ?int
    {
        try {
            return (int) $this->storeManager->getStore()->getId();
        } catch (NoSuchEntityException) {
            return null;
        }
    }

    /**
     * Injects the smarty-autocomplete component under the shipping-address-fieldset children.
     *
     * Also forces per-keystroke updates on the first street line, which the component subscribes to.
     *
     * @param array $jsLayout
     * @return array
     */
    private function addAutocompleteComponent(array $jsLayout): array
    {
        if (!isset(
            $jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']
            ['children']['shippingAddress']['children']['shipping-address-fieldset']['children']
        )) {
            return $jsLayout;
        }

        $fieldsetChildren = &$jsLayout['components']['checkout']['children']['steps']['children']
            ['shipping-step']['children']['shippingAddress']['children']['shipping-address-fieldset']['children'];

        if (isset($fieldsetChildren['street']['children'][0])) {
            $fieldsetChildren['street']['children'][0]['config']['valueUpdate'] = self::STREET_VALUE_UPDATE;
        }

        $fieldsetChildren['smarty-autocomplete'] = [
            'component' => 'Custom_SmartyAddressValidation/js/view/shipping-address/smarty-autocomplete',
            'sortOrder' => (int) ($fieldsetChildren['street']['sortOrder'] ?? 0) + 1,
            'config' => $this->getComponentConfig(),
        ];

        return $jsLayout;
    }

    /**
     * Injects the smarty-validation-panel component as a sibling of shipping-address-fieldset.
     *
     * @param array $jsLayout
     * @return array
     */
    private function addValidationPanelComponent(array $jsLayout): array
    {
        if (!isset(
            $jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']
            ['children']['shippingAddress']['children']
        )) {
            return $jsLayout;
        }

        $shippingAddressChildren = &$jsLayout['components']['checkout']['children']['steps']['children']
            ['shipping-step']['children']['shippingAddress']['children'];

        $shippingAddressChildren['smarty-validation-panel'] = [
            'component' => 'Custom_SmartyAddressValidation/js/view/shipping-address/smarty-validation-panel',
            'displayArea' => self::PANEL_DISPLAY_AREA,
            'config' => $this->getComponentConfig(),
        ];

        return $jsLayout;
    }

    /**
     * Builds the shared config sub-array injected into both Smarty jsLayout component nodes.
     *
     * @return array
     */
    private function getComponentConfig(): array
    {
        return [
            'minSearchLength' => self::MIN_SEARCH_LENGTH,
            'debounceMs' => self::DEBOUNCE_MS,
            'allowedCountries' => self::ALLOWED_COUNTRIES,
        ];
    }
}
