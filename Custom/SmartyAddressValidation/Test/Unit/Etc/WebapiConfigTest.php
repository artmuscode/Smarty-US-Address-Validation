<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Test\Unit\Etc;

use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

class WebapiConfigTest extends TestCase
{
    private const WEBAPI_XML_PATH = __DIR__ . '/../../../etc/webapi.xml';

    public function testItExposesAddressAutocompleteInterfaceSuggestAtPostV1SmartyAddressValidationAutocomplete(): void
    {
        $route = $this->findRouteByUrl('/V1/smarty-address-validation/autocomplete');

        $this->assertNotNull($route, 'Expected route for /V1/smarty-address-validation/autocomplete to exist');
        $this->assertSame('POST', (string) $route['method']);
        $this->assertSame(
            'Custom\SmartyAddressValidation\Api\AddressAutocompleteInterface',
            (string) $route->service['class']
        );
        $this->assertSame('suggest', (string) $route->service['method']);
    }

    public function testItExposesAddressValidatorInterfaceValidateAtPostV1SmartyAddressValidationValidate(): void
    {
        $route = $this->findRouteByUrl('/V1/smarty-address-validation/validate');

        $this->assertNotNull($route, 'Expected route for /V1/smarty-address-validation/validate to exist');
        $this->assertSame('POST', (string) $route['method']);
        $this->assertSame(
            'Custom\SmartyAddressValidation\Api\AddressValidatorInterface',
            (string) $route->service['class']
        );
        $this->assertSame('validate', (string) $route->service['method']);
    }

    public function testItAllowsAnonymousGuestAccessToTheAutocompleteEndpoint(): void
    {
        $route = $this->findRouteByUrl('/V1/smarty-address-validation/autocomplete');

        $this->assertNotNull($route);
        $this->assertSame('anonymous', (string) $route->resources->resource['ref']);
    }

    public function testItAllowsAnonymousGuestAccessToTheValidateEndpoint(): void
    {
        $route = $this->findRouteByUrl('/V1/smarty-address-validation/validate');

        $this->assertNotNull($route);
        $this->assertSame('anonymous', (string) $route->resources->resource['ref']);
    }

    private function findRouteByUrl(string $url): ?SimpleXMLElement
    {
        $webapiXml = simplexml_load_file(self::WEBAPI_XML_PATH);

        foreach ($webapiXml->route as $route) {
            if ((string) $route['url'] === $url) {
                return $route;
            }
        }

        return null;
    }
}
