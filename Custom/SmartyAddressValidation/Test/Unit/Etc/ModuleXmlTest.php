<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Test\Unit\Etc;

use PHPUnit\Framework\TestCase;

class ModuleXmlTest extends TestCase
{
    private const MODULE_XML_PATH = __DIR__ . '/../../../etc/module.xml';

    public function testItDeclaresModuleXmlWithNameCustomSmartyAddressValidation(): void
    {
        $moduleXml = simplexml_load_file(self::MODULE_XML_PATH);

        $this->assertSame('Custom_SmartyAddressValidation', (string) $moduleXml->module['name']);
    }

    public function testItDeclaresCheckoutCustomerDirectoryAndUiAsSequencedModuleDependencies(): void
    {
        $moduleXml = simplexml_load_file(self::MODULE_XML_PATH);

        $sequencedModules = [];
        foreach ($moduleXml->module->sequence->module as $sequenceModule) {
            $sequencedModules[] = (string) $sequenceModule['name'];
        }

        $this->assertSame(
            ['Magento_Checkout', 'Magento_Customer', 'Magento_Directory', 'Magento_Ui'],
            $sequencedModules
        );
    }
}
