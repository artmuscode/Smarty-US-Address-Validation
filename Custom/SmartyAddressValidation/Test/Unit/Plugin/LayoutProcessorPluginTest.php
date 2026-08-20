<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Test\Unit\Plugin;

use Custom\SmartyAddressValidation\Model\Config\ModuleConfig;
use Custom\SmartyAddressValidation\Plugin\LayoutProcessorPlugin;
use Magento\Checkout\Block\Checkout\LayoutProcessor;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LayoutProcessorPluginTest extends TestCase
{
    private const STORE_ID = 7;
    private const STREET_SORT_ORDER = 60;

    /**
     * @var ModuleConfig&MockObject
     */
    private $moduleConfig;

    /**
     * @var StoreManagerInterface&MockObject
     */
    private $storeManager;

    /**
     * @var LayoutProcessor&MockObject
     */
    private $subject;

    /**
     * @var LayoutProcessorPlugin
     */
    private LayoutProcessorPlugin $plugin;

    protected function setUp(): void
    {
        $this->moduleConfig = $this->createMock(ModuleConfig::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->subject = $this->createMock(LayoutProcessor::class);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(self::STORE_ID);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->plugin = new LayoutProcessorPlugin($this->moduleConfig, $this->storeManager);
    }

    /**
     * Stubs the module as enabled, optionally with the separate autocomplete feature switched off.
     *
     * @param bool $autocompleteEnabled
     * @return void
     */
    private function enableModule(bool $autocompleteEnabled = true): void
    {
        $this->moduleConfig->method('isEnabled')->willReturn(true);
        $this->moduleConfig->method('isAutocompleteEnabled')->willReturn($autocompleteEnabled);
    }

    /**
     * Builds a minimal but representative $jsLayout array shaped like the real checkout LayoutProcessor output.
     *
     * @return array
     */
    private function buildJsLayout(): array
    {
        return [
            'components' => [
                'checkout' => [
                    'children' => [
                        'steps' => [
                            'children' => [
                                'shipping-step' => [
                                    'children' => [
                                        'shippingAddress' => [
                                            'children' => [
                                                'shipping-address-fieldset' => [
                                                    'children' => [
                                                        'street' => [
                                                            'sortOrder' => self::STREET_SORT_ORDER,
                                                            'children' => [
                                                                '0' => ['component' => 'text'],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Extracts the shipping-address-fieldset children from a processed jsLayout.
     *
     * @param array $jsLayout
     * @return array
     */
    private function getFieldsetChildren(array $jsLayout): array
    {
        return $jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']
            ['children']['shippingAddress']['children']['shipping-address-fieldset']['children'];
    }

    public function testItAddsTheSmartyAutocompleteComponentUnderTheShippingAddressFieldsetChildrenWhenEnabled(): void
    {
        $this->enableModule();

        $result = $this->plugin->afterProcess($this->subject, $this->buildJsLayout());

        $fieldsetChildren = $result['components']['checkout']['children']['steps']['children']['shipping-step']
            ['children']['shippingAddress']['children']['shipping-address-fieldset']['children'];

        $this->assertArrayHasKey('smarty-autocomplete', $fieldsetChildren);
        $this->assertSame(
            'Custom_SmartyAddressValidation/js/view/shipping-address/smarty-autocomplete',
            $fieldsetChildren['smarty-autocomplete']['component']
        );
    }

    public function testItAddsTheValidationPanelAsASiblingNotAChildOfTheShippingFieldsetWhenEnabled(): void
    {
        $this->enableModule();

        $result = $this->plugin->afterProcess($this->subject, $this->buildJsLayout());

        $shippingAddressChildren = $result['components']['checkout']['children']['steps']['children']
            ['shipping-step']['children']['shippingAddress']['children'];

        $this->assertArrayHasKey('smarty-validation-panel', $shippingAddressChildren);
        $this->assertSame(
            'Custom_SmartyAddressValidation/js/view/shipping-address/smarty-validation-panel',
            $shippingAddressChildren['smarty-validation-panel']['component']
        );
        $this->assertArrayNotHasKey(
            'smarty-validation-panel',
            $shippingAddressChildren['shipping-address-fieldset']['children']
        );
    }

    public function testItPassesMinSearchLengthDebounceMsAndAllowedCountriesConfigIntoBothInjectedComponentNodes(): void
    {
        $this->enableModule();

        $result = $this->plugin->afterProcess($this->subject, $this->buildJsLayout());

        $expectedConfig = [
            'minSearchLength' => 3,
            'debounceMs' => 300,
            'allowedCountries' => ['US'],
        ];

        $fieldsetChildren = $result['components']['checkout']['children']['steps']['children']['shipping-step']
            ['children']['shippingAddress']['children']['shipping-address-fieldset']['children'];
        $shippingAddressChildren = $result['components']['checkout']['children']['steps']['children']
            ['shipping-step']['children']['shippingAddress']['children'];

        $this->assertSame($expectedConfig, $fieldsetChildren['smarty-autocomplete']['config']);
        $this->assertSame($expectedConfig, $shippingAddressChildren['smarty-validation-panel']['config']);
    }

    public function testItLeavesTheJsLayoutUnchangedWhenTheModuleIsDisabled(): void
    {
        $this->moduleConfig->method('isEnabled')->willReturn(false);

        $jsLayout = $this->buildJsLayout();

        $result = $this->plugin->afterProcess($this->subject, $jsLayout);

        $this->assertSame($jsLayout, $result);
    }

    public function testItInjectsOnlyTheValidationPanelWhenAutocompleteIsDisabledButTheModuleIsEnabled(): void
    {
        $this->enableModule(false);

        $result = $this->plugin->afterProcess($this->subject, $this->buildJsLayout());

        $shippingAddressChildren = $result['components']['checkout']['children']['steps']['children']
            ['shipping-step']['children']['shippingAddress']['children'];
        $fieldsetChildren = $this->getFieldsetChildren($result);

        $this->assertArrayHasKey('smarty-validation-panel', $shippingAddressChildren);
        $this->assertArrayNotHasKey('smarty-autocomplete', $fieldsetChildren);
        $this->assertArrayNotHasKey('config', $fieldsetChildren['street']['children'][0]);
    }

    public function testItGivesTheValidationPanelADisplayAreaSoTheShippingTemplateActuallyRendersIt(): void
    {
        $this->enableModule();

        $result = $this->plugin->afterProcess($this->subject, $this->buildJsLayout());

        $shippingAddressChildren = $result['components']['checkout']['children']['steps']['children']
            ['shipping-step']['children']['shippingAddress']['children'];

        $this->assertSame(
            'before-shipping-method-form',
            $shippingAddressChildren['smarty-validation-panel']['displayArea']
        );
    }

    public function testItForcesKeyupValueUpdateOnTheFirstStreetLineSoTypingUpdatesTheObservable(): void
    {
        $this->enableModule();

        $result = $this->plugin->afterProcess($this->subject, $this->buildJsLayout());

        $fieldsetChildren = $this->getFieldsetChildren($result);

        $this->assertSame('keyup', $fieldsetChildren['street']['children'][0]['config']['valueUpdate']);
    }

    public function testItSortsTheAutocompleteDropdownDirectlyAfterTheStreetField(): void
    {
        $this->enableModule();

        $result = $this->plugin->afterProcess($this->subject, $this->buildJsLayout());

        $fieldsetChildren = $this->getFieldsetChildren($result);

        $this->assertSame(self::STREET_SORT_ORDER + 1, $fieldsetChildren['smarty-autocomplete']['sortOrder']);
    }

    public function testItChecksTheEnabledFlagAgainstTheCurrentStoreScope(): void
    {
        $this->moduleConfig->expects($this->once())
            ->method('isEnabled')
            ->with(self::STORE_ID)
            ->willReturn(true);

        $this->plugin->afterProcess($this->subject, $this->buildJsLayout());
    }

    public function testItStillInjectsTheComponentsWhenTheFieldsetHasNoStreetField(): void
    {
        $this->enableModule();

        $jsLayout = $this->buildJsLayout();
        unset(
            $jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']
            ['children']['shippingAddress']['children']['shipping-address-fieldset']['children']['street']
        );

        $fieldsetChildren = $this->getFieldsetChildren($this->plugin->afterProcess($this->subject, $jsLayout));

        $this->assertArrayHasKey('smarty-autocomplete', $fieldsetChildren);
        $this->assertSame(1, $fieldsetChildren['smarty-autocomplete']['sortOrder']);
    }

    public function testItReturnsTheJsLayoutUnchangedWhenTheShippingAddressOrFieldsetPathIsMissing(): void
    {
        $this->enableModule();

        $jsLayout = [
            'components' => [
                'checkout' => [
                    'children' => [
                        'steps' => [
                            'children' => [
                                'shipping-step' => [
                                    'children' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->plugin->afterProcess($this->subject, $jsLayout);

        $this->assertSame($jsLayout, $result);
    }
}
