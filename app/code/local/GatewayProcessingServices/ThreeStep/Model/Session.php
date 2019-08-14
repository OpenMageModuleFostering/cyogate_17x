<?php
/**
 * ThreeStep session model.
 *
 * @category   Local
 * @package    GatewayProcessingServices_ThreeStep
 * @author     GPS
 */
class GatewayProcessingServices_ThreeStep_Model_Session extends Mage_Core_Model_Session_Abstract
{
    /**
     * Class constructor. Initialize session namespace
     */
    public function __construct()
    {
        $this->init('threestep');
    }

    /**
     * Add order IncrementId to session
     *
     * @param string $orderIncrementId
     */
    public function addCheckoutOrderIncrementId($orderIncrementId)
    {
        $orderIncIds = $this->getThreeStepOrderIncrementIds();
        if (!$orderIncIds) {
            $orderIncIds = array();
        }
        $orderIncIds[$orderIncrementId] = 1;
        $this->setThreeStepOrderIncrementIds($orderIncIds);
    }

    /**
     * Remove order IncrementId from session
     *
     * @param string $orderIncrementId
     */
    public function removeCheckoutOrderIncrementId($orderIncrementId)
    {
        $orderIncIds = $this->getThreeStepOrderIncrementIds();

        if (!is_array($orderIncIds)) {
            return;
        }

        if (isset($orderIncIds[$orderIncrementId])) {
            unset($orderIncIds[$orderIncrementId]);
        }
        $this->setThreeStepOrderIncrementIds($orderIncIds);
    }

    /**
     * Return if order incrementId is in session.
     *
     * @param string $orderIncrementId
     * @return bool
     */
    public function isCheckoutOrderIncrementIdExist($orderIncrementId)
    {
        $orderIncIds = $this->getThreeStepOrderIncrementIds();
        if (is_array($orderIncIds) && isset($orderIncIds[$orderIncrementId])) {
            return true;
        }
        return false;
    }
}
