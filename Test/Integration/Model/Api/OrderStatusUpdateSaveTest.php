<?php
declare(strict_types=1);

namespace Networld\CustomOrderProcessing\Test\Integration\Model\Api;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;


class OrderStatusUpdateSaveTest extends TestCase
{
    private $moduleName = 'Networld_CustomOrderProcessing';

    public function testTheModuleIsRegistered()
    {
        $registrar = new \Magento\Framework\Component\ComponentRegistrar();
        $this->assertArrayHasKey(
            $this->moduleName,
            $registrar->getPaths(\Magento\Framework\Component\ComponentRegistrar::MODULE)
        );
    }

    public function testTheModuleIsConfiguredAndEnabled()
    {
        /** @var \Magento\TestFramework\ObjectManager $objectManager */
        $objectManager = \Magento\TestFramework\ObjectManager::getInstance();

        /** @var \Magento\Framework\Module\ModuleList $moduleList */
        $moduleList = $objectManager->create(\Magento\Framework\Module\ModuleList::class);

        $this->assertTrue(
            $moduleList->has($this->moduleName),
            'The module is not enabled in the test environment'
        );
    }

    /**
     * @magentoDataFixture Magento/Sales/_files/order.php
     * @magentoDbIsolation disabled     
     */
    public function testSameStatusShouldFail(): void
    {
         $objectManager = \Magento\TestFramework\ObjectManager::getInstance();
         $orderStatusUpdater = $objectManager->create(\Networld\CustomOrderProcessing\Api\OrderStatusUpdateSaveInterface::class);

        $data = [
        'order_increment_id' => '100000001',
        'new_order_status' => 'processing',
        ];

        $result = $orderStatusUpdater->updateOrderStatus($data);
        $response = $result[0];

        $this->assertFalse($result[0]['status']);
        $this->assertStringContainsString('Current Order status and new order status are same, Please modify the status', (string)$result[0]['message']);
    }

    /**
     * @magentoDataFixture Magento/Sales/_files/order.php
     * @magentoDbIsolation disabled     
     */
    public function testRateLimitExceeded(): void
    {
        $objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $orderStatusUpdater = $objectManager->create(\Networld\CustomOrderProcessing\Api\OrderStatusUpdateSaveInterface::class);

        $data = [
            'order_increment_id' => '100000001',
            'new_order_status' => 'processing',
        ];

        // First call to store cache entry
        $orderStatusUpdater->updateOrderStatus($data);

        // Second call should trigger rate limiting
        $result = $orderStatusUpdater->updateOrderStatus($data);
        $response = $result[0];

        $this->assertFalse($response['status']);
        $this->assertStringContainsString('Rate limit exceeded for order status updates. Please try again after some time.', (string)$response['message']);
    }

    /**
     * @magentoDataFixture Magento/Sales/_files/order.php
     * @magentoDbIsolation disabled  
     */
    public function testCompletedOrCanceledOrder(): void
    {
        $objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $order = $objectManager->create(\Magento\Sales\Model\Order::class)
            ->loadByIncrementId('100000001');
        $order->setStatus('complete')->setState(\Magento\Sales\Model\Order::STATE_COMPLETE)->save();
        $orderStatusUpdater = $objectManager->create(\Networld\CustomOrderProcessing\Api\OrderStatusUpdateSaveInterface::class);

        $data = [
            'order_increment_id' => '100000001',
            'new_order_status' => 'complete',
        ];

        $result = $orderStatusUpdater->updateOrderStatus($data);
        $response = $result[0];

        $this->assertFalse($response['status']);
        $this->assertStringContainsString('Cannot change status as given order status is completed or canceled', (string)$response['message']);
    }

    /**
     * @magentoDataFixture Magento/Sales/_files/order.php
     * @magentoDbIsolation disabled   
     */
    public function testShipmentRequiredForShippedStatus(): void
    {   
        $objectManager = \Magento\TestFramework\ObjectManager::getInstance();        
        $orderStatusUpdater = $objectManager->create(\Networld\CustomOrderProcessing\Api\OrderStatusUpdateSaveInterface::class);

        $data = [
            'order_increment_id' => '100000001',
            'new_order_status' => 'shipped',
        ];

        $result = $orderStatusUpdater->updateOrderStatus($data);
        $response = $result[0];

        $this->assertFalse($response['status']);
        $this->assertStringContainsString('Order cannot mark as shipped until shipment is generated', (string)$response['message']);
    }
}
