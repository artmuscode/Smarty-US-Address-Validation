<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Test\Unit;

use Magento\Framework\Component\ComponentRegistrar;
use PHPUnit\Framework\TestCase;

class RegistrationTest extends TestCase
{
    public function testItRegistersTheCustomSmartyAddressValidationModuleViaRegistrationPhp(): void
    {
        // registration.php is auto-loaded for all app/code modules via
        // app/etc/NonComposerComponentRegistration.php during bootstrap.
        $paths = (new ComponentRegistrar())->getPaths(ComponentRegistrar::MODULE);

        $this->assertArrayHasKey('Custom_SmartyAddressValidation', $paths);
        $this->assertSame(
            realpath(__DIR__ . '/../..'),
            realpath($paths['Custom_SmartyAddressValidation'])
        );
    }
}
