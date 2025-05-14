<?php
declare(strict_types=1);

namespace Networld\CustomOrderProcessing\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderCommentSender;
use Magento\Sales\Model\Order\Status\HistoryFactory;
use Psr\Log\LoggerInterface;
use Networld\CustomOrderProcessing\Model\CustomOrderProcessingFactory;
use Networld\CustomOrderProcessing\Model\ResourceModel\CustomOrderProcessing as CustomOrderProcessResourceModel;

/**
 * Observes the `sales_order_save_after` event.
 */
class OrderStatusChangeObserver implements ObserverInterface
{
    private LoggerInterface $logger;
    private CustomOrderProcessingFactory $customOrderProcessingFactory;
    private CustomOrderProcessResourceModel $customOrderProcessingResourceModel;
    private TimezoneInterface $timezoneInterface;
    private HistoryFactory $orderHistoryFactory;
    private OrderRepositoryInterface $orderRepository;
    private OrderCommentSender $orderCommentSender;

    /**
     * @param LoggerInterface $logger
     * @param CustomOrderProcessingFactory $customOrderProcessingFactory
     * @param CustomOrderProcessResourceModel $customOrderProcessingResourceModel
     * @param TimezoneInterface $timezoneInterface
     * @param HistoryFactory $orderHistoryFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderCommentSender $orderCommentSender
     */
    public function __construct(
        LoggerInterface $logger,
        CustomOrderProcessingFactory $customOrderProcessingFactory,
        CustomOrderProcessResourceModel $customOrderProcessingResourceModel,
        TimezoneInterface $timezoneInterface,
        HistoryFactory $orderHistoryFactory,
        OrderRepositoryInterface $orderRepository,
        OrderCommentSender $orderCommentSender
    ) {
        $this->logger = $logger;
        $this->customOrderProcessingFactory = $customOrderProcessingFactory;
        $this->customOrderProcessingResourceModel = $customOrderProcessingResourceModel;
        $this->timezoneInterface = $timezoneInterface;
        $this->orderHistoryFactory = $orderHistoryFactory;
        $this->orderRepository = $orderRepository;
        $this->orderCommentSender = $orderCommentSender;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            $order = $observer->getEvent()->getOrder();
            $orderId = $order->getIncrementId();
            $oldStatus = $order->getOrigData('status');
            $newStatus = $order->getStatus();
            $createdAt = $this->timezoneInterface->date()->format('Y-m-d H:i:s');

            $customOrderProcessingObj = $this->customOrderProcessingFactory->create();
            $customOrderProcessingObj->setOrderId($orderId);
            $customOrderProcessingObj->setOldStatus($oldStatus);
            $customOrderProcessingObj->setCurrentStatus($newStatus);
            $customOrderProcessingObj->setCreatedAt($createdAt);
            $this->customOrderProcessingResourceModel->save($customOrderProcessingObj);

            // If the order is marked as shipped, trigger an email notification to the customer.
            if ($newStatus == 'shipped') {
                $newState = $this->getStateForOrderStatus($order, $newStatus);
                $orderHistory = $this->orderHistoryFactory->create();
                $orderHistory->setParentId($orderId)
                    ->setStatus($newState)
                    ->setComment('Order status changed manually to ' . $newStatus)
                    ->setEntityName(Order::ENTITY)
                    ->setIsCustomerNotified(true);
                $order->addStatusHistory($orderHistory);
                $this->orderCommentSender->send($order, true,
                    'This is to notify that your order status changed to ' . $newStatus);
                $this->orderRepository->save($order);
                $this->logger->error('customer has been notified for order shipment.');
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception in OrderStatusChange observer: ' . $e->getMessage());
        }
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
