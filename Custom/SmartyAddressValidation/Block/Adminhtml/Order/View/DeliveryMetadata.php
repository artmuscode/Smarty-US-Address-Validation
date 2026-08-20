<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Block\Adminhtml\Order\View;

use Custom\SmartyAddressValidation\Model\AddressGeoFields;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Block\Adminhtml\Order\AbstractOrder;

/**
 * Shows the Smarty delivery metadata captured for the order's shipping address.
 *
 * Present so staff can see what an ERP will be handed, and spot a bad classification before it
 * turns into a carrier surcharge dispute.
 */
class DeliveryMetadata extends AbstractOrder
{
    /**
     * Whether anything was captured for this order.
     *
     * @return bool
     */
    public function hasDeliveryMetadata(): bool
    {
        return $this->getRdi() !== null
            || $this->getCoordinates() !== null
            || $this->getGeoPrecision() !== null;
    }

    /**
     * Human-readable residential/commercial classification, or null when unknown.
     *
     * @return string|null
     */
    public function getRdi(): ?string
    {
        $rdi = $this->getShippingAddressData(AddressGeoFields::RDI);

        if ($rdi === null) {
            return null;
        }

        return $rdi === AddressGeoFields::RDI_RESIDENTIAL ? (string) __('Residential') : (string) __('Commercial');
    }

    /**
     * Formatted "latitude, longitude" pair, or null when no geocode was captured.
     *
     * @return string|null
     */
    public function getCoordinates(): ?string
    {
        $latitude = $this->getShippingAddressData(AddressGeoFields::LATITUDE);
        $longitude = $this->getShippingAddressData(AddressGeoFields::LONGITUDE);

        if ($latitude === null || $longitude === null) {
            return null;
        }

        return sprintf('%.7F, %.7F', (float) $latitude, (float) $longitude);
    }

    /**
     * Coordinate precision reported by Smarty, or null when no geocode was captured.
     *
     * @return string|null
     */
    public function getGeoPrecision(): ?string
    {
        return $this->getShippingAddressData(AddressGeoFields::GEO_PRECISION);
    }

    /**
     * Read one captured field off the order's shipping address.
     *
     * @param string $field
     * @return string|null
     */
    private function getShippingAddressData(string $field): ?string
    {
        try {
            // getOrder() throws rather than returning null when no order is in scope, and a
            // rendering exception here would take down the whole admin order page.
            $shippingAddress = $this->getOrder()->getShippingAddress();
        } catch (LocalizedException $exception) {
            return null;
        }

        if ($shippingAddress === null) {
            return null;
        }

        $value = $shippingAddress->getData($field);

        return $value === null || $value === '' ? null : (string) $value;
    }
}
