<?php
namespace Op\Checkout\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Sales\Model\ResourceModel\Order\StatusFactory as StatusResourceFactory;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * Class Recurring
 */
class Recurring implements InstallSchemaInterface
{
    /**
     * @var string
     */
    const ORDER_STATE_CUSTOM_CODE = 'pending_opcheckout_state';

    /**
     * @var string
     */
    const ORDER_STATUS_CUSTOM_CODE = 'pending_opcheckout';

    /**
     * @var string
     */
    const ORDER_STATUS_CUSTOM_LABEL_OLD = 'Pending OP Checkout'; //the module has been rebranded,

    /**
     * @var string
     */
    const ORDER_STATUS_CUSTOM_LABEL = 'Pending Checkout Finland';

    /**
     * @var StatusFactory
     */
    private $statusFactory;

    /**
     * @var StatusResourceFactory
     */
    private $statusResourceFactory;

    /**
     * Recurring constructor.
     * @param StatusFactory $statusFactory
     * @param StatusResourceFactory $statusResourceFactory
     */
    public function __construct(
        StatusFactory $statusFactory,
        StatusResourceFactory $statusResourceFactory
    ) {
        $this->statusFactory = $statusFactory;
        $this->statusResourceFactory = $statusResourceFactory;
    }

    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $this->addNewOrderStateAndStatus();
    }

    /**
     * Add new order and status or rename if exists.
     */
    protected function addNewOrderStateAndStatus()
    {
        /** @var \Magento\Sales\Model\ResourceModel\Order\Status $statusResource */
        $statusResource = $this->statusResourceFactory->create();

        /** @var \Magento\Sales\Model\Order\Status $status */
        $status = $this->statusFactory->create();

        $status->setData([
            'status' => self::ORDER_STATUS_CUSTOM_CODE,
            'label' => self::ORDER_STATUS_CUSTOM_LABEL,
        ]);

        try { //if the status with the code does not exist it creates a new one, otherwise it changes the status label
            $statusResource->save($status);
        } catch (\Exception $exception) {

            return;
        }

        $status->assignState(self::ORDER_STATE_CUSTOM_CODE, true, true);
    }
}
