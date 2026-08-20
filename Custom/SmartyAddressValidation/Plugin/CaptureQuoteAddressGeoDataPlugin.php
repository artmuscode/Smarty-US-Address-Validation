<?php
/**
 * Copyright © Custom. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Custom\SmartyAddressValidation\Plugin;

use Custom\SmartyAddressValidation\Api\AddressValidatorInterface;
use Custom\SmartyAddressValidation\Api\Data\AddressValidationRequestInterfaceFactory;
use Custom\SmartyAddressValidation\Model\AddressGeoFields;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Api\Data\PaymentDetailsInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\ResourceModel\Quote\Address as AddressResource;
use Psr\Log\LoggerInterface;

/**
 * Records Smarty delivery metadata on the quote's shipping address when the shopper completes the
 * checkout shipping step.
 *
 * Captured here rather than at order placement so that submitting an order stays a pure data copy
 * with no outbound HTTP call. The storefront has normally just validated the same address, so the
 * validator's short-lived cache usually answers this without a second Smarty request.
 */
class CaptureQuoteAddressGeoDataPlugin
{
    /**
     * @var AddressValidatorInterface
     */
    private AddressValidatorInterface $addressValidator;

    /**
     * @var AddressValidationRequestInterfaceFactory
     */
    private AddressValidationRequestInterfaceFactory $requestFactory;

    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $cartRepository;

    /**
     * @var AddressResource
     */
    private AddressResource $addressResource;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param AddressValidatorInterface $addressValidator
     * @param AddressValidationRequestInterfaceFactory $requestFactory
     * @param CartRepositoryInterface $cartRepository
     * @param AddressResource $addressResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        AddressValidatorInterface $addressValidator,
        AddressValidationRequestInterfaceFactory $requestFactory,
        CartRepositoryInterface $cartRepository,
        AddressResource $addressResource,
        LoggerInterface $logger
    ) {
        $this->addressValidator = $addressValidator;
        $this->requestFactory = $requestFactory;
        $this->cartRepository = $cartRepository;
        $this->addressResource = $addressResource;
        $this->logger = $logger;
    }

    /**
     * Look up and store the delivery metadata for the shipping address that was just saved.
     *
     * Fails open: capturing this metadata must never be able to block checkout.
     *
     * @param ShippingInformationManagementInterface $subject
     * @param PaymentDetailsInterface $result
     * @param int $cartId
     * @param ShippingInformationInterface $addressInformation
     * @return PaymentDetailsInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterSaveAddressInformation(
        ShippingInformationManagementInterface $subject,
        PaymentDetailsInterface $result,
        $cartId,
        ShippingInformationInterface $addressInformation
    ): PaymentDetailsInterface {
        try {
            $quote = $this->cartRepository->get((int) $cartId);

            // getShippingAddress() lives on the concrete quote, not on CartInterface.
            if ($quote instanceof Quote && $quote->getShippingAddress() instanceof Address) {
                $this->captureGeoData($quote->getShippingAddress());
            }
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('%s: %s', get_class($exception), $exception->getMessage()),
                ['cart_id' => $cartId, 'exception' => $exception]
            );
        }

        return $result;
    }

    /**
     * Validate the address and persist the resulting delivery metadata onto it.
     *
     * @param Address $shippingAddress
     * @return void
     */
    private function captureGeoData(Address $shippingAddress): void
    {
        $street = (array) $shippingAddress->getStreet();

        $request = $this->requestFactory->create();
        $request->setStreet((string) ($street[0] ?? ''));
        $request->setStreet2(isset($street[1]) && $street[1] !== '' ? (string) $street[1] : null);
        $request->setCity((string) $shippingAddress->getCity());
        $request->setRegionCode((string) $shippingAddress->getRegionCode());
        $request->setRegionId($shippingAddress->getRegionId() ? (int) $shippingAddress->getRegionId() : null);
        $request->setPostcode((string) $shippingAddress->getPostcode());
        $request->setCountryId((string) $shippingAddress->getCountryId());

        $geoData = $this->addressValidator->validate($request)->getGeoData();

        if ($geoData === null) {
            return;
        }

        $shippingAddress->setData(AddressGeoFields::RDI, $geoData->getRdi());
        $shippingAddress->setData(AddressGeoFields::LATITUDE, $geoData->getLatitude());
        $shippingAddress->setData(AddressGeoFields::LONGITUDE, $geoData->getLongitude());
        $shippingAddress->setData(AddressGeoFields::GEO_PRECISION, $geoData->getGeoPrecision());

        // Targeted save: a full quote save here would re-collect totals for no reason.
        $this->addressResource->save($shippingAddress);
    }
}
