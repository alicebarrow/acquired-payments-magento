<?php

declare(strict_types=1);

/**
 * Acquired Limited Payment module (https://acquired.com/)
 *
 * Copyright (c) 2023 Acquired.com (https://acquired.com/)
 * See LICENSE.txt for license details.
 */

namespace Acquired\Payments\Block\Hosted;

use Magento\Framework\View\Element\Template;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\NotFoundException;

class Response extends Template
{

    protected $_template = "Acquired_Payments::hosted/response.phtml";

    /** @var \Acquired\Payments\Helper\Multishipping */
    private $helper;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    private $orderFactory;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private $serializer;

    /**
     * @var \Magento\Framework\Registr
     */
    private $coreRegistry;

    /**
     * @var Magento\Framework\Encryption\EncryptorInterface
     */
    private $encryptor;

    /**
     * @param \Acquired\Payments\Helper\Multishipping $helper
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array $data
     */
    public function __construct(
        \Acquired\Payments\Helper\Multishipping $helper,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \Magento\Framework\Registry $coreRegistry,
        EncryptorInterface $encryptor,
        \Magento\Framework\View\Element\Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->helper = $helper;
        $this->orderFactory = $orderFactory;
        $this->serializer = $serializer;
        $this->encryptor = $encryptor;
        $this->coreRegistry = $coreRegistry;
    }

    public function getOrderId()
    {
        return $this->coreRegistry->registry('acquired_order_id');
    }

    public function getResponseData()
    {
        return $this->coreRegistry->registry('acquired_hosted_response');
    }

    public function getEncryptedNonce()
    {
        try {
            $order = $this->getOrder();
            $payment = $order->getPayment();

            // get additional data
            $additionalData = $payment->getAdditionalInformation();

            if (!isset($additionalData['acquired_nonce_salt'])) {
                return 'Missing nonce salt';
            }

            $nonce = $this->encryptor->encrypt(implode("::", [
                $this->getOrderId(),
                $payment->getLastTransId(),
                $additionalData['acquired_nonce_salt']
            ]));
            return $nonce;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    protected function getOrder()
    {
        $order = $this->orderFactory->create()->loadByIncrementId($this->getOrderId());

        if (!$order->getId()) {
            throw new NotFoundException(__('Order not found'));
        }

        return $order;
    }
}
