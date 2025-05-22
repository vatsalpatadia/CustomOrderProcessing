<?php
declare(strict_types=1);

namespace Networld\CustomOrderProcessing\Model\Api;

use Exception;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Networld\CustomOrderProcessing\Api\OrderStatusUpdateSaveInterface;

class OrderStatusUpdateSave implements OrderStatusUpdateSaveInterface
{
    const MESSAGE = 'message';
    const STATUS = 'status';
    const XML_PATH_CUSTOM_ORDER_STATUS_UPDATE_ENABLE = 'networld_general_config/general/enable';
    const XML_PATH_CUSTOM_ORDER_STATUS_RATE_LIMIT_TIMEOUT = 'networld_general_config/general/rate_limit_timeout';
    private ScopeConfigInterface $scopeConfigInterface;
    private OrderRepositoryInterface $orderRepository;
    private LoggerInterface $logger;
    private CacheInterface $cache;

    /**
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     * @param CacheInterface $cache
     */
    public function __construct(
        ScopeConfigInterface $scopeConfigInterface,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger,
        CacheInterface $cache
    ) {
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        $this->cache = $cache;
    }

    /**
     * @param mixed $data
     * @return array[]
     */
    public function updateOrderStatus($data): array
    {
        $response = [self::STATUS => false, "message" => ""];
        try {
            $orderId = trim($data['order_increment_id']) ?? null;
            $newOrderStatus = trim($data['new_order_status']) ?? null;
            $status = $this->scopeConfigInterface->getValue(self::XML_PATH_CUSTOM_ORDER_STATUS_UPDATE_ENABLE,
                ScopeInterface::SCOPE_STORE);
            if ($status) {
                $order = $this->orderRepository->get($orderId);
                if(!$order){
                    throw new LocalizedException(__('Order does not exist.'));
                }
                $newState = $this->getStateForOrderStatus($order, $newOrderStatus);
                
                // Implemented rate limiting to restrict excessive API usage; added caching to reduce redundant processing                
                $orderStatusRateLimit = $this->scopeConfigInterface->getValue(self::XML_PATH_CUSTOM_ORDER_STATUS_RATE_LIMIT_TIMEOUT,
                    ScopeInterface::SCOPE_STORE);                
                $cacheKey = 'order_status_change_' . $orderId;
                $this->logger->debug('Cache key used: ' . $cacheKey);
                if ($this->cache->load($cacheKey)) {
                    throw new LocalizedException(__('Rate limit exceeded for order status updates. Please try again after some time.'));
                }
                
                $this->cache->save('1', $cacheKey, [], $orderStatusRateLimit);

                // check current order status and new order status
                $currentStatus = $order->getStatus();
                if (strtolower($newOrderStatus) === $currentStatus) {
                    throw new InputException(__('Current Order status and new order status are same, Please modify the status'));
                }
                // check approprite order id
                if (!is_numeric($orderId) || $orderId <= 0) {
                    throw new InputException(__('Invalid order id, please provide valid order id'));
                }

                // if order is completed or cancelled then disallow the order status change
                $restrictedStates = ['complete', 'canceled'];
                if (in_array($order->getStatus(), $restrictedStates, true)) {
                    throw new LocalizedException(__('Cannot change status as given order status is completed or canceled.'));
                }
               
                // allow status change to shipped only if shipment is created
                if ($status === 'shipped' && !$order->hasShipments()) {
                    throw new LocalizedException(__('Order cannot mark as shipped until shipment is generated.'));
                }

                if ($orderId && $newOrderStatus) {
                    $order->setState($newState)->setStatus($newOrderStatus);
                    $this->orderRepository->save($order);
                    $response[self::STATUS] = true;
                    $response[self::MESSAGE] = __("Order Status Updated Successfully");
                } else {
                    $response[self::STATUS] = false;
                    $response[self::MESSAGE] = __('Please provide valid orderId and order status.');
                }
            } else {
                $response[self::STATUS] = false;
                $response[self::MESSAGE] = __('This featrue is disabled, Please contact us.');
            }
        } catch (NoSuchEntityException $e) {
            $response[self::STATUS] = false;
            $response[self::MESSAGE] = __('Order does not exist with order Id %1.', $orderId);
            $this->logger->error('order does not exist with order Id ' . $orderId);
        } catch (Exception $exception) {
            $response[self::STATUS] = false;
            $response[self::MESSAGE] = $exception->getMessage();
            $this->logger->error('Exception thrown in V1/order-status-update API, ' . $exception->getMessage());
        }
        return [$response];
    }

    /**
     *
     * @param $order
     * @param string $status
     * @return string
     */
    private function getStateForOrderStatus($order, string $status): string
    {
        $states = $order->getConfig()->getStates();
        foreach ($states as $state => $label) {
            $statuses = $order->getConfig()->getStateStatuses($state);
            if (in_array($status, $statuses)) {
                return $state;
            }
        }
        return $order->getState();
    }

}
