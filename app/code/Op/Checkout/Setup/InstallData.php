<?php
namespace Op\Checkout\Setup;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Sales\Model\ResourceModel\Order\StatusFactory as StatusResourceFactory;

class InstallData implements InstallDataInterface
{

    const ORDER_STATE_CUSTOM_CODE = 'pending_opcheckout';
    const ORDER_STATUS_CUSTOM_CODE = 'pending_opcheckout';
    const ORDER_STATUS_CUSTOM_LABEL = 'Pending OP Checkout';
    protected $statusFactory;
    protected $statusResourceFactory;
    public function __construct(
        StatusFactory $statusFactory,
        StatusResourceFactory $statusResourceFactory
    ) {
        $this->statusFactory = $statusFactory;
        $this->statusResourceFactory = $statusResourceFactory;
    }

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $this->addNewOrderStateAndStatus();
    }

    protected function addNewOrderStateAndStatus()
    {
        $statusResource = $this->statusResourceFactory->create();
        $status = $this->statusFactory->create();
        $status->setData([
            'status' => self::ORDER_STATUS_CUSTOM_CODE,
            'label' => self::ORDER_STATUS_CUSTOM_LABEL,
        ]);

        try {
            $statusResource->save($status);
        } catch (AlreadyExistsException $exception) {
            return;
        }

        $status->assignState(self::ORDER_STATE_CUSTOM_CODE, true, true);
    }
}
