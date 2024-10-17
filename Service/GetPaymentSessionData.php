<?php
declare(strict_types=1);

/**
 * Acquired Limited Payment module (https://acquired.com/)
 *
 * Copyright (c) 2023 Acquired.com (https://acquired.com/)
 * See LICENSE.txt for license details.
 */

namespace Acquired\Payments\Service;

use Exception;
use Psr\Log\LoggerInterface;
use Acquired\Payments\Model\Api\CreateAcquiredCustomer;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Acquired\Payments\Gateway\Config\Card\Config as CardConfig;
use Magento\Framework\UrlInterface;
use Acquired\Payments\Exception\Api\SessionException;

class GetPaymentSessionData implements PaymentSessionDataInterface
{

    /**
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param StoreManagerInterface $storeManager
     * @param CardConfig $cardConfig
     * @param CreateAcquiredCustomer $createAcquiredCustomer
     * @param SerializerInterface $serializer
     * @param UrlInterface $urlBuilder
     * @param CartRepositoryInterface $cartRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly CheckoutSession $checkoutSession,
        private readonly StoreManagerInterface $storeManager,
        private readonly CardConfig $cardConfig,
        private readonly CreateAcquiredCustomer $createAcquiredCustomer,
        private readonly SerializerInterface $serializer,
        private readonly UrlInterface $urlBuilder,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly LoggerInterface $logger,
        private readonly MultishippingService $multishippingService
    ) {
    }

    /**
     * Get payload for creating checkout session on acquired
     *
     * @param string $orderId
     * @param array|null $customData
     * @return array|array[]
     * @throws SessionException
     */
    public function execute(string $orderId, ?array $customData = null): array
    {
        try {
            $quote = $this->checkoutSession->getQuote();

            $payload = [
                'transaction' => [
                    'order_id' => $orderId,
                    'amount' => number_format((float) $quote->getGrandTotal(), 2, '.', ''),
                    'currency' => strtolower($this->storeManager->getStore()->getCurrentCurrencyCode()),
                    'capture' => $this->cardConfig->getCaptureAction()
                ]
            ];

            // if multishipping checkout, set capture to false, as we want to authorize only
            if ($quote->getIsMultiShipping()) {
                $orderIds = $this->multishippingService->reserveOrderIds($quote);
                $payload['transaction']['capture'] = false;
                $payload['transaction']['custom1'] = 'multishipping order';
                $payload['transaction']['custom2'] = implode(",", $orderIds);
                $payload['transaction']['order_id'] = $orderIds[0] . '-ACQM';
            }

            if ($customData) {
                $payload['transaction']['custom_data'] = base64_encode($this->serializer->serialize($customData));
            }

            $contactUrl = $this->cardConfig->getTdsContactUrl();
            $redirectUrl = $this->urlBuilder->getUrl('acquired/threedsecure/response');
            $webhookUrl = $this->urlBuilder->getUrl('acquired/webhook');

            // if not https make it for all urls
            if (strpos($contactUrl, 'https://') === false) {
                $contactUrl = str_replace('http://', 'https://', $contactUrl);
            }
            if (strpos($redirectUrl, 'https://') === false) {
                $redirectUrl = str_replace('http://', 'https://', $redirectUrl);
            }
            if (strpos($webhookUrl, 'https://') === false) {
                $webhookUrl = str_replace('http://', 'https://', $webhookUrl);
            }

            $payload['tds'] = [
                'is_active' => $this->cardConfig->isTdsActive(),
                'challenge_preference' => $this->cardConfig->getTdsChallengePreference(),
                'contact_url' =>  $contactUrl,
                'redirect_url' => $redirectUrl,
                'webhook_url' => $webhookUrl
            ];

            if ($this->customerSession->isLoggedIn()) {
                $acquiredCustomer = $this->createAcquiredCustomer->execute();
                $payload['customer'] = [
                    'customer_id' => $acquiredCustomer['customer_id']
                ];

                if ($this->cardConfig->isCreateCardEnabled()) {
                    $payload['payment']['create_card'] = true;
                    $payload['payment']['reference'] = $this->customerSession->getCustomerId();
                }
            }

        } catch (Exception $e) {
            $message = __('Get Payment Session data failed: %1', $e->getMessage());
            $this->logger->critical($message, ['exception' => $e]);

            throw new SessionException(__($e->getMessage()));
        }

        return $payload;
    }
}
