<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Plugin;

use Custom\SmartyAddressValidation\Model\AddressGeoFields;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\ToOrderAddress;
use Magento\Sales\Api\Data\OrderAddressInterface;

/**
 * Copies the Smarty delivery metadata from the quote address onto the order address at submit.
 *
 * Done explicitly rather than through a fieldset.xml aspect: ToOrderAddress hands the copied data to
 * DataObjectHelper::populateWithArray, which keeps only keys backed by a real setter method on the
 * target (see DataObjectHelper::getSetters, which reads get_class_methods). OrderAddress exposes
 * these columns through __call rather than declared setters, so a declarative copy would be dropped
 * without any error.
 */
class CopyGeoDataToOrderAddressPlugin
{
    /**
     * Carry the delivery metadata across to the order address.
     *
     * Runs for the billing address too, which simply has nothing to copy.
     *
     * @param ToOrderAddress $subject
     * @param OrderAddressInterface $result
     * @param Address $object
     * @return OrderAddressInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterConvert(
        ToOrderAddress $subject,
        OrderAddressInterface $result,
        Address $object
    ): OrderAddressInterface {
        // setData() is on the model, not on OrderAddressInterface.
        if (!$result instanceof DataObject) {
            return $result;
        }

        foreach (AddressGeoFields::ALL as $field) {
            $value = $object->getData($field);

            if ($value !== null && $value !== '') {
                $result->setData($field, $value);
            }
        }

        return $result;
    }
}
