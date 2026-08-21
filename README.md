# Custom_SmartyAddressValidation

Adds Smarty (SmartyStreets) address autocomplete and validation to the
**checkout shipping address step**. As a customer types their street address,
they see live suggestions; before they can advance past the shipping step,
their entered address is validated/standardized against Smarty and, if a
correction is proposed, they can compare "as entered" vs. "Smarty suggests"
and pick either.

Module Requires Smarty SDK:
composer require smartystreets/phpsdk

## What it does

- **Live autocomplete**: as the customer types a US shipping street address,
  a dropdown of Smarty US Autocomplete Pro suggestions appears. Selecting one
  fills in street, city, region (by region ID, not just the text
  abbreviation), and postcode.
- **Validate and compare**: when the customer clicks "Next" on the shipping
  step, the entered address is validated against Smarty's US Street Address
  API. If Smarty proposes a materially different address (after normalizing
  case/abbreviations/ZIP+4), navigation pauses and a comparison panel shows
  both the entered and suggested address; the customer chooses one and
  checkout proceeds. If Smarty confirms the address as entered (or only
  trivially reformats it), checkout proceeds without interruption.
- **Delivery metadata for ERP**: the Smarty geo data for the shipping address
  (residential/commercial indicator, latitude/longitude, geo precision) is
  captured onto the quote at the end of the shipping step, copied onto the
  order at submit, and exposed on the order REST payload and the admin order
  view. See [Delivery metadata on orders](#delivery-metadata-on-orders).
- All calls to the Smarty SDK happen **server-side only**, behind this
  module's own REST endpoints — the Smarty Auth Token never reaches the
  browser.

## Scope and limitations

- **Checkout shipping address only.** Billing address, the customer address
  book (My Account → Addresses), and admin-side address *entry* (order
  edit/creation) are all out of scope — this module never autocompletes,
  validates, or rewrites them. The one admin-side surface it does add is a
  read-only delivery-metadata panel on the order view.
- **US addresses only.** Autocomplete and validation are only triggered when
  the shipping country is `US`. This is enforced both in the frontend JS
  (skips the call entirely) and server-side in the service layer
  (`Model\AddressAutocomplete` / `Model\AddressValidator` reject non-US
  requests before ever calling the Smarty gateway) — a client that bypasses
  the JS and calls the REST endpoints directly with a non-US address still
  gets a no-op response, not a real Smarty lookup.
- **Single best match only.** Both the autocomplete suggestions and the
  validation result surface at most one candidate; Smarty's other candidates
  (when ambiguous) are not exposed.
- **International addresses are not supported** — Smarty's International
  Street API is not integrated.
- If a customer selects an existing saved address-book entry at checkout
  (rather than typing a new address), validation is skipped entirely — saved
  addresses are not re-validated.

## Fail-open behavior

Address validation must never block a sale. Any failure talking to Smarty —
disabled module, missing/invalid credentials, network error, API error, or a
request that exceeds the bounded timeout (3s server-side SDK calls, ~5s
client-side REST calls) — is caught, logged (exception details only, never
the customer's address, which is PII), and treated as "no correction
available." Checkout proceeds uninterrupted with the address as the customer
entered it. This applies to both the autocomplete dropdown (it simply stays
closed/empty) and the validate-and-compare flow (the comparison panel is
never shown; the shipping step advances normally).

## Delivery metadata on orders

Beyond correcting the address, the module records what Smarty knows about
*delivering* to it, so downstream systems (an ERP, a rate shopper, a fraud
check) do not have to re-query Smarty per order.

Four values are carried: `smarty_rdi` (`residential` / `commercial`),
`smarty_latitude`, `smarty_longitude`, and `smarty_geo_precision`. The names
are defined once in `Model/AddressGeoFields.php` so the schema, the
quote-to-order copy, and the REST attributes cannot drift apart.

The data moves in three hops, each a plugin registered in `etc/di.xml`:

| Hop | Plugin | Hooks |
|---|---|---|
| **Capture** onto the quote address | `Plugin/CaptureQuoteAddressGeoDataPlugin.php` | `after` `ShippingInformationManagementInterface::saveAddressInformation` |
| **Copy** onto the order address at submit | `Plugin/CopyGeoDataToOrderAddressPlugin.php` | `after` `Quote\Address\ToOrderAddress::convert` |
| **Publish** as extension attributes on read | `Plugin/OrderAddressGeoDataExtensionPlugin.php` | `after` `OrderRepositoryInterface::get` and `getList` |

Capture happens at the end of the shipping step rather than at order
placement, so submitting an order stays a pure data copy with no outbound HTTP
call. The storefront has normally just validated the same address, so the
validator's short-TTL cache usually answers this without a second Smarty
request. It writes through the address resource directly rather than saving
the whole quote, to avoid a pointless totals re-collect.

Storage is four nullable columns added by `etc/db_schema.xml` to **both**
`quote_address` and `sales_order_address`. Orders placed before this feature
existed simply have nulls.

Where it shows up:

- **REST** — `etc/extension_attributes.xml` declares the four values on
  `Magento\Sales\Api\Data\OrderAddressInterface`, so `GET /V1/orders/{id}` and
  `GET /V1/orders` return them under the address's `extension_attributes`.
  They need the plugin above because Magento's REST output builder serialises
  via interface getters only and would otherwise ignore the columns.
- **Admin** — a read-only panel on Sales → Orders → View, rendered by
  `Block/Adminhtml/Order/View/DeliveryMetadata.php` +
  `view/adminhtml/templates/order/view/delivery-metadata.phtml` (wired in
  `view/adminhtml/layout/sales_order_view.xml`). It hides itself entirely
  when an order has no metadata.

Capture is fail-open on the same terms as the rest of the module: any
exception is logged and swallowed, and an order is placed with null metadata
rather than failing.

> **Maintainer note:** the four values only become real methods once Magento
> generates `OrderAddressExtensionInterface` into `generated/`. If that
> directory has been wiped, `Test/Unit/Plugin/OrderAddressGeoDataExtensionPluginTest.php`
> errors with *"Trying to configure method `setSmartyRdi` … does not exist"*.
> Run `bin/magento setup:di:compile` to regenerate.

## Configuration

**Stores → Configuration → Sales → Smarty Address Validation → General**
(admin resource `Custom_SmartyAddressValidation::config`, config section
`smarty_address_validation`):

| Field | Config path | Notes |
|---|---|---|
| Enable Smarty Address Validation | `smarty_address_validation/general/enabled` | Yes/No. Defaults to **No**. Autocomplete and validation are fully disabled (and the checkout JS components are never injected) until this is turned on. |
| Enable Address Autocomplete | `smarty_address_validation/general/autocomplete_enabled` | Yes/No. Defaults to **Yes**, and only applies when the module itself is enabled. Set to **No** to switch off just the checkout dropdown while keeping address validation. |
| Smarty Auth ID | `smarty_address_validation/general/auth_id` | Plain text, from your Smarty account. |
| Smarty Auth Token | `smarty_address_validation/general/auth_token` | Stored **encrypted at rest** (`Magento\Config\Model\Config\Backend\Encrypted`) and decrypted on read via `EncryptorInterface::decrypt()`. |

The module also treats itself as effectively disabled — even with `enabled`
set to Yes — if either the Auth ID or Auth Token is missing, so an
incomplete configuration fails open rather than authenticating with Smarty
using garbage credentials.

### Smarty subscriptions

The two features use **different Smarty APIs**, which are billed separately:

- **Autocomplete dropdown** → US Autocomplete Pro API (a paid add-on that not
  every Smarty plan includes).
- **Address validation, the comparison panel, and the delivery metadata
  captured onto the order** → US Street Address API.

Validation works normally on an account without US Autocomplete Pro — the two
paths are independent. If your plan does not include it, set **Enable Address
Autocomplete** to No so checkout does not call an endpoint your account cannot
use. If it is left on, autocomplete still fails open (the dropdown simply never
appears), and after the first failure the module stops calling Smarty
autocomplete for 5 minutes per store rather than retrying on every keystroke —
so a missing subscription costs one log line per 5 minutes, not one per
keypress.

After changing these values, flush cache (`bin/magento cache:flush`) as
needed for your environment.

## REST endpoints

Both endpoints are anonymous/guest-accessible (checkout supports guest
customers) and proxy the module's service contracts — the storefront JS
never talks to Smarty directly:

- `POST /V1/smarty-address-validation/autocomplete` →
  `Custom\SmartyAddressValidation\Api\AddressAutocompleteInterface::suggest`
- `POST /V1/smarty-address-validation/validate` →
  `Custom\SmartyAddressValidation\Api\AddressValidatorInterface::validate`

With the module disabled (the default) or for non-US requests, both
endpoints return an empty/unmatched result rather than an error. The
autocomplete endpoint additionally no-ops when **Enable Address Autocomplete**
is off, while validate stays live.

The module also extends two endpoints it does not own: `GET /V1/orders/{id}`
and `GET /V1/orders` carry the Smarty delivery metadata under the order
address's `extension_attributes` — see
[Delivery metadata on orders](#delivery-metadata-on-orders).

## Architecture notes

- `Api/` — service contracts (`AddressAutocompleteInterface`,
  `AddressValidatorInterface`) and their DTOs (`Api/Data/*`).
- `Model/AddressAutocomplete.php`, `Model/AddressValidator.php` — business
  logic: enabled check, US-only gate, minimum-input gating, short-TTL
  response caching, fail-open error handling, and mapping to DTOs (including
  resolving `regionId` via `Model/RegionResolver.php`).
- `Model/Smarty/*Gateway*` — thin adapters over the `smartystreets/phpsdk`
  Composer package (`SmartyStreets\PhpSdk`), obtained per-call from
  `Model/Smarty/ClientFactory.php` so credential changes take effect without
  requiring a DI-constructed client to go stale.
- `Model/Config/ModuleConfig.php` — reads `enabled`, `autocomplete_enabled`,
  `auth_id` and `auth_token` from store-scoped config. `isEnabled()` is the
  master gate (and also returns false when either credential is blank);
  `isAutocompleteEnabled()` is composite — it requires `isEnabled()` *and* the
  autocomplete flag, so turning the module off turns autocomplete off, but not
  the other way round.
- `Plugin/LayoutProcessorPlugin.php` (`etc/frontend/di.xml`) — injects the
  jsLayout components into the checkout shipping step, under **two separate
  gates**. Whenever the module is enabled it injects the comparison-panel
  component (as a sibling of the address fieldset, in the
  `before-shipping-method-form` display area). Only when autocomplete is
  *also* enabled does it additionally inject the autocomplete component into
  the fieldset and force `valueUpdate: 'keyup'` on the first street line. The
  validation/comparison flow does not depend on either of those two, so
  disabling autocomplete leaves the panel fully working.
- `Model/AddressGeoFields.php`, `Api/Data/AddressGeoDataInterface.php`,
  `Plugin/CaptureQuoteAddressGeoDataPlugin.php`,
  `Plugin/CopyGeoDataToOrderAddressPlugin.php`,
  `Plugin/OrderAddressGeoDataExtensionPlugin.php`,
  `Block/Adminhtml/Order/View/DeliveryMetadata.php`, `etc/db_schema.xml`,
  `etc/extension_attributes.xml` — the delivery-metadata pipeline described
  under [Delivery metadata on orders](#delivery-metadata-on-orders).
- `view/frontend/web/js/view/shipping-address/smarty-autocomplete.js` — the
  live autocomplete dropdown component.
- `view/frontend/requirejs-config.js` +
  `view/frontend/web/js/mixin/shipping-mixin.js` — mixes into
  `Magento_Checkout/js/view/shipping`'s `setShippingInformation` to run
  validation before advancing past the shipping step, and
  `view/frontend/web/js/view/shipping-address/smarty-validation-panel.js` +
  `view/frontend/web/template/smarty-address-comparison.html` render the
  comparison panel.

## Known environment caveats for maintainers

- **Jasmine JS tests do not run on arm64 (Apple Silicon) dev machines in
  this repo.** The pure-logic JS specs
  (`autocomplete-request.test.js`, `validation-result-mapper.test.js`) live
  under `src/dev/tests/js/jasmine/tests/app/code/Custom/SmartyAddressValidation/`
  and are picked up by the standard spec glob, but this project's own
  `bin/setup-grunt` strips `grunt-contrib-jasmine` from `package.json` on
  arm64 as incompatible, so `bin/grunt spec:<theme>` cannot execute them
  here (see tasks 010/011 Implementation Notes). They run correctly in a
  CI/x86 environment where the grunt/Jasmine toolchain is installed. In the
  interim, the DOM-interactive parts of both components (dropdown
  keyboard/selection behavior, comparison-panel flow) are only covered by
  the manual QA scripts documented in tasks 010/011 — verify these by hand
  after any change to the frontend JS.
- **`bin/dev-test-run api-functional` does not work against this module out
  of the box.** `src/dev/tests/api-functional/` ships only
  `phpunit_rest.xml.dist` (no plain `phpunit.xml.dist`, which is what
  `bin/dev-test-run` expects), and a real run additionally requires a fully
  installed, network-reachable Magento instance plus `TESTS_BASE_URL` and
  admin credentials. The automated gate for the REST routes is instead a
  plain unit test (`Test/Unit/Etc/WebapiConfigTest.php`) asserting the
  structure of `etc/webapi.xml`. To run the endpoints manually against a
  live instance (see task 008 Implementation Notes for full detail):

  ```bash
  # Copy the dist config once, then run with a filter
  bin/clinotty bash -c "cd dev/tests/api-functional && cp -n phpunit_rest.xml.dist phpunit_rest.xml; ../../../vendor/bin/phpunit -c phpunit_rest.xml --filter Smarty"

  # Or a curl smoke test (module ships disabled by default, so expect an
  # empty/unmatched result rather than real Smarty data unless configured):
  curl -s -X POST "https://<host>/rest/V1/smarty-address-validation/autocomplete" \
    -H "Content-Type: application/json" \
    -d '{"search":"1 Rosedale","countryId":"US"}'
  ```

## Running this module's tests

```bash
bin/test/unit app/code/Custom/SmartyAddressValidation
bin/phpcs app/code/Custom/SmartyAddressValidation
bin/analyse app/code/Custom/SmartyAddressValidation
```
