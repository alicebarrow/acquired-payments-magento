<?php

declare(strict_types=1);

/**
 * Acquired Limited Payment module (https://acquired.com/)
 *
 * Copyright (c) 2024 Acquired.com (https://acquired.com/)
 * See LICENSE.txt for license details.
 */

namespace Acquired\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class AcquiredCardDataAssignObserver extends AbstractDataAssignObserver
{
    public const TRANSACTION_ID = 'transaction_id';

    public const ORDER_ID = 'order_id';

    public const TIMESTAMP = 'timestamp';

    private const VALID_KEYS = [
        self::TRANSACTION_ID,
        self::ORDER_ID,
        self::TIMESTAMP
    ];

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $data = $this->readDataArgument($observer);
        $paymentInfo = $this->readPaymentModelArgument($observer);
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);

        if (!is_array($additionalData)) {
            return;
        }

        $additionalData = $this->getValidAdditionalData($additionalData);
        foreach ($additionalData as $key => $data) {
            $paymentInfo->setAdditionalInformation($key, $data);
        }
    }

    /**
     * Get valid keys values only
     *
     * @param array $additionalData
     * @return array
     */
    private function getValidAdditionalData(array $additionalData): array
    {
        $result = [];
        foreach (self::VALID_KEYS as $key) {
            if (isset($additionalData[$key])) {
                $result[$key] = $additionalData[$key];
            }
        }
        return $result;
    }
}
