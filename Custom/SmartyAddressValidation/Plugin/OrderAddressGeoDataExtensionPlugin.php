<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Plugin;

use Custom\SmartyAddressValidation\Model\AddressGeoFields;
use Magento\Framework\DataObject;
use Magento\Sales\Api\Data\OrderAddressExtensionFactory;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Publishes the stored Smarty delivery metadata as order-address extension attributes.
 *
 * The values live in columns on sales_order_address, which the REST output builder ignores because
 * it serialises via OrderAddressInterface getters only. Copying them onto extension attributes is
 * what makes them visible to API clients such as an ERP.
 */
class OrderAddressGeoDataExtensionPlugin
{
    /**
     * @var OrderAddressExtensionFactory
     */
    private OrderAddressExtensionFactory $extensionFactory;

    /**
     * @param OrderAddressExtensionFactory $extensionFactory
     */
    public function __construct(OrderAddressExtensionFactory $extensionFactory)
    {
        $this->extensionFactory = $extensionFactory;
    }

    /**
     * Populate extension attributes for a single loaded order.
     *
     * @param OrderRepositoryInterface $subject
     * @param OrderInterface $result
     * @return OrderInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGet(OrderRepositoryInterface $subject, OrderInterface $result): OrderInterface
    {
        $this->applyToOrder($result);

        return $result;
    }

    /**
     * Populate extension attributes for every order in a list.
     *
     * @param OrderRepositoryInterface $subject
     * @param OrderSearchResultInterface $result
     * @return OrderSearchResultInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetList(
        OrderRepositoryInterface $subject,
        OrderSearchResultInterface $result
    ): OrderSearchResultInterface {
        foreach ($result->getItems() as $order) {
            $this->applyToOrder($order);
        }

        return $result;
    }

    /**
     * Copy the stored metadata onto the extension attributes of each of the order's addresses.
     *
     * @param OrderInterface $order
     * @return void
     */
    private function applyToOrder(OrderInterface $order): void
    {
        // getAddresses() is on the order model, not on OrderInterface.
        if (!$order instanceof Order) {
            return;
        }

        foreach ($order->getAddresses() as $address) {
            if ($address instanceof OrderAddressInterface) {
                $this->applyToAddress($address);
            }
        }
    }

    /**
     * Copy the stored metadata onto one address's extension attributes.
     *
     * @param OrderAddressInterface $address
     * @return void
     */
    private function applyToAddress(OrderAddressInterface $address): void
    {
        if (!$address instanceof DataObject) {
            return;
        }

        $extensionAttributes = $address->getExtensionAttributes() ?: $this->extensionFactory->create();

        $extensionAttributes->setSmartyRdi($this->asString($address->getData(AddressGeoFields::RDI)));
        $extensionAttributes->setSmartyLatitude($this->asFloat($address->getData(AddressGeoFields::LATITUDE)));
        $extensionAttributes->setSmartyLongitude($this->asFloat($address->getData(AddressGeoFields::LONGITUDE)));
        $extensionAttributes->setSmartyGeoPrecision(
            $this->asString($address->getData(AddressGeoFields::GEO_PRECISION))
        );

        $address->setExtensionAttributes($extensionAttributes);
    }

    /**
     * Normalise a stored column value to a string, preserving "not captured" as null.
     *
     * @param mixed $value
     * @return string|null
     */
    private function asString($value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    /**
     * Normalise a stored column value to a float, preserving "not captured" as null.
     *
     * @param mixed $value
     * @return float|null
     */
    private function asFloat($value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}
