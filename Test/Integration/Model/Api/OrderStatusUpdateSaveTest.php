<?php
declare(strict_types=1);

namespace Networld\CustomOrderProcessing\Test\Integration\Model\Api;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Networld\CustomOrderProcessing\Model\Api\OrderStatusUpdateSave;

/**
 * @magentoDataFixture Magento/Sales/_files/order.php
 */
class OrderStatusUpdateSaveTest extends TestCase
{
    private OrderStatusUpdateSave $orderStatusUpdater;
    private OrderRepositoryInterface $orderRepository;
    private CacheInterface $cache;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->orderStatusUpdater = $objectManager->get(OrderStatusUpdateSave::class);
        $this->orderRepository = $objectManager->get(OrderRepositoryInterface::class);
        $this->cache = $objectManager->get(CacheInterface::class);
    }

    public function testSuccessfulOrderStatusUpdate(): void
    {
        $order = $this->orderRepository->get('000000003');

        // Clear cache key to simulate no recent update
        $this->cache->remove('order_status_change_' . $order->getIncrementId());

        $data = [
            'order_increment_id' => $order->getIncrementId(),
            'new_order_status' => 'processing'
        ];

        $result = $this->orderStatusUpdater->updateOrderStatus($data);

        $this->assertTrue($result[0]['status']);
        $this->assertEquals('Order Status Updated Successfully', $result[0]['message']);
    }

    public function testSameStatusShouldFail(): void
    {
        $order = $this->orderRepository->get('000000003');

        $data = [
            'order_increment_id' => $order->getIncrementId(),
            'new_order_status' => $order->getStatus()
        ];

        $result = $this->orderStatusUpdater->updateOrderStatus($data);

        $this->assertFalse($result[0]['status']);
        $this->assertStringContainsString('Current Order status and new order status are same', $result[0]['message']);
    }

    public function testCompletedOrCanceledOrderFail(): void
    {
        $order = $this->orderRepository->get('000000003');

        $order->setStatus('complete')->setState(\Magento\Sales\Model\Order::STATE_COMPLETE)->save();

        $data = [
            'order_increment_id' => '000000003',
            'new_order_status' => 'pending',
        ];

        $result = $this->orderStatusUpdateSave->updateOrderStatus($data);
        $response = $result[0];

        $this->assertFalse($response['status']);
        $this->assertStringContainsString('Cannot change status as given order status is completed or canceled', $response['message']);
    }

    public function testRateLimitExceeded(): void
    {
        $data = [
            'order_increment_id' => '000000003',
            'new_order_status' => 'pending',
        ];

        // First call to store cache entry
        $this->orderStatusUpdateSave->updateOrderStatus($data);

        // Second call should trigger rate limiting
        $result = $this->orderStatusUpdateSave->updateOrderStatus($data);
        $response = $result[0];

        $this->assertFalse($response['status']);
        $this->assertStringContainsString('Rate limit exceeded', $response['message']);
    }
}
