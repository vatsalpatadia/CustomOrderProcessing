<?php
declare(strict_types=1);

namespace Networld\CustomOrderProcessing\Model\ResourceModel\CustomOrderProcessing;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Networld\CustomOrderProcessing\Model\CustomOrderProcessing as Model;
use Networld\CustomOrderProcessing\Model\ResourceModel\CustomOrderProcessing as ResourceModel;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'networld_order_processing_status_collection';

    /**
     * Initialize collection model.
     */
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
