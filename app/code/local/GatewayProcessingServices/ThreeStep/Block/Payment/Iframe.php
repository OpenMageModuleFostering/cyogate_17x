<?php

/**
 * ThreeStep iframe block
 *
 * @category   Local
 * @package    GatewayProcessingServices_ThreeStep
 * @author     GPS
 */
class GatewayProcessingServices_ThreeStep_Block_Payment_Iframe extends Mage_Core_Block_Template
{
    /**
     * Request params
     * @var array
     */
    protected $_params = array();

    /**
     * Internal constructor
     * Set template for iframe
     *
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('threestep/payment/iframe.phtml');
        $this->setTemplate('../../../default/default/template/threestep/payment/iframe.phtml');
    }

    /**
     * Set output params
     *
     * @param array $params
     * @return GatewayProcessingServices_ThreeStep_Block_Payment_Iframe
     */
    public function setParams($params)
    {
        $this->_params = $params;
        return $this;
    }

    /**
     * Get params
     *
     * @return array
     */
    public function getParams()
    {
        return $this->_params;
    }
}
