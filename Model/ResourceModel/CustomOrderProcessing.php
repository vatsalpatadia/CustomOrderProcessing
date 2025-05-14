<?php
declare(strict_types=1);

namespace Networld\CustomOrderProcessing\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class CustomOrderProcessing extends AbstractDb
{
    /**
     * @var string
     */
    protected string $_eventPrefix = 'networld_order_processing_status_resource_model';

    /**
     * Initialize resource model.
     */
    protected function _construct()
    {
        $this->_init('networld_order_processing_status', 'id');
    }
}
