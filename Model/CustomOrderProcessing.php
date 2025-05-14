<?php
declare(strict_types=1);

namespace Networld\CustomOrderProcessing\Model;

use Magento\Framework\Model\AbstractModel;
use Networld\CustomOrderProcessing\Model\ResourceModel\CustomOrderProcessing as ResourceModel;

class CustomOrderProcessing extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'networld_order_processing_status_model';

    /**
     * Initialize magento model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }
}
