<?php
declare(strict_types=1);

/**
 * Acquired.com Payments Integration for Magento2
 *
 * Copyright (c) 2024 Acquired Limited (https://acquired.com/)
 *
 * This file is open source under the MIT license.
 * Please see LICENSE file for more details.
 */

namespace Acquired\Payments\Api;

use Acquired\Payments\Api\Data\SessionDataInterface;

interface SessionInterface
{
    /**
     * Create a checkout session with acquired
     *
     * @param string nonce
     * @param mixed $customData
     * @return SessionDataInterface
     */
    public function get(string $nonce, array $customData = null): SessionDataInterface;

    /**
     * Update checkout session data
     *
     * @param string nonce
     * @param string $sessionId
     * @param mixed $customData
     * @return SessionDataInterface
     */
    public function update(string $nonce, string $sessionId, array $customData = null): SessionDataInterface;
}