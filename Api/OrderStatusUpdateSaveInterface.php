<?php
declare(strict_types=1);

namespace Networld\CustomOrderProcessing\Api;

/**
 * Interface OrderStatusUpdateSave
 * @package Networld\CustomOrderProcessing\Api
 */
interface OrderStatusUpdateSaveInterface
{
    /**
     * @param mixed $data
     * @return array[]
     */
    public function updateOrderStatus($data): array;

}
