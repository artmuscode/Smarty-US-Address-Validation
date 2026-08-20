<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Test\Unit;

use PHPUnit\Framework\TestCase;

class ComposerDependencyTest extends TestCase
{
    public function testItListsSmartystreetsPhpsdkAsAComposerDependency(): void
    {
        $composerJson = json_decode(
            file_get_contents(__DIR__ . '/../../../../../../composer.json'),
            true
        );

        $this->assertArrayHasKey('smartystreets/phpsdk', $composerJson['require']);
    }
}
